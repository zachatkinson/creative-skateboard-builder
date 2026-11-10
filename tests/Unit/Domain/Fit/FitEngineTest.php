<?php
/**
 * FitEngine.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SlotOptions;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleSet;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each slot's options against the picks before it, what was hidden and why, and a whole build.
 */
final class FitEngineTest extends TestCase {

	private static function deck( string $width = '8.5', int $id = 100 ): Part {
		return Parts::of( array( 'deck_width' => $width, 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), $id );
	}

	private static function truck( string $size ): Part {
		return Parts::indy( $size, array( 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 2 ) );
	}

	private static function wheel( string $diameter, int $id = 300 ): Part {
		return Parts::of( array( 'wheel_diameter' => $diameter, 'set_size' => 4 ), $id );
	}

	private static function riser( string $thickness, int $id = 400 ): Part {
		return Parts::of( array( 'riser_thickness' => $thickness, 'set_size' => 2 ), $id );
	}

	private static function bearings( string $bore = '8', int $id = 500 ): Part {
		return Parts::of( array( 'bearing_bore' => $bore, 'set_size' => 8 ), $id );
	}

	private static function bolts( string $length = '1', int $id = 600 ): Part {
		return Parts::of( array( 'bolt_length' => $length, 'set_size' => 8 ), $id );
	}

	private static function grip( string $width = '9', int $id = 700 ): Part {
		return Parts::of( array( 'grip_width' => $width, 'grip_length' => '33', 'set_size' => 1 ), $id );
	}

	/**
	 * A complete that fits.
	 *
	 * @param array<string, Part> $with Parts to add or replace.
	 */
	private static function complete( array $with = array() ): Picks {
		return Parts::picks(
			$with + array(
				SlotKey::DECK     => self::deck(),
				SlotKey::TRUCKS   => self::truck( '149' ),
				SlotKey::WHEELS   => self::wheel( '54' ),
				SlotKey::BEARINGS => self::bearings(),
				SlotKey::HARDWARE => self::bolts(),
				SlotKey::GRIP     => self::grip(),
			)
		);
	}

	/**
	 * The ids of some options.
	 *
	 * @param list<Option> $options The options.
	 * @return list<int>
	 */
	private static function ids( array $options ): array {
		return array_map( static fn( Option $option ): int => $option->part->id, $options );
	}

	/**
	 * @return array<string, array{string, int, int|null}>
	 */
	public static function quantities(): array {
		return array(
			'a deck'                        => array( SlotKey::DECK, 1, 1 ),
			'trucks sold as a pair'         => array( SlotKey::TRUCKS, 2, 1 ),
			'trucks sold singly'            => array( SlotKey::TRUCKS, 1, 2 ),
			'wheels in fours'               => array( SlotKey::WHEELS, 4, 1 ),
			'wheels in pairs'               => array( SlotKey::WHEELS, 2, 2 ),
			'wheels in threes are rounded up' => array( SlotKey::WHEELS, 3, 2 ),
			'bearings in eights'            => array( SlotKey::BEARINGS, 8, 1 ),
			'bearings in sixteens'          => array( SlotKey::BEARINGS, 16, 1 ),
			'bearings singly'               => array( SlotKey::BEARINGS, 1, 8 ),
			'bolts in sevens'               => array( SlotKey::HARDWARE, 7, 2 ),
			'a slot the kit lacks'          => array( SlotKey::SPACERS, 4, null ),
		);
	}

	#[DataProvider( 'quantities' )]
	public function test_r7_a_build_takes_as_many_of_a_part_as_cover_the_slot( string $slot, int $setSize, ?int $quantity ): void {
		self::assertSame( $quantity, FitEngine::standard()->quantity( KitDocuments::kit(), $slot, Parts::of( array( 'set_size' => $setSize ) ) ) );
	}

	public function test_r7_a_part_with_no_set_size_has_no_quantity_and_is_not_offered(): void {
		$engine = FitEngine::standard();
		$kit    = KitDocuments::kit();
		$wheel  = Parts::of( array( 'wheel_diameter' => '54' ) );
		self::assertNull( $engine->quantity( $kit, SlotKey::WHEELS, $wheel ) );
		$options = $engine->options( $kit, SlotKey::WHEELS, Picks::none(), array( $wheel, self::wheel( '54', 301 ) ) );
		self::assertSame( array( 301 ), self::ids( $options->offered ) );
		self::assertSame( 1, $options->offered[0]->quantity );
		self::assertNull( $options->hidden[0]->quantity );
		self::assertEquals( array( Finding::missing( RuleId::R7, SlotKey::WHEELS, SpecKey::SetSize ) ), $options->hidden[0]->findings );
	}

