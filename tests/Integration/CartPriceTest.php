<?php
/**
 * What a build costs in the cart.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WooPartCards;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Coupon;
use WC_Product_Simple;
use WC_Customer;
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

	/**
	 * The auditor's finding 9 on milestone 5: a cart read back from the session works its totals out again only
	 * when it changed, so on every page but the cart and the checkout the classic mini-cart showed each part at
	 * the shop's full price beside a subtotal that had the discount in it.
	 */
	public function test_a_cart_read_from_the_session_shows_each_part_at_what_it_is_charged(): void {
		self::settings( array( 'build_discount' => '10' ) );
		$key = $this->added();
		WC()->cart->calculate_totals();
		WC()->cart->set_session();
		$child = WC()->cart->get_cart()[ $key ][ Options::CART_CHILDREN ][0];

		// The next page: the cart is read from the session, and nothing in it has changed.
		$counted = did_action( 'woocommerce_before_calculate_totals' );
		WC()->cart->set_cart_contents( array() );
		WC()->cart->get_cart_from_session();
		self::assertSame( $counted, did_action( 'woocommerce_before_calculate_totals' ), 'the totals were not worked out again' );
		$deck = WC()->cart->get_cart()[ $child ];
		self::assertSame( '63.00', $deck['data']->get_price() );
		$cell = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $deck['data'] ), $deck, $child );
		self::assertMatchesRegularExpression( '#<del[^>]*>.*70\.00.*</del>.*<ins[^>]*>.*63\.00.*</ins>#s', $cell );
	}

	/**
	 * The auditor's finding 5 on milestone 5: a coupon for the whole cart that leaves a product out took that
	 * product all the same once it was a part of a build, since the coupon takes the kit.
	 */
	public function test_a_coupon_that_leaves_a_part_out_leaves_it_out_of_a_build_too(): void {
		$wheels = wc_get_product( $this->parts['wheels'] );
		$wheels->set_regular_price( '40' );
		$wheels->set_sale_price( '32' );
		$wheels->save();
		$this->added( true );
		$every = array(
			'kit'      => 1.5,
			'deck'     => 7.0,
			'trucks'   => 5.4,
			'wheels'   => 3.2,
			'bearings' => 1.5,
			'hardware' => 0.4,
			'grip'     => 0.8,
		);
		$decks = (int) current( wc_get_product( $this->parts['deck'] )->get_category_ids() );
		foreach ( array(
			'the deck by its ID'       => array( array( 'excluded_product_ids' => array( $this->parts['deck'] ) ), 'deck' ),
			'the deck by its category' => array( array( 'excluded_product_categories' => array( $decks ) ), 'deck' ),
			'what is on sale'          => array( array( 'exclude_sale_items' => true ), 'wheels' ),
		) as $what => [ $props, $left ] ) {
			self::coupon( 'percent', '10', $props );
			self::assertSame( array_diff_key( $every, array( $left => 0 ) ), self::discounts(), $what );
			WC()->cart->remove_coupons();
		}
	}

	/**
	 * Spec, Tests that must pass: each coupon type with the coupon setting both ways. Where a fixed product coupon
	 * lands changes nothing for the other types.
	 */
	public function test_a_percentage_and_a_fixed_cart_coupon_are_the_same_with_the_setting_either_way(): void {
		$this->added( true );
		$seen = array();
		foreach ( array( 'kit', 'parts' ) as $setting ) {
			self::settings( array( 'fixed_coupons' => $setting ) );
			self::coupon( 'percent', '10', array( 'product_ids' => array( $this->kit ) ) );
			$seen[ $setting ]['percent'] = self::discounts();
			WC()->cart->remove_coupons();
			self::coupon( 'fixed_cart', '26.80' );
			$seen[ $setting ]['fixed_cart']       = self::discounts();
			$seen[ $setting ]['fixed_cart_total'] = number_format( (float) WC()->cart->get_discount_total(), 2, '.', '' );
			WC()->cart->remove_coupons();
		}
		self::assertSame( $seen['kit'], $seen['parts'] );
		self::assertSame( 19.8, round( array_sum( $seen['kit']['percent'] ), 2 ), 'ten per cent of the fee and the parts' );
		self::assertSame( '26.80', $seen['kit']['fixed_cart_total'] );
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

	/**
	 * Issue #27: where tax depends on where the shopper is, the builder's total is the cart's.
	 */
	// Claude in Cowork's review of milestone 5, S2: the demo shows prices without tax and has 17 rates over 13
	// provinces. No price it shows depends on where the shopper is, so no province may have a context of its own:
	// each context is its own rows of the parts table and its own first read.
	public function test_where_no_shown_price_goes_by_address_every_shopper_shares_one_price_context(): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		$base   = WC()->countries->get_base_country();
		$abroad = 'FR' === $base ? 'DE' : 'FR';
		foreach ( array( $base => '10.0000', $abroad => '20.0000' ) as $country => $rate ) {
			WC_Tax::_insert_tax_rate(
				array(
					'tax_rate_country' => $country,
					'tax_rate'         => $rate,
					'tax_rate_name'    => 'Tax ' . $country,
					'tax_rate_class'   => '',
				)
			);
		}
		$own = WC()->customer;
		$in  = static function ( string $country ): string {
			$shopper = new WC_Customer( 0, true );
			$shopper->set_billing_country( $country );
			$shopper->set_shipping_country( $country );
			WC()->customer = $shopper;
			return WooPartCards::context();
		};
		$request = new WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
		$request->set_query_params( array( 'picks' => $this->picks() ) );
		try {
			self::assertSame( $in( $base ), $in( $abroad ), 'prices entered and shown without tax' );
			WC()->customer = null;
			self::assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
			self::assertNull( WC()->customer, 'and the route asks for no shopper it has no use for' );

			update_option( 'woocommerce_tax_display_shop', 'incl' );
			self::assertNotSame( $in( $base ), $in( $abroad ), 'shown with tax, a price goes by the rates where the shopper is' );

			// Entered with tax and shown without: WooCommerce takes the shop's own rates off, wherever the shopper
			// is, unless a shop's code says prices are not adjusted (includes/wc-product-functions.php).
			update_option( 'woocommerce_prices_include_tax', 'yes' );
			update_option( 'woocommerce_tax_display_shop', 'excl' );
			self::assertSame( $in( $base ), $in( $abroad ) );
			add_filter( 'woocommerce_adjust_non_base_location_prices', '__return_false' );
			self::assertNotSame( $in( $base ), $in( $abroad ) );
		} finally {
			remove_filter( 'woocommerce_adjust_non_base_location_prices', '__return_false' );
			WC()->customer = $own;
		}
	}

	// The audit's finding, milestone 5: WooCommerce's cart reads itself from the session when the request shuts
	// down (includes/class-wc-cart-session.php:95 and :400), before PHP sends the answer. A cart made for the
	// route would have every state request of a shopper with a build in the cart pay for reading that cart.
	public function test_the_route_finds_the_shopper_and_makes_no_cart_for_the_request(): void {
		// A shop whose shown prices go by where the shopper is: any other asks for no shopper at all (D-139).
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );
		$kept    = array( WC()->customer, WC()->cart, WC()->session );
		$request = new WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
		$request->set_query_params( array( 'picks' => $this->picks() ) );
		try {
			// As a request to the plugin's route starts (includes/class-woocommerce.php:347).
			WC()->customer = null;
			WC()->cart     = null;
			WC()->session  = null;
			self::assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
			self::assertInstanceOf( WC_Customer::class, WC()->customer, 'the shopper is known' );
			self::assertNull( WC()->cart, 'and no cart is made' );
			self::assertFalse( has_action( 'shutdown', array( WC()->customer, 'save' ) ), 'nor is the customer saved: the route changes nothing of them' );
		} finally {
			[ WC()->customer, WC()->cart, WC()->session ] = $kept;
		}
	}

	public function test_the_route_prices_for_the_shopper_the_cart_will_charge(): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );
		update_option( 'woocommerce_tax_display_cart', 'incl' );
		$base = WC()->countries->get_base_country();
		foreach ( array( $base => '10.0000', 'FR' === $base ? 'DE' : 'FR' => '20.0000' ) as $country => $rate ) {
			WC_Tax::_insert_tax_rate(
				array(
					'tax_rate_country' => $country,
					'tax_rate'         => $rate,
					'tax_rate_name'    => 'Tax ' . $country,
					'tax_rate_class'   => '',
				)
			);
		}
		$abroad  = 'FR' === $base ? 'DE' : 'FR';
		$shopper = self::factory()->user->create( array( 'user_email' => 'shopper+27@example.com' ) );
		update_user_meta( $shopper, 'billing_country', $abroad );
		update_user_meta( $shopper, 'shipping_country', $abroad );
		wp_set_current_user( $shopper );

		// A request to the plugin's own route is not the Store API's, and WooCommerce loads no customer for it
		// (includes/class-woocommerce.php:347). The process's own customer is put back whatever happens: the cart
		// and the session are left as they are, since every later test shares them.
		global $wpdb;
		$sessions = static fn(): int => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_sessions" ); // phpcs:ignore
		$kept     = $sessions();
		$own      = WC()->customer;
		$request  = new WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
		$request->set_query_params(
			array(
				'picks'    => $this->picks(),
				'assembly' => false,
			)
		);
		try {
			WC()->customer = null;
			$state         = rest_get_server()->dispatch( $request );
			self::assertSame( 200, $state->get_status() );
			self::assertSame( 21960, $state->get_data()['total']['total']['amount'], '183 and the 20 % of where the shopper is, not the 10 % of where the shop is' );

			// The price-build ability, for the same shopper (Zach's answer of 11 November 2026, D-137): what an
			// ability says of a build is what the route says of it.
			WC()->customer = null;
			$ability       = wp_get_ability( 'creative-skateboard-builder/price-build' );
			self::assertNotNull( $ability );
			$priced = $ability->execute(
				array(
					'kit'      => $this->kit,
					'picks'    => $this->picks(),
					'assembly' => false,
				)
			);
			self::assertIsArray( $priced, is_wp_error( $priced ) ? $priced->get_error_message() : '' );
			self::assertSame( 21960, $priced['total']['total']['amount'] );

			// The cart, for the same shopper.
			$this->added();
			WC()->cart->calculate_totals();
			self::assertSame( '219.60', number_format( (float) WC()->cart->get_total( 'edit' ), 2, '.', '' ), 'which is what the cart charges' );
			WC()->cart->empty_cart();

			// A visitor nobody knows is priced for the shop's own address, and nothing is kept of the asking.
			wp_set_current_user( 0 );
			WC()->customer = null;
			$guest         = rest_get_server()->dispatch( $request );
			self::assertSame( 20130, $guest->get_data()['total']['total']['amount'] );
			// What WooCommerce does as the request ends, done here.
			WC()->customer->save();
			WC()->session->save_data();
			self::assertSame( $kept, $sessions(), 'no session is stored for a visitor who only asked' );
		} finally {
			WC()->customer = $own;
		}
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
