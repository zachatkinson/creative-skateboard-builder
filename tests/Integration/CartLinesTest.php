<?php
/**
 * A build in the cart, kept together.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Product_Simple;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Spec, Cart, checkout and orders, Keeping it together. Roadmap, milestone 5, Done when: no route through any
 * cart can split, change or orphan a build.
 */
final class CartLinesTest extends CartTestCase {

	/**
	 * Add the usual build through the Store API and give its kit's line's key.
	 *
	 * @param int $quantity How many.
	 */
	private function added( int $quantity = 1 ): string {
		$before   = array_keys( WC()->cart->get_cart() );
		$response = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => $quantity,
				'cbuild'   => array( 'picks' => $this->picks() ),
			)
		);
		self::assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( isset( $item[ Options::CART_BUILD ] ) && ! in_array( $key, $before, true ) ) {
				return $key;
			}
		}
		self::fail( 'no kit line was added' );
	}

	/**
	 * Ask a route of the Store API's cart.
	 *
	 * @param string               $method The method.
	 * @param string               $route  The route under the cart's.
	 * @param array<string, mixed> $body   The body.
	 */
	private function cart( string $method, string $route, array $body = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/wc/store/v1/cart' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * A loose product, in the cart beside the build.
	 */
	private function loose(): string {
		$product = new WC_Product_Simple();
		$product->set_name( 'Sticker' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '2' );
		$product->save();
		return (string) WC()->cart->add_to_cart( $product->get_id(), 3 );
	}

	/**
	 * Each line's quantity, by cart key.
	 *
	 * @return array<string, int>
	 */
	private static function quantities(): array {
		return array_map( static fn( array $item ): int => (int) $item['quantity'], WC()->cart->get_cart() );
	}

	/**
	 * Keep a count of a part, as the shop does.
	 *
	 * @param string $slot  The slot whose part it is.
	 * @param int    $stock How many the shop has.
	 */
	private function stock( string $slot, int $stock ): void {
		$product = wc_get_product( $this->parts[ $slot ] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $stock );
		$product->save();
	}

	public function test_the_kits_quantity_sets_its_parts_by_every_route_and_the_totals_follow(): void {
		$loose    = $this->loose();
		$key      = $this->added();
		$children = WC()->cart->get_cart()[ $key ][ Options::CART_CHILDREN ];
		$one      = array_intersect_key( self::quantities(), array_flip( $children ) );

		$response = $this->cart( 'POST', '/update-item', array( 'key' => $key, 'quantity' => 3 ) );
		self::assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$now = self::quantities();
		foreach ( $one as $child => $each ) {
			self::assertSame( 3 * $each, $now[ $child ] );
		}
		self::assertSame( 3, $now[ $loose ] );
		self::assertSame( 3 * ( 70 + 2 * 27 + 32 + 15 + 4 + 8 ) + 6, (int) WC()->cart->get_subtotal(), 'worked out with the parts already following' );
		self::assertSame( (string) ( ( 3 * 183 + 6 ) * 100 ), $response->get_data()['totals']->total_items );

		// The classic cart's Update cart, and any plugin, set a quantity this way.
		WC()->cart->set_quantity( $key, 2 );
		foreach ( $one as $child => $each ) {
			self::assertSame( 2 * $each, self::quantities()[ $child ] );
		}
		self::assertSame( 2 * 183 + 6, (int) WC()->cart->get_subtotal() );

		// A part set alone, past every check, goes back to what its kit's line asks for.
		WC()->cart->set_quantity( $children[0], 9 );
		self::assertSame( 2 * $one[ $children[0] ], self::quantities()[ $children[0] ] );
	}

	public function test_a_parts_quantity_is_locked_in_the_store_api_and_refused_by_both_carts(): void {
		$key      = $this->added( 2 );
		$children = WC()->cart->get_cart()[ $key ][ Options::CART_CHILDREN ];
		$before   = self::quantities();

		$items = array_column( $this->cart( 'GET', '' )->get_data()['items'], null, 'key' );
		self::assertTrue( $items[ $key ]['quantity_limits']->editable );
		foreach ( $children as $child ) {
			self::assertFalse( $items[ $child ]['quantity_limits']->editable );
		}

		$response = $this->cart( 'POST', '/update-item', array( 'key' => $children[0], 'quantity' => 5 ) );
		self::assertContains( $response->get_status(), array( 400, 409 ), (string) wp_json_encode( $response->get_data() ) );
		self::assertSame( $before, self::quantities() );

		// The plugin's own answer to the Store API, were WooCommerce's lock not asked first.
		$item  = WC()->cart->get_cart()[ $children[0] ];
		$error = apply_filters( 'woocommerce_store_api_cart_item_quantity_validation', true, 5, $item['data'], $item );
		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'cbuild_part_locked', $error->get_error_code() );
		self::assertSame( 'A part of a build comes in the quantity the build needs. Change the quantity of the build instead.', $error->get_error_message() );
		self::assertSame( 400, $error->get_error_data()['status'] );
		self::assertTrue( apply_filters( 'woocommerce_store_api_cart_item_quantity_validation', true, $before[ $children[0] ], $item['data'], $item ) );

		// The classic cart's Update cart (includes/class-wc-form-handler.php:859).
		self::assertFalse( apply_filters( 'woocommerce_update_cart_validation', true, $children[0], $item, 5 ) );
		self::assertSame( array( 'A part of a build comes in the quantity the build needs. Change the quantity of the build instead.' ), self::errors() );
		wc_clear_notices();
		self::assertTrue( apply_filters( 'woocommerce_update_cart_validation', true, $key, WC()->cart->get_cart()[ $key ], 9 ) );
		self::assertSame( array(), self::errors() );
	}

	public function test_a_kits_line_is_held_to_what_the_scarcest_part_allows(): void {
		$this->stock( 'deck', 5 );
		$this->stock( 'trucks', 7 );
		$key = $this->added( 2 );

		$items = array_column( $this->cart( 'GET', '' )->get_data()['items'], null, 'key' );
		self::assertSame( 3, $items[ $key ]['quantity_limits']->maximum, 'seven trucks, two a build' );

		$response = $this->cart( 'POST', '/update-item', array( 'key' => $key, 'quantity' => 4 ) );
		self::assertSame( 400, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		self::assertSame( 2, self::quantities()[ $key ] );

		$item  = WC()->cart->get_cart()[ $key ];
		$error = apply_filters( 'woocommerce_store_api_cart_item_quantity_validation', true, 6, $item['data'], $item );
		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'cbuild_build_refused', $error->get_error_code() );
		self::assertSame( 'Deck: the shop has fewer of the part chosen than a build takes. Trucks: the shop has fewer of the part chosen than a build takes.', $error->get_error_message() );
		self::assertSame(
			array(
				array( 'code' => 'part_short', 'slot' => 'deck', 'part' => $this->parts['deck'] ),
				array( 'code' => 'part_short', 'slot' => 'trucks', 'part' => $this->parts['trucks'] ),
			),
			$error->get_error_data()['problems']
		);

		self::assertFalse( apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, 4 ) );
		self::assertSame( array( 'Trucks: the shop has fewer of the part chosen than a build takes.' ), self::errors() );
		wc_clear_notices();
		self::assertTrue( apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, 3 ) );

		$response = $this->cart( 'POST', '/update-item', array( 'key' => $key, 'quantity' => 3 ) );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( 3, self::quantities()[ $key ] );
	}

	public function test_removing_any_line_of_a_build_removes_all_of_it_by_every_route(): void {
		$loose  = $this->loose();
		$first  = $this->added();
		$second = $this->added();
		$third  = $this->added();
		self::assertCount( 22, WC()->cart->get_cart() );

		$child = WC()->cart->get_cart()[ $first ][ Options::CART_CHILDREN ][3];
		self::assertSame( 200, $this->cart( 'POST', '/remove-item', array( 'key' => $child ) )->get_status() );
		self::assertCount( 15, WC()->cart->get_cart(), 'from a part, through the Store API' );
		self::assertArrayNotHasKey( $first, WC()->cart->get_cart() );

		self::assertSame( 200, $this->cart( 'POST', '/remove-item', array( 'key' => $second ) )->get_status() );
		self::assertCount( 8, WC()->cart->get_cart(), 'from the kit' );

		WC()->cart->set_quantity( WC()->cart->get_cart()[ $third ][ Options::CART_CHILDREN ][0], 0 );
		self::assertSame( array( $loose ), array_keys( WC()->cart->get_cart() ), 'and a quantity of none is a removal' );
		self::assertSame( 6, (int) WC()->cart->get_subtotal() );
	}

	public function test_the_classic_carts_undo_brings_the_whole_build_back(): void {
		$key      = $this->added( 2 );
		$children = WC()->cart->get_cart()[ $key ][ Options::CART_CHILDREN ];
		$before   = self::quantities();

		foreach ( array( $children[2], $key ) as $removed ) {
			WC()->cart->remove_cart_item( $removed );
			self::assertSame( array(), WC()->cart->get_cart() );
			WC()->cart->restore_cart_item( $removed );
			self::assertEquals( $before, self::quantities() );
			$line = WC()->cart->get_cart()[ $key ];
			self::assertSame( $children, $line[ Options::CART_CHILDREN ] );
			self::assertTrue( $line['data']->is_purchasable(), 'the kit\'s line is one the cart sells' );
			self::assertSame( 2 * 183, (int) WC()->cart->get_subtotal() );
			self::assertSame( array(), WC()->cart->get_removed_cart_contents() );
		}
	}

	public function test_the_cart_is_looked_over_and_a_build_that_came_apart_is_taken_out(): void {
		$loose = $this->loose();
		$this->cart( 'GET', '' );
		self::assertSame( array(), $this->cart( 'GET', '' )->get_data()['errors'], 'a cart with no build in it' );

		$key      = $this->added();
		$children = WC()->cart->get_cart()[ $key ][ Options::CART_CHILDREN ];
		self::assertSame( array(), $this->cart( 'GET', '' )->get_data()['errors'] );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its cart page does it.
		do_action( 'woocommerce_check_cart_items' );
		self::assertSame( array(), self::errors() );
		self::assertCount( 8, WC()->cart->get_cart() );

		// A part's line lost past every hook, as a session cut short or another plugin's filter leaves it.
		unset( WC()->cart->cart_contents[ $children[1] ] );
		$errors = $this->cart( 'GET', '' )->get_data()['errors'];
		self::assertSame( array( 'A build in your cart had lost one of its parts and was removed. Add the build again.' ), array_column( $errors, 'message' ) );
		self::assertSame( array( $loose ), array_keys( WC()->cart->get_cart() ) );
		self::assertSame( array(), $this->cart( 'GET', '' )->get_data()['errors'], 'said once' );

		// And a kit's line lost, on the classic cart's page.
		$key = $this->added();
		unset( WC()->cart->cart_contents[ $key ] );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		do_action( 'woocommerce_check_cart_items' );
		self::assertSame( array( 'A build in your cart had lost one of its parts and was removed. Add the build again.' ), self::errors() );
		self::assertSame( array( $loose ), array_keys( WC()->cart->get_cart() ) );
	}

	public function test_a_build_the_shop_would_no_longer_sell_stops_the_order_and_stays(): void {
		$key = $this->added();

		// The deck's spec changes after the build is in the cart: it is now too wide for the trucks.
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data(
			Options::META_SPECS,
			array(
				'deck_width'   => '9.5000',
				'deck_length'  => '32.0000',
				'hole_pattern' => 'new_school',
			)
		);
		$deck->save();
		// The job a save queues, run.
		$this->service( SpecIndexer::class )->index( $deck->get_id() );
		$this->stock( 'grip', 0 );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		do_action( 'woocommerce_check_cart_items' );
		$errors = self::errors();
		self::assertCount( 7, WC()->cart->get_cart(), 'the build stays, for the shopper to change' );
		self::assertNotSame( array(), preg_grep( '/^Trucks: /', $errors ), implode( ' | ', $errors ) );
		self::assertNotSame( array(), preg_grep( '/^Grip: /', $errors ), implode( ' | ', $errors ) );
		// The reviews of milestone 5: the notice names the build and says what to do, before it says why.
		self::assertSame( 'Complete cannot be bought as it is now. Remove it from your cart to check out, then build it again.', $errors[0] );
		wc_clear_notices();

		$errors = array_column( $this->cart( 'GET', '' )->get_data()['errors'], 'message' );
		self::assertNotSame( array(), preg_grep( '/^Trucks: /', $errors ), 'and the Store API says the same' );

		// The kit taken down.
		$kit = wc_get_product( $this->kit );
		$kit->set_status( 'draft' );
		$kit->save();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		do_action( 'woocommerce_check_cart_items' );
		self::assertSame( array( 'Complete is no longer sold. Remove it from your cart to check out.' ), self::errors(), 'and nothing is said of an add that never happened' );
		self::assertArrayHasKey( $key, WC()->cart->get_cart() );
	}
}