	public function test_the_trucks_step_offers_what_fits_the_deck_and_hides_the_rest_with_reasons(): void {
		$loose      = Parts::of( array( 'axle_width' => '8.0', 'baseplate' => 'new_school', 'set_size' => 2 ), 800 );
		$unmeasured = Parts::of( array( 'baseplate' => 'new_school', 'set_size' => 2 ), 801 );
		$candidates = array( self::truck( '139' ), self::truck( '144' ), self::truck( '149' ), self::truck( '159' ), $loose, $unmeasured, self::truck( '129' ) );
		$options    = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => self::deck( '8.5' ) ) ), $candidates );

		self::assertSame( SlotKey::TRUCKS, $options->slot );
		self::assertSame( array( 149, 800 ), self::ids( $options->offered ), 'in the order given' );
		self::assertSame( array( 139, 144, 159, 801, 129 ), self::ids( $options->hidden ), 'in the order given' );
		self::assertSame( Severity::Fits, $options->offered[0]->severity );
		self::assertSame( array(), $options->offered[0]->findings );
		self::assertNull( $options->offered[0]->reason() );
		self::assertSame( 1, $options->offered[0]->quantity );
		self::assertSame( Severity::Warn, $options->offered[1]->severity, 'a warning is offered' );
		self::assertSame( MessageCode::AxleNarrower, $options->offered[1]->findings[0]->code );
		self::assertNull( $options->offered[1]->reason() );
		self::assertSame( Severity::Block, $options->hidden[0]->severity );
		self::assertSame( MessageCode::DeckAboveRange, $options->hidden[0]->reason()?->code );

		$groups = $options->hiddenGroups();
		self::assertSame(
			array(
				array( 'r1', 'deck_above_range', 3, array( 139, 144, 129 ) ),
				array( 'r1', 'deck_below_range', 1, array( 159 ) ),
				array( 'r1', 'spec_missing', 1, array( 801 ) ),
			),
			array_map( static fn( $group ): array => array( $group->rule->value, $group->code->value, $group->count, self::ids( $group->options ) ), $groups )
		);
		self::assertSame( count( $options->hidden ), array_sum( array_column( $groups, 'count' ) ), 'every hidden part is in one group' );
		self::assertEquals( Parts::inches( '8.5' ), $groups[0]->example->values['deck_width'], 'the heading can name the deck' );
		self::assertEquals( Parts::inches( '8.2' ), $groups[0]->example->values['deck_max'], 'from the first part in the group' );
		self::assertSame( $options->hidden[0]->reason(), $groups[0]->example );
	}

	public function test_a_part_blocked_twice_is_counted_once_under_its_first_reason(): void {
		$wrong   = Parts::indy( '139', array( 'baseplate' => 'old_school', 'set_size' => 2 ) );
		$options = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => self::deck( '8.5' ) ) ), array( $wrong ) );
		self::assertSame( array( MessageCode::DeckAboveRange, MessageCode::PatternMismatch, MessageCode::LimitFromClass, MessageCode::AxleAssumed ), array_column( $options->hidden[0]->findings, 'code' ), 'two blocks, and the notes kept' );
		$groups = $options->hiddenGroups();
		self::assertCount( 1, $groups );
		self::assertSame( RuleId::R1, $groups[0]->rule );
		self::assertSame( MessageCode::DeckAboveRange, $groups[0]->code );
		self::assertSame( 1, $groups[0]->count );
	}

	public function test_the_same_code_under_two_rules_is_two_groups(): void {
		$noBaseplate = Parts::indy( '149', array( 'set_size' => 2 ) );
		$noAxle      = Parts::of( array( 'baseplate' => 'new_school', 'set_size' => 2 ), 2 );
		$options     = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => self::deck() ) ), array( $noBaseplate, $noAxle ) );
		self::assertSame(
			array( array( RuleId::R2, MessageCode::SpecMissing ), array( RuleId::R1, MessageCode::SpecMissing ) ),
			array_map( static fn( $group ): array => array( $group->rule, $group->code ), $options->hiddenGroups() )
		);
	}

	public function test_only_a_blocked_option_is_grouped(): void {
		$fits    = new Option( self::truck( '149' ), array(), 1 );
		$blocked = new Option( self::truck( '139' ), array( Finding::missing( RuleId::R1, SlotKey::TRUCKS, SpecKey::AxleWidth ) ), 1 );
		$groups  = ( new SlotOptions( SlotKey::TRUCKS, array(), array( $fits, $blocked ) ) )->hiddenGroups();
		self::assertCount( 1, $groups );
		self::assertSame( array( $blocked ), $groups[0]->options );
	}

	public function test_nothing_hidden_means_no_groups(): void {
		$options = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => self::deck() ) ), array( self::truck( '149' ) ) );
		self::assertSame( array(), $options->hidden );
		self::assertSame( array(), $options->hiddenGroups() );
		$none = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::TRUCKS, Picks::none(), array() );
		self::assertSame( array(), $none->offered );
		self::assertSame( array(), $none->hidden );
	}

	public function test_the_deck_step_is_held_against_nothing(): void {
		$bare    = Parts::of( array( 'deck_width' => '8.25', 'set_size' => 1 ), 101 );
		$unknown = Parts::of( array( 'deck_length' => '32', 'set_size' => 1 ), 102 );
		// Every later slot is filled with parts no 10 in deck fits: none of them is read.
		$options = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::DECK, self::complete(), array( self::deck( '10', 103 ), $bare, $unknown ) );
		self::assertSame( array( 103, 101 ), self::ids( $options->offered ) );
		self::assertSame( array(), $options->offered[0]->findings );
		self::assertSame( Severity::Fits, $options->offered[1]->severity, 'a note is not a warning' );
		self::assertSame( array( MessageCode::PatternAssumed, MessageCode::LengthUnchecked ), array_column( $options->offered[1]->findings, 'code' ) );
		self::assertSame( array( 102 ), self::ids( $options->hidden ) );
		self::assertSame( array( RuleId::R1, RuleId::R6 ), array_column( array_filter( $options->hidden[0]->findings, static fn( Finding $finding ): bool => Severity::Block === $finding->severity ), 'rule' ) );
	}

	public function test_a_slot_is_held_against_the_picks_before_it_and_not_its_own_or_later_ones(): void {
		// The trucks picked are Indy 129 and the wheels 70 mm; the trucks step still judges each truck by the deck alone.
		$picks   = self::complete( array( SlotKey::TRUCKS => self::truck( '129' ), SlotKey::WHEELS => self::wheel( '70' ) ) );
		$options = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::TRUCKS, $picks, array( self::truck( '149' ) ) );
		self::assertSame( array( 149 ), self::ids( $options->offered ) );
		self::assertSame( array(), $options->offered[0]->findings );
		// The wheels step reads the trucks picked, and says nothing of the deck and trucks not fitting each other.
		$wheels = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::WHEELS, $picks, array( self::wheel( '54' ), self::wheel( '58', 301 ) ) );
		self::assertSame( array( 300, 301 ), self::ids( $wheels->offered ) );
		self::assertSame( array(), $wheels->offered[0]->findings );
		self::assertSame( array( MessageCode::WheelNeedsRiser ), array_column( $wheels->offered[1]->findings, 'code' ) );
	}

	public function test_the_risers_step_shows_which_riser_clears_the_wheels(): void {
		$picks   = self::complete( array( SlotKey::WHEELS => self::wheel( '58' ), SlotKey::HARDWARE => self::bolts( '7/8' ) ) );
		$options = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::RISERS, $picks, array( self::riser( '1/8', 401 ), self::riser( '1/4', 402 ) ) );
		self::assertSame( array( 401, 402 ), self::ids( $options->offered ), 'the bolts come later and are not read' );
		self::assertSame( Severity::Warn, $options->offered[0]->severity );
		self::assertSame( Severity::Fits, $options->offered[1]->severity );
		// The hardware step then holds the bolts to the riser picked.
		$bolts = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::HARDWARE, $picks->with( SlotKey::RISERS, self::riser( '1/4', 402 ) ), array( self::bolts( '1', 601 ), self::bolts( '1 1/4', 602 ) ) );
		self::assertSame( array( 602 ), self::ids( $bolts->offered ) );
		self::assertSame( array( array( 'r4', 'bolts_too_short', 1 ) ), array_map( static fn( $group ): array => array( $group->rule->value, $group->code->value, $group->count ), $bolts->hiddenGroups() ) );
	}

	public function test_a_rule_the_kit_switched_off_hides_nothing(): void {
		$rules                  = KitDocuments::rules();
		$rules['r1']['enabled'] = false;
		$options                = FitEngine::standard()->options( KitDocuments::kit( null, $rules ), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => self::deck( '8.5' ) ) ), array( self::truck( '139' ) ) );
		self::assertSame( array( 139 ), self::ids( $options->offered ) );
	}

	public function test_the_engine_asks_the_rules_it_was_given(): void {
		$engine  = new FitEngine( new RuleSet( array() ) );
		$options = $engine->options( KitDocuments::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => self::deck( '8.5' ) ) ), array( self::truck( '139' ) ) );
		self::assertSame( array( 139 ), self::ids( $options->offered ) );
	}

	public function test_an_offer_is_found_by_its_parts_id(): void {
		$options = FitEngine::standard()->options( KitDocuments::kit(), SlotKey::TRUCKS, Parts::picks( array( SlotKey::DECK => self::deck( '8.5' ) ) ), array( self::truck( '144' ), self::truck( '146' ), self::truck( '149' ) ) );
		self::assertSame( 149, $options->offer( 149 )?->part->id );
		self::assertSame( 146, $options->offer( 146 )?->part->id );
		self::assertNull( $options->offer( 144 ), 'hidden' );
		self::assertNull( $options->offer( 215 ), 'never a candidate' );
	}

	public function test_judging_a_part_keeps_only_what_reads_its_slot(): void {
		$others   = Parts::picks( array( SlotKey::DECK => self::deck( '9.5' ), SlotKey::TRUCKS => self::truck( '139' ) ) );
		$findings = FitEngine::standard()->judge( KitDocuments::kit(), SlotKey::WHEELS, self::wheel( '60' ), $others );
		self::assertSame( array( MessageCode::WheelNeedsRiser ), array_column( $findings, 'code' ), 'the deck and trucks not fitting is not the wheel\'s' );
		$replaced = FitEngine::standard()->judge( KitDocuments::kit(), SlotKey::TRUCKS, self::truck( '169' ), $others );
		self::assertSame( array(), $replaced, 'the slot\'s own pick is replaced, not read' );
	}

	public function test_a_whole_build_that_fits(): void {
		$build = FitEngine::standard()->build( KitDocuments::kit(), self::complete() );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), array_keys( $build ), 'in step order, filled slots only' );
		foreach ( $build as $slot => $option ) {
			self::assertSame( array(), $option->findings, $slot );
			self::assertSame( Severity::Fits, $option->severity );
			self::assertSame( 1, $option->quantity );
		}
		self::assertSame( 100, $build[ SlotKey::DECK ]->part->id );
		self::assertSame( array(), FitEngine::standard()->build( KitDocuments::kit(), Picks::none() ) );
	}

	public function test_a_whole_build_is_held_together_so_a_riser_picked_later_clears_the_wheels(): void {
		$tall = self::complete( array( SlotKey::WHEELS => self::wheel( '58' ) ) );
		$bare = FitEngine::standard()->build( KitDocuments::kit(), $tall );
		self::assertSame( Severity::Warn, $bare[ SlotKey::WHEELS ]->severity );
		self::assertSame( Severity::Warn, $bare[ SlotKey::TRUCKS ]->severity, 'the warning is both slots\'' );
		self::assertSame( Severity::Fits, $bare[ SlotKey::DECK ]->severity );
		$raised = FitEngine::standard()->build( KitDocuments::kit(), $tall->with( SlotKey::RISERS, self::riser( '1/4' ) )->with( SlotKey::HARDWARE, self::bolts( '1 1/4' ) ) );
		foreach ( $raised as $slot => $option ) {
			self::assertSame( Severity::Fits, $option->severity, $slot );
		}
		self::assertSame( 1, $raised[ SlotKey::RISERS ]->quantity );
	}

	public function test_a_whole_build_names_both_slots_of_a_pair_that_does_not_fit(): void {
		$build = FitEngine::standard()->build( KitDocuments::kit(), self::complete( array( SlotKey::DECK => self::deck( '9.5' ) ) ) );
		self::assertSame(
			array(
				SlotKey::DECK     => Severity::Block,
				SlotKey::TRUCKS   => Severity::Block,
				SlotKey::WHEELS   => Severity::Fits,
				SlotKey::BEARINGS => Severity::Fits,
				SlotKey::HARDWARE => Severity::Fits,
				SlotKey::GRIP     => Severity::Block,
			),
			array_map( static fn( Option $option ): Severity => $option->severity, $build )
		);
		self::assertSame( array( MessageCode::DeckAboveRange, MessageCode::GripTooNarrow ), array_column( $build[ SlotKey::DECK ]->findings, 'code' ) );
	}

	public function test_a_whole_build_does_not_read_a_pick_for_a_slot_the_kit_lacks(): void {
		$picks = self::complete( array( SlotKey::BEARINGS => Parts::of( array( 'bearing_bore' => '8', 'spacers_built_in' => true, 'set_size' => 8 ), 500 ) ) )->with( SlotKey::SPACERS, Parts::of( array( 'set_size' => 4 ), 900 ) );
		$build = FitEngine::standard()->build( KitDocuments::kit(), $picks );
		self::assertArrayNotHasKey( SlotKey::SPACERS, $build );
		self::assertSame( Severity::Fits, $build[ SlotKey::BEARINGS ]->severity, 'spacers the kit has no slot for block nothing' );
	}
}
