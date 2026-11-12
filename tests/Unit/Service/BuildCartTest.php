<?php
/**
 * A build on its way into the cart.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildCart;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;
use CreativeBranding\SkateboardBuilder\Service\Verdict;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\CountingUuids;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryCart;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Cart, checkout and orders, Adding a build: the whole build is checked again on the server (slots, rules
 * and stock, each part counted across every line already in the cart), it is given an identifier so two of the
 * same do not merge, and each part becomes a line tied to the kit's. Issue #26: a pick is read from the shop as
 * it is now.
 */
final class BuildCartTest extends TestCase {

	private const KIT = 77;

	/**
	 * A build of the usual kit that fits: an 8.5 deck on 149s with 54 mm wheels.
	 */
	private const PICKS = array(
		'deck'     => 103,
		'trucks'   => 149,
		'wheels'   => 302,
		'bearings' => 501,
		'hardware' => 601,
		'grip'     => 701,
	);

	/**
	 * How many of each one build takes: every part here is sold as the set a build needs.
	 */
	private const QUANTITIES = array(
		'deck'     => 1,
		'trucks'   => 1,
		'wheels'   => 1,
		'bearings' => 1,
		'hardware' => 1,
		'grip'     => 1,
	);

	private InMemoryKits $kits;

	private InMemoryCart $cart;

	private InMemoryShopSettings $settings;

	private CountingUuids $uuids;

	private BuildCart $builds;

	protected function setUp(): void {
		$this->kits     = new InMemoryKits();
		$this->cart     = new InMemoryCart();
		$this->settings = new InMemoryShopSettings();
		$this->uuids    = new CountingUuids();
		$this->builds   = new BuildCart( $this->kits, $this->cart, $this->settings, $this->uuids );
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
	}

	/**
	 * What the cart says of the usual build, with picks changed.
	 *
	 * @param array<string, mixed> $with     Picks to add or replace; null takes one away.
	 * @param array<string, mixed> $request  What else the request says.
	 * @param int                  $quantity How many builds.
	 */
	private function consider( array $with = array(), array $request = array(), int $quantity = 1 ): Verdict {
		$picks = array_filter( $with + self::PICKS, static fn( mixed $pick ): bool => null !== $pick );
		return $this->builds->consider( self::KIT, array( 'picks' => $picks ) + $request, $quantity );
	}

	/**
	 * A refused build's problems, as code, slot and part.
	 *
	 * @return list<array{string, string, int|null}>
	 */
	private static function problems( Verdict $verdict ): array {
		return array_map( static fn( BuildProblem $problem ): array => array( $problem->code->value, $problem->slot, $problem->part ), $verdict->problems );
	}

	public function test_a_build_that_fits_is_taken_with_an_identifier_its_picks_and_how_many_of_each(): void {
		$verdict = $this->consider();
		self::assertNull( $verdict->refusal );
		self::assertSame( array(), $verdict->problems );
		self::assertEquals( new Build( 'build-1', self::KIT, self::PICKS, self::QUANTITIES, false ), $verdict->build );
		self::assertSame( array_values( self::PICKS ), $this->kits->picked, 'each pick is read from the shop itself, once' );
		self::assertSame( 1, $this->kits->readings, 'and all in one reading of the shop, not one for each part' );
		self::assertSame( 0, $this->kits->offered, 'and never from what is kept of the slots\' parts' );
	}

	public function test_a_kit_with_no_build_is_refused_whatever_arrives_in_its_place(): void {
		foreach ( array( null, '', 'picks', array(), array( 'picks' => '103' ), array( 'assembly' => true ) ) as $request ) {
			$verdict = $this->builds->consider( self::KIT, $request, 1 );
			self::assertSame( CartRefusal::NoBuild, $verdict->refusal );
			self::assertNull( $verdict->build );
		}
		self::assertFalse( $this->builds->admits( self::KIT ) );
		self::assertSame( array(), $this->kits->picked );
	}

	public function test_a_kit_the_shop_does_not_have_or_does_not_show_is_refused(): void {
		self::assertSame( CartRefusal::KitNotFound, $this->builds->consider( 78, array( 'picks' => self::PICKS ), 1 )->refusal );
		$this->kits->hidden = array( self::KIT );
		self::assertSame( CartRefusal::KitNotFound, $this->consider()->refusal, 'a draft, a private kit, one behind a password' );
		$this->kits->hidden = array();
		$this->kits->unread = array( 79 );
		self::assertSame( CartRefusal::KitNotFound, $this->builds->consider( 79, array( 'picks' => self::PICKS ), 1 )->refusal, 'one whose settings do not read' );
	}

