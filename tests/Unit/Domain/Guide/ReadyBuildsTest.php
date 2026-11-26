<?php
/**
 * ReadyBuilds.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Guide;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Guide\ReadyBuild;
use CreativeBranding\SkateboardBuilder\Domain\Guide\ReadyBuilds;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Span;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Tier;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Rule\BearingRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\BoltLengthRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\DeckWidthRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\GripRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MountingRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Rule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleSet;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use CreativeBranding\SkateboardBuilder\Domain\Rule\WheelClearanceRule;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use PHPUnit\Framework\TestCase;

/**
 * #134: the guided start's three ready builds, budget, mid and premium, from in-stock parts that pass the fit engine
 * at the guided width and wheel size: as many as the shop can make, never one that fails fit (D-401).
 */
final class ReadyBuildsTest extends TestCase {

	/**
	 * The builds as tier code to part ID by slot.
	 *
	 * @param list<ReadyBuild> $builds The builds.
	 * @return array<string, array<string, int>>
	 */
	private static function ids( array $builds ): array {
		$ids = array();
		foreach ( $builds as $build ) {
			$ids[ $build->tier->value ] = array_map( static fn( Part $part ): int => $part->id, self::parts( $build->picks ) );
		}
		return $ids;
	}

	/**
	 * A build's parts by slot, in the kit's order.
	 *
	 * @param Picks $picks The picks.
	 * @return array<string, Part>
	 */
	private static function parts( Picks $picks ): array {
		$parts = array();
		foreach ( Shop::kit()->slots->keys() as $slot ) {
			$part = $picks->part( $slot );
			if ( null !== $part ) {
				$parts[ $slot ] = $part;
			}
		}
		return $parts;
	}

	/**
	 * The builds of a shop.
	 *
	 * @param array<string, list<Part>> $parts  The shop's parts by slot.
	 * @param Measure|null              $width  The deck width.
	 * @param Span|null                 $wheels The wheel sizes.
	 * @param FitEngine|null            $engine The engine, the standard one unless given.
	 * @param Kit|null                  $kit    The kit, the usual one unless given.
	 * @return list<ReadyBuild>
	 */
	private static function builds( array $parts, ?Measure $width, ?Span $wheels = null, ?FitEngine $engine = null, ?Kit $kit = null ): array {
		return ( new ReadyBuilds( $engine ?? FitEngine::standard() ) )->choose( $kit ?? Shop::kit(), $parts, $width, $wheels );
	}

	/**
	 * Wheels from 50 to 54 mm, street's.
	 */
	private static function street(): Span {
		return new Span( Measure::millimetres( 500000 ), Measure::millimetres( 540000 ) );
	}

	/**
	 * Three of a part at three prices, ids from a base.
	 *
	 * @param Part $part  The part.
	 * @param int  $id    The first ID.
	 * @param int  $price The cheapest price, each next 1000 more.
	 * @return list<Part>
	 */
	private static function three( Part $part, int $id, int $price ): array {
		$parts = array();
		foreach ( array( 2, 0, 1 ) as $at ) {
			$parts[] = new Part( $id + $at, $part->brand, $part->specs, $price + 1000 * $at, true, null );
		}
		return $parts;
	}

	/**
	 * A shop with three of every part in the width and wheel size asked, each at three prices.
	 *
	 * @return array<string, list<Part>>
	 */
	private static function stocked(): array {
		$parts                     = Shop::parts();
		$parts[ SlotKey::DECK ]    = array( ...self::three( Builds::deck( '8.25' ), 110, 5000 ), Shop::priced( Builds::deck( '8.5', 120 ), 100 ) );
		$parts[ SlotKey::WHEELS ]  = array( ...self::three( Builds::wheel( '52' ), 310, 2000 ), Shop::priced( Builds::wheel( '58', 320 ), 100 ) );
		$parts[ SlotKey::GRIP ]    = self::three( Builds::grip(), 710, 500 );
		return $parts;
	}

