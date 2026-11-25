<?php
/**
 * Swapper.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Swap;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SwapKind;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Swapper;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * Smart swaps (#135, D-394): for less, the cheapest part in stock that fits the whole build; better, the next up by
 * the shop's rank, or by price where the slot has no ranks; and the same board for less, every slot but the deck.
 */
final class SwapperTest extends TestCase {

	/**
	 * A set of four wheels.
	 *
	 * @param int    $id       Its ID.
	 * @param int    $price    Its price.
	 * @param string $diameter Its diameter in millimetres.
	 * @param bool   $inStock  Whether it is in stock.
	 */
	private static function wheel( int $id, int $price, string $diameter = '54', bool $inStock = true ): Part {
		return Parts::of( array( 'wheel_diameter' => $diameter, 'set_size' => 4 ), $id, $price, '', $inStock );
	}

	/**
	 * The complete build, its wheels the ones given, and every slot's candidates: the build's own part in each, and
	 * these wheels.
	 *
	 * @param Part       $wheels The build's wheels.
	 * @param list<Part> $others The other wheels.
	 * @return array{Picks, array<string, list<Part>>}
	 */
	private static function build( Part $wheels, array $others ): array {
		$build      = Builds::complete( array( SlotKey::WHEELS => $wheels ) );
		$candidates = array();
		foreach ( $build->slots() as $slot ) {
			$candidates[ $slot ] = array( $build->part( $slot ) ?? Builds::deck() );
		}
		$candidates[ SlotKey::WHEELS ] = array_merge( array( $wheels ), $others );
		return array( $build, $candidates );
	}

	/**
	 * The slot's swaps, as kind => the part swapped in.
	 *
	 * @param list<Swap> $swaps The swaps.
	 * @return array<string, int>
	 */
	private static function offered( array $swaps ): array {
		$offered = array();
		foreach ( $swaps as $swap ) {
			foreach ( $swap->to as $option ) {
				$offered[ $swap->kind->value ] = $option->part->id;
			}
		}
		return $offered;
	}