	public function test_assembly_is_what_the_request_says_and_the_kits_default_when_it_says_nothing(): void {
		self::assertFalse( $this->consider()->build?->assembly );
		foreach ( array( true, 1, '1', 'true', 'yes', 'on' ) as $yes ) {
			self::assertTrue( $this->consider( array(), array( 'assembly' => $yes ) )->build?->assembly, var_export( $yes, true ) );
		}
		foreach ( array( false, 0, '0', '', 'no', 'off', null, array(), 2 ) as $no ) {
			self::assertFalse( $this->consider( array(), array( 'assembly' => $no ) )->build?->assembly, var_export( $no, true ) );
		}
		$this->kits->add( 80, Shop::kit( array(), true ), Shop::parts() );
		self::assertTrue( $this->builds->consider( 80, array( 'picks' => self::PICKS ), 1 )->build?->assembly, 'the kit assembles by default' );
		self::assertFalse( $this->builds->consider( 80, array( 'picks' => self::PICKS, 'assembly' => '0' ), 1 )->build?->assembly );
	}

	public function test_a_pick_is_an_id_as_a_number_or_as_a_form_sends_it_and_anything_else_is_no_part(): void {
		self::assertSame( self::PICKS, $this->consider( array( 'deck' => '103' ) )->build?->picks );
		foreach ( array( 0, -103, '0103', '103 ', '1e2', 103.0, true, array( 103 ), '1234567890123456789' ) as $sent ) {
			$verdict = $this->consider( array( 'deck' => $sent ) );
			self::assertSame( CartRefusal::BuildRefused, $verdict->refusal, var_export( $sent, true ) );
			self::assertContains( array( 'part_not_in_slot', 'deck', null ), self::problems( $verdict ) );
		}
		self::assertNotContains( 0, $this->kits->picked, 'and the shop is not asked for it' );
	}

	public function test_a_build_with_something_wrong_is_refused_with_each_thing(): void {
		$verdict = $this->consider( array( 'deck' => 302, 'wheels' => null, 'tail' => 9, 7 => 8 ) );
		self::assertSame( CartRefusal::BuildRefused, $verdict->refusal );
		self::assertNull( $verdict->build );
		$problems = self::problems( $verdict );
		self::assertContains( array( 'part_not_in_slot', 'deck', 302 ), $problems, 'a wheel is not a deck, though the shop sells it' );
		self::assertContains( array( 'slot_unknown', 'tail', 9 ), $problems );
		self::assertContains( array( 'slot_unknown', '7', 8 ), $problems, 'a slot sent as a number is a key all the same' );
		self::assertContains( array( 'slot_empty', 'wheels', null ), $problems );
		self::assertFalse( $this->builds->admits( self::KIT ) );

		$blocked = $this->consider( array( 'deck' => 101, 'trucks' => 169 ) );
		self::assertSame( CartRefusal::BuildRefused, $blocked->refusal );
		self::assertContains( 'part_blocked', array_column( self::problems( $blocked ), 0 ), '169s are too wide for an 8.0 deck' );
	}

	// Issue #26: the builder may have shown a part as it was up to two hours ago.
	public function test_a_part_is_judged_as_the_shop_has_it_now(): void {
		$this->kits->now[601] = Shop::priced( Shop::parts()['hardware'][0], 400, false );
		$verdict              = $this->consider();
		self::assertSame( array( array( 'part_sold_out', 'hardware', 601 ) ), self::problems( $verdict ) );

		$this->kits->now[601] = false;
		$gone                 = $this->consider( array(), array( 'assembly' => true ) );
		self::assertSame( array( 'part_not_in_slot', 'hardware', 601 ), self::problems( $gone )[0], 'no longer one a shopper could buy' );
	}

	public function test_each_part_is_counted_across_the_builds_asked_for_and_the_lines_the_cart_holds(): void {
		$this->kits->now[103] = Shop::priced( Shop::parts()['deck'][2], 7000, true, 3 );
		self::assertNull( $this->consider( array(), array(), 3 )->refusal, 'three builds, three decks' );
		self::assertSame( array( array( 'part_short', 'deck', 103 ) ), self::problems( $this->consider( array(), array(), 4 ) ) );

		$this->cart->put( 103, 1 );
		$this->cart->put( 999, 5 );
		$this->cart->put( 103, 1 );
		self::assertNull( $this->consider( array(), array( 'assembly' => true ), 1 )->refusal, 'two in the cart and one more' );
		self::assertSame( array( array( 'part_short', 'deck', 103 ) ), self::problems( $this->consider( array(), array(), 2 ) ), 'two in the cart and two more' );

		$this->kits->now[103] = Shop::priced( Shop::parts()['deck'][2], 7000, true, 2 );
		self::assertSame( array( array( 'part_short', 'deck', 103 ) ), self::problems( $this->consider( array(), array(), 0 ) ), 'a quantity of none is one build, not none' );

		$this->kits->now[103] = Shop::priced( Shop::parts()['deck'][2], 7000, true, 0 );
		$this->cart->lines    = array();
		self::assertSame( array( array( 'part_short', 'deck', 103 ) ), self::problems( $this->consider() ), 'said once, by the validator, where one build alone is too many' );
	}

