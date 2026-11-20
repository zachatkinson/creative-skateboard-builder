<?php
/**
 * ChangePlanner.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\ChangeList;
use CreativeBranding\SkateboardBuilder\Domain\Fit\ChangePlanner;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use PHPUnit\Framework\TestCase;

/**
 * A change that breaks later picks (spec: The builder; docs/UX-RESEARCH.md, P8).
 */
final class ChangePlannerTest extends TestCase {

	/**
	 * The change list for one pick changed, on the usual kit.
	 *
	 * @param Picks                     $before     The build as it is.
	 * @param string                    $slot       The slot changed.
	 * @param Part                      $part       The part it changes to.
	 * @param array<string, list<Part>> $candidates The later slots' parts.
	 */
	private static function plan( Picks $before, string $slot, Part $part, array $candidates = array() ): ChangeList {
		return ( new ChangePlanner( FitEngine::standard() ) )->plan( KitDocuments::kit(), $before, $slot, $part, $candidates );
	}

	/**
	 * A truck with a range, by axle, brand and price.
	 */
	private static function truck( int $id, string $axle, string $brand = 'independent', int $price = 2999, bool $inStock = true ): Part {
		return Parts::of(
			array(
				'axle_width'    => $axle,
				'deck_min'      => '8.4',
				'deck_max'      => '9.0',
				'baseplate'     => 'new_school',
				'max_wheel'     => '56',
				'axle_diameter' => '8',
				'set_size'      => 2,
			),
			$id,
			$price,
			$brand,
			$inStock
		);
	}

	public function test_p8_a_wider_deck_names_what_is_kept_what_no_longer_fits_and_the_closest_part_that_does(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$wider  = Builds::deck( '8.5', 101 );
		$list   = self::plan( $before, SlotKey::DECK, $wider, array( SlotKey::TRUCKS => array( Builds::truck( '139' ), Builds::truck( '144' ), Builds::truck( '151' ), Builds::truck( '149' ), Builds::truck( '146' ) ) ) );

		self::assertFalse( $list->isClean() );
		self::assertSame( array( SlotKey::WHEELS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), $list->kept );
		self::assertCount( 1, $list->changes );
		$change = $list->changes[0];
		self::assertSame( SlotKey::TRUCKS, $change->slot );
		self::assertSame( 144, $change->was->id );
		self::assertSame( array( MessageCode::DeckAboveRange ), array_column( $change->findings, 'code' ), 'why it no longer fits' );
		self::assertSame( 149, $change->closest?->part->id, 'the axle nearest the new deck: the 149 is 8.5 in, and the 146 that sits nearer the old truck is 8.375' );
		self::assertSame( Severity::Fits, $change->closest?->severity );
		self::assertSame( 1, $change->closest?->quantity );
	}

	public function test_p8_the_list_is_a_proposal_and_an_undo_and_applies_nothing(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$wider  = Builds::deck( '8.5', 101 );
		$list   = self::plan( $before, SlotKey::DECK, $wider, array( SlotKey::TRUCKS => array( Builds::truck( '149' ) ) ) );
		self::assertSame( $before, $list->before, 'Undo is the build as it was' );
		self::assertSame( 100, $before->part( SlotKey::DECK )?->id, 'which nothing has touched' );
		self::assertSame( 144, $before->part( SlotKey::TRUCKS )?->id );
		self::assertSame( 101, $list->proposed->part( SlotKey::DECK )?->id, 'Use these is the build with the change made' );
		self::assertSame( 149, $list->proposed->part( SlotKey::TRUCKS )?->id, 'and the closest fit swapped in' );
		foreach ( $list->kept as $slot ) {
			self::assertSame( $before->part( $slot ), $list->proposed->part( $slot ), $slot );
		}
		self::assertEqualsCanonicalizing( $before->slots(), $list->proposed->slots() );
		foreach ( FitEngine::standard()->build( KitDocuments::kit(), $list->proposed ) as $slot => $option ) {
			self::assertSame( Severity::Fits, $option->severity, $slot . ' fits in the proposed build' );
		}
	}

