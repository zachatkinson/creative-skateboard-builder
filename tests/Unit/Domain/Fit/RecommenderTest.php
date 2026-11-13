<?php
/**
 * Recommender.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Recommender;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Rule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleSet;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use PHPUnit\Framework\TestCase;

/**
 * The part a slot starts on (spec: How rules run; docs/UX-RESEARCH.md, P9).
 */
final class RecommenderTest extends TestCase {

	/**
	 * A kit whose slots have these default parts.
	 *
	 * @param array<string, int> $defaults The default part by slot key.
	 */
	private static function kit( array $defaults = array() ): Kit {
		$slots = KitDocuments::slots();
		foreach ( $slots['slots'] as $at => $slot ) {
			if ( isset( $defaults[ $slot['key'] ] ) ) {
				$slots['slots'][ $at ]['default'] = $defaults[ $slot['key'] ];
			}
		}
		return KitDocuments::kit( $slots );
	}

	/**
	 * The id of the part recommended, or null.
	 *
	 * @param Kit        $kit        The kit.
	 * @param string     $slot       The slot.
	 * @param Picks      $picks      The picks so far.
	 * @param list<Part> $candidates The slot's parts.
	 */
	private static function recommend( Kit $kit, string $slot, Picks $picks, array $candidates ): ?int {
		return ( new Recommender( FitEngine::standard() ) )->recommend( $kit, $slot, $picks, $candidates )?->part->id;
	}

	/**
	 * A truck that takes an 8.5 in deck, by id, price and stock.
	 */
	private static function truck( int $id, int $price, bool $inStock = true, string $axle = '8.5' ): Part {
		return Parts::of( array( 'axle_width' => $axle, 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 2 ), $id, $price, 'thunder', $inStock );
	}

