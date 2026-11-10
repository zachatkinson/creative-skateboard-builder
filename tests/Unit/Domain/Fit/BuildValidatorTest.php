<?php
/**
 * BuildValidator.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Validation;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleSet;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * The check a build passes before it is sold (spec: Cart, checkout and orders; Security).
 */
final class BuildValidatorTest extends TestCase {

	/**
	 * Each pick's own id as its slot's only part.
	 *
	 * @param Picks $picks The picks.
	 * @return array<string, list<int>>
	 */
	private static function own( Picks $picks ): array {
		$ids = array();
		foreach ( $picks->slots() as $slot ) {
			$ids[ $slot ] = array( $picks->part( $slot )?->id ?? 0 );
		}
		return $ids;
	}

	/**
	 * Validate picks on the usual kit, each pick being one of its slot's parts unless told otherwise.
	 *
	 * @param Picks                         $picks The picks.
	 * @param array<string, list<int>>|null $parts The slots' parts.
	 * @param Kit|null                      $kit   The kit.
	 */
	private static function validate( Picks $picks, ?array $parts = null, ?Kit $kit = null ): Validation {
		return ( new BuildValidator( FitEngine::standard() ) )->validate( $kit ?? KitDocuments::kit(), $picks, $parts ?? self::own( $picks ) );
	}

	/**
	 * The problems as rows of code, slot and part.
	 *
	 * @param Validation $validation The answer.
	 * @return list<array{string, string, int|null}>
	 */
	private static function rows( Validation $validation ): array {
		return array_map( static fn( BuildProblem $problem ): array => array( $problem->code->value, $problem->slot, $problem->part ), $validation->problems );
	}