	public function test_a_build_the_shops_settings_cannot_price_is_refused(): void {
		$this->kits->decimals = 9;
		self::assertSame( CartRefusal::NoPrice, $this->consider( array(), array( 'assembly' => true ) )->refusal, 'the fee has no such minor unit' );
		self::assertFalse( $this->builds->admits( self::KIT ) );

		$this->kits->decimals  = 2;
		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '10' ) );
		self::assertNull( $this->consider( array(), array( 'assembly' => true ), 2 )->refusal, 'another request, once the settings are right, with the discount the shop set' );
	}

	public function test_the_hooks_that_ask_twice_are_answered_once_and_two_builds_alike_are_two(): void {
		$first = $this->consider();
		self::assertSame( $first, $this->consider(), 'the same request, the same build' );
		self::assertSame( 1, $this->uuids->given );
		self::assertCount( 6, $this->kits->picked );

		$this->cart->put( self::KIT, 1, $first->build?->toArray() );
		self::assertNull( $this->builds->attach( 'line-1' ) );
		$second = $this->consider();
		self::assertSame( 'build-2', $second->build?->id, 'once the first is in the cart, the same picks are another build' );
		self::assertNotSame( 'build-2', $this->consider( array(), array(), 2 )->build?->id, 'and so is another quantity' );
		self::assertNotSame( $second, $this->consider( array(), array( 'assembly' => true ) ) );
		self::assertNotSame( $second, $this->consider( array( 'risers' => 401 ) ) );

		$this->kits->add( 81, Shop::kit(), Shop::parts() );
		$here  = $this->consider();
		$there = $this->builds->consider( 81, array( 'picks' => self::PICKS ), 1 );
		self::assertSame( array( self::KIT, 81 ), array( $here->build?->kit, $there->build?->kit ), 'the same picks for another kit are that kit\'s build' );
	}

	public function test_a_kit_may_be_bought_only_between_its_build_being_taken_and_its_parts_being_added(): void {
		self::assertFalse( $this->builds->admits( self::KIT ) );
		$build = $this->consider()->build;
		self::assertNotNull( $build );
		self::assertTrue( $this->builds->admits( self::KIT ) );
		self::assertFalse( $this->builds->admits( 78 ) );

		$key = $this->cart->put( self::KIT, 1, $build->toArray() );
		self::assertNull( $this->builds->attach( $key ) );
		self::assertFalse( $this->builds->admits( self::KIT ), 'the next kit needs a build of its own' );
	}

	public function test_a_kits_line_gets_a_line_for_each_part_tied_to_it_at_the_builds_quantity(): void {
		$build = $this->consider( array(), array(), 2 )->build;
		self::assertNotNull( $build );
		$key = $this->cart->put( self::KIT, 2, $build->toArray() );
		self::assertNull( $this->builds->attach( $key ) );

		$lines = $this->cart->lines();
		self::assertCount( 7, $lines );
		$children = $lines[ $key ]->children;
		self::assertCount( 6, $children );
		$slots = array();
		foreach ( $children as $child ) {
			self::assertSame( $key, $lines[ $child ]->kit );
			self::assertSame( 2 * self::QUANTITIES[ $lines[ $child ]->slot ], $lines[ $child ]->quantity );
			$slots[ $lines[ $child ]->slot ] = $lines[ $child ]->part;
		}
		self::assertSame( self::PICKS, $slots, 'in step order' );

		self::assertNull( $this->builds->attach( $key ), 'asked again, it adds nothing' );
		self::assertCount( 7, $this->cart->lines() );
		self::assertNull( $this->builds->attach( $children[0] ), 'a part\'s line is no kit\'s' );
		self::assertNull( $this->builds->attach( 'line-none' ) );
		self::assertCount( 7, $this->cart->lines() );
	}

	public function test_a_kits_line_whose_build_was_not_taken_here_is_removed(): void {
		$forged = ( new Build( 'build-9', self::KIT, self::PICKS, self::QUANTITIES, false ) )->toArray();
		$key    = $this->cart->put( self::KIT, 1, $forged );
		self::assertSame( CartRefusal::NoBuild, $this->builds->attach( $key ) );
		self::assertSame( array(), $this->cart->lines() );

		$key = $this->cart->put( self::KIT, 1, array( 'id' => 'build-1' ) );
		$this->consider();
		self::assertSame( CartRefusal::NoBuild, $this->builds->attach( $key ), 'nor one that does not read' );
		self::assertSame( array(), $this->cart->lines() );

		$loose = $this->cart->put( 103 );
		self::assertNull( $this->builds->attach( $loose ), 'a line with no build is left alone' );
		self::assertCount( 1, $this->cart->lines() );
	}

	public function test_a_part_woocommerce_will_not_add_takes_the_whole_build_out(): void {
		$build = $this->consider()->build;
		self::assertNotNull( $build );
		$loose               = $this->cart->put( 103 );
		$key                 = $this->cart->put( self::KIT, 1, $build->toArray() );
		$this->cart->refuses = array( 302 );
		self::assertSame( CartRefusal::NotAdded, $this->builds->attach( $key ) );
		self::assertSame( array( $loose ), array_keys( $this->cart->lines() ), 'the deck and the trucks that were added go too, and the kit' );
		self::assertFalse( $this->builds->admits( self::KIT ) );
	}

	public function test_the_kits_line_costs_the_assembly_fee_and_nothing_without_assembly(): void {
		$fee = Shop::kit()->assembly->fee( 2 );
		self::assertGreaterThan( 0, $fee, 'the fixture kit charges for assembly' );
		$with    = new Build( 'a', self::KIT, self::PICKS, self::QUANTITIES, true );
		$without = new Build( 'b', self::KIT, self::PICKS, self::QUANTITIES, false );
		self::assertSame( $fee, $this->builds->fee( $with ) );
		self::assertSame( 0, $this->builds->fee( $without ) );
		self::assertSame( 0, $this->builds->fee( new Build( 'c', 9, self::PICKS, self::QUANTITIES, true ) ), 'a kit that no longer reads' );

		$this->kits->decimals = 0;
		self::assertSame( Shop::kit()->assembly->fee( 0 ), $this->builds->fee( $with ), "in the shop's own minor units" );
		$this->kits->decimals = 9;
		self::assertSame( 0, $this->builds->fee( $with ), 'a fee with no such minor unit' );
	}

	public function test_a_part_is_charged_the_shops_price_less_the_build_discount(): void {
		self::assertSame( 7000, $this->builds->charged( 7000 ), 'no discount unless the shop sets one' );
		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '7.5' ) );
		self::assertSame( 6475, $this->builds->charged( 7000 ) );
		self::assertSame( 924, $this->builds->charged( 999 ), '924.075, to the minor unit' );
		self::assertSame( 19, $this->builds->charged( 20 ), '18.5, half up' );
	}

	// The auditor's finding 1 on milestone 5: this arithmetic was in the hook, where no unit test or mutant reached it.
	public function test_a_lines_price_is_the_shops_less_the_discount_taken_once_however_often_it_is_asked(): void {
		self::assertSame( array( '70', null ), $this->builds->repriced( '70', null, 2 ), 'no discount: the price asked, and nothing to give' );
		self::assertSame( array( '80', null ), $this->builds->repriced( '70', array( '80', '70' ), 2 ), 'a discount the shop has taken away: the price asked comes back' );

		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '10' ) );
		self::assertSame( array( '70', '63.00' ), $this->builds->repriced( '70', null, 2 ) );
		self::assertSame( array( '70', '63.00' ), $this->builds->repriced( '63.00', array( '70', '63.00' ), 2 ), 'asked again: the discount comes off the price asked, not off what was given' );
		self::assertSame( array( '63.00', '56.70' ), $this->builds->repriced( '63.00', null, 2 ), 'a price nobody gave is the shop\'s own' );
		self::assertSame( array( '75', '67.50' ), $this->builds->repriced( '75', array( '70', '63.00' ), 2 ), 'the shop\'s price moved since: the new one is the one asked' );
		self::assertSame( array( '9.99', '8.99' ), $this->builds->repriced( '9.99', null, 2 ), '8.991, to the minor unit' );
		self::assertSame( array( '0.15', '0.14' ), $this->builds->repriced( '0.15', null, 2 ), '13.5, half up' );
		self::assertSame( array( '1234.5', '1111.050' ), $this->builds->repriced( '1234.5', null, 3 ), 'in the currency\'s own decimals, with no thousands mark' );
		self::assertSame( array( '700', '630' ), $this->builds->repriced( '700', null, 0 ) );
		self::assertSame( array( '0', null ), $this->builds->repriced( '0', null, 2 ), 'nothing off nothing' );
		self::assertSame( array( '19.999', '18.00' ), $this->builds->repriced( '19.999', null, 2 ), 'a price finer than the currency is rounded before the discount' );
		self::assertSame( array( '19.991', '17.99' ), $this->builds->repriced( '19.991', null, 2 ), 'to the nearest, down as well as up' );
		self::assertSame( array( '', null ), $this->builds->repriced( '', null, 2 ), 'a product with no price is given none' );
	}

	public function test_a_coupon_reaches_a_part_through_its_kit_and_a_fixed_one_where_the_setting_says(): void {
		// Not a fixed product coupon: fixed, part, kit, the kit left out, the part itself left out.
		self::assertTrue( $this->builds->couponTakes( false, true, false, false, false ), 'one for the part itself' );
		self::assertTrue( $this->builds->couponTakes( false, false, true, false, false ), 'one for the kit' );
		self::assertFalse( $this->builds->couponTakes( false, false, false, false, false ) );
		self::assertFalse( $this->builds->couponTakes( false, true, true, true, false ), 'the kit left out takes its parts with it' );
		self::assertFalse( $this->builds->couponTakes( false, false, true, false, true ), 'a part the coupon leaves out is left out, though the coupon takes its kit' );

		self::assertFalse( $this->builds->couponTakes( true, true, true, false, false ), 'a fixed product coupon stays on the kit\'s line' );
		$this->settings->terms = BuildTerms::read( array( BuildTerms::COUPONS => BuildTerms::ON_PARTS ) );
		self::assertTrue( $this->builds->couponTakes( true, false, true, false, false ), 'unless the shop sends it to the parts' );
		self::assertTrue( $this->builds->couponTakes( true, true, false, false, false ) );
		self::assertFalse( $this->builds->couponTakes( true, false, false, false, false ) );
		self::assertFalse( $this->builds->couponTakes( true, true, true, true, false ) );
		self::assertFalse( $this->builds->couponTakes( true, false, true, false, true ), 'and so is one a fixed product coupon leaves out' );
		$this->settings->terms = BuildTerms::read( array( BuildTerms::COUPONS => BuildTerms::ON_KIT ) );
		self::assertFalse( $this->builds->couponTakes( true, true, true, false, false ) );
	}

	public function test_a_stored_build_reads_back_and_anything_else_does_not(): void {
		$build = new Build( 'build-1', self::KIT, array( 'deck' => 103 ), array( 'deck' => 1 ), true );
		self::assertEquals( $build, Build::read( $build->toArray() ) );
		$first = new Build( 'a', 1, array( 'deck' => 1 ), array( 'deck' => 1 ), false );
		self::assertEquals( $first, Build::read( $first->toArray() ), 'the smallest of everything' );
		self::assertSame( 1, $build->toArray()['schema_version'] );

		$stored = $build->toArray();
		foreach (
			array(
				null,
				'build-1',
				array(),
				array( 'schema_version' => 2 ) + $stored,
				array( 'schema_version' => '1' ) + $stored,
				array( 'id' => '' ) + $stored,
				array( 'id' => 7 ) + $stored,
				array( 'kit' => 0 ) + $stored,
				array( 'kit' => '77' ) + $stored,
				array( 'assembly' => 1 ) + $stored,
				array( 'picks' => array() , 'quantities' => array() ) + $stored,
				array( 'picks' => 'deck' ) + $stored,
				array( 'picks' => array( 'deck' => 0 ) ) + $stored,
				array( 'picks' => array( 'deck' => '103' ) ) + $stored,
				array( 'picks' => array( 103 ) , 'quantities' => array( 1 ) ) + $stored,
				array( 'quantities' => array( 'deck' => 0 ) ) + $stored,
				array( 'quantities' => array( 'trucks' => 1 ) ) + $stored,
				array( 'quantities' => null ) + $stored,
				array_diff_key( $stored, array( 'id' => 0 ) ),
				array_diff_key( $stored, array( 'kit' => 0 ) ),
				array_diff_key( $stored, array( 'assembly' => 0 ) ),
				array_diff_key( $stored, array( 'picks' => 0 ) ),
			) as $at => $other
		) {
			self::assertNull( Build::read( $other ), (string) $at );
		}
	}
}
