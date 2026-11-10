<?php
/**
 * Severity, Finding and the Reader.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\InvalidFinding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Reader;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * What a rule says, and how it reads the picks.
 */
final class FindingTest extends TestCase {

	public function test_block_is_worse_than_warn_and_warn_than_fits(): void {
		self::assertSame( Severity::Block, Severity::Fits->worst( Severity::Block ) );
		self::assertSame( Severity::Block, Severity::Block->worst( Severity::Warn ) );
		self::assertSame( Severity::Warn, Severity::Warn->worst( Severity::Fits ) );
		self::assertSame( Severity::Warn, Severity::Fits->worst( Severity::Warn ) );
		self::assertSame( Severity::Fits, Severity::Fits->worst( Severity::Fits ) );
		self::assertSame( Severity::Block, Severity::Warn->worst( Severity::Block ) );
	}

	public function test_the_worst_of_some_findings(): void {
		$note  = new Finding( RuleId::R2, Severity::Fits, MessageCode::PatternAssumed, Source::Standard, array( SlotKey::DECK ), array() );
		$warn  = new Finding( RuleId::R3, Severity::Warn, MessageCode::WheelNeedsRiser, Source::Maker, array( SlotKey::TRUCKS, SlotKey::WHEELS ), array() );
		$block = new Finding( RuleId::R1, Severity::Block, MessageCode::DeckAboveRange, Source::Maker, array( SlotKey::DECK, SlotKey::TRUCKS ), array() );
		self::assertSame( Severity::Fits, Finding::worst( array() ) );
		self::assertSame( Severity::Fits, Finding::worst( array( $note ) ) );
		self::assertSame( Severity::Warn, Finding::worst( array( $note, $warn ) ) );
		self::assertSame( Severity::Block, Finding::worst( array( $block, $warn, $note ) ) );
		self::assertSame( Severity::Block, Finding::worst( array( $warn, $block ) ) );
	}

