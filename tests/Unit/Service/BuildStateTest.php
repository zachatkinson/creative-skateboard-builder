<?php
/**
 * A build's state.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\PartRows;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Service\StateStep;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Catalogue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Spec, How it fits: the one answer the state route and the abilities give. Each option's fit, the hidden counts,
 * the recommendations, the change list, what keeps the build from the cart, and the total.
 */
final class BuildStateTest extends TestCase {

	private const int KIT = 77;

	private InMemoryKits $kits;

	private InMemoryShopSettings $settings;

	private BuildState $state;

	protected function setUp(): void {
		$this->kits     = new InMemoryKits();
		$this->settings = new InMemoryShopSettings();
		$this->state    = new BuildState( $this->kits, $this->settings );
		$this->kits->add( self::KIT, self::kit(), self::shop() );
	}

	// D-116: reading a slot's parts loads every product its sources find, so they are read once and kept.
	public function test_each_slots_parts_are_read_from_the_shop_once_and_kept(): void {
		$slots = count( self::kit()->slots->keys() );
		$first = $this->of( array( 'deck' => 101 ) );
		self::assertSame( $slots, $this->kits->found_parts );
		self::assertCount( $slots, $this->kits->kept );
		self::assertSame( PartRows::of( self::shop()['trucks'] ), $this->kits->kept[ self::KIT . '|trucks' ] );
		$again = $this->of( array( 'deck' => 101 ) );
		self::assertSame( $slots, $this->kits->found_parts, 'the second answer loads no product' );
		self::assertEquals( $first, $again, 'and is the same answer' );
	}

	public function test_what_is_kept_is_what_the_answer_is_made_of(): void {
		$this->of();
		$shop = self::shop();
		$this->kits->kept[ self::KIT . '|deck' ] = PartRows::of( array( self::priced( $shop['deck'][0], 12345 ) ) );
		$state = $this->of();
		self::assertSame( array( $shop['deck'][0]->id ), self::ids( array( ...self::step( $state, 'deck' )->options->offered, ...self::step( $state, 'deck' )->options->hidden ) ) );
		self::assertSame( 12345, self::step( $state, 'deck' )->options->offered[0]->part->price );
	}

	public function test_a_kept_list_that_does_not_read_is_read_from_the_shop_again_and_kept_over(): void {
		$first = $this->of();
		$asked = $this->kits->found_parts;
		$this->kits->kept[ self::KIT . '|trucks' ] = array( array( 'not', 'a', 'part' ) );
		self::assertEquals( $first, $this->of() );
		self::assertSame( $asked + 1, $this->kits->found_parts, 'that slot alone' );
		self::assertSame( PartRows::of( self::shop()['trucks'] ), $this->kits->kept[ self::KIT . '|trucks' ] );
	}

	public function test_forgotten_lists_are_read_again(): void {
		$this->of();
		$asked = $this->kits->found_parts;
		$this->kits->forget();
		$this->of();
		self::assertSame( 2 * $asked, $this->kits->found_parts );
	}

	public function test_one_kit_is_read_and_not_the_shops_whole_list(): void {
		$this->kits->add( 78, self::kit(), self::shop() );
		$this->kits->read = 0;
		$this->of();
		self::assertSame( 1, $this->kits->read );
		self::assertNull( $this->state->of( 79, array() ) );
	}

	/**
	 * The usual kit, with a fee of 15.00 for assembly, off by default.
	 *
	 * @param array<string, int> $defaults The default part by slot key.
	 */
	private static function kit( array $defaults = array(), bool $on = false ): Kit {
		return Shop::kit( $defaults, $on );
	}

	/**
	 * A part of the usual kind with a price of its own.
	 */
	private static function priced( Part $part, int $price, bool $inStock = true, ?int $stock = null ): Part {
		return Shop::priced( $part, $price, $inStock, $stock );
	}

	/**
	 * A small shop.
	 *
	 * @return array<string, list<Part>>
	 */
	private static function shop(): array {
		return Shop::parts();
	}

	/**
	 * The state of a build of the usual kit.
	 *
	 * @param array<mixed>      $picks    The picks.
	 * @param array<mixed>|null $previous The picks before.
	 */
	private function of( array $picks = array(), ?array $previous = null, ?string $step = null, ?bool $assembly = null ): State {
		$state = $this->state->of( self::KIT, $picks, $previous, $step, $assembly );
		self::assertNotNull( $state );
		return $state;
	}