	public function test_a_complete_that_fits_can_go_to_the_cart(): void {
		$validation = self::validate( Builds::complete() );
		self::assertTrue( $validation->isValid() );
		self::assertSame( array(), $validation->problems );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), array_keys( $validation->build ), 'and the build is there to be priced' );
		self::assertSame( 1, $validation->build[ SlotKey::TRUCKS ]->quantity );
	}

	public function test_a_build_that_only_warns_can_go_ahead(): void {
		$validation = self::validate( Builds::complete( array( SlotKey::WHEELS => Builds::wheel( '58' ) ) ) );
		self::assertSame( Severity::Warn, $validation->build[ SlotKey::WHEELS ]->severity );
		self::assertTrue( $validation->isValid() );
	}

	public function test_a_slot_the_kit_does_not_require_can_be_empty_or_filled(): void {
		self::assertTrue( self::validate( Builds::complete( array( SlotKey::RISERS => Builds::riser( '1/8' ) ) ) )->isValid() );
		self::assertTrue( self::validate( Builds::complete()->without( SlotKey::RISERS ) )->isValid() );
	}

	public function test_every_required_slot_is_filled(): void {
		$validation = self::validate( Builds::complete()->without( SlotKey::WHEELS )->without( SlotKey::GRIP ) );
		self::assertFalse( $validation->isValid() );
		self::assertSame( array( array( 'slot_empty', SlotKey::WHEELS, null ), array( 'slot_empty', SlotKey::GRIP, null ) ), self::rows( $validation ) );
		self::assertSame( array(), $validation->problems[0]->findings );
		self::assertSame(
			array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ),
			array_column( self::rows( self::validate( Picks::none() ) ), 1 ),
			'an empty build, in step order, and the risers are not asked for'
		);
	}

	public function test_a_pick_for_a_slot_the_kit_lacks_is_refused(): void {
		$picks      = Builds::complete()->with( 'stickers', Parts::of( array( 'set_size' => 1 ), 950 ) );
		$validation = self::validate( $picks );
		self::assertSame( array( array( 'slot_unknown', 'stickers', 950 ) ), self::rows( $validation ) );
		self::assertArrayNotHasKey( 'stickers', $validation->build, 'and is not priced' );
		self::assertSame( array( array( 'slot_unknown', 'Deck', 950 ) ), self::rows( self::validate( Builds::complete()->with( 'Deck', Parts::of( array( 'set_size' => 1 ), 950 ) ) ) ), 'a slot\'s key is exact' );
	}

	public function test_a_part_must_be_one_of_its_slots_parts(): void {
		$picks = Builds::complete();
		$parts = self::own( $picks );
		$parts[ SlotKey::WHEELS ] = array( 301, 302 );
		self::assertSame( array( array( 'part_not_in_slot', SlotKey::WHEELS, 300 ) ), self::rows( self::validate( $picks, $parts ) ), 'a truck\'s id sent as a wheel, or a product the kit never listed' );
		$parts[ SlotKey::WHEELS ] = array( 301, 300, 302 );
		self::assertTrue( self::validate( $picks, $parts )->isValid() );
		unset( $parts[ SlotKey::GRIP ] );
		self::assertSame( array( array( 'part_not_in_slot', SlotKey::GRIP, 700 ) ), self::rows( self::validate( $picks, $parts ) ), 'a slot the server found no parts for' );
		self::assertCount( 6, self::validate( $picks, array() )->problems );
	}

	public function test_a_sold_out_part_stops_the_build(): void {
		$soldOut    = new Part( 300, '', Builds::wheel( '54' )->specs, 4500, false );
		$validation = self::validate( Builds::complete( array( SlotKey::WHEELS => $soldOut ) ) );
		self::assertSame( array( array( 'part_sold_out', SlotKey::WHEELS, 300 ) ), self::rows( $validation ) );
	}

	public function test_a_pair_that_does_not_fit_is_refused_on_both_its_slots_with_the_reasons(): void {
		$validation = self::validate( Builds::complete( array( SlotKey::TRUCKS => Parts::indy( '139', array( 'baseplate' => 'new_school', 'set_size' => 2 ) ) ) ) );
		self::assertSame( array( array( 'part_blocked', SlotKey::DECK, 100 ), array( 'part_blocked', SlotKey::TRUCKS, 139 ) ), self::rows( $validation ) );
		self::assertSame( array( MessageCode::DeckAboveRange ), array_column( $validation->problems[0]->findings, 'code' ) );
		self::assertSame( array( MessageCode::DeckAboveRange ), array_column( $validation->problems[1]->findings, 'code' ), 'the blocks, without the notes about the truck' );
		self::assertSame( array( MessageCode::DeckAboveRange, MessageCode::LimitFromClass, MessageCode::AxleAssumed ), array_column( $validation->build[ SlotKey::TRUCKS ]->findings, 'code' ) );
	}

	public function test_a_problems_findings_are_a_list_when_a_note_comes_before_the_block(): void {
		// The truck gives no wheel limit, a note, and its 10 mm axle does not take the bearings, a block, in that order.
		$validation = self::validate( Builds::complete( array( SlotKey::TRUCKS => Parts::indy( '149', array( 'baseplate' => 'new_school', 'axle_diameter' => '10', 'set_size' => 2 ) ) ) ) );
		self::assertSame( array( MessageCode::LimitFromClass, MessageCode::BoreMismatch ), array_column( $validation->build[ SlotKey::TRUCKS ]->findings, 'code' ) );
		self::assertSame( array( 'part_blocked', SlotKey::TRUCKS, 149 ), self::rows( $validation )[0] );
		self::assertSame( array( 0 ), array_keys( $validation->problems[0]->findings ) );
		self::assertSame( MessageCode::BoreMismatch, $validation->problems[0]->findings[0]->code );
	}

	public function test_a_part_with_no_set_size_is_refused(): void {
		$validation = self::validate( Builds::complete( array( SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ), 300 ) ) ) );
		self::assertSame( array( array( 'part_blocked', SlotKey::WHEELS, 300 ) ), self::rows( $validation ) );
		self::assertSame( array( MessageCode::SpecMissing ), array_column( $validation->problems[0]->findings, 'code' ) );
	}

	public function test_everything_wrong_with_a_part_is_said_at_once(): void {
		$wrong      = new Part( 139, 'independent', Parts::indy( '139', array( 'baseplate' => 'new_school', 'set_size' => 2 ) )->specs, 2999, false );
		$picks      = Builds::complete( array( SlotKey::TRUCKS => $wrong ) )->with( 'stickers', Parts::of( array(), 950 ) );
		$parts      = array( SlotKey::TRUCKS => array( 149 ) ) + self::own( $picks );
		$validation = self::validate( $picks, $parts );
		self::assertSame(
			array(
				array( 'slot_unknown', 'stickers', 950 ),
				array( 'part_blocked', SlotKey::DECK, 100 ),
				array( 'part_not_in_slot', SlotKey::TRUCKS, 139 ),
				array( 'part_sold_out', SlotKey::TRUCKS, 139 ),
				array( 'part_blocked', SlotKey::TRUCKS, 139 ),
			),
			self::rows( $validation )
		);
	}

	public function test_fit_is_the_engines_answer_and_nothing_else(): void {
		$picks     = Builds::complete( array( SlotKey::TRUCKS => Parts::indy( '139', array( 'baseplate' => 'new_school', 'set_size' => 2 ) ) ) );
		$noRules   = ( new BuildValidator( new FitEngine( new RuleSet( array() ) ) ) )->validate( KitDocuments::kit(), $picks, self::own( $picks ) );
		$rules     = KitDocuments::rules();
		$rules['r1']['enabled'] = false;
		$switched  = self::validate( $picks, null, KitDocuments::kit( null, $rules ) );
		self::assertTrue( $noRules->isValid() );
		self::assertTrue( $switched->isValid(), 'a rule the kit switched off refuses nothing' );
	}

	public function test_a_preset_is_checked_as_any_build_is(): void {
		$presets = array(
			'schema_version' => 1,
			'presets'        => array(
				array(
					'name'        => 'Street',
					'description' => '',
					'picks'       => array(
						SlotKey::DECK     => 100,
						SlotKey::TRUCKS   => 139,
						SlotKey::WHEELS   => 300,
						SlotKey::BEARINGS => 500,
						SlotKey::HARDWARE => 600,
						SlotKey::GRIP     => 700,
					),
				),
			),
		);
		$kit     = KitDocuments::kit( null, null, $presets );
		// The server looks the preset's ids up; here the shop's parts are the usual complete and two trucks.
		$shop  = array( 100 => Builds::deck(), 139 => Builds::truck( '139' ), 149 => Builds::truck( '149' ), 300 => Builds::wheel( '54' ), 500 => Builds::bearings(), 600 => Builds::bolts(), 700 => Builds::grip() );
		$picks = Picks::none();
		foreach ( $kit->presets[0]->picks as $slot => $id ) {
			$picks = $picks->with( $slot, $shop[ $id ] );
		}
		$parts = array( SlotKey::TRUCKS => array( 139, 149 ) ) + self::own( $picks );
		self::assertSame( array( array( 'part_blocked', SlotKey::DECK, 100 ), array( 'part_blocked', SlotKey::TRUCKS, 139 ) ), self::rows( self::validate( $picks, $parts, $kit ) ), 'a preset whose trucks do not take its deck' );
		self::assertTrue( self::validate( $picks->with( SlotKey::TRUCKS, $shop[149] ), $parts, $kit )->isValid() );
	}

	public function test_the_problems_are_keys_not_words(): void {
		self::assertSame( array( 'slot_unknown', 'slot_empty', 'part_not_in_slot', 'part_sold_out', 'part_blocked' ), array_column( Problem::cases(), 'value' ) );
	}

	public function test_a_problem_holds_what_it_was_given(): void {
		$problem = new BuildProblem( Problem::SlotEmpty, SlotKey::GRIP );
		self::assertSame( Problem::SlotEmpty, $problem->code );
		self::assertSame( SlotKey::GRIP, $problem->slot );
		self::assertNull( $problem->part );
		self::assertSame( array(), $problem->findings );
	}
}
