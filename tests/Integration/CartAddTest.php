<?php
/**
 * A build added to the cart, by both routes.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;

/**
 * Spec, Cart, checkout and orders, Adding a build: the same three steps whichever way it arrives, the Store API
 * or the classic form. Roadmap, milestone 5: a bare kit is refused. Issue #26: the cart reads a picked part from
 * WooCommerce itself.
 */
final class CartAddTest extends CartTestCase {

	public function test_the_store_api_adds_the_kit_with_its_build_and_a_line_for_each_part(): void {
		$response = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => 2,
				'cbuild'   => array(
					'picks'    => $this->picks(),
					'assembly' => true,
				),
			)
		);
		self::assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		$builds = self::builds();
		self::assertCount( 1, $builds );
		self::assertCount( 7, WC()->cart->get_cart(), 'the kit and six parts' );
		self::assertSame( $this->kit, $builds[0]['build']->kit );
		self::assertSame( $this->picks(), $builds[0]['build']->picks );
		self::assertTrue( $builds[0]['build']->assembly );
		self::assertTrue( wp_is_uuid( $builds[0]['build']->id, 4 ) );
		self::assertSame( 2, $builds[0]['quantity'] );
		self::assertSame( '15.00', $builds[0]['price'], 'the assembly fee' );
		$expected = array();
		foreach ( $this->picks() as $slot => $part ) {
			$expected[ $slot ] = array( $part, 2 * $builds[0]['build']->quantities[ $slot ] );
		}
		self::assertSame( $expected, $builds[0]['parts'], 'each part at what two builds take of it' );
		self::assertSame( 2, $builds[0]['build']->quantities['trucks'], 'trucks sold singly: a build takes a pair' );

		WC()->cart->calculate_totals();
		self::assertSame( 2 * ( 70 + 2 * 27 + 32 + 15 + 4 + 8 + 15 ), (int) WC()->cart->get_subtotal(), "each part at the shop's own price, and the fee" );
	}

	public function test_the_form_adds_the_same_build_with_the_kits_default_for_assembly(): void {
		$this->form( array( 'picks' => array_map( 'strval', $this->picks() ) ) );
		self::assertSame( array(), self::errors() );

		$builds = self::builds();
		self::assertCount( 1, $builds );
		self::assertCount( 7, WC()->cart->get_cart() );
		self::assertSame( $this->picks(), $builds[0]['build']->picks );
		self::assertFalse( $builds[0]['build']->assembly );
		self::assertSame( '0.00', $builds[0]['price'], 'no assembly, no fee' );
		self::assertSame( array_keys( $this->picks() ), array_keys( $builds[0]['parts'] ) );
		self::assertNotSame( array(), wc_get_notices( 'success' ), "WooCommerce's own message that it was added" );
	}

	public function test_two_builds_alike_are_two_lines_each_with_parts_of_its_own(): void {
		$body = array(
			'id'     => $this->kit,
			'cbuild' => array( 'picks' => $this->picks() ),
		);
		self::assertSame( 201, $this->store( $body )->get_status() );
		self::assertSame( 201, $this->store( $body )->get_status() );
		$builds = self::builds();
		self::assertCount( 2, $builds );
		self::assertCount( 14, WC()->cart->get_cart() );
		self::assertNotSame( $builds[0]['build']->id, $builds[1]['build']->id );
		self::assertSame( array( 1, 1 ), array_column( $builds, 'quantity' ) );
	}

	public function test_a_kit_with_no_build_is_refused_by_every_route(): void {
		$response = $this->store( array( 'id' => $this->kit ) );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'cbuild_no_build', $response->get_data()['code'] );
		self::assertSame( 'Choose the parts for this build first, then add it to the cart.', $response->get_data()['message'] );

		$this->form( null );
		self::assertSame( array( 'Choose the parts for this build first, then add it to the cart.' ), self::errors() );
		wc_clear_notices();

		self::assertFalse( WC()->cart->add_to_cart( $this->kit ), 'another plugin, or an express button, adding the product itself' );
		self::assertFalse( WC()->cart->add_to_cart( $this->kit, 1, 0, array(), array( Options::CART_BUILD => ( new Build( wp_generate_uuid4(), $this->kit, $this->picks(), array_fill_keys( array_keys( $this->picks() ), 1 ), false ) )->toArray() ) ), 'nor with a build it made up' );
		self::assertSame( array(), WC()->cart->get_cart() );
		self::assertFalse( wc_get_product( $this->kit )->is_purchasable(), 'and the kit is still not for sale as a product' );
	}

	public function test_a_build_with_something_wrong_is_refused_and_says_what(): void {
		$sold = wc_get_product( $this->parts['wheels'] );
		$sold->set_stock_status( 'outofstock' );
		$sold->save();
		$picks = array_diff_key( $this->picks(), array( 'grip' => 0 ) );

		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array( 'picks' => $picks ),
			)
		);
		self::assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		self::assertSame( 'cbuild_build_refused', $data['code'] );
		$codes = array_column( $data['data']['problems'], 'slot', 'code' );
		self::assertSame( 'wheels', $codes['part_sold_out'] );
		self::assertSame( 'grip', $codes['slot_empty'] );
		self::assertSame( array(), WC()->cart->get_cart() );

		$this->form( array( 'picks' => $picks ) );
		self::assertEqualsCanonicalizing( array( 'Wheels: the part chosen is sold out.', 'Grip: nothing is chosen yet.' ), self::errors() );
		self::assertSame( array(), WC()->cart->get_cart() );
		self::assertSame( array(), wc_get_notices( 'success' ) );
	}

	public function test_a_kit_that_is_not_published_is_not_found(): void {
		$draft = wc_get_product( $this->kit );
		$draft->set_status( 'draft' );
		$draft->save();
		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array( 'picks' => $this->picks() ),
			)
		);
		self::assertContains( $response->get_status(), array( 400, 404 ) );
		self::assertSame( array(), WC()->cart->get_cart() );
	}

	// Issue #26: a price changed where no hook fires. The builder shows what is kept; the cart charges what is.
	public function test_the_cart_charges_what_woocommerce_holds_whatever_the_builder_was_shown(): void {
		$deck  = $this->parts['deck'];
		$state = $this->service( BuildState::class )->of( $this->kit, $this->picks() );
		self::assertSame( 7000, $state?->quote->lines[0]->price );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the test's point: a write that fires nothing.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = '99' WHERE post_id = %d AND meta_key IN ( '_price', '_regular_price' )", $deck ) );
		wp_cache_flush();
		self::assertSame( 7000, $this->service( BuildState::class )->of( $this->kit, $this->picks() )?->quote->lines[0]->price, 'the builder still shows what is kept' );

		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $deck === $item['product_id'] ) {
				self::assertSame( '99', $item['data']->get_price(), 'the cart charges what the shop holds' );
			}
		}
	}

	public function test_a_part_woocommerce_will_not_add_takes_the_whole_build_out_and_fails_the_add(): void {
		$wheels = $this->parts['wheels'];
		// Once the build has been judged and its kit added, the wheels stop being for sale.
		add_action(
			'woocommerce_add_to_cart',
			static function () use ( $wheels ): void {
				add_filter( 'woocommerce_is_purchasable', static fn( $purchasable, $product ) => $wheels === $product->get_id() ? false : $purchasable, 99, 2 );
			},
			5
		);
		$this->form( array( 'picks' => $this->picks() ) );
		self::assertSame( array(), WC()->cart->get_cart(), 'nothing of the build stays' );
		self::assertContains( 'One of the parts could not be added, so nothing of the build was. Check the parts and add the build again.', self::errors() );
		self::assertSame( array(), wc_get_notices( 'success' ) );
		remove_all_filters( 'woocommerce_is_purchasable', 99 );

		$response = $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) );
		self::assertSame( 400, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		self::assertSame( 'cbuild_not_added', $response->get_data()['code'] );
		self::assertSame( array(), WC()->cart->get_cart() );
	}

	public function test_a_build_comes_back_from_the_session_whole_priced_and_for_sale(): void {
		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks(), 'assembly' => true ) ) )->get_status() );
		$before = self::builds();
		WC()->cart->set_session();
		WC()->cart->cart_contents = array();
		wc_clear_notices();
		WC()->cart->get_cart_from_session();

		self::assertSame( array(), self::errors(), 'WooCommerce does not remove the kit as one that cannot be bought' );
		$after = self::builds();
		self::assertEquals( $before, $after );
		self::assertSame( '15.00', $after[0]['price'] );
		foreach ( WC()->cart->get_cart() as $item ) {
			self::assertTrue( $item['data']->is_purchasable() );
		}

		// A kit's line whose build another version wrote is not one this version sells.
		$key = array_key_first( WC()->cart->get_cart() );
		WC()->cart->cart_contents[ $key ][ Options::CART_BUILD ]['schema_version'] = 2;
		WC()->cart->set_session();
		WC()->cart->cart_contents = array();
		WC()->cart->get_cart_from_session();
		self::assertSame( array(), self::builds() );
		self::assertCount( 1, self::errors() );
	}
}