	public function test_three_builds_the_cheapest_the_middle_and_the_dearest_part_a_slot_each_passing_the_engine(): void {
		$parts  = self::stocked();
		$builds = self::builds( $parts, Measure::inches( 82500 ), self::street() );
		self::assertSame(
			array(
				'budget'  => array( 'deck' => 110, 'trucks' => 144, 'wheels' => 310, 'bearings' => 501, 'hardware' => 601, 'grip' => 710 ),
				'mid'     => array( 'deck' => 111, 'trucks' => 144, 'wheels' => 311, 'bearings' => 501, 'hardware' => 601, 'grip' => 711 ),
				'premium' => array( 'deck' => 112, 'trucks' => 144, 'wheels' => 312, 'bearings' => 501, 'hardware' => 601, 'grip' => 712 ),
			),
			self::ids( $builds ),
			'the deck in the width asked and the wheels in the sizes asked, however cheap the others; the trucks, the bolts and the grip in the size the build takes'
		);
		$validator = new BuildValidator( FitEngine::standard() );
		$ids       = array_map( static fn( array $list ): array => array_map( static fn( Part $part ): int => $part->id, $list ), $parts );
		foreach ( $builds as $build ) {
			$checked = $validator->validate( Shop::kit(), $build->picks, $ids );
			self::assertTrue( $checked->isValid(), $build->tier->value );
			foreach ( $checked->build as $slot => $option ) {
				self::assertSame( Severity::Fits, $option->severity, $build->tier->value . ': ' . $slot );
			}
		}
	}

