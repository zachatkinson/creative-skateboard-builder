<?php
/**
 * RuleSet.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\DeckWidthRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MountingRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Rule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleSet;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * The six rules run together, and a kit's switches.
 */
final class RuleSetTest extends TestCase {

	/**
	 * A build that every rule has something to say about.
	 */
	private static function wrong(): Picks {
		return Parts::picks(
			array(
				SlotKey::DECK     => Parts::of( array( 'deck_width' => '9.5', 'deck_length' => '33', 'hole_pattern' => 'old_school' ) ),
				SlotKey::TRUCKS   => Parts::indy( '144', array( 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8' ) ),
				SlotKey::WHEELS   => Parts::of( array( 'wheel_diameter' => '60' ) ),
				SlotKey::BEARINGS => Parts::of( array( 'bearing_bore' => '7' ) ),
				SlotKey::HARDWARE => Parts::of( array( 'bolt_length' => '3/4' ) ),
				SlotKey::GRIP     => Parts::of( array( 'grip_width' => '9', 'grip_length' => '33' ) ),
			)
		);
	}

	/**
	 * A build that fits.
	 */
	private static function right(): Picks {
		return Parts::picks(
			array(
				SlotKey::DECK     => Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '31.875', 'hole_pattern' => 'new_school' ) ),
				SlotKey::TRUCKS   => Parts::indy( '144', array( 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8' ) ),
				SlotKey::WHEELS   => Parts::of( array( 'wheel_diameter' => '54' ) ),
				SlotKey::BEARINGS => Parts::of( array( 'bearing_bore' => '8' ) ),
				SlotKey::HARDWARE => Parts::of( array( 'bolt_length' => '1' ) ),
				SlotKey::GRIP     => Parts::of( array( 'grip_width' => '9', 'grip_length' => '33' ) ),
			)
		);
	}

	public function test_a_build_that_fits_has_no_findings(): void {
		self::assertSame( array(), RuleSet::standard()->check( self::right(), RuleSettings::defaults() ) );
		self::assertSame( array(), RuleSet::standard()->check( Picks::none(), RuleSettings::defaults() ) );
	}

	public function test_the_standard_set_is_r1_to_r6_in_order(): void {
		$findings = RuleSet::standard()->check( self::wrong(), RuleSettings::defaults() );
		self::assertSame( RuleId::fit(), array_column( $findings, 'rule' ) );
		self::assertSame(
			array(
				MessageCode::DeckAboveRange,
				MessageCode::PatternMismatch,
				MessageCode::WheelNeedsRiser,
				MessageCode::BoltsTooShort,
				MessageCode::BoreMismatch,
				MessageCode::GripTooNarrow,
			),
			array_column( $findings, 'code' )
		);
		self::assertSame( Severity::Block, Finding::worst( $findings ) );
	}

	public function test_a_rule_the_kit_switched_off_finds_nothing(): void {
		foreach ( RuleId::fit() as $off ) {
			$document                           = RuleSettings::DEFAULTS;
			$document[ $off->value ]['enabled'] = false;
			$findings                           = RuleSet::standard()->check( self::wrong(), RuleSettings::fromDocument( $document ) );
			self::assertSame(
				array_values( array_filter( RuleId::fit(), static fn( RuleId $rule ): bool => $rule !== $off ) ),
				array_column( $findings, 'rule' ),
				$off->value
			);
		}
	}

	public function test_a_slot_is_read_with_the_slots_its_rules_read_and_by_none_where_its_rules_are_off(): void {
		// Cowork's review, should-fix 5: a card's fit label names what the part was checked against.
		$on  = RuleSettings::defaults();
		$set = RuleSet::standard();
		self::assertSame( array( SlotKey::TRUCKS, SlotKey::GRIP ), $set->reading( SlotKey::DECK, $on ) );
		self::assertSame( array( SlotKey::DECK, SlotKey::WHEELS, SlotKey::RISERS, SlotKey::BEARINGS, SlotKey::SPACERS ), $set->reading( SlotKey::TRUCKS, $on ) );
		self::assertSame( array( SlotKey::TRUCKS, SlotKey::RISERS ), $set->reading( SlotKey::WHEELS, $on ) );
		self::assertSame( array( SlotKey::RISERS ), $set->reading( SlotKey::HARDWARE, $on ) );
		self::assertSame( array( SlotKey::DECK ), $set->reading( SlotKey::GRIP, $on ) );
		self::assertNull( $set->reading( 'stickers', $on ), 'a slot no rule reads' );

		$document                               = RuleSettings::DEFAULTS;
		$document[ RuleId::R6->value ]['enabled'] = false;
		self::assertNull( $set->reading( SlotKey::GRIP, RuleSettings::fromDocument( $document ) ), 'grip, with R6 off' );
		self::assertSame( array( SlotKey::TRUCKS ), $set->reading( SlotKey::DECK, RuleSettings::fromDocument( $document ) ) );
		self::assertNull( ( new RuleSet( array() ) )->reading( SlotKey::DECK, $on ), 'a set of no rules reads nothing' );
	}

	public function test_the_deck_width_and_mounting_rules_each_read_the_deck_with_the_trucks_alone(): void {
		// R1 and R2 read the same two slots, so in the standard set either one hides the other's list.
		$on = RuleSettings::defaults();
		self::assertSame( array( SlotKey::DECK ), ( new RuleSet( array( new DeckWidthRule() ) ) )->reading( SlotKey::TRUCKS, $on ), 'R1' );
		self::assertSame( array( SlotKey::DECK ), ( new RuleSet( array( new MountingRule() ) ) )->reading( SlotKey::TRUCKS, $on ), 'R2' );
	}

	public function test_a_slot_read_again_by_a_later_rule_with_a_new_one_is_still_a_list(): void {
		// No two of the six rules overlap this way; a seventh may. The list starts again at 0, or the state route's JSON
		// would carry an object.
		$wider = new class() implements Rule {
			public function id(): RuleId {
				return RuleId::R3;
			}

			/** @return list<string> */
			public function slots(): array {
				return array( SlotKey::TRUCKS, SlotKey::DECK, SlotKey::WHEELS );
			}

			/** @return list<Finding> */
			public function check( Picks $picks, RuleSettings $settings ): array {
				return array();
			}
		};
		$set = new RuleSet( array( new DeckWidthRule(), $wider ) );
		self::assertSame( array( SlotKey::DECK, SlotKey::WHEELS ), $set->reading( SlotKey::TRUCKS, RuleSettings::defaults() ) );
	}

	public function test_a_set_of_no_rules_finds_nothing(): void {
		self::assertSame( array(), ( new RuleSet( array() ) )->check( self::wrong(), RuleSettings::defaults() ) );
	}
}