	public function test_a_finding_holds_what_it_was_given(): void {
		$width   = Parts::inches( '8.5' );
		$finding = new Finding( RuleId::R1, Severity::Block, MessageCode::DeckAboveRange, Source::Maker, array( SlotKey::DECK, SlotKey::TRUCKS ), array( 'deck_width' => $width ) );
		self::assertSame( RuleId::R1, $finding->rule );
		self::assertSame( Severity::Block, $finding->severity );
		self::assertSame( MessageCode::DeckAboveRange, $finding->code );
		self::assertSame( Source::Maker, $finding->source );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS ), $finding->slots );
		self::assertSame( array( 'deck_width' => $width ), $finding->values );
		self::assertTrue( $finding->reads( SlotKey::TRUCKS ) );
		self::assertTrue( $finding->reads( SlotKey::DECK ) );
		self::assertFalse( $finding->reads( SlotKey::WHEELS ) );
	}

	public function test_a_missing_spec_is_a_block_on_its_own_slot(): void {
		$finding = Finding::missing( RuleId::R6, SlotKey::GRIP, SpecKey::GripWidth );
		self::assertSame( RuleId::R6, $finding->rule );
		self::assertSame( Severity::Block, $finding->severity );
		self::assertSame( MessageCode::SpecMissing, $finding->code );
		self::assertSame( Source::Maker, $finding->source );
		self::assertSame( array( SlotKey::GRIP ), $finding->slots );
		self::assertSame( array( 'spec' => 'grip_width' ), $finding->values );
	}

	public function test_the_blocking_findings_are_a_list_in_the_order_given(): void {
		$note  = new Finding( RuleId::R3, Severity::Fits, MessageCode::LimitFromClass, Source::Standard, array( SlotKey::TRUCKS ), array() );
		$warn  = new Finding( RuleId::R3, Severity::Warn, MessageCode::WheelNeedsRiser, Source::Maker, array( SlotKey::WHEELS ), array() );
		$first = new Finding( RuleId::R5, Severity::Block, MessageCode::BoreMismatch, Source::Maker, array( SlotKey::BEARINGS ), array() );
		$next  = new Finding( RuleId::R1, Severity::Block, MessageCode::DeckAboveRange, Source::Maker, array( SlotKey::DECK ), array() );

		self::assertSame( array( $first, $next ), Finding::blocking( array( $note, $first, $warn, $next ) ) );
		self::assertSame( array(), Finding::blocking( array( $note, $warn ) ) );
		self::assertSame( array(), Finding::blocking( array() ) );
	}

	public function test_each_code_names_the_values_it_carries(): void {
		$names = array();
		foreach ( MessageCode::cases() as $code ) {
			$names[ $code->value ] = $code->values();
		}
		self::assertSame(
			array(
				'spec_missing'      => array( 'spec' ),
				'deck_below_range'  => array( 'deck_width', 'deck_min', 'deck_max' ),
				'deck_above_range'  => array( 'deck_width', 'deck_min', 'deck_max' ),
				'axle_narrower'     => array( 'deck_width', 'axle_width', 'gap' ),
				'axle_wider'        => array( 'deck_width', 'axle_width', 'gap' ),
				'pattern_mismatch'  => array( 'hole_pattern', 'baseplate' ),
				'pattern_assumed'   => array( 'hole_pattern' ),
				'wheel_needs_riser' => array( 'wheel_diameter', 'limit', 'over', 'riser' ),
				'wheel_no_riser'    => array( 'wheel_diameter', 'limit', 'over' ),
				'limit_from_class'  => array( 'height_class', 'limit' ),
				'bolts_too_short'   => array( 'bolt_length', 'shortest', 'usual', 'riser_thickness' ),
				'bolts_too_long'    => array( 'bolt_length', 'shortest', 'usual', 'riser_thickness' ),
				'bore_mismatch'     => array( 'bearing_bore', 'axle_diameter' ),
				'axle_assumed'      => array( 'axle_diameter' ),
				'spacers_built_in'  => array(),
				'grip_too_narrow'   => array( 'grip_width', 'deck_width' ),
				'grip_too_short'    => array( 'grip_length', 'deck_length' ),
				'length_unchecked'  => array(),
			),
			$names
		);
	}

	public function test_a_finding_carries_only_the_values_its_code_names(): void {
		$some = new Finding( RuleId::R1, Severity::Block, MessageCode::DeckBelowRange, Source::Maker, array( SlotKey::DECK ), array( 'deck_min' => Parts::inches( '8.62' ) ) );
		self::assertSame( array( 'deck_min' ), array_keys( $some->values ), 'a code need not carry every value it names' );
		try {
			new Finding( RuleId::R1, Severity::Block, MessageCode::DeckBelowRange, Source::Maker, array( SlotKey::DECK ), array( 'deck_width' => Parts::inches( '8.5' ), 'gap' => Parts::inches( '0.1' ) ) );
			self::fail( 'accepted' );
		} catch ( InvalidFinding $e ) {
			self::assertSame( 'finding_value_not_named', $e->getMessage() );
		}
		$this->expectException( InvalidFinding::class );
		new Finding( RuleId::R6, Severity::Fits, MessageCode::LengthUnchecked, Source::Standard, array( SlotKey::DECK ), array( 'deck_length' => Parts::inches( '32' ) ) );
	}

	public function test_the_codes_are_keys_not_words(): void {
		foreach ( array( ...MessageCode::cases(), ...Severity::cases(), ...Source::cases() ) as $case ) {
			self::assertMatchesRegularExpression( '/\A[a-z][a-z0-9_]*\z/', $case->value );
		}
		self::assertSame( 'spec_missing', MessageCode::SpecMissing->value );
		self::assertSame( array( 'fits', 'warn', 'block' ), array_column( Severity::cases(), 'value' ) );
		self::assertSame( array( 'maker', 'kit', 'standard' ), array_column( Source::cases(), 'value' ) );
	}

	public function test_the_reader_reads_a_picked_parts_specs(): void {
		$picks = Parts::picks(
			array(
				SlotKey::DECK   => Parts::of( array( 'deck_width' => '8.5', 'hole_pattern' => 'both' ) ),
				SlotKey::TRUCKS => Parts::indy( '149' ),
			)
		);
		$read  = new Reader( RuleId::R1, $picks );
		self::assertTrue( $read->picked( SlotKey::DECK ) );
		self::assertFalse( $read->picked( SlotKey::WHEELS ) );
		self::assertTrue( Parts::inches( '8.5' )->equals( $read->measure( SlotKey::DECK, SpecKey::DeckWidth ) ?? Parts::inches( '0' ) ) );
		self::assertSame( 'both', $read->choice( SlotKey::DECK, SpecKey::HolePattern ) );
		self::assertTrue( Parts::inches( '8.6' )->equals( $read->optional( SlotKey::TRUCKS, SpecKey::DeckMax ) ?? Parts::inches( '0' ) ) );
		self::assertSame( array(), $read->missing() );
	}

	public function test_the_reader_says_nothing_of_an_empty_slot(): void {
		$read = new Reader( RuleId::R1, Parts::picks( array() ) );
		self::assertNull( $read->measure( SlotKey::DECK, SpecKey::DeckWidth ) );
		self::assertNull( $read->choice( SlotKey::DECK, SpecKey::HolePattern ) );
		self::assertNull( $read->optional( SlotKey::DECK, SpecKey::DeckLength ) );
		self::assertSame( array(), $read->missing() );
	}

	public function test_the_reader_records_each_spec_a_picked_part_lacks(): void {
		$picks = Parts::picks(
			array(
				SlotKey::DECK   => Parts::of(),
				SlotKey::TRUCKS => Parts::of(),
			)
		);
		$read  = new Reader( RuleId::R2, $picks );
		self::assertNull( $read->measure( SlotKey::DECK, SpecKey::DeckWidth ) );
		self::assertNull( $read->optional( SlotKey::DECK, SpecKey::DeckLength ), 'an optional spec is not missed' );
		self::assertNull( $read->choice( SlotKey::TRUCKS, SpecKey::Baseplate ) );
		$missing = $read->missing();
		self::assertCount( 2, $missing );
		self::assertEquals( Finding::missing( RuleId::R2, SlotKey::DECK, SpecKey::DeckWidth ), $missing[0] );
		self::assertEquals( Finding::missing( RuleId::R2, SlotKey::TRUCKS, SpecKey::Baseplate ), $missing[1] );
	}
}
