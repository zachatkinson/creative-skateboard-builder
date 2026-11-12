<?php
/**
 * Order again, on an order with a build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Order;

/**
 * The auditor's finding 7 on milestone 5, and Zach's answer of 11 November 2026 (D-137): WooCommerce's Order again
 * button refused the kit and left each part of a build in the cart as a line of its own. It puts the build back
 * whole, judged as the shop is now, or puts none of it back and says so.
 *
 * WooCommerce fills the cart from the order as the cart is read from the session
 * (includes/class-wc-cart-session.php:139 and :625), which is how each test asks for it.
 */
final class OrderAgainTest extends CartTestCase {

	private int $shopper;

	public function set_up(): void {
		parent::set_up();
		$this->shopper = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => 'shopper+7@example.com' ) );
		wp_set_current_user( $this->shopper );
	}

	public function tear_down(): void {
		unset( $_GET['order_again'], $_GET['_wpnonce'] );
		wc_clear_notices();
		parent::tear_down();
	}

	/**
	 * A completed order of the usual build, twice, with a deck beside it.
	 */
	private function ordered(): WC_Order {
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
		WC()->cart->add_to_cart( $this->parts['deck'], 1 );
		$id = WC()->checkout()->create_order(
			array(
				'billing_first_name' => 'Sam',
				'billing_last_name'  => 'Shopper',
				'billing_email'      => 'shopper+7@example.com',
				'payment_method'     => 'cheque',
			)
		);
		self::assertIsInt( $id );
		$order = wc_get_order( $id );
		self::assertInstanceOf( WC_Order::class, $order );
		$order->set_customer_id( $this->shopper );
		$order->set_status( 'completed' );
		$order->save();
		WC()->cart->empty_cart();
		return $order;
	}

	/**
	 * Press Order again, as the cart's next read from the session does it.
	 *
	 * @param WC_Order $order The order.
	 */
	private function again( WC_Order $order ): void {
		wc_clear_notices();
		$_GET['order_again'] = (string) $order->get_id();
		$_GET['_wpnonce']    = wp_create_nonce( 'woocommerce-order_again' );
		// WooCommerce redirects to the cart and exits when it is done (class-wc-cart-session.php:313): the test
		// stops it at the redirect, which is after everything else.
		$stop = static function (): never {
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $stop );
		try {
			WC()->cart->get_cart_from_session();
			self::fail( 'Order again did not run: nothing was redirected.' );
		} catch ( \RuntimeException $redirected ) {
			self::assertSame( 'redirected', $redirected->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}
		unset( $_GET['order_again'], $_GET['_wpnonce'] );
	}

	/**
	 * The cart's lines, by what each is: 'kit', a slot's key, or 'loose'.
	 *
	 * @return array<string, int> The quantity of each.
	 */
	private static function lines(): array {
		$lines = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$role           = isset( $item[ Options::CART_BUILD ] ) ? 'kit' : ( $item[ Options::CART_SLOT ] ?? 'loose' );
			$lines[ $role ] = (int) $item['quantity'];
		}
		return $lines;
	}

	public function test_a_build_is_put_back_whole_with_a_new_identifier(): void {
		$order = $this->ordered();
		$was   = '';
		foreach ( $order->get_items() as $item ) {
			$was = (string) $item->get_meta( Options::ORDER_BUILD ) ?: $was;
		}
		$this->again( $order );

		self::assertSame(
			array(
				'kit'      => 2,
				'deck'     => 2,
				'trucks'   => 4,
				'wheels'   => 2,
				'bearings' => 2,
				'hardware' => 2,
				'grip'     => 2,
				'loose'    => 1,
			),
			self::lines()
		);
		self::assertSame( array(), self::errors() );
		$kit = null;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( isset( $item[ Options::CART_BUILD ] ) ) {
				$kit = $item;
				self::assertSame( $key, $item['key'] );
			}
		}
		self::assertIsArray( $kit );
		self::assertNotSame( $was, $kit[ Options::CART_BUILD ]['id'], 'a build of its own, not the order\'s' );
		self::assertTrue( $kit[ Options::CART_BUILD ]['assembly'] );
		self::assertCount( 6, $kit[ Options::CART_CHILDREN ] );
		foreach ( $kit[ Options::CART_CHILDREN ] as $child ) {
			self::assertSame( $kit['key'], WC()->cart->get_cart()[ $child ][ Options::CART_PARENT ] );
		}
		// The fee and each part as the shop charges them now: two builds at 183 and 15, and the deck.
		WC()->cart->calculate_totals();
		self::assertSame( '466.00', number_format( (float) WC()->cart->get_subtotal(), 2, '.', '' ) );
		// And it is a build the check before paying has nothing to say of.
		WC()->cart->check_cart_items();
		self::assertSame( array(), self::errors() );
		self::assertCount( 8, WC()->cart->get_cart() );
	}

	public function test_a_build_the_shop_would_no_longer_sell_is_not_put_back_and_nothing_of_it_is_left_loose(): void {
		$order = $this->ordered();
		// The grip no longer fits: it is no longer one of the slot's parts.
		wp_set_object_terms( $this->parts['grip'], array(), 'product_cat' );
		$this->again( $order );

		self::assertSame( array( 'loose' => 1 ), self::lines(), 'the deck bought beside the build, and none of the build' );
		$errors = self::errors();
		self::assertContains( 'A build from that order cannot be bought as it was, so none of it was added. Build it again to order it.', $errors );
		self::assertSame( array(), preg_grep( '/Choose the parts/', $errors ), 'and the kit is left out without the notice of a kit added bare' );
	}

	public function test_a_build_with_a_part_woocommerce_leaves_out_is_not_put_back_in_pieces(): void {
		$order = $this->ordered();
		// Sold out since: WooCommerce's Order again leaves the line out by itself (class-wc-cart-session.php:673).
		$wheels = wc_get_product( $this->parts['wheels'] );
		$wheels->set_stock_status( 'outofstock' );
		$wheels->save();
		$this->again( $order );

		self::assertSame( array( 'loose' => 1 ), self::lines() );
		self::assertContains( 'A build from that order cannot be bought as it was, so none of it was added. Build it again to order it.', self::errors() );
	}
}
