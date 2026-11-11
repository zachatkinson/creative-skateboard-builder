<?php
/**
 * A build's shipping, and no express pay on a kit.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Product_Simple;
use WC_Shipping_Zone;

/**
 * Spec, Pricing, stock, assembly and returns, Pricing ("at most one line is $0") and Shipping; Compatibility,
 * Express payment buttons. Roadmap, milestone 5: a $0 parent through the shop's own shipping methods, which in CI
 * are WooCommerce's three.
 *
 * The build's parts cost 183 in all, and the kit's line nothing without assembly.
 */
final class CartShippingTest extends CartTestCase {

	/**
	 * The three methods' rate IDs, by method.
	 *
	 * @var array<string, string>
	 */
	private array $rates = array();

	public function set_up(): void {
		parent::set_up();
		$zone = new WC_Shipping_Zone( 0 );
		foreach ( array( 'flat_rate', 'free_shipping', 'local_pickup' ) as $method ) {
			$instance               = $zone->add_shipping_method( $method );
			$this->rates[ $method ] = $method . ':' . $instance;
		}
		update_option(
			'woocommerce_flat_rate_' . explode( ':', $this->rates['flat_rate'] )[1] . '_settings',
			array(
				'title'      => 'Flat rate',
				'tax_status' => 'none',
				'cost'       => '10',
			)
		);
		WC()->customer->set_shipping_country( 'US' );
		WC()->customer->set_shipping_state( 'CA' );
		WC()->customer->set_shipping_postcode( '90210' );
		WC()->customer->set_calculated_shipping( true );
	}

	public function tear_down(): void {
		$zone = new WC_Shipping_Zone( 0 );
		foreach ( $this->rates as $rate ) {
			$zone->delete_shipping_method( (int) explode( ':', $rate )[1] );
		}
		WC()->session->set( 'chosen_shipping_methods', null );
		WC()->shipping()->reset_shipping();
		remove_all_filters( 'cbuild_build_in_one_package' );
		parent::tear_down();
	}

	/**
	 * Add the usual build.
	 *
	 * @param bool $assembly Whether the shop assembles it.
	 */
	private function added( bool $assembly = false ): void {
		$response = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => 1,
				'cbuild'   => array(
					'picks'    => $this->picks(),
					'assembly' => $assembly,
				),
			)
		);
		self::assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * A shop's own code that ships each line in a package of its own.
	 */
	private function split(): void {
		add_filter(
			'woocommerce_cart_shipping_packages',
			static function ( array $packages ): array {
				$split = array();
				foreach ( $packages[0]['contents'] as $key => $line ) {
					$split[] = array(
						'contents'      => array( $key => $line ),
						'contents_cost' => $line['line_total'],
					) + $packages[0];
				}
				return $split;
			}
		);
	}

	public function test_a_kit_that_costs_nothing_goes_through_each_of_the_shops_methods(): void {
		$this->added();
		WC()->cart->calculate_totals();

		$packages = WC()->shipping()->get_packages();
		self::assertCount( 1, $packages );
		self::assertCount( 6, $packages[0]['contents'], 'the parts ship; the kit is virtual and is in no package' );
		foreach ( $packages[0]['contents'] as $line ) {
			self::assertArrayNotHasKey( Options::CART_BUILD, $line );
		}
		self::assertSame( 183.0, (float) $packages[0]['contents_cost'] );
		self::assertSame( array(), array_diff( array_values( $this->rates ), array_keys( $packages[0]['rates'] ) ), 'all three methods offer a rate' );

		foreach ( array(
			'flat_rate'     => '193.00',
			'free_shipping' => '183.00',
			'local_pickup'  => '183.00',
		) as $method => $total ) {
			WC()->session->set( 'chosen_shipping_methods', array( $this->rates[ $method ] ) );
			WC()->cart->calculate_totals();
			self::assertSame( $total, WC()->cart->get_total( 'edit' ), $method );
			self::assertSame( array(), self::errors(), $method );
		}

		$order = wc_get_order( WC()->checkout()->create_order( array( 'payment_method' => 'cheque' ) ) );
		self::assertSame( '183.00', $order->get_total() );
		self::assertCount( 1, $order->get_shipping_methods() );
		self::assertCount( 7, $order->get_items(), 'the kit\'s line is on the order at nothing' );
	}

	public function test_an_assembled_builds_parts_are_put_back_in_one_package_where_the_shop_splits_them(): void {
		$wax = new WC_Product_Simple();
		$wax->set_name( 'Wax' );
		$wax->set_status( 'publish' );
		$wax->set_regular_price( '3' );
		$wax->save();
		$this->added( true );
		WC()->cart->add_to_cart( $wax->get_id() );
		$this->split();
		WC()->cart->calculate_totals();

		$packages = WC()->cart->get_shipping_packages();
		self::assertCount( 2, $packages );
		self::assertCount( 6, $packages[0]['contents'] );
		self::assertSame( 183.0, (float) $packages[0]['contents_cost'] );
		self::assertSame( array( $wax->get_id() ), array_column( $packages[1]['contents'], 'product_id' ) );

		$asked = array();
		add_filter(
			'cbuild_build_in_one_package',
			static function ( bool $together, int $kit, bool $assembly ) use ( &$asked ): bool {
				$asked[] = array( $together, $kit, $assembly );
				return false;
			},
			10,
			3
		);
		self::assertCount( 7, WC()->cart->get_shipping_packages(), 'a shop that ships the parts apart says so' );
		self::assertSame( array( array( true, $this->kit, true ) ), $asked );
	}

	public function test_a_build_the_shop_does_not_assemble_ships_as_the_shop_splits_it_unless_it_says_otherwise(): void {
		$this->added( false );
		$this->split();
		WC()->cart->calculate_totals();
		self::assertCount( 6, WC()->cart->get_shipping_packages() );

		add_filter( 'cbuild_build_in_one_package', '__return_true' );
		self::assertCount( 1, WC()->cart->get_shipping_packages() );
	}

	public function test_no_express_button_is_offered_on_a_kit(): void {
		$kit  = wc_get_product( $this->kit );
		$part = wc_get_product( $this->parts['deck'] );
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the payment plugins' own filters, applied as each applies them.
		self::assertTrue( apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false, get_post( $this->kit ) ) );
		self::assertFalse( apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false, get_post( $this->parts['deck'] ) ) );
		self::assertTrue( apply_filters( 'wc_stripe_hide_payment_request_on_product_page', true, get_post( $this->parts['deck'] ) ), 'what another said of a part stands' );
		self::assertFalse( apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false, null ) );

		self::assertFalse( apply_filters( 'wcpay_payment_request_is_product_supported', true, $kit ) );
		self::assertTrue( apply_filters( 'wcpay_payment_request_is_product_supported', true, $part ) );
		self::assertFalse( apply_filters( 'wcpay_payment_request_is_product_supported', false, $part ) );
		self::assertTrue( apply_filters( 'wcpay_payment_request_is_product_supported', true, null ) );

		foreach ( array( 'wc_stripe_payment_request_supported_types', 'wcpay_payment_request_supported_types' ) as $filter ) {
			self::assertSame( array( 'simple', 'variable' ), apply_filters( $filter, array( 'simple', Options::PRODUCT_TYPE_KIT, 'variable' ) ), $filter );
			self::assertSame( 'not a list', apply_filters( $filter, 'not a list' ), $filter );
		}
		// phpcs:enable
	}
}