	public function test_the_middle_of_an_even_number_is_the_cheaper_of_the_two_and_a_price_is_for_what_the_build_takes(): void {
		$parts                    = self::stocked();
		$four                     = self::three( Builds::deck( '8.25' ), 110, 5000 );
		$four[]                   = Shop::priced( Builds::deck( '8.25', 113 ), 9000 );
		$parts[ SlotKey::DECK ]   = $four;
		$single                   = Builds::wheel( '52', 330 );
		$parts[ SlotKey::WHEELS ] = array(
			Shop::priced( Builds::wheel( '52', 331 ), 3000 ),
			// Sold singly, four cost 4000: dearer than a set of four at 3000.
			new Part( 330, $single->brand, $single->specs->with( \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey::SetSize, 1 ), 1000, true, null ),
		);
		$ids = self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street() ) );
		self::assertSame( 111, $ids['mid']['deck'] ?? null, 'of 5000, 6000, 7000 and 9000, the 6000' );
		self::assertSame( 331, $ids['budget']['wheels'] ?? null, 'a set of four at 3000 before four singles at 1000' );
		self::assertSame( 330, $ids['premium']['wheels'] ?? null );
		// A sheet that gives no set size is one sheet: 600, between 500 and 1100.
		$parts[ SlotKey::GRIP ] = array(
			Shop::priced( Builds::grip( '9', 721 ), 500 ),
			Parts::of( array( 'grip_width' => '9', 'grip_length' => '33' ), 722, 600 ),
			Shop::priced( Builds::grip( '9', 723 ), 1100 ),
		);
		$ids = self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street() ) );
		self::assertSame( array( 721, 722, 723 ), array( $ids['budget']['grip'] ?? null, $ids['mid']['grip'] ?? null, $ids['premium']['grip'] ?? null ) );
	}

	public function test_a_shop_with_one_part_a_slot_makes_one_build(): void {
		$parts = array_map( static fn( array $list ): array => array( $list[0] ), Shop::parts() );
		$parts[ SlotKey::DECK ]   = array( Shop::priced( Builds::deck( '8.25', 102 ), 6500 ) );
		$parts[ SlotKey::TRUCKS ] = array( Shop::priced( Builds::truck( '144' ), 5200 ) );
		$builds = self::builds( $parts, Measure::inches( 82500 ), self::street() );
		self::assertCount( 1, $builds );
		self::assertSame( Tier::Only, $builds[0]->tier );
	}

	public function test_two_builds_alike_are_one_named_for_what_it_is(): void {
		// Two wheels: the middle of two is the cheaper, so the budget and the mid are one build, the cheapest.
		$parts                    = self::stocked();
		$parts[ SlotKey::DECK ]   = array( Shop::priced( Builds::deck( '8.25', 102 ), 6500 ) );
		$parts[ SlotKey::GRIP ]   = array( Shop::priced( Builds::grip(), 800 ) );
		$parts[ SlotKey::WHEELS ] = array( Shop::priced( Builds::wheel( '52', 301 ), 3000 ), Shop::priced( Builds::wheel( '54', 302 ), 3200 ) );
		self::assertSame( array( 'budget', 'premium' ), array_keys( self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street() ) ) ) );
		// Three, the dearest twice: the mid and the premium are one, the dearest.
		$parts[ SlotKey::WHEELS ][] = Shop::priced( Builds::wheel( '53', 303 ), 3200 );
		$ids                        = self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street() ) );
		self::assertSame( array( 'budget', 'mid', 'premium' ), array_keys( $ids ), 'of the same price, the lower ID first: three builds still' );
		self::assertSame( array( 301, 302, 303 ), array( $ids['budget']['wheels'], $ids['mid']['wheels'], $ids['premium']['wheels'] ) );
	}

	public function test_a_sold_out_part_or_one_the_shop_holds_too_few_of_is_left_out(): void {
		$parts                    = self::stocked();
		$parts[ SlotKey::WHEELS ] = array(
			Shop::priced( Builds::wheel( '52', 310 ), 1000, false ),
			// Sold singly, three in stock: a build takes four.
			new Part( 311, '', Builds::wheel( '52' )->specs->with( \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey::SetSize, 1 ), 500, true, 3 ),
			Shop::priced( Builds::wheel( '52', 312 ), 3000 ),
		);
		$parts[ SlotKey::DECK ]   = array( Shop::priced( Builds::deck( '8.25', 110 ), 1000, false ), Shop::priced( Builds::deck( '8.25', 111 ), 6000 ) );
		$builds                   = self::builds( $parts, Measure::inches( 82500 ), self::street() );
		foreach ( self::ids( $builds ) as $tier => $ids ) {
			self::assertSame( 312, $ids['wheels'], $tier . ': the sold-out set and the three wheels where a build takes four are left out' );
			self::assertSame( 111, $ids['deck'], $tier );
		}
	}

	public function test_the_deck_is_in_the_width_asked_and_the_wheels_in_the_sizes_asked_or_there_is_no_build(): void {
		$parts = self::stocked();
		self::assertSame( array(), self::builds( $parts, Measure::inches( 90000 ), self::street() ), 'no deck in 9 in' );
		self::assertSame( array(), self::builds( $parts, Measure::inches( 82500 ), new Span( Measure::millimetres( 600000 ), Measure::millimetres( 650000 ) ) ), 'no wheel from 60 to 65 mm' );
		$any = self::ids( self::builds( $parts, null, null ) );
		self::assertSame( 120, $any['budget']['deck'], 'with no width asked, any deck: the cheapest is 8.5 in' );
		self::assertSame( 149, $any['budget']['trucks'], 'and the trucks fit it' );
		self::assertSame( 310, $any['budget']['wheels'], 'with no size asked, any wheel, one that fits before a cheaper one that asks for a riser' );
		self::assertArrayNotHasKey( 'risers', $any['budget'] );
	}

	public function test_wheels_over_the_trucks_limit_bring_the_riser_the_kit_asks_for_and_bolts_through_it(): void {
		$parts = self::stocked();
		$ids   = self::ids( self::builds( $parts, Measure::inches( 82500 ), new Span( Measure::millimetres( 580000 ), Measure::millimetres( 600000 ) ) ) );
		self::assertSame( array( 'budget', 'mid', 'premium' ), array_keys( $ids ), 'the grip still three ways' );
		foreach ( $ids as $tier => $build ) {
			self::assertSame( 320, $build['wheels'], $tier . ': one wheel in the sizes asked' );
			self::assertSame( 402, $build['risers'] ?? null, $tier . ': the riser the kit\'s table asks for a 58 mm wheel on a 56 mm limit, a quarter inch' );
			self::assertSame( 602, $build['hardware'], $tier . ': 1 in and the riser\'s quarter' );
		}
		$parts[ SlotKey::GRIP ] = array( Shop::priced( Builds::grip(), 800 ) );
		$parts[ SlotKey::DECK ] = array( Shop::priced( Builds::deck( '8.25', 102 ), 6500 ) );
		self::assertSame( Tier::Only, self::builds( $parts, Measure::inches( 82500 ), new Span( Measure::millimetres( 580000 ), null ) )[0]->tier ?? null, 'and larger, one deck and one grip: the only build' );
		$parts[ SlotKey::RISERS ] = array( Shop::priced( Builds::riser( '0.25', 402 ), 600, false ) );
		self::assertSame( array(), self::builds( $parts, Measure::inches( 82500 ), new Span( Measure::millimetres( 580000 ), null ) ), 'the riser asked for sold out: no build, since the wheels only fit with a warning' );
	}

	public function test_a_slot_the_kit_does_not_require_is_left_empty_unless_the_build_asks_for_it(): void {
		foreach ( self::ids( self::builds( self::stocked(), Measure::inches( 82500 ), self::street() ) ) as $tier => $ids ) {
			self::assertArrayNotHasKey( 'risers', $ids, $tier );
		}
	}

	public function test_wheels_with_their_bearings_take_no_others(): void {
		$parts                    = self::stocked();
		$with                     = Builds::wheel( '52', 340 );
		$parts[ SlotKey::WHEELS ] = array( new Part( 340, $with->brand, $with->specs->with( \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey::BearingsIncluded, true ), 4000, true, null ) );
		foreach ( self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street() ) ) as $tier => $ids ) {
			self::assertArrayNotHasKey( 'bearings', $ids, $tier );
			self::assertSame( 340, $ids['wheels'], $tier );
		}
	}

	public function test_a_required_slot_nothing_fills_makes_no_build(): void {
		$parts                      = self::stocked();
		$parts[ SlotKey::BEARINGS ] = array( Shop::priced( Builds::bearings( '8', 501 ), 1500, false ) );
		self::assertSame( array(), self::builds( $parts, Measure::inches( 82500 ), self::street() ), 'the bearings sold out' );
		unset( $parts[ SlotKey::BEARINGS ] );
		self::assertSame( array(), self::builds( $parts, Measure::inches( 82500 ), self::street() ), 'no bearings at all' );
		$parts                    = self::stocked();
		$parts[ SlotKey::TRUCKS ] = array( Shop::priced( Builds::truck( '215' ), 6000 ) );
		self::assertSame( array(), self::builds( $parts, Measure::inches( 82500 ), self::street() ), 'no trucks fit the deck' );
	}

	public function test_a_build_whose_parts_only_fit_with_a_warning_is_never_offered(): void {
		// A rule of the test's own that warns of the grip once the wheels are picked: each step offers the grip, and the
		// whole build is not one that fits.
		$warns  = new class() implements Rule {
			public function id(): RuleId {
				return RuleId::R6;
			}

			/**
			 * @return list<string>
			 */
			public function slots(): array {
				return array( SlotKey::WHEELS, SlotKey::GRIP );
			}

			public function check( Picks $picks, RuleSettings $settings ): array {
				return null === $picks->part( SlotKey::GRIP ) || null === $picks->part( SlotKey::WHEELS ) ? array() : array( new Finding( RuleId::R6, Severity::Warn, MessageCode::GripTooNarrow, Source::Kit, array( SlotKey::GRIP ), array() ) );
			}
		};
		$engine = new FitEngine( new RuleSet( array( new DeckWidthRule(), new MountingRule(), new WheelClearanceRule(), new BoltLengthRule(), new BearingRule(), new GripRule(), $warns ) ) );
		self::assertSame( array(), self::builds( self::stocked(), Measure::inches( 82500 ), self::street(), $engine ) );
	}

	public function test_a_tier_whose_whole_build_does_not_fit_is_left_out_and_the_others_kept(): void {
		// A rule of the test's own that warns of the wheels once the cheapest grip is on: each step takes it, as the
		// grip fits, and only the whole budget build does not.
		$warns  = new class() implements Rule {
			public function id(): RuleId {
				return RuleId::R6;
			}

			/**
			 * @return list<string>
			 */
			public function slots(): array {
				return array( SlotKey::WHEELS, SlotKey::GRIP );
			}

			public function check( Picks $picks, RuleSettings $settings ): array {
				return 710 === $picks->part( SlotKey::GRIP )?->id && null !== $picks->part( SlotKey::WHEELS ) ? array( new Finding( RuleId::R6, Severity::Warn, MessageCode::GripTooNarrow, Source::Kit, array( SlotKey::WHEELS ), array() ) ) : array();
			}
		};
		$engine = new FitEngine( new RuleSet( array( new DeckWidthRule(), new MountingRule(), new WheelClearanceRule(), new BoltLengthRule(), new BearingRule(), new GripRule(), $warns ) ) );
		self::assertSame( array( 'mid', 'premium' ), array_keys( self::ids( self::builds( self::stocked(), Measure::inches( 82500 ), self::street(), $engine ) ) ) );
	}

	public function test_a_slot_the_kit_does_not_require_that_the_build_asks_for_and_the_shop_cannot_fill_is_left_empty_and_the_rest_filled(): void {
		// A rule of the test's own that notes the risers once the wheels are on, as fitting: asked for, and not needed.
		$notes  = new class() implements Rule {
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
				return null === $picks->part( SlotKey::WHEELS ) ? array() : array( new Finding( RuleId::R3, Severity::Fits, MessageCode::LimitFromClass, Source::Kit, array( SlotKey::WHEELS, SlotKey::RISERS ), array() ) );
			}
		};
		$engine = new FitEngine( new RuleSet( array( new DeckWidthRule(), new MountingRule(), new WheelClearanceRule(), new BoltLengthRule(), new BearingRule(), new GripRule(), $notes ) ) );
		$parts  = self::stocked();
		$parts[ SlotKey::RISERS ] = array( Shop::priced( Builds::riser( '0.125', 401 ), 500, false ) );
		$ids    = self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street(), $engine ) );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'bearings', 'hardware', 'grip' ), array_keys( $ids['budget'] ?? array() ) );
		$parts[ SlotKey::RISERS ] = array( Shop::priced( Builds::riser( '0.125', 401 ), 500 ) );
		self::assertSame( 401, self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street(), $engine ) )['budget']['risers'] ?? null, 'and filled where the shop can' );
		$parts[ SlotKey::RISERS ] = array( Parts::of( array( 'set_size' => 2 ), 409, 500 ) );
		self::assertSame( 409, self::ids( self::builds( $parts, Measure::inches( 82500 ), self::street(), new FitEngine( new RuleSet( array( $notes ) ) ) ) )['budget']['risers'] ?? null, 'a riser that gives no thickness, where no rule needs one, is one size of its own' );
	}

	public function test_the_width_is_the_in_stock_deck_nearest_the_middle_of_the_guides_row(): void {
		$decks = array(
			Builds::deck( '7.75', 101 ),
			Builds::deck( '8.0', 102 ),
			Builds::deck( '8.25', 103 ),
			Shop::priced( Builds::deck( '8.125', 104 ), 6000, false ),
			Builds::deck( '8.5', 105 ),
		);
		$width = static fn( string $from, ?string $to, array $among = array() ): ?string => ReadyBuilds::width( new Span( Parts::inches( $from ), null === $to ? null : Parts::inches( $to ) ), array() === $among ? $decks : $among )?->decimal();
		self::assertSame( '8.0000', $width( '8', '8.25' ), '8 and 8.25 are as near 8.125, which is sold out: the narrower' );
		self::assertSame( '8.2500', $width( '8.25', '8.5' ), 'as near: the narrower' );
		self::assertSame( '8.0000', $width( '7.75', '8.25' ), 'the middle itself' );
		self::assertSame( '8.2500', $width( '8.25', null ), 'and wider: the narrowest from it' );
		self::assertSame( '8.5000', $width( '8.3', null ), 'and wider: the nearest above it' );
		self::assertNull( $width( '9', '9.5' ), 'no deck in the row' );
		self::assertNull( $width( '8.1', '8.2' ), 'only a sold-out deck in the row' );
		$last = array( Shop::priced( Builds::deck( '8.125', 106 ), 6000, true, 1 ), Builds::deck( '8.0', 102 ) );
		self::assertSame( '8.1250', $width( '8', '8.25', $last ), 'one left is enough for a deck' );
		$none = array( Shop::priced( Builds::deck( '8.125', 106 ), 6000, true, 0 ), Builds::deck( '8.0', 102 ) );
		self::assertSame( '8.0000', $width( '8', '8.25', $none ), 'none left is sold out, whatever the shop says' );
		self::assertNull( $width( '8', '8.5', array( Parts::of( array( 'deck_length' => '32' ), 109 ) ) ), 'a deck with no width is in no row' );
	}

	public function test_a_budget_keeps_the_builds_it_covers_or_the_cheapest_where_it_covers_none(): void {
		self::assertSame( array( 0, 1, 2 ), ReadyBuilds::affordable( array( 15000, 20000, 30000 ), null ), 'no budget, every build' );
		self::assertSame( array( 0, 1 ), ReadyBuilds::affordable( array( 15000, 20000, 30000 ), 20000 ), 'a build at the budget is in it' );
		self::assertSame( array( 0 ), ReadyBuilds::affordable( array( 15000, 20000, 30000 ), 19999 ) );
		self::assertSame( array( 1 ), ReadyBuilds::affordable( array( 15000, 12000 ), 10000 ), 'none in it: the cheapest alone' );
		self::assertSame( array(), ReadyBuilds::affordable( array(), 10000 ), 'no builds, none' );
		self::assertSame( array( 0 ), ReadyBuilds::affordable( array( 15000, 20000 ), 0 ), 'a budget of nothing: the cheapest' );
		self::assertSame( array( 0 ), ReadyBuilds::affordable( array( 15000, 15000 ), 100 ), 'of two as cheap, the first' );
	}
}