	private static function deckPicked(): Picks {
		return Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) );
	}

	public function test_p9_the_deck_is_never_picked_for_the_shopper(): void {
		$decks = array( Builds::deck( '8.0', 101 ), Builds::deck( '8.25', 102 ), Builds::deck( '8.5', 103 ) );
		self::assertNull( self::recommend( self::kit(), SlotKey::DECK, Picks::none(), $decks ) );
		self::assertNull( self::recommend( self::kit( array( SlotKey::DECK => 102 ) ), SlotKey::DECK, Picks::none(), $decks ), 'not even the kit\'s default' );
	}

	public function test_the_kits_default_when_it_fits_and_is_in_stock(): void {
		$trucks = array( self::truck( 1, 2000 ), self::truck( 2, 3000 ), self::truck( 3, 9000 ) );
		self::assertSame( 3, self::recommend( self::kit( array( SlotKey::TRUCKS => 3 ) ), SlotKey::TRUCKS, self::deckPicked(), $trucks ), 'the default, though it is the dearest' );
		self::assertSame( 1, self::recommend( self::kit( array( SlotKey::TRUCKS => 1 ) ), SlotKey::TRUCKS, self::deckPicked(), $trucks ) );
	}

	public function test_a_part_the_shop_holds_too_few_of_is_not_recommended(): void {
		// Sold singly, a build takes two; the shop holds one of the default and two of another (issue #14).
		$single = static fn( int $id, int $price, ?int $stock ): Part => new Part( $id, 'thunder', Parts::of( array( 'axle_width' => '8.5', 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 1 ) )->specs, $price, true, $stock );
		$kit    = self::kit( array( SlotKey::TRUCKS => 1 ) );
		self::assertSame( 2, self::recommend( $kit, SlotKey::TRUCKS, self::deckPicked(), array( $single( 1, 2000, 1 ), $single( 2, 9000, 2 ) ) ), 'not the default, of which one is left' );
		self::assertSame( 1, self::recommend( $kit, SlotKey::TRUCKS, self::deckPicked(), array( $single( 1, 2000, 2 ), $single( 2, 9000, 2 ) ) ) );
		self::assertNull( self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array( $single( 1, 2000, 1 ) ) ), 'nor as the nearest the middle' );
	}

	public function test_the_recommendation_comes_as_the_slot_offers_it(): void {
		$option = ( new Recommender( FitEngine::standard() ) )->recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array( self::truck( 1, 2000 ) ) );
		self::assertSame( Severity::Fits, $option?->severity );
		self::assertSame( 1, $option?->quantity, 'so the total can count it from the start' );
		self::assertSame( array(), $option?->findings );
	}

	public function test_otherwise_the_fitting_in_stock_part_nearest_the_middle_of_the_slots_prices(): void {
		$trucks = array( self::truck( 1, 2000 ), self::truck( 2, 3000 ), self::truck( 3, 9000 ) );
		self::assertSame( 2, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), $trucks ), 'no default: the middle price of three' );
		self::assertSame( 2, self::recommend( self::kit( array( SlotKey::TRUCKS => 77 ) ), SlotKey::TRUCKS, self::deckPicked(), $trucks ), 'a default that is not among the parts' );
		$soldOut = array( self::truck( 1, 2000 ), self::truck( 2, 3000 ), self::truck( 3, 9000, false ) );
		self::assertSame( 2, self::recommend( self::kit( array( SlotKey::TRUCKS => 3 ) ), SlotKey::TRUCKS, self::deckPicked(), $soldOut ), 'a default that is sold out' );
		$narrow = array( self::truck( 1, 2000 ), self::truck( 2, 3000 ), self::truck( 3, 9000, true, '7.0' ) );
		self::assertSame( 2, self::recommend( self::kit( array( SlotKey::TRUCKS => 3 ) ), SlotKey::TRUCKS, self::deckPicked(), $narrow ), 'a default that does not fit' );
	}

	public function test_the_middle_is_of_every_price_in_the_slot_and_the_part_is_one_that_fits_and_is_in_stock(): void {
		// The middle price is the 3000 truck's, which is sold out; 3400 is nearer to it than 2500.
		$trucks = array( self::truck( 1, 2500 ), self::truck( 2, 3000, false ), self::truck( 3, 3400 ), self::truck( 4, 1000, true, '7.0' ), self::truck( 5, 9000, true, '7.0' ) );
		self::assertSame( 3, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), $trucks ) );
		// With 3700 in its place, 2500 is the nearer.
		$trucks[2] = self::truck( 3, 3700 );
		self::assertSame( 1, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), $trucks ) );
	}

	public function test_the_middle_of_an_even_number_of_prices_is_halfway_between_the_two_in_the_middle(): void {
		// 1000, 2000, 4000, 9000: the middle is 3000.
		$trucks = array( self::truck( 1, 1000 ), self::truck( 2, 9000 ), self::truck( 3, 4000 ), self::truck( 4, 2000 ) );
		self::assertSame( 3, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), $trucks ), '2000 and 4000 are as near as each other: the first given' );
		self::assertSame( 4, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array_reverse( $trucks ) ), 'whichever that is' );
		$trucks[3] = self::truck( 4, 2000, false );
		$trucks[2] = self::truck( 3, 5100 );
		self::assertSame( 3, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), $trucks ), '1000, 2000 sold out, 5100, 9000: the middle is 3550, and 5100 is 1550 from it, 1000 is 2550' );
		$trucks[3] = self::truck( 4, 2000 );
		$trucks[2] = self::truck( 3, 5100, false );
		self::assertSame( 4, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), $trucks ), '1000, 2000, 5100 sold out, 9000: 2000 is 1550 from the middle, 1000 is 2550 and 9000 is 5450' );
		self::assertSame( 1, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array( self::truck( 1, 1000 ), self::truck( 2, 9000 ) ) ), 'two prices: each as near, the first given' );
		self::assertSame( 2, self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array( self::truck( 1, 1000, false ), self::truck( 2, 9000 ) ) ) );
	}

	public function test_a_part_that_only_fits_with_a_warning_is_never_recommended(): void {
		// Axles 0.4 in narrower than the deck warn (R1), and are offered, but not put in the build for the shopper.
		$warned = array( self::truck( 1, 2000, true, '8.1' ), self::truck( 2, 3000, true, '8.1' ) );
		self::assertNull( self::recommend( self::kit( array( SlotKey::TRUCKS => 2 ) ), SlotKey::TRUCKS, self::deckPicked(), $warned ) );
		$mixed = array( self::truck( 1, 2000, true, '8.1' ), self::truck( 2, 3000, true, '8.1' ), self::truck( 3, 9000 ) );
		self::assertSame( 3, self::recommend( self::kit( array( SlotKey::TRUCKS => 2 ) ), SlotKey::TRUCKS, self::deckPicked(), $mixed ) );
	}

	public function test_with_nothing_to_recommend_the_shopper_is_left_to_pick(): void {
		self::assertNull( self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array() ) );
		self::assertNull( self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array( self::truck( 1, 2000, false ) ) ), 'all sold out' );
		self::assertNull( self::recommend( self::kit(), SlotKey::TRUCKS, self::deckPicked(), array( self::truck( 1, 2000, true, '7.0' ) ) ), 'nothing fits' );
		self::assertNull( self::recommend( self::kit(), SlotKey::SPACERS, self::deckPicked(), array( Parts::of( array( 'set_size' => 4 ) ) ) ), 'a slot the kit lacks' );
	}

	public function test_the_slot_is_held_against_the_picks_before_it(): void {
		$trucks = array( self::truck( 1, 2000, true, '8.0' ), self::truck( 2, 3000 ), self::truck( 3, 2400, true, '8.0' ) );
		self::assertSame( 3, self::recommend( self::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => Builds::deck( '8.0' ) ) ), $trucks ), 'for an 8.0 in deck' );
		self::assertSame( 2, self::recommend( self::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => Builds::deck( '8.75' ) ) ), $trucks ), 'for an 8.75 in deck' );
	}

	public function test_a_slot_the_kit_does_not_require_gets_one_only_when_the_build_asks_for_it(): void {
		$risers = array( Builds::riser( '1/8', 401 ), Builds::riser( '1/4', 402 ), Builds::riser( '1/2', 403 ) );
		$fine   = Parts::picks( array( SlotKey::DECK => Builds::deck(), SlotKey::TRUCKS => Builds::truck( '149' ), SlotKey::WHEELS => Builds::wheel( '54' ) ) );
		self::assertNull( self::recommend( self::kit(), SlotKey::RISERS, $fine, $risers ), '54 mm wheels need no riser' );
		self::assertNull( self::recommend( self::kit( array( SlotKey::RISERS => 402 ) ), SlotKey::RISERS, $fine, $risers ), 'whatever the kit\'s default' );
		$noted = $fine->with( SlotKey::DECK, Parts::of( array( 'deck_width' => '8.5', 'set_size' => 1 ) ) );
		self::assertNull( self::recommend( self::kit(), SlotKey::RISERS, $noted, $risers ), 'a note about the deck is not a call for risers' );
		$tall = $fine->with( SlotKey::WHEELS, Builds::wheel( '58' ) );
		self::assertSame( 402, self::recommend( self::kit(), SlotKey::RISERS, $tall, $risers ), '58 mm wheels ask for a quarter inch: an eighth still warns' );
		self::assertSame( 403, self::recommend( self::kit( array( SlotKey::RISERS => 403 ) ), SlotKey::RISERS, $tall, $risers ), 'and the default is taken when it clears the warning' );
		$later = $tall->with( SlotKey::HARDWARE, Builds::bolts( '2' ) );
		self::assertSame( 402, self::recommend( self::kit(), SlotKey::RISERS, $later, $risers ), 'what is picked after the slot does not ask' );
		self::assertNull( self::recommend( self::kit(), SlotKey::RISERS, $fine->with( SlotKey::HARDWARE, Builds::bolts( '2' ) ), $risers ), 'bolts that stand proud are picked after the risers step, and do not ask' );
	}

	public function test_p2_the_riser_the_wheels_ask_for_comes_before_the_middle_price(): void {
		// An eighth at 4.00, a half at 8.00 and a quarter at 12.00: the half is nearest the middle price, and with it no
		// bolt a shop stocks reaches (R4). 58 mm wheels on a 56 mm limit ask for a quarter inch.
		$riser  = static fn( string $thickness, int $id, int $price ): Part => Parts::of( array( 'riser_thickness' => $thickness, 'set_size' => 2 ), $id, $price );
		$risers = array( $riser( '1/8', 401, 400 ), $riser( '1/2', 403, 800 ), $riser( '1/4', 402, 1200 ) );
		$tall   = Parts::picks( array( SlotKey::DECK => Builds::deck(), SlotKey::TRUCKS => Builds::truck( '149' ), SlotKey::WHEELS => Builds::wheel( '58' ) ) );
		self::assertSame( 402, self::recommend( self::kit(), SlotKey::RISERS, $tall, $risers ), 'the quarter inch asked for, not the half nearest the middle price' );
		self::assertSame( 403, self::recommend( self::kit(), SlotKey::RISERS, $tall, array( $risers[0], $risers[1] ) ), 'with no quarter inch, the next one up' );
		self::assertSame( 404, self::recommend( self::kit(), SlotKey::RISERS, $tall, array( ...$risers, $riser( '1/4', 404, 900 ) ) ), 'of two quarter inches, the one nearest the middle price' );
		self::assertSame( 403, self::recommend( self::kit( array( SlotKey::RISERS => 403 ) ), SlotKey::RISERS, $tall, $risers ), 'the kit\'s default still comes first' );
	}

	public function test_p2_a_part_thinner_than_asked_or_with_no_figure_comes_after_one_that_covers_it(): void {
		// A rule of the test's own, which asks for a quarter inch and takes any riser, so the order is the recommender's.
		$asks = new class() implements Rule {
			/**
			 * @var list<array{MessageCode, string|null}> Each finding's code and the riser it names.
			 */
			public array $findings = array( array( MessageCode::WheelNeedsRiser, '1/4' ) );

			public function id(): RuleId {
				return RuleId::R3;
			}

			/**
			 * @return list<string>
			 */
			public function slots(): array {
				return array( SlotKey::WHEELS, SlotKey::RISERS );
			}

			public function check( Picks $picks, RuleSettings $settings ): array {
				$found = array();
				foreach ( null === $picks->part( SlotKey::RISERS ) && null !== $picks->part( SlotKey::WHEELS ) ? $this->findings : array() as [ $code, $riser ] ) {
					$found[] = new Finding( RuleId::R3, Severity::Warn, $code, Source::Kit, array( SlotKey::WHEELS, SlotKey::RISERS ), null === $riser ? array() : array( 'riser' => Parts::inches( $riser ) ) );
				}
				return $found;
			}
		};
		$recommend = static fn( array $risers ): ?int => ( new Recommender( new FitEngine( new RuleSet( array( $asks ) ) ) ) )->recommend( self::kit(), SlotKey::RISERS, Parts::picks( array( SlotKey::WHEELS => Builds::wheel( '58' ) ) ), $risers )?->part->id;
		$riser     = static fn( string $thickness, int $id, int $price ): Part => Parts::of( array( 'riser_thickness' => $thickness, 'set_size' => 2 ), $id, $price );
		$unsized   = Parts::of( array( 'set_size' => 2 ), 409, 800 );
		self::assertSame( 403, $recommend( array( $riser( '3/16', 401, 800 ), $riser( '1/2', 403, 800 ) ) ), 'a sixteenth under is nearer, and a quarter over covers it' );
		self::assertSame( 402, $recommend( array( $riser( '1/2', 403, 800 ), $riser( '5/16', 402, 800 ) ) ), 'of two that cover it, the nearer' );
		self::assertSame( 401, $recommend( array( $unsized, $riser( '1/8', 401, 100 ) ) ), 'a part with no figure comes last' );
		self::assertSame( 409, $recommend( array( $unsized ) ), 'and is still a recommendation' );
		$three          = array( $riser( '1/8', 401, 100 ), $riser( '1/2', 403, 800 ), $riser( '1/4', 402, 2000 ) );
		$asks->findings = array( array( MessageCode::WheelNeedsRiser, null ) );
		self::assertSame( 403, $recommend( $three ), 'a finding that names no figure leaves it to the middle price' );
		$asks->findings = array( array( MessageCode::LimitFromClass, null ) );
		self::assertSame( 403, $recommend( $three ), 'and so does a finding of another kind' );
		$asks->findings = array( array( MessageCode::WheelNeedsRiser, null ), array( MessageCode::WheelNeedsRiser, '1/4' ) );
		self::assertSame( 402, $recommend( $three ), 'the first finding that names a figure is the one that counts' );
		$asks->findings = array( array( MessageCode::LimitFromClass, null ), array( MessageCode::WheelNeedsRiser, '1/4' ) );
		self::assertSame( 402, $recommend( $three ), 'whatever kind comes before it' );
		$asks->findings = array( array( MessageCode::WheelNeedsRiser, '1/8' ), array( MessageCode::WheelNeedsRiser, '1/2' ) );
		self::assertSame( 401, $recommend( $three ), 'and not one after it' );
	}
}