	public function test_for_less_is_the_cheapest_part_in_stock_that_fits_the_whole_build(): void {
		[ $build, $candidates ] = self::build(
			self::wheel( 300, 1000 ),
			array(
				// Cheapest, but too big for the trucks without risers.
				self::wheel( 301, 500, '60' ),
				// Cheaper, but sold out.
				self::wheel( 302, 600, '54', false ),
				self::wheel( 303, 800 ),
				self::wheel( 304, 700 ),
				self::wheel( 305, 900 ),
			)
		);
		$swaps = ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() );
		self::assertSame( SwapKind::Less, $swaps[0]->kind );
		self::assertSame( 304, $swaps[0]->to[ SlotKey::WHEELS ]->part->id, 'the cheapest that fits and is in stock' );
		self::assertSame( 300, $swaps[0]->was[ SlotKey::WHEELS ]->id );
		self::assertSame( array( SlotKey::WHEELS ), array_keys( $swaps[0]->to ) );
		self::assertSame( Severity::Fits, $swaps[0]->to[ SlotKey::WHEELS ]->severity );
		self::assertSame( 304, $swaps[0]->after->build[ SlotKey::WHEELS ]->part->id, 'the build after the swap, for its price' );
		self::assertSame( 149, $swaps[0]->after->build[ SlotKey::TRUCKS ]->part->id, 'the rest of the build kept' );
		self::assertTrue( $swaps[0]->after->isValid() );
	}

	public function test_better_follows_price_where_the_slot_has_no_ranks(): void {
		[ $build, $candidates ] = self::build(
			self::wheel( 300, 1000 ),
			array(
				self::wheel( 306, 1100, '54', false ),
				self::wheel( 307, 1500 ),
				self::wheel( 308, 1200 ),
				// Dearer, but too big.
				self::wheel( 309, 1050, '60' ),
				// The same price is no step up.
				self::wheel( 310, 1000 ),
			)
		);
		$swaps = ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() );
		self::assertSame( array( 'better' => 308 ), self::offered( $swaps ), 'the next dearer part that fits; nothing for less' );
	}

	public function test_better_follows_the_shops_ranks_where_the_slot_has_any(): void {
		[ $build, $candidates ] = self::build(
			self::wheel( 300, 1000 ),
			array(
				self::wheel( 311, 3000 ),
				self::wheel( 312, 2000 ),
				self::wheel( 313, 1100 ),
				// Unranked, so no better than the pick, though dearer.
				self::wheel( 314, 1050 ),
				// Ranked the same as the pick.
				self::wheel( 315, 1300 ),
				// Ranked below it.
				self::wheel( 316, 1400 ),
				// Ranked next up, but sold out.
				self::wheel( 317, 1600, '54', false ),
			)
		);
		$ranks = array(
			300 => 2,
			311 => 3,
			312 => 3,
			313 => 5,
			315 => 2,
			316 => 1,
			317 => 3,
		);
		$swaps = ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, $ranks );
		self::assertSame( array( 'better' => 312 ), self::offered( $swaps ), 'the next rank up, the cheaper of two, not the top rank or the price' );
	}

	public function test_any_ranked_part_is_better_than_an_unranked_pick(): void {
		[ $build, $candidates ] = self::build( self::wheel( 300, 1000 ), array( self::wheel( 318, 1500 ), self::wheel( 319, 900 ), self::wheel( 320, 1200 ) ) );
		$swaps = ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array( 318 => 4, 319 => 1 ) );
		self::assertSame(
			array( 'better' => 319 ),
			self::offered( $swaps ),
			'the lowest ranked part, which is the cheapest too, offered once; the unranked dearer part is no better'
		);
	}

	public function test_a_pick_ranked_at_the_top_with_nothing_cheaper_is_offered_no_swap(): void {
		[ $build, $candidates ] = self::build( self::wheel( 300, 1000 ), array( self::wheel( 321, 1500 ), self::wheel( 322, 1000 ) ) );
		self::assertSame( array(), ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array( 300 => 9, 321 => 4 ) ) );
	}

	public function test_a_part_the_shop_cannot_supply_in_the_quantity_the_build_takes_is_passed_over(): void {
		$short                  = new Part( 323, '', Builds::wheel( '54' )->specs, 500, true, 0 );
		[ $build, $candidates ] = self::build( self::wheel( 300, 1000 ), array( $short ) );
		self::assertSame( array(), ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() ) );
	}

	public function test_wheels_that_come_with_bearings_swap_only_for_wheels_that_leave_no_slot_empty(): void {
		$own                    = Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4, 'bearings_included' => true ), 300, 1000 );
		[ $build, $candidates ] = self::build( $own, array( self::wheel( 324, 500 ), Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4, 'bearings_included' => true ), 325, 800 ) ) );
		$build                  = $build->without( SlotKey::BEARINGS );
		$swaps                  = ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() );
		self::assertSame( array( 'less' => 325 ), self::offered( $swaps ), 'wheels without bearings would leave the bearings slot empty' );
	}

	public function test_a_build_not_yet_whole_is_offered_swaps_that_add_no_problem_to_it(): void {
		[ $build, $candidates ] = self::build( self::wheel( 300, 1000 ), array( self::wheel( 326, 700 ) ) );
		$build                  = $build->without( SlotKey::GRIP );
		$swaps                  = ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() );
		self::assertSame( array( 'less' => 326 ), self::offered( $swaps ), 'the empty grip slot was empty before' );
		self::assertFalse( $swaps[0]->after->isValid() );
	}

	public function test_a_build_keeps_the_problems_it_had_and_takes_on_no_other(): void {
		$sold                   = new Part( 500, '', Builds::bearings()->specs, 1000, false );
		[ $build, $candidates ] = self::build( self::wheel( 300, 1000 ), array( self::wheel( 339, 700 ) ) );
		$build                  = $build->with( SlotKey::BEARINGS, $sold )->without( SlotKey::GRIP );
		self::assertSame( array( 'less' => 339 ), self::offered( ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() ) ), 'sold-out bearings and no grip, before and after' );
	}

	public function test_a_problem_is_new_where_its_kind_or_its_slot_is(): void {
		$bundled = static fn( int $id, int $price ): Part => Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4, 'bearings_included' => true ), $id, $price );
		// No grip and no bearings, beside wheels that come with them: wheels without would leave a second slot empty.
		[ $build, $candidates ] = self::build( $bundled( 300, 1000 ), array( self::wheel( 340, 500 ), $bundled( 341, 800 ) ) );
		$build                  = $build->without( SlotKey::GRIP )->without( SlotKey::BEARINGS );
		$swapper                = new Swapper( FitEngine::standard() );
		self::assertSame( array( 'less' => 341 ), self::offered( $swapper->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() ) ), 'an empty slot is new in another slot' );
		// Sold-out wheels: wheels the shop holds too few of are short, a problem of another kind in the same slot.
		$short                  = new Part( 342, '', Builds::wheel( '54' )->specs, 500, true, 0 );
		[ $build, $candidates ] = self::build( self::wheel( 300, 1000, '54', false ), array( $short, self::wheel( 343, 900 ) ) );
		self::assertSame( array( 'less' => 343 ), self::offered( $swapper->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() ) ), 'a short part is new where the pick was sold out' );
	}

	public function test_a_part_with_no_set_size_is_never_swapped_in_and_a_pick_with_none_is_offered_nothing(): void {
		$loose                  = Parts::of( array( 'wheel_diameter' => '54' ), 327, 100 );
		[ $build, $candidates ] = self::build( self::wheel( 300, 1000 ), array( $loose ) );
		$swapper                = new Swapper( FitEngine::standard() );
		self::assertSame( array(), $swapper->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() ), 'a part with no set size has no price in a build' );
		[ $build, $candidates ] = self::build( $loose, array( self::wheel( 328, 50 ) ) );
		self::assertSame( array(), $swapper->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, $candidates, array() ) );
	}

	public function test_the_deck_an_empty_slot_and_a_slot_the_kit_lacks_are_offered_nothing(): void {
		[ $build, $candidates ]        = self::build( self::wheel( 300, 1000 ), array() );
		$candidates[ SlotKey::DECK ][] = Builds::deck( '8.5', 101 );
		$swapper                       = new Swapper( FitEngine::standard() );
		$cheap                         = new Part( 102, '', Builds::deck()->specs, 1, true );
		$candidates[ SlotKey::DECK ][] = $cheap;
		self::assertSame( array(), $swapper->slot( KitDocuments::kit(), $build, SlotKey::DECK, $candidates, array() ), 'the deck is the shopper\'s look' );
		self::assertSame( array(), $swapper->slot( KitDocuments::kit(), $build, SlotKey::RISERS, $candidates + array( SlotKey::RISERS => array( Builds::riser( '0.125' ) ) ), array() ) );
		$odd = $build->with( 'stickers', self::wheel( 330, 100 ) );
		self::assertSame( array(), $swapper->slot( KitDocuments::kit(), $odd, 'stickers', array( 'stickers' => array( self::wheel( 331, 50 ) ) ), array() ) );
	}

	public function test_a_slot_with_no_candidates_is_offered_nothing(): void {
		$build = Builds::complete( array( SlotKey::WHEELS => self::wheel( 300, 1000 ) ) );
		self::assertSame( array(), ( new Swapper( FitEngine::standard() ) )->slot( KitDocuments::kit(), $build, SlotKey::WHEELS, array(), array() ) );
	}

	public function test_the_same_board_for_less_swaps_every_slot_but_the_deck_and_fits_as_a_whole(): void {
		[ $build, $candidates ]            = self::build( self::wheel( 300, 1000 ), array( self::wheel( 332, 400, '60' ), self::wheel( 333, 700 ) ) );
		$candidates[ SlotKey::DECK ][]     = new Part( 103, '', Builds::deck()->specs, 1, true );
		$candidates[ SlotKey::BEARINGS ][] = Builds::bearings( '8', 501 );
		$candidates[ SlotKey::BEARINGS ][] = new Part( 502, '', Builds::bearings()->specs, 400, true );
		$candidates[ SlotKey::BEARINGS ][] = new Part( 503, '', Builds::bearings( '7' )->specs, 100, true );
		$swap                              = ( new Swapper( FitEngine::standard() ) )->board( KitDocuments::kit(), $build, $candidates );
		self::assertNotNull( $swap );
		self::assertSame( SwapKind::Board, $swap->kind );
		self::assertSame(
			array(
				SlotKey::WHEELS   => 333,
				SlotKey::BEARINGS => 502,
			),
			array_map( static fn( $option ): int => $option->part->id, $swap->to ),
			'in step order; the deck kept, and bearings that do not fit the axles passed over'
		);
		self::assertSame(
			array(
				SlotKey::WHEELS   => 300,
				SlotKey::BEARINGS => 500,
			),
			array_map( static fn( Part $part ): int => $part->id, $swap->was )
		);
		self::assertSame( 100, $swap->after->build[ SlotKey::DECK ]->part->id );
		self::assertSame( 333, $swap->after->build[ SlotKey::WHEELS ]->part->id, 'the board after every swap' );
		self::assertSame( 502, $swap->after->build[ SlotKey::BEARINGS ]->part->id );
		self::assertTrue( $swap->after->isValid() );
	}

	public function test_the_same_board_for_less_holds_each_slot_against_the_slots_swapped_before_it(): void {
		$riser = static fn( int $id, string $thickness, int $price ): Part => Parts::of( array( 'riser_thickness' => $thickness, 'set_size' => 2 ), $id, $price );
		$bolts = static fn( int $id, string $length, int $price ): Part => Parts::of( array( 'bolt_length' => $length, 'set_size' => 8 ), $id, $price );
		$build = Builds::complete(
			array(
				SlotKey::RISERS   => $riser( 400, '0.125', 1000 ),
				SlotKey::HARDWARE => $bolts( 600, '1.25', 1000 ),
			)
		);
		$candidates = array();
		foreach ( $build->slots() as $slot ) {
			$candidates[ $slot ] = array( $build->part( $slot ) ?? Builds::deck() );
		}
		$candidates[ SlotKey::RISERS ][]   = $riser( 401, '0.25', 500 );
		$candidates[ SlotKey::HARDWARE ][] = $bolts( 602, '1', 300 );
		$swapper                           = new Swapper( FitEngine::standard() );
		$swap                              = $swapper->board( KitDocuments::kit(), $build, $candidates );
		self::assertSame(
			array( SlotKey::RISERS => 401 ),
			array_map( static fn( $option ): int => $option->part->id, $swap->to ?? array() ),
			'1″ bolts reach through ⅛″ risers, not the ¼″ ones the board swapped in before them'
		);
		self::assertSame( array( 'less' => 602 ), self::offered( $swapper->slot( KitDocuments::kit(), $build, SlotKey::HARDWARE, $candidates, array() ) ), 'alone, the bolts are offered for less' );
	}

	public function test_a_board_with_nothing_for_less_is_offered_no_board(): void {
		[ $build, $candidates ]        = self::build( self::wheel( 300, 1000 ), array( self::wheel( 336, 1100 ) ) );
		$candidates[ SlotKey::DECK ][] = new Part( 104, '', Builds::deck()->specs, 1, true );
		self::assertNull( ( new Swapper( FitEngine::standard() ) )->board( KitDocuments::kit(), $build, $candidates ), 'a cheaper deck is no swap' );
	}

	public function test_a_board_passes_over_a_pick_with_no_set_size_and_a_slot_with_none(): void {
		$loose                  = Parts::of( array( 'wheel_diameter' => '54' ), 337, 1000 );
		[ $build, $candidates ] = self::build( $loose, array( self::wheel( 338, 100 ) ) );
		$build                  = $build->without( SlotKey::GRIP );
		$candidates[ SlotKey::HARDWARE ][] = new Part( 601, '', Builds::bolts()->specs, 1, true );
		$swap                   = ( new Swapper( FitEngine::standard() ) )->board( KitDocuments::kit(), $build, $candidates );
		self::assertSame( array( SlotKey::HARDWARE ), array_keys( $swap->to ?? array() ), 'the loose wheels have no price; the grip has no pick' );
	}
}