	/**
	 * A step of a state, by its slot.
	 */
	private static function step( State $state, string $slot ): StateStep {
		foreach ( $state->steps as $step ) {
			if ( $slot === $step->slot->key ) {
				return $step;
			}
		}
		self::fail( 'No step ' . $slot );
	}

	/**
	 * The ids of options.
	 *
	 * @param list<Option> $options The options.
	 * @return list<int>
	 */
	private static function ids( array $options ): array {
		return array_map( static fn( Option $option ): int => $option->part->id, $options );
	}

	/**
	 * Problems as rows of code, slot and part.
	 *
	 * @param list<BuildProblem> $problems The problems.
	 * @return list<array{string, string, int|null}>
	 */
	private static function rows( array $problems ): array {
		return array_map( static fn( BuildProblem $problem ): array => array( $problem->code->value, $problem->slot, $problem->part ), $problems );
	}

	/**
	 * A complete of the shop's parts that fits: an 8.5 deck on 149s with 54 mm wheels.
	 */
	private const array COMPLETE = array(
		SlotKey::DECK     => 103,
		SlotKey::TRUCKS   => 149,
		SlotKey::WHEELS   => 302,
		SlotKey::BEARINGS => 501,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

	public function test_a_kit_the_shop_does_not_have_has_no_state(): void {
		self::assertNull( $this->state->of( 78, array() ) );
		self::assertNull( $this->state->of( 0, self::COMPLETE ) );
	}

	public function test_with_nothing_picked_every_deck_is_offered_and_nothing_is_picked_for_the_shopper(): void {
		$state = $this->of();
		self::assertSame( self::KIT, $state->id );
		self::assertSame( $this->kits->kits[ self::KIT ]['kit'], $state->kit );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'risers', 'bearings', 'hardware', 'grip' ), array_map( static fn( StateStep $step ): string => $step->slot->key, $state->steps ) );
		self::assertSame( SlotKey::DECK, $state->step );
		self::assertSame( array( 101, 102, 103 ), self::ids( self::step( $state, SlotKey::DECK )->options->offered ) );
		foreach ( $state->steps as $step ) {
			self::assertNull( $step->picked, $step->slot->key );
			self::assertNull( $step->recommended, $step->slot->key . ': nothing starts before the deck' );
			self::assertNull( $step->counted(), $step->slot->key );
			self::assertSame( array(), $step->options->hidden, $step->slot->key . ': held against nothing, everything is offered' );
		}
		self::assertSame( array(), $state->refused );
		self::assertNull( $state->changes );
		self::assertFalse( $state->ready() );
		self::assertSame(
			array( array( 'slot_empty', 'deck', null ), array( 'slot_empty', 'trucks', null ), array( 'slot_empty', 'wheels', null ), array( 'slot_empty', 'bearings', null ), array( 'slot_empty', 'hardware', null ), array( 'slot_empty', 'grip', null ) ),
			self::rows( $state->validation->problems )
		);
		self::assertSame( array(), $state->quote->lines );
		self::assertSame( 0, $state->quote->total, 'assembly is off until the shopper or the kit turns it on' );
	}

	public function test_once_the_deck_is_picked_each_later_slot_shows_what_fits_hides_the_rest_with_why_and_starts_on_a_part(): void {
		$state = $this->of( array( SlotKey::DECK => 103 ) );
		self::assertSame( SlotKey::TRUCKS, $state->step, 'the first step with nothing picked' );
		self::assertSame( 103, self::step( $state, SlotKey::DECK )->picked?->part->id );
		self::assertSame( Severity::Fits, self::step( $state, SlotKey::DECK )->picked?->severity );

		// An 8.5 deck: the 149 takes it, the 139 and the 144 are too narrow and the 169 too wide.
		$trucks = self::step( $state, SlotKey::TRUCKS );
		self::assertSame( array( 149 ), self::ids( $trucks->options->offered ) );
		self::assertSame( array( 139, 144, 169 ), self::ids( $trucks->options->hidden ) );
		$groups = $trucks->options->hiddenGroups();
		self::assertSame( array( array( RuleId::R1, MessageCode::DeckAboveRange, 2 ), array( RuleId::R1, MessageCode::DeckBelowRange, 1 ) ), array_map( static fn( $group ): array => array( $group->rule, $group->code, $group->count ), $groups ) );
		self::assertNull( $trucks->picked );
		self::assertSame( 149, $trucks->recommended?->part->id );
		self::assertSame( $trucks->recommended, $trucks->counted() );

		// The wheels are held against the trucks the slot before them starts on: their limit is 56 mm.
		$wheels = self::step( $state, SlotKey::WHEELS );
		self::assertSame( array( 301, 302, 303 ), self::ids( $wheels->options->offered ) );
		self::assertSame( Severity::Warn, $wheels->options->offer( 303 )?->severity, '58 mm on a 56 mm truck warns' );
		self::assertSame( 302, $wheels->recommended?->part->id, 'the middle price, of those that fit clean' );
		self::assertNull( self::step( $state, SlotKey::RISERS )->recommended, 'a slot the kit does not require starts empty' );
		self::assertSame( 501, self::step( $state, SlotKey::BEARINGS )->recommended?->part->id );
		self::assertSame( 601, self::step( $state, SlotKey::HARDWARE )->recommended?->part->id );
		self::assertSame( 701, self::step( $state, SlotKey::GRIP )->recommended?->part->id );

		// The total counts each from the start (P9), and the build could go to the cart as it stands.
		self::assertSame( array( 103, 149, 302, 501, 601, 701 ), array_column( $state->quote->lines, 'part' ) );
		self::assertSame( array( 1, 1, 1, 1, 1, 1 ), array_column( $state->quote->lines, 'quantity' ) );
		self::assertSame( 7000 + 5400 + 3200 + 1500 + 400 + 800, $state->quote->total );
		self::assertTrue( $state->ready() );
		self::assertSame( array(), $state->validation->problems );
	}

	public function test_a_slot_is_held_against_the_picks_before_it_and_a_pick_stands_in_place_of_a_recommendation(): void {
		$state  = $this->of( array( SlotKey::DECK => 103, SlotKey::WHEELS => 303 ) );
		$wheels = self::step( $state, SlotKey::WHEELS );
		self::assertSame( 303, $wheels->picked?->part->id );
		self::assertSame( Severity::Warn, $wheels->picked?->severity );
		self::assertNull( $wheels->recommended, 'a slot with a pick has no recommendation' );
		self::assertSame( $wheels->picked, $wheels->counted() );
		// 58 mm wheels over the trucks' limit ask for a riser, so the risers start on one.
		self::assertSame( 402, self::step( $state, SlotKey::RISERS )->recommended?->part->id );
		self::assertContains( 402, array_column( $state->quote->lines, 'part' ) );
		self::assertSame( SlotKey::TRUCKS, $state->step );
		// The riser the slot starts on is what the hardware is held against: 1 in bolts are too short over it, so the
		// hardware has nothing to start on, and neither has the grip after it.
		$hardware = self::step( $state, SlotKey::HARDWARE );
		self::assertSame( array( 601 ), self::ids( $hardware->options->hidden ) );
		self::assertSame( RuleId::R4, $hardware->options->hiddenGroups()[0]->rule );
		self::assertNull( $hardware->recommended );
		self::assertNull( self::step( $state, SlotKey::GRIP )->recommended );
		self::assertSame( array( array( 'slot_empty', 'hardware', null ), array( 'slot_empty', 'grip', null ) ), self::rows( $state->validation->problems ) );
		self::assertFalse( $state->ready() );

		// With bolts that reach, the build could go to the cart: a warning does not keep it from there.
		$shop                        = self::shop();
		$shop[ SlotKey::HARDWARE ][] = self::priced( Builds::bolts( '1.25', 602 ), 450 );
		$this->kits->add( self::KIT, self::kit(), $shop );
		$state = $this->of( array( SlotKey::DECK => 103, SlotKey::WHEELS => 303 ) );
		self::assertSame( 602, self::step( $state, SlotKey::HARDWARE )->recommended?->part->id );
		self::assertTrue( $state->ready(), 'a warning does not keep a build from the cart' );
	}

	public function test_the_kits_default_part_is_what_a_slot_starts_on(): void {
		$this->kits->add( self::KIT, self::kit( array( SlotKey::WHEELS => 301 ) ), self::shop() );
		self::assertSame( 301, self::step( $this->of( array( SlotKey::DECK => 103 ) ), SlotKey::WHEELS )->recommended?->part->id );
	}

	public function test_a_slot_with_nothing_to_start_on_stops_the_slots_after_it_from_starting(): void {
		// A 10 in deck: none of the shop's trucks takes it, so nothing after the trucks starts on a part.
		$shop                    = self::shop();
		$shop[ SlotKey::DECK ][] = self::priced( Builds::deck( '10', 104 ), 9000 );
		$this->kits->add( self::KIT, self::kit(), $shop );
		$state = $this->of( array( SlotKey::DECK => 104 ) );
		self::assertSame( array(), self::step( $state, SlotKey::TRUCKS )->options->offered );
		foreach ( array( SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ) as $slot ) {
			self::assertNull( self::step( $state, $slot )->recommended, $slot );
		}
		self::assertSame( array( 104 ), array_column( $state->quote->lines, 'part' ) );
		self::assertFalse( $state->ready() );

		// With the trucks picked by the shopper, the slots after them start again.
		$state = $this->of( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ) );
		self::assertSame( 302, self::step( $state, SlotKey::WHEELS )->recommended?->part->id );
		self::assertSame( SlotKey::WHEELS, $state->step );
	}

	public function test_a_pick_that_is_not_one_of_its_slots_parts_is_refused_and_nothing_is_said_of_it(): void {
		// A truck's ID sent as the deck, an ID the shop does not have, text, and a slot the kit lacks.
		$state = $this->of(
			array(
				SlotKey::DECK   => 149,
				SlotKey::TRUCKS => 999999,
				SlotKey::WHEELS => '302',
				SlotKey::GRIP   => -5,
				'bearings'      => 0,
				'stickers'      => 701,
				'patches'       => 0,
				7               => 103,
			)
		);
		self::assertSame(
			array(
				array( 'part_not_in_slot', 'deck', 149 ),
				array( 'part_not_in_slot', 'trucks', 999999 ),
				array( 'part_not_in_slot', 'wheels', null ),
				array( 'part_not_in_slot', 'grip', null ),
				array( 'part_not_in_slot', 'bearings', null ),
				array( 'slot_unknown', 'stickers', 701 ),
				array( 'slot_unknown', 'patches', null ),
				array( 'slot_unknown', '7', 103 ),
			),
			self::rows( $state->refused )
		);
		foreach ( $state->steps as $step ) {
			self::assertNull( $step->counted(), $step->slot->key );
		}
		self::assertSame( array(), $state->quote->lines, 'none of them is priced' );
		self::assertSame( array(), $state->validation->build );
		self::assertSame( SlotKey::DECK, $state->step );
	}

	public function test_a_pick_the_build_before_it_blocks_is_shown_with_its_reason_and_keeps_the_build_from_the_cart(): void {
		$state  = $this->of( array( SlotKey::TRUCKS => 139 ) + self::COMPLETE );
		$trucks = self::step( $state, SlotKey::TRUCKS );
		self::assertSame( 139, $trucks->picked?->part->id, 'a hidden part that is picked is still the pick' );
		self::assertSame( Severity::Block, $trucks->picked?->severity );
		self::assertSame( MessageCode::DeckAboveRange, $trucks->picked?->reason()?->code );
		self::assertFalse( $state->ready() );
		self::assertSame( array( array( 'part_blocked', 'deck', 103 ), array( 'part_blocked', 'trucks', 139 ) ), self::rows( $state->validation->problems ) );
		self::assertSame( SlotKey::RISERS, $state->step, 'the one step left with nothing picked' );
	}

	public function test_a_part_that_is_sold_out_or_that_the_shop_holds_too_few_of_keeps_the_build_from_the_cart(): void {
		$shop                      = self::shop();
		$shop[ SlotKey::WHEELS ]   = array( self::priced( Builds::wheel( '54', 302 ), 3200, false, 0 ) );
		$single                    = Parts::indy( '149', array( 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 1 ) );
		$shop[ SlotKey::TRUCKS ][] = new Part( 150, 'independent', $single->specs, 2700, true, 1 );
		$this->kits->add( self::KIT, self::kit(), $shop );

		$state = $this->of( array( SlotKey::TRUCKS => 150 ) + self::COMPLETE );
		self::assertSame( array( array( 'part_short', 'trucks', 150 ), array( 'part_sold_out', 'wheels', 302 ) ), self::rows( $state->validation->problems ) );
		self::assertFalse( $state->ready() );
		self::assertSame( 2, self::step( $state, SlotKey::TRUCKS )->picked?->quantity, 'two of a truck sold singly' );
		self::assertFalse( self::step( $state, SlotKey::TRUCKS )->picked?->available() );
		self::assertSame( 2 * 2700, $state->quote->lines[1]->total );

		// Neither is what its slot starts on.
		$state = $this->of( array( SlotKey::DECK => 103 ) );
		self::assertSame( 149, self::step( $state, SlotKey::TRUCKS )->recommended?->part->id );
		self::assertNull( self::step( $state, SlotKey::WHEELS )->recommended );
		self::assertSame( array( 302 ), self::ids( self::step( $state, SlotKey::WHEELS )->options->offered ), 'a sold-out part is offered, marked so' );
	}

	public function test_the_step_is_the_one_asked_for_when_the_kit_has_it(): void {
		self::assertSame( SlotKey::GRIP, $this->of( array(), null, SlotKey::GRIP )->step );
		self::assertSame( SlotKey::DECK, $this->of( self::COMPLETE, null, SlotKey::DECK )->step );
		self::assertSame( SlotKey::DECK, $this->of( array(), null, 'stickers' )->step, 'a slot the kit lacks is no step' );
		self::assertSame( SlotKey::DECK, $this->of( array(), null, '' )->step );
		self::assertSame( SlotKey::GRIP, $this->of( self::COMPLETE + array( SlotKey::RISERS => 401 ) )->step, 'with every slot picked, the last' );
	}

	public function test_the_total_is_the_parts_the_fee_when_the_shop_assembles_and_the_build_discount(): void {
		$parts = 7000 + 5400 + 3200 + 1500 + 400 + 800;
		$state = $this->of( self::COMPLETE );
		self::assertFalse( $state->quote->assembly, 'the kit\'s default: off' );
		self::assertSame( 0, $state->quote->fee );
		self::assertSame( $parts, $state->quote->total );

		$state = $this->of( self::COMPLETE, null, null, true );
		self::assertTrue( $state->quote->assembly );
		self::assertSame( 1500, $state->quote->fee, '15.00 in a currency with two decimals' );
		self::assertSame( $parts + 1500, $state->quote->total );

		// A kit that starts with assembly on, a shopper who turns it off, and a currency with none.
		$this->kits->add( self::KIT, self::kit( array(), true ), self::shop() );
		self::assertTrue( $this->of( self::COMPLETE )->quote->assembly );
		self::assertFalse( $this->of( self::COMPLETE, null, null, false )->quote->assembly );
		$this->kits->decimals = 0;
		self::assertSame( 15, $this->of( self::COMPLETE )->quote->fee );
		$this->kits->decimals = 2;

		// The shop's build discount comes off each part, and never off the fee.
		$this->settings->terms = BuildTerms::read( array( 'build_discount' => '10' ) );
		$state                 = $this->of( self::COMPLETE );
		self::assertSame( 6300, $state->quote->lines[0]->charged );
		self::assertSame( $parts - intdiv( $parts, 10 ), $state->quote->parts );
		self::assertSame( intdiv( $parts, 10 ), $state->quote->saved );
		self::assertSame( 1500, $state->quote->fee );
	}

	public function test_a_changed_pick_that_breaks_later_picks_comes_with_its_change_list(): void {
		// The deck goes from 8.5 to 8.0: the 149s no longer take it, and the 139s are the closest that do.
		$before = self::COMPLETE;
		$now    = array( SlotKey::DECK => 101 ) + self::COMPLETE;
		$list   = $this->of( $now, $before )->changes;
		self::assertNotNull( $list );
		self::assertSame( array( 'wheels', 'bearings', 'hardware', 'grip' ), $list->kept );
		self::assertCount( 1, $list->changes );
		self::assertSame( SlotKey::TRUCKS, $list->changes[0]->slot );
		self::assertSame( 149, $list->changes[0]->was->id );
		self::assertSame( 139, $list->changes[0]->closest?->part->id );
		self::assertSame( MessageCode::DeckBelowRange, $list->changes[0]->findings[0]->code );
		self::assertSame( 103, $list->before->part( SlotKey::DECK )?->id, 'the undo' );
		self::assertSame( 101, $list->proposed->part( SlotKey::DECK )?->id );
		self::assertSame( 139, $list->proposed->part( SlotKey::TRUCKS )?->id );

		// Nothing has changed yet: the state is of the picks as sent, the trucks blocked.
		$state = $this->of( $now, $before );
		self::assertSame( 149, self::step( $state, SlotKey::TRUCKS )->picked?->part->id );
		self::assertFalse( $state->ready() );
	}

	public function test_no_change_list_without_the_picks_before_or_when_nothing_breaks(): void {
		$now = array( SlotKey::DECK => 101 ) + self::COMPLETE;
		self::assertNull( $this->of( $now )->changes, 'the picks before were not sent' );
		self::assertNull( $this->of( self::COMPLETE, self::COMPLETE )->changes, 'nothing changed' );
		self::assertNull( $this->of( array( SlotKey::WHEELS => 301 ) + self::COMPLETE, self::COMPLETE )->changes, 'the change breaks nothing' );
		self::assertNull( $this->of( array( SlotKey::DECK => 103 ), self::COMPLETE )->changes, 'picks taken away are no change to plan for' );
		self::assertNull( $this->of( self::COMPLETE, array() )->changes, 'a first pick with nothing after it as it was' );
		$blocked = array( SlotKey::TRUCKS => 139 ) + self::COMPLETE;
		self::assertNull( $this->of( $blocked, $blocked )->changes, 'a build that did not fit before and was not changed' );
	}

	public function test_the_change_is_the_first_pick_that_differs_and_the_picks_before_are_looked_up_as_any_are(): void {
		// The deck and the wheels both differ: the deck's change is the one planned for.
		$list = $this->of( array( SlotKey::DECK => 101, SlotKey::WHEELS => 301 ) + self::COMPLETE, self::COMPLETE )->changes;
		self::assertSame( SlotKey::TRUCKS, $list?->changes[0]->slot );

		// A part before that is not its slot's is no part: nothing is planned around it, and nothing is said of it.
		$state = $this->of( array( SlotKey::DECK => 101 ) + self::COMPLETE, array( SlotKey::TRUCKS => 999999, 'stickers' => 1 ) + self::COMPLETE );
		self::assertNull( $state->changes, 'with no trucks before, the deck\'s change breaks nothing' );
		self::assertSame( array(), $state->refused, 'only the picks as they are can be refused' );
	}

	public function test_whatever_the_state_counts_the_validator_answers_for_the_same_build(): void {
		foreach ( array( 1, 2, 3 ) as $seed ) {
			$catalogue = Catalogue::generate( $seed, 25 );
			$this->kits->add( self::KIT, KitDocuments::kit(), $catalogue );
			$deck  = $catalogue[ SlotKey::DECK ][ $seed ]->id;
			$state = $this->of( array( SlotKey::DECK => $deck ) );
			$picks = Parts::picks( array_filter( array_combine( array_map( static fn( StateStep $step ): string => $step->slot->key, $state->steps ), array_map( static fn( StateStep $step ): ?Part => $step->counted()?->part, $state->steps ) ) ) );
			$again = ( new BuildValidator( FitEngine::standard() ) )->validate( KitDocuments::kit(), $picks, Catalogue::ids( $catalogue ) );
			self::assertSame( self::rows( $again->problems ), self::rows( $state->validation->problems ), 'seed ' . $seed );
			self::assertSame( $again->isValid(), $state->ready() );
			foreach ( $state->steps as $step ) {
				self::assertSame( count( $catalogue[ $step->slot->key ] ), count( $step->options->offered ) + count( $step->options->hidden ), 'every part is offered or hidden' );
				if ( null !== $step->recommended ) {
					self::assertSame( Severity::Fits, $step->recommended->severity );
					self::assertTrue( $step->recommended->available() );
				}
			}
		}
	}

	public function test_a_step_counts_its_pick_before_its_recommendation(): void {
		$state  = $this->of( array( SlotKey::DECK => 103 ) );
		$trucks = self::step( $state, SlotKey::TRUCKS );
		$deck   = self::step( $state, SlotKey::DECK );
		self::assertNotNull( $deck->picked );
		$both = new StateStep( $trucks->slot, $trucks->options, $deck->picked, $trucks->recommended );
		self::assertSame( $deck->picked, $both->counted() );
		self::assertSame( $trucks->slot, $both->slot );
		self::assertSame( $trucks->options, $both->options );
	}
}
