<?php
/**
 * What a build costs in the cart.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Coupon;
use WC_Product_Simple;
use WC_Tax;
use WP_REST_Request;

/**
 * Spec, Pricing, stock, assembly and returns, Pricing; D-009. Roadmap, milestone 5: each coupon type with the
 * setting both ways; tax classes, with prices shown with and without tax.
 *
 * The build's parts cost 70, 27 a truck (two), 32, 15, 4 and 8: 183 in all. The assembly fee is 15.
 */
final class CartPriceTest extends CartTestCase {

	public function tear_down(): void {
		WC()->cart->remove_coupons();
		delete_option( Options::SETTINGS );
		update_option( 'woocommerce_calc_taxes', 'no' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		parent::tear_down();
	}

	/**
	 * Add the usual build, and give its kit's line's key.
	 *
	 * @param bool $assembly Whether the shop assembles it.
	 * @param int  $quantity How many.
	 */
	private function added( bool $assembly = false, int $quantity = 1 ): string {
		$response = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => $quantity,
				'cbuild'   => array(
					'picks'    => $this->picks(),
					'assembly' => $assembly,
				),
			)
		);
		self::assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( isset( $item[ Options::CART_BUILD ] ) ) {
				return $key;
			}
		}
		self::fail( 'no kit line was added' );
	}

	/**
	 * The shop's settings for builds.
	 *
	 * @param array<string, string> $settings The keys to set.
	 */
	private static function settings( array $settings ): void {
		update_option( Options::SETTINGS, $settings );
	}

	/**
	 * Make a coupon and apply it.
	 *
	 * @param string               $type   The discount type.
	 * @param string               $amount Its amount.
	 * @param array<string, mixed> $props  Its restrictions.
	 */
	private static function coupon( string $type, string $amount, array $props = array() ): string {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'c' . wp_generate_password( 8, false ) );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		$coupon->set_props( $props );
		$coupon->save();
		self::assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ), implode( ' | ', self::errors() ) );
		WC()->cart->calculate_totals();
		return strtolower( $coupon->get_code() );
	}

	/**
	 * What a coupon takes off each line, by the slot the line fills; the kit's line is 'kit', and a line of no
	 * build is 'loose'.
	 *
	 * @return array<string, float>
	 */
	private static function discounts(): array {
		$out = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$role         = isset( $item[ Options::CART_BUILD ] ) ? 'kit' : ( $item[ Options::CART_SLOT ] ?? 'loose' );
			$out[ $role ] = round( (float) $item['line_subtotal'] - (float) $item['line_total'], 2 );
		}
		return array_filter( $out );
	}

	public function test_the_build_discount_comes_off_each_part_once_and_never_off_the_fee_or_another_line(): void {
		self::settings( array( 'build_discount' => '10' ) );
		$loose = (string) WC()->cart->add_to_cart( $this->parts['deck'], 1 );
		$key   = $this->added( true, 2 );

		WC()->cart->calculate_totals();
		WC()->cart->calculate_totals();
		$cart = WC()->cart->get_cart();
		self::assertSame( '15.00', $cart[ $key ]['data']->get_price(), 'the fee is not discounted' );
		self::assertSame( '70', $cart[ $loose ]['data']->get_price(), 'nor a deck bought beside the build' );
		$prices = array();
		foreach ( $cart[ $key ][ Options::CART_CHILDREN ] as $child ) {
			$prices[ $cart[ $child ][ Options::CART_SLOT ] ] = $cart[ $child ]['data']->get_price();
		}
		self::assertSame(
			array(
				'deck'     => '63.00',
				'trucks'   => '24.30',
				'wheels'   => '28.80',
				'bearings' => '13.50',
				'hardware' => '3.60',
				'grip'     => '7.20',
			),
			$prices,
			'ten per cent off each, taken once however often the totals are worked out'
		);
		self::assertSame( '429.40', number_format( (float) WC()->cart->get_subtotal(), 2, '.', '' ), 'two builds at 164.70 and their fees, and a deck at 70' );

		// The classic cart shows the part's own price struck through; the Store API gives both.
		$deck = $cart[ $cart[ $key ][ Options::CART_CHILDREN ][0] ];
		$cell = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $deck['data'] ), $deck, $deck['key'] );
		self::assertMatchesRegularExpression( '#<del[^>]*>.*70\.00.*</del>.*<ins[^>]*>.*63\.00.*</ins>#s', $cell );
		$plain = WC()->cart->get_product_price( $cart[ $loose ]['data'] );
		self::assertSame( $plain, apply_filters( 'woocommerce_cart_item_price', $plain, $cart[ $loose ], $loose ) );
		$items = array_column( rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wc/store/v1/cart' ) )->get_data()['items'], null, 'key' );
		self::assertSame( '6300', $items[ $deck['key'] ]['prices']->price );
		self::assertSame( '7000', $items[ $deck['key'] ]['prices']->regular_price );

		// The shop takes the discount away: the next totals are at the shop's own prices.
		self::settings( array( 'build_discount' => '0' ) );
		WC()->cart->calculate_totals();
		self::assertSame( '70', $deck['data']->get_price() );
		self::assertSame( '466.00', number_format( (float) WC()->cart->get_subtotal(), 2, '.', '' ) );
	}

	public function test_a_price_another_plugin_sets_on_the_line_is_the_one_discounted(): void {
		self::settings( array( 'build_discount' => '50' ) );
		$key  = $this->added();
		$deck = WC()->cart->get_cart()[ WC()->cart->get_cart()[ $key ][ Options::CART_CHILDREN ][0] ]['data'];
		// A trade price, set on every totals as such plugins do.
		add_action(
			'woocommerce_before_calculate_totals',
			static function () use ( $deck ): void {
				$deck->set_price( '60' );
			}
		);
		WC()->cart->calculate_totals();
		WC()->cart->calculate_totals();
		self::assertSame( '30.00', $deck->get_price() );
	}

	public function test_a_coupon_that_takes_the_kit_takes_its_parts(): void {
		$this->added( true );
		$loose = (string) WC()->cart->add_to_cart( $this->parts['deck'], 1 );

		// A percentage off the kit alone: off the fee, and off each of its parts. Not off the deck beside it.
		self::coupon( 'percent', '10', array( 'product_ids' => array( $this->kit ) ) );
		self::assertSame(
			array(
				'kit'      => 1.5,
				'deck'     => 7.0,
				'trucks'   => 5.4,
				'wheels'   => 3.2,
				'bearings' => 1.5,
				'hardware' => 0.4,
				'grip'     => 0.8,
			),
			self::discounts()
		);
		WC()->cart->remove_coupons();

		// A percentage off decks: off the build's deck as off any other.
		$decks = (int) current( wc_get_product( $this->parts['deck'] )->get_category_ids() );
		self::coupon( 'percent', '10', array( 'product_categories' => array( $decks ) ) );
		self::assertSame(
			array(
				'deck'  => 7.0,
				'loose' => 7.0,
			),
			self::discounts()
		);
		WC()->cart->remove_coupons();

		// A percentage off everything but the kit: its parts are left out with it.
		self::coupon( 'percent', '10', array( 'excluded_product_ids' => array( $this->kit ) ) );
		self::assertSame( array( 'loose' => 7.0 ), self::discounts() );
		WC()->cart->remove_coupons();

		// A fixed cart coupon is spread over the cart as WooCommerce spreads it.
		self::coupon( 'fixed_cart', '26.80' );
		self::assertSame( '26.80', number_format( (float) WC()->cart->get_discount_total(), 2, '.', '' ) );
	}

	public function test_a_fixed_product_coupon_lands_where_the_setting_says(): void {
		$this->added( true, 2 );
		WC()->cart->add_to_cart( $this->parts['deck'], 1 );

		// By default on the kit's line alone, for each of the two builds, and on what is not of a build.
		$code = self::coupon( 'fixed_product', '3' );
		self::assertSame(
			array(
				'kit'   => 6.0,
				'loose' => 3.0,
			),
			self::discounts()
		);

		// The shop chooses the parts too: its amount comes off each, for each of them the cart holds.
		self::settings( array( 'fixed_coupons' => 'parts' ) );
		WC()->cart->calculate_totals();
		self::assertSame(
			array(
				'kit'      => 6.0,
				'deck'     => 6.0,
				'trucks'   => 12.0,
				'wheels'   => 6.0,
				'bearings' => 6.0,
				'hardware' => 6.0,
				'grip'     => 6.0,
				'loose'    => 3.0,
			),
			self::discounts()
		);
		self::assertContains( $code, WC()->cart->get_applied_coupons() );
		WC()->cart->remove_coupons();

		// One for the kit alone follows the same setting, and with it back at the kit leaves the parts be.
		self::coupon( 'fixed_product', '3', array( 'product_ids' => array( $this->kit ) ) );
		self::assertSame( array( 'kit', 'deck', 'trucks', 'wheels', 'bearings', 'hardware', 'grip' ), array_keys( self::discounts() ) );
		self::settings( array( 'fixed_coupons' => 'kit' ) );
		WC()->cart->calculate_totals();
		self::assertSame( array( 'kit' => 6.0 ), self::discounts() );
	}

	public function test_a_build_counts_once_in_the_carts_count(): void {
		$this->added( false, 2 );
		self::assertSame( 2, WC()->cart->get_cart_contents_count(), 'two builds' );
		WC()->cart->add_to_cart( $this->parts['deck'], 3 );
		self::assertSame( 5, WC()->cart->get_cart_contents_count(), 'and three decks beside them' );
		$cart = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wc/store/v1/cart' ) )->get_data();
		self::assertSame( 5, $cart['items_count'], 'which the Mini-Cart shows' );
		self::assertCount( 8, $cart['items'], 'every line is still there: the kit, its six parts and the decks' );
	}

	public function test_each_part_is_taxed_in_its_own_class_however_prices_are_shown(): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'base' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country' => '',
				'tax_rate'         => '10.0000',
				'tax_rate_name'    => 'Standard',
				'tax_rate_class'   => '',
			)
		);
		WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country' => '',
				'tax_rate'         => '5.0000',
				'tax_rate_name'    => 'Reduced',
				'tax_rate_class'   => 'reduced-rate',
			)
		);
		$bearings = wc_get_product( $this->parts['bearings'] );
		$bearings->set_tax_class( 'reduced-rate' );
		$bearings->save();
		$grip = wc_get_product( $this->parts['grip'] );
		$grip->set_tax_status( 'none' );
		$grip->save();
		self::settings( array( 'build_discount' => '10' ) );

		foreach ( array( 'excl', 'incl' ) as $shown ) {
			update_option( 'woocommerce_tax_display_cart', $shown );
			WC()->cart->empty_cart();
			$key = $this->added( true );
			WC()->cart->calculate_totals();
			$taxes = array();
			foreach ( WC()->cart->get_cart() as $item ) {
				$taxes[ isset( $item[ Options::CART_BUILD ] ) ? 'kit' : $item[ Options::CART_SLOT ] ] = round( (float) $item['line_tax'], 2 );
			}
			self::assertSame(
				array(
					'kit'      => 1.5,
					'deck'     => 6.3,
					'trucks'   => 4.86,
					'wheels'   => 2.88,
					'bearings' => 0.68,
					'hardware' => 0.36,
					'grip'     => 0.0,
				),
				$taxes,
				'the fee and each part at the rate of its own class, on its discounted price, shown ' . $shown
			);
			self::assertSame( '179.70', number_format( (float) WC()->cart->get_subtotal(), 2, '.', '' ) );
			self::assertSame( '196.28', number_format( (float) WC()->cart->get_total( 'edit' ), 2, '.', '' ) );

			$deck = WC()->cart->get_cart()[ WC()->cart->get_cart()[ $key ][ Options::CART_CHILDREN ][0] ];
			$cell = wp_strip_all_tags( (string) apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $deck['data'] ), $deck, $deck['key'] ) );
			self::assertStringContainsString( 'incl' === $shown ? '77.00' : '70.00', $cell, 'the price struck through is shown as the cart shows prices' );
			self::assertStringContainsString( 'incl' === $shown ? '69.30' : '63.00', $cell );
		}
	}
}
