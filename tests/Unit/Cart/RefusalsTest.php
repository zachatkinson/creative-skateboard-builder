<?php
/**
 * A refused build, in sentences.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Cart;

use CreativeBranding\SkateboardBuilder\Cart\Refusals;
use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;
use CreativeBranding\SkateboardBuilder\Service\Verdict;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Cart, checkout and orders: a build that is refused says what is wrong with it, slot by slot, in the
 * words the builder uses; a build that is in the cart and can no longer be bought names itself and says what to
 * do. The class needs no more of WordPress than its translation functions, so it is tested and mutated here
 * (D-087).
 */
final class RefusalsTest extends TestCase {

	private const KIT = 77;

	private InMemoryKits $kits;

	private Refusals $refusals;

	protected function setUp(): void {
		$this->kits = new InMemoryKits();
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$this->refusals = new Refusals( fn(): Kits => $this->kits );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'] );
	}

	/**
	 * A verdict with two things wrong: one in a slot the kit has, one in a slot it has not.
	 */
	private static function wrong(): Verdict {
		return Verdict::refused(
			CartRefusal::BuildRefused,
			array(
				new BuildProblem( Problem::PartSoldOut, 'trucks', 149 ),
				new BuildProblem( Problem::SlotUnknown, 'nose' ),
			)
		);
	}

	public function test_each_problem_is_a_sentence_naming_its_slot_by_its_label_or_as_it_was_sent(): void {
		$label = (string) $this->kits->kit( self::KIT )?->slots->get( 'trucks' )?->label;
		self::assertNotSame( '', $label );
		self::assertSame(
			array( $label . ': the part chosen is sold out.', 'This kit has no slot called "nose".' ),
			$this->refusals->sentences( self::KIT, self::wrong() )
		);
	}

	public function test_a_kit_that_has_gone_leaves_each_slot_named_as_it_was_sent(): void {
		self::assertNull( $this->kits->kit( 99 ) );
		self::assertSame(
			array( 'trucks: the part chosen is sold out.', 'This kit has no slot called "nose".' ),
			$this->refusals->sentences( 99, self::wrong() )
		);
	}

	public function test_a_slots_label_is_the_one_a_translation_plugin_answers_with(): void {
		$GLOBALS['cbuild_test_filters']['cbuild_slot_label'] = static fn( string $label, string $slot, int $kit ): string => "{$slot} of {$kit}";
		self::assertSame( 'trucks of 77: the part chosen is sold out.', $this->refusals->sentences( self::KIT, self::wrong() )[0] );
	}

	public function test_a_refusal_with_no_problem_is_its_one_reason_and_reads_no_kit(): void {
		$read = $this->kits->read;
		self::assertSame( array( 'This build is no longer sold. Nothing was added to the cart.' ), $this->refusals->sentences( self::KIT, Verdict::refused( CartRefusal::KitNotFound ) ) );
		self::assertSame( array( 'This build cannot be priced at the moment. Nothing was added to the cart.' ), $this->refusals->sentences( self::KIT, Verdict::refused( CartRefusal::NoPrice ) ) );
		self::assertSame( $read, $this->kits->read );
	}

	public function test_a_verdict_that_refuses_nothing_reads_as_no_build(): void {
		$taken = Verdict::taken( new Build( 'b-1', self::KIT, array( 'deck' => 103 ), array( 'deck' => 1 ), false ) );
		self::assertSame( array( 'Choose the parts for this build first, then add it to the cart.' ), $this->refusals->sentences( self::KIT, $taken ) );
	}

	public function test_a_build_held_in_the_cart_names_itself_and_says_what_to_do(): void {
		self::assertSame(
			array( 'Complete is no longer sold. Remove it from your cart to check out.' ),
			$this->refusals->held( self::KIT, 'Complete', Verdict::refused( CartRefusal::KitNotFound ) )
		);
		self::assertSame(
			array( 'Complete cannot be bought as it is now. Remove it from your cart to check out.' ),
			$this->refusals->held( self::KIT, 'Complete', Verdict::refused( CartRefusal::NoPrice ) )
		);
		$held = $this->refusals->held( self::KIT, 'Complete', self::wrong() );
		self::assertSame( 'Complete cannot be bought as it is now. Remove it from your cart to check out, then build it again.', $held[0] );
		self::assertSame( $this->refusals->sentences( self::KIT, self::wrong() ), array_slice( $held, 1 ) );
	}

	public function test_the_store_api_is_sent_each_problems_code_slot_and_part(): void {
		self::assertSame(
			array(
				array(
					'code' => 'part_sold_out',
					'slot' => 'trucks',
					'part' => 149,
				),
				array(
					'code' => 'slot_unknown',
					'slot' => 'nose',
					'part' => null,
				),
			),
			Refusals::problems( self::wrong() )
		);
		self::assertSame( array(), Refusals::problems( Verdict::refused( CartRefusal::NoBuild ) ) );
	}
}