	public function test_wheels_that_come_with_their_bearings_take_the_bearing_picked_out(): void {
		$included = Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4, 'bearings_included' => true ), 301 );
		$list     = self::plan( Builds::complete(), SlotKey::WHEELS, $included, array( SlotKey::BEARINGS => array( Builds::bearings( '8', 501 ) ) ) );
		self::assertSame( array( SlotKey::BEARINGS ), array_column( $list->changes, 'slot' ) );
		self::assertSame( array( MessageCode::BearingsIncluded ), array_column( $list->changes[0]->findings, 'code' ) );
		self::assertNull( $list->changes[0]->closest, 'no bearing goes with them' );
		self::assertNull( $list->proposed->part( SlotKey::BEARINGS ) );
	}

	public function test_a_changes_findings_are_a_list_when_a_note_comes_before_the_block(): void {
		// A kit that asks for trucks after wheels and bearings: the truck's note about its wheel limit comes first, and
		// its 10 mm axle, which the bearings do not fit, second.
		$document          = KitDocuments::slots();
		$rows              = array_column( $document['slots'], null, 'key' );
		$document['slots'] = array( $rows['deck'], $rows['wheels'], $rows['risers'], $rows['bearings'], $rows['trucks'], $rows['hardware'], $rows['grip'] );
		$before            = Builds::complete( array( SlotKey::TRUCKS => Parts::indy( '149', array( 'baseplate' => 'new_school', 'axle_diameter' => '10', 'set_size' => 2 ) ) ) );
		$list              = ( new ChangePlanner( FitEngine::standard() ) )->plan( KitDocuments::kit( $document ), $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array() );

		self::assertCount( 1, $list->changes );
		self::assertSame( SlotKey::TRUCKS, $list->changes[0]->slot );
		self::assertSame( array( 0 ), array_keys( $list->changes[0]->findings ) );
		self::assertSame( MessageCode::BoreMismatch, $list->changes[0]->findings[0]->code );
	}

	public function test_a_slot_the_kit_lacks_changes_nothing(): void {
		$before = Builds::complete();
		$list   = self::plan( $before, 'stickers', Parts::of( array( 'set_size' => 1 ), 900 ) );

		self::assertEquals( $before, $list->proposed, 'every pick is still there, and the stray part is not' );
		self::assertNull( $list->proposed->part( 'stickers' ) );
		self::assertSame( $before->slots(), $list->kept );
		self::assertSame( array(), $list->changes );
		self::assertTrue( $list->isClean() );
	}

	public function test_a_change_that_breaks_nothing_is_clean(): void {
		$before = Builds::complete();
		$list   = self::plan( $before, SlotKey::DECK, Builds::deck( '8.4', 101 ) );
		self::assertTrue( $list->isClean() );
		self::assertSame( array(), $list->changes );
		self::assertSame( array( SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), $list->kept );
		self::assertSame( 101, $list->proposed->part( SlotKey::DECK )?->id );
		self::assertSame( $before->part( SlotKey::GRIP ), $list->proposed->part( SlotKey::GRIP ) );
	}

	public function test_a_later_pick_that_only_warns_is_kept(): void {
		// Bigger wheels on the same trucks want a riser: a warning, so the bearings, bolts and grip stay and nothing is asked.
		$list = self::plan( Builds::complete(), SlotKey::WHEELS, Builds::wheel( '58', 301 ) );
		self::assertTrue( $list->isClean() );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), $list->kept, 'earlier picks are kept as they are, in step order' );
		// A narrower axle within the kit's second tolerance warns against the deck already picked.
		$loose = Parts::of( array( 'axle_width' => '8.0', 'baseplate' => 'new_school', 'max_wheel' => '56', 'set_size' => 2 ), 250 );
		$list  = self::plan( Builds::complete(), SlotKey::DECK, Builds::deck( '8.5', 101 ), array() );
		self::assertTrue( $list->isClean() );
		$list = self::plan( Builds::complete( array( SlotKey::TRUCKS => $loose ) ), SlotKey::DECK, Builds::deck( '8.45', 101 ) );
		self::assertTrue( $list->isClean(), 'a warned truck is kept' );
	}

	public function test_only_the_slots_after_the_change_are_asked_about(): void {
		// The deck and trucks do not fit each other; changing the wheels is not the moment to say so.
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '9.5' ) ) );
		$list   = self::plan( $before, SlotKey::WHEELS, Builds::wheel( '52', 301 ) );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::BEARINGS, SlotKey::HARDWARE ), $list->kept );
		self::assertSame( array( SlotKey::GRIP ), array_column( $list->changes, 'slot' ), 'the grip is after the wheels, and is held against the deck as it stands' );
		self::assertSame( $before->part( SlotKey::DECK ), $list->proposed->part( SlotKey::DECK ) );
	}

	public function test_a_slot_with_nothing_picked_is_passed_over(): void {
		$before = Parts::picks( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::GRIP => Builds::grip() ) );
		$list   = self::plan( $before, SlotKey::DECK, Builds::deck( '9.5', 101 ), array( SlotKey::TRUCKS => array( Builds::truck( '169' ) ), SlotKey::GRIP => array( Builds::grip( '10', 701 ) ) ) );
		self::assertSame( array(), $list->kept );
		self::assertSame( array( SlotKey::GRIP ), array_column( $list->changes, 'slot' ) );
		self::assertSame( 701, $list->changes[0]->closest?->part->id );
		self::assertEqualsCanonicalizing( array( SlotKey::DECK, SlotKey::GRIP ), $list->proposed->slots(), 'no slot is filled that was empty' );
	}

	public function test_with_nothing_that_fits_the_slot_is_left_to_pick_again(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$wider  = Builds::deck( '8.5', 101 );
		$soldOut = Parts::indy( '149', array( 'baseplate' => 'new_school', 'max_wheel' => '56', 'set_size' => 2 ) );
		$soldOut = new Part( 149, 'independent', $soldOut->specs, 2999, false );
		foreach ( array( array(), array( SlotKey::TRUCKS => array() ), array( SlotKey::TRUCKS => array( Builds::truck( '139' ) ) ), array( SlotKey::TRUCKS => array( $soldOut ) ) ) as $candidates ) {
			$list = self::plan( $before, SlotKey::DECK, $wider, $candidates );
			self::assertCount( 1, $list->changes );
			self::assertNull( $list->changes[0]->closest );
			self::assertNull( $list->proposed->part( SlotKey::TRUCKS ), 'the slot is empty in the proposed build' );
			self::assertSame( array( SlotKey::WHEELS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), $list->kept, 'and the rest is held against the build without it' );
		}
	}

	public function test_closest_is_the_nearest_spec_before_brand_or_price(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$list   = self::plan(
			$before,
			SlotKey::DECK,
			Builds::deck( '8.5', 101 ),
			array(
				SlotKey::TRUCKS => array(
					self::truck( 1, '8.75', 'independent', 2999 ),
					self::truck( 2, '8.5', 'thunder', 9999 ),
					self::truck( 3, '8.6', 'independent', 2999 ),
				),
			)
		);
		self::assertSame( 2, $list->changes[0]->closest?->part->id, 'another brand at three times the price, and the nearest axle' );
	}

	public function test_closest_is_measured_both_ways(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$list = self::plan(
			$before,
			SlotKey::DECK,
			Builds::deck( '8.5', 101 ),
			array( SlotKey::TRUCKS => array( self::truck( 1, '8.75' ), self::truck( 2, '8.3' ), self::truck( 3, '8.9' ) ) )
		);
		self::assertSame( 2, $list->changes[0]->closest?->part->id, '8.3 in is 0.2 under the 8.5 in deck; 8.75 is 0.25 over' );
	}

	public function test_closest_then_prefers_the_same_brand(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$list   = self::plan(
			$before,
			SlotKey::DECK,
			Builds::deck( '8.5', 101 ),
			array(
				SlotKey::TRUCKS => array(
					self::truck( 1, '8.5', 'thunder', 2999 ),
					self::truck( 2, '8.5', 'independent', 9999 ),
					self::truck( 3, '8.5', 'venture', 2999 ),
				),
			)
		);
		self::assertSame( 2, $list->changes[0]->closest?->part->id, 'the same axle, and the brand picked before, whatever it costs' );
	}

	public function test_closest_then_prefers_the_nearest_price_and_then_the_first_given(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$plan   = static fn( array $trucks ): ?int => self::plan( $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array( SlotKey::TRUCKS => $trucks ) )->changes[0]->closest?->part->id;
		self::assertSame( 3, $plan( array( self::truck( 1, '8.5', 'thunder', 3600 ), self::truck( 2, '8.5', 'thunder', 2300 ), self::truck( 3, '8.5', 'thunder', 3500 ) ) ), '501 dearer is nearer than 699 cheaper' );
		self::assertSame( 2, $plan( array( self::truck( 1, '8.5', 'thunder', 3600 ), self::truck( 2, '8.5', 'thunder', 2500 ), self::truck( 3, '8.5', 'thunder', 3500 ) ) ), '499 cheaper is nearer than 501 dearer' );
		self::assertSame( 1, $plan( array( self::truck( 1, '8.5', 'thunder', 3499 ), self::truck( 2, '8.5', 'thunder', 2499 ), self::truck( 3, '8.5', 'thunder', 3499 ) ) ), 'all three tie: the first given' );
	}

	public function test_closest_is_in_stock(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$list   = self::plan(
			$before,
			SlotKey::DECK,
			Builds::deck( '8.5', 101 ),
			array( SlotKey::TRUCKS => array( self::truck( 1, '8.25', 'independent', 2999, false ), self::truck( 2, '8.75', 'thunder', 9999 ) ) )
		);
		self::assertSame( 2, $list->changes[0]->closest?->part->id );
	}

	public function test_closest_is_one_the_shop_holds_enough_of(): void {
		// The nearest truck is sold singly with one left, and the build takes two (issue #14).
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$near   = self::truck( 1, '8.5' );
		$single = static fn( int $stock ): Part => new Part( 1, 'independent', $near->specs->with( SpecKey::SetSize, 1 ), 2999, true, $stock );
		$far    = self::truck( 2, '8.75', 'thunder', 9999 );
		self::assertSame( 2, self::plan( $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array( SlotKey::TRUCKS => array( $single( 1 ), $far ) ) )->changes[0]->closest?->part->id );
		self::assertSame( 1, self::plan( $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array( SlotKey::TRUCKS => array( $single( 2 ), $far ) ) )->changes[0]->closest?->part->id );
	}

	public function test_a_part_with_no_figure_on_the_slots_spec_is_the_furthest(): void {
		$before  = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$unsized = Parts::of( array( 'deck_min' => '8.4', 'deck_max' => '9.0', 'baseplate' => 'new_school', 'max_wheel' => '56', 'set_size' => 2 ), 1, 2999, 'independent' );
		$list    = self::plan( $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array( SlotKey::TRUCKS => array( $unsized, self::truck( 2, '9.0', 'thunder', 9999 ) ) ) );
		self::assertSame( 2, $list->changes[0]->closest?->part->id, 'a range and no axle fits, and is nearest to nothing' );
		$list = self::plan( $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array( SlotKey::TRUCKS => array( $unsized ) ) );
		self::assertSame( 1, $list->changes[0]->closest?->part->id, 'and is still better than an empty slot' );
	}

	public function test_a_swap_is_what_the_next_slot_is_held_against(): void {
		// The only truck that takes the wider deck has a 10 mm axle, so the 8 mm bearings go too.
		$before   = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$longAxle = Parts::of( array( 'axle_width' => '8.5', 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '10', 'set_size' => 2 ), 250 );
		$list     = self::plan(
			$before,
			SlotKey::DECK,
			Builds::deck( '8.5', 101 ),
			array(
				SlotKey::TRUCKS   => array( $longAxle ),
				SlotKey::BEARINGS => array( Builds::bearings( '8', 501 ), Builds::bearings( '10', 502 ) ),
			)
		);
		self::assertSame( array( SlotKey::TRUCKS, SlotKey::BEARINGS ), array_column( $list->changes, 'slot' ) );
		self::assertSame( 250, $list->changes[0]->closest?->part->id );
		self::assertSame( array( MessageCode::BoreMismatch ), array_column( $list->changes[1]->findings, 'code' ) );
		self::assertSame( 502, $list->changes[1]->closest?->part->id );
		self::assertSame( array( SlotKey::WHEELS, SlotKey::HARDWARE, SlotKey::GRIP ), $list->kept );
		self::assertSame( 502, $list->proposed->part( SlotKey::BEARINGS )?->id );
	}

	public function test_a_slot_the_rules_do_not_know_goes_by_brand_and_price(): void {
		$slots            = KitDocuments::slots();
		$slots['slots'][] = array(
			'key'      => 'tool',
			'label'    => 'Skate tool',
			'required' => false,
			'units'    => 2,
		);
		$kit              = KitDocuments::kit( $slots );
		$unsized          = Parts::of( array(), 900, 1500, 'rascals' );
		$before           = Builds::complete()->with( 'tool', $unsized );
		$tool             = static fn( int $id, string $brand, int $price ): Part => Parts::of( array( 'set_size' => 1 ), $id, $price, $brand );
		$list             = ( new ChangePlanner( FitEngine::standard() ) )->plan( $kit, $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array( 'tool' => array( $tool( 901, 'other', 1500 ), $tool( 902, 'rascals', 900 ), $tool( 903, 'rascals', 1400 ) ) ) );
		self::assertSame( array( 'tool' ), array_column( $list->changes, 'slot' ) );
		self::assertSame( RuleId::R7, $list->changes[0]->findings[0]->rule, 'a pick with no set size was never offerable' );
		self::assertSame( 903, $list->changes[0]->closest?->part->id );
	}

	public function test_a_pick_with_no_figure_of_its_own_goes_by_brand_and_price(): void {
		$unsized = Parts::of( array( 'grip_length' => '33', 'set_size' => 1 ), 700, 1000, 'mob' );
		$grip    = static fn( int $id, string $width, string $brand, int $price ): Part => Parts::of( array( 'grip_width' => $width, 'grip_length' => '33', 'set_size' => 1 ), $id, $price, $brand );
		$list    = self::plan( Builds::complete( array( SlotKey::GRIP => $unsized ) ), SlotKey::DECK, Builds::deck( '8.5', 101 ), array( SlotKey::GRIP => array( $grip( 701, '9', 'jessup', 1000 ), $grip( 702, '10', 'mob', 1200 ), $grip( 703, '11', 'mob', 1300 ) ) ) );
		self::assertSame( 702, $list->changes[0]->closest?->part->id );
	}

	public function test_the_planner_asks_the_engine_it_was_given(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$rules  = KitDocuments::rules();
		$rules['r1']['enabled'] = false;
		$list   = ( new ChangePlanner( FitEngine::standard() ) )->plan( KitDocuments::kit( null, $rules ), $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array() );
		self::assertTrue( $list->isClean(), 'with R1 off, the engine keeps the trucks, and so does the list' );
	}

	public function test_a_truck_is_swapped_for_the_one_nearest_the_new_deck_not_the_old_truck(): void {
		$before = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.0' ), SlotKey::TRUCKS => Builds::truck( '139' ) ) );
		$list   = self::plan( $before, SlotKey::DECK, Builds::deck( '8.5', 101 ), array( SlotKey::TRUCKS => array( Builds::truck( '139' ), Builds::truck( '146' ), Builds::truck( '149' ), Builds::truck( '151' ) ) ) );
		self::assertSame( 149, $list->changes[0]->closest?->part->id, 'from an 8.0 in deck to an 8.5: the 8.5 in axle, not the 8.375 nearest the 139' );
	}

	public function test_with_no_width_on_the_new_deck_a_truck_is_swapped_for_the_one_nearest_the_old(): void {
		// The new deck is drilled old school and gives no width: R2 puts the trucks out, and there is no deck to measure to.
		$before   = Builds::complete( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$unsized  = Parts::of( array( 'deck_length' => '32', 'hole_pattern' => 'old_school' ), 101 );
		$old      = static fn( int $id, string $axle ): Part => Parts::of( array( 'axle_width' => $axle, 'baseplate' => 'old_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 2 ), $id, 2999, 'independent' );
		$list     = self::plan( $before, SlotKey::DECK, $unsized, array( SlotKey::TRUCKS => array( $old( 1, '8.75' ), $old( 2, '8.3' ), $old( 3, '7.6' ) ) ) );
		self::assertSame( array( MessageCode::PatternMismatch ), array_column( $list->changes[0]->findings, 'code' ) );
		self::assertSame( 2, $list->changes[0]->closest?->part->id, '8.3 in is nearest the 144\'s 8.25' );
	}
}
