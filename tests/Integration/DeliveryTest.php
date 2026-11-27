<?php
/**
 * What a shop says of a build's delivery.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\Delivery;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;

/**
 * Spec, The builder, The total: the arrival date and the shipping words each come from a filter the shop answers.
 */
final class DeliveryTest extends CartTestCase {

	/**
	 * Zones made by a test, deleted after it.
	 *
	 * @var list<\WC_Shipping_Zone>
	 */
	private array $zones = array();

	public function tear_down(): void {
		remove_all_filters( 'cbuild_arrival_estimate' );
		remove_all_filters( 'cbuild_delivery_line' );
		foreach ( $this->zones as $zone ) {
			$zone->delete();
		}
		WC()->customer->set_calculated_shipping( false );
		WC()->customer->set_shipping_postcode( '' );
		WC()->customer->set_shipping_city( '' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		parent::tear_down();
	}

	/**
	 * A zone for a province, with its methods and their settings.
	 *
	 * @param string                               $state   The province's code in Canada.
	 * @param array<string, array<string, string>> $methods Each method's type and its settings.
	 */
	private function zone( string $state, array $methods ): void {
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( 'Zone ' . $state );
		$zone->add_location( 'CA:' . $state, 'state' );
		$zone->save();
		foreach ( $methods as $type => $settings ) {
			$id = $zone->add_shipping_method( $type );
			update_option( 'woocommerce_' . $type . '_' . $id . '_settings', $settings + array( 'enabled' => 'yes' ) );
		}
		$this->zones[] = $zone;
	}

	/**
	 * The shopper has worked out shipping to a province, as the cart's calculator does.
	 *
	 * @param string $state The province's code in Canada.
	 */
	private static function shipsTo( string $state ): void {
		WC()->customer->set_shipping_country( 'CA' );
		WC()->customer->set_shipping_state( $state );
		WC()->customer->set_calculated_shipping( true );
	}

	private function line( int $total ): string {
		return $this->service( Delivery::class )->said( $this->kit, true, $total, 2 )['line'];
	}

	/**
	 * Spec: The builder, The total. "With no answer, the builder words the line itself from WooCommerce's shipping
	 * zones" (D-166).
	 */
	public function test_with_nobody_answering_the_line_is_worded_from_the_shoppers_zone(): void {
		$this->zone(
			'ON',
			array(
				'flat_rate'     => array(
					'cost'       => '12',
					'tax_status' => 'none',
				),
				'free_shipping' => array(
					'requires'   => 'min_amount',
					'min_amount' => '200',
				),
			)
		);
		$this->zone( 'AB', array( 'flat_rate' => array( 'cost' => '15.50', 'tax_status' => 'none' ) ) );

		self::shipsTo( 'ON' );
		self::assertSame( 'Spend $18.00 more for free shipping to Ontario.', $this->line( 18200 ) );
		self::assertSame( 'Free shipping to Ontario.', $this->line( 20000 ) );
		self::shipsTo( 'AB' );
		self::assertSame( 'Shipping to Alberta: $15.50.', $this->line( 18200 ) );

		add_filter( 'cbuild_delivery_line', static fn(): string => 'Ships from our shop in two days.' );
		self::assertSame( 'Ships from our shop in two days.', $this->line( 18200 ), 'a plugin\'s answer is the line' );
	}

	public function test_a_rate_the_shopper_would_not_pay_as_set_is_not_said(): void {
		$this->zone( 'AB', array( 'flat_rate' => array( 'cost' => '10 + [qty]', 'tax_status' => 'none' ) ) );
		$this->zone( 'QC', array( 'flat_rate' => array( 'cost' => '12', 'tax_status' => 'taxable' ) ) );
		$this->zone(
			'MB',
			array(
				'free_shipping' => array(
					'requires'   => 'coupon',
					'min_amount' => '0',
				),
			)
		);
		self::shipsTo( 'AB' );
		self::assertSame( '', $this->line( 18200 ), 'a cost from a formula' );
		self::shipsTo( 'MB' );
		self::assertSame( '', $this->line( 18200 ), 'free shipping that asks for a coupon' );
		self::shipsTo( 'QC' );
		self::assertSame( 'Shipping to Quebec: $12.00.', $this->line( 18200 ), 'taxable, shown without tax: as set' );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_display_cart', 'incl' );
		self::assertSame( '', $this->line( 18200 ), 'taxable, shown with tax: not what is paid' );
		update_option( 'woocommerce_calc_taxes', 'no' );
	}

	public function test_a_shopper_with_no_address_held_is_not_guessed_at_and_a_guest_is_given_no_session(): void {
		$this->zone( 'ON', array( 'flat_rate' => array( 'cost' => '12', 'tax_status' => 'none' ) ) );
		WC()->customer->set_shipping_country( 'CA' );
		WC()->customer->set_shipping_state( 'ON' );
		self::assertSame( '', $this->line( 18200 ), 'the shop\'s default, not the shopper\'s' );

		$customer      = WC()->customer;
		$session       = WC()->session;
		WC()->customer = null;
		try {
			self::assertSame( '', $this->line( 18200 ) );
			self::assertNull( WC()->customer, 'no customer made for a guest with no session' );
		} finally {
			WC()->customer = $customer;
			WC()->session  = $session;
		}
	}

	public function test_an_address_typed_through_the_store_api_is_held_as_the_classic_calculator_s_is(): void {
		// Cowork's review, should-fix 6: the Cart block's calculator, the Checkout block and the Mini-Cart send the
		// address through `cart/update-customer`, which never marks shipping as worked out, so a guest on a shop
		// with the Checkout block never saw the line. WooCommerce's default fills the country and state alone
		// (WC_Customer_Data_Store_Session::set_defaults()), so a postcode or a city is the shopper's own.
		$this->zone( 'ON', array( 'free_shipping' => array( 'requires' => '' ) ) );
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/cart/update-customer' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'shipping_address' => array(
						'country'  => 'CA',
						'state'    => 'ON',
						'postcode' => 'M5V 2T6',
						'city'     => 'Toronto',
					),
				)
			)
		);
		self::assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
		self::assertFalse( WC()->customer->has_calculated_shipping(), 'as WooCommerce leaves it' );
		self::assertSame( 'Free shipping to Ontario.', $this->line( 18200 ) );
	}

	public function test_with_nobody_answering_and_no_address_there_is_nothing_to_show(): void {
		self::assertSame(
			array(
				'arrival' => '',
				'line'    => '',
			),
			$this->service( Delivery::class )->said( $this->kit, true, 19800, 2 )
		);
	}

	public function test_each_filter_is_given_the_build_and_its_answer_is_shown_as_plain_text(): void {
		$kit = wc_get_product( $this->kit );
		$kit->setDocument(
			Options::META_KIT_ASSEMBLY,
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '15',
				'lead_time'      => 'Two business days',
				'returns_notice' => '',
			)
		);
		$kit->save();
		$given = array();
		add_filter(
			'cbuild_arrival_estimate',
			static function ( string $estimate, array $context ) use ( &$given ): string {
				$given[] = array( $estimate, $context );
				return ' Arrives by <strong>Thu, Nov 19</strong>, assembled ';
			},
			10,
			2
		);
		add_filter(
			'cbuild_delivery_line',
			static function ( string $line, array $context ) use ( &$given ): string {
				$given[] = array( $line, $context );
				return 'Spend $41.00 more for free shipping to Ontario.';
			},
			10,
			2
		);

		$context = array(
			'kit'        => $this->kit,
			'assembly'   => true,
			'lead_time'  => 'Two business days',
			'total'      => 19800,
			'decimals'   => 2,
			// From 1.2.0 (#142): the kit has no assembly time, and no lines were given.
			'lead_days'  => '',
			'parts'      => array(),
			'quantities' => array(),
			// From 1.4.0 (#168): no Edit build, so nothing replaced.
			'replace'    => '',
		);
		self::assertSame(
			array(
				'arrival' => 'Arrives by Thu, Nov 19, assembled',
				'line'    => 'Spend $41.00 more for free shipping to Ontario.',
			),
			$this->service( Delivery::class )->said( $this->kit, true, 19800, 2 )
		);
		self::assertSame( array( array( '', $context ), array( '', $context ) ), $given );

		$given = array();
		$this->service( Delivery::class )->said( $this->kit, false, 18300, 2 );
		self::assertSame( '', $given[0][1]['lead_time'], 'a build the shop does not assemble has no lead time' );
		self::assertFalse( $given[0][1]['assembly'] );
		self::assertSame( 18300, $given[1][1]['total'] );
	}

	// #142, with Cowork's comment: the state route gives both filters the kit's assembly time in business days for an
	// assembled build, and the parts its total counts, each with how many one build takes, so a plugin can give no
	// date while a part is on backorder. The trucks are sold singly and a build takes two; the risers are left empty.
	public function test_both_filters_are_given_the_assembly_time_and_each_part_with_its_quantity_by_the_state_route(): void {
		$kit = wc_get_product( $this->kit );
		$kit->setDocument(
			Options::META_KIT_SETTINGS,
			array(
				'schema_version' => 1,
				'lead_days'      => '2-4',
			)
		);
		$kit->save();
		$given = array();
		foreach ( array( 'cbuild_arrival_estimate', 'cbuild_delivery_line' ) as $filter ) {
			add_filter(
				$filter,
				static function ( string $said, array $context ) use ( &$given, $filter ): string {
					$given[ $filter ] = $context;
					return '';
				},
				10,
				2
			);
		}
		$ask = function ( bool $assembly ): void {
			$request = new \WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
			$request->set_query_params(
				array(
					'picks'    => $this->picks(),
					'assembly' => $assembly ? '1' : '0',
				)
			);
			self::assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
		};

		$ask( true );
		self::assertSame( $given['cbuild_arrival_estimate'], $given['cbuild_delivery_line'], 'the same for both' );
		$context = $given['cbuild_delivery_line'];
		self::assertSame( array( 'kit', 'assembly', 'lead_time', 'total', 'decimals', 'lead_days', 'parts', 'quantities', 'replace' ), array_keys( $context ), '1.1.0\'s keys first, as they were, then 1.2.0\'s and 1.4.0\'s' );
		self::assertTrue( $context['assembly'] );
		self::assertSame( '2-4', $context['lead_days'] );
		self::assertSame( $this->picks(), $context['parts'] );
		self::assertArrayNotHasKey( 'risers', $context['parts'], 'a slot left empty' );
		self::assertArrayNotHasKey( 'risers', $context['quantities'], 'is in neither' );
		self::assertSame( array_keys( $this->picks() ), array_keys( $context['quantities'] ) );
		self::assertSame( 2, $context['quantities']['trucks'], 'two trucks, sold singly' );
		self::assertSame( 1, $context['quantities']['deck'] );

		$ask( false );
		self::assertFalse( $given['cbuild_delivery_line']['assembly'] );
		self::assertSame( '', $given['cbuild_delivery_line']['lead_days'], 'a build the shop does not assemble has none' );
		self::assertSame( '', $given['cbuild_arrival_estimate']['lead_days'] );
		self::assertSame( $this->picks(), $given['cbuild_delivery_line']['parts'], 'and the same parts' );

		$kit = wc_get_product( $this->kit );
		$kit->setDocument( Options::META_KIT_SETTINGS, array( 'schema_version' => 1 ) );
		$kit->save();
		$ask( true );
		self::assertSame( '', $given['cbuild_delivery_line']['lead_days'], 'a kit with no time set has none' );
	}

	// #168, Creative Checkout 1.7.1's ask: an Edit build's request names the build in the cart it would replace
	// (`_replace`), and both filters are given that build's kit line's cart key, so a plugin counting the cart leaves it
	// out. With no `_replace`, or one no line of this shopper's holds, the key is empty.
	public function test_both_filters_are_given_the_cart_key_of_the_kit_line_an_edit_build_would_replace(): void {
		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		[ $first, $second ] = self::builds();
		$given = array();
		foreach ( array( 'cbuild_arrival_estimate', 'cbuild_delivery_line' ) as $filter ) {
			add_filter(
				$filter,
				static function ( string $said, array $context ) use ( &$given, $filter ): string {
					$given[ $filter ] = $context;
					return '';
				},
				10,
				2
			);
		}
		$replacing = function ( ?string $replace ) use ( &$given ): string {
			$given = array();
			$link  = array_map( 'strval', $this->picks() ) + array( 'step' => 'review' );
			if ( null !== $replace ) {
				$link['_replace'] = $replace;
			}
			$request = new \WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
			$request->set_query_params( array( 'cbuild' => $link ) );
			self::assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
			self::assertSame( $given['cbuild_arrival_estimate']['replace'], $given['cbuild_delivery_line']['replace'], 'the same for both' );
			return $given['cbuild_delivery_line']['replace'];
		};

		self::assertSame( $second['key'], $replacing( $second['build']->id ) );
		self::assertSame( $first['key'], $replacing( $first['build']->id ), 'the line of the build named, not the last one' );
		self::assertSame( '', $replacing( null ), 'no build replaced' );
		self::assertSame( '', $replacing( 'ffffffff-ffff-4fff-8fff-ffffffffffff' ), 'a build no line holds' );
		// Hostile: a value in the identifiers' own shape that is no build of this shopper's says nothing. A part's line's
		// key, the kit line's own key, and a build that was in the cart once.
		self::assertSame( '', $replacing( $first['key'] ), 'a cart key is no build\'s identifier' );
		self::assertSame( '', $replacing( (string) WC()->cart->get_cart()[ $first['key'] ][ Options::CART_CHILDREN ][0] ), 'nor a part\'s line\'s' );
		$gone = $first['build']->id;
		WC()->cart->remove_cart_item( $first['key'] );
		self::assertSame( '', $replacing( $gone ), 'a build no longer in the cart' );

		// As the state route has it on a live site: WooCommerce loads no cart for a REST request (D-138), and the line
		// is read from the session's own copy.
		WC()->cart->set_session();
		$cart       = WC()->cart;
		WC()->cart = null;
		try {
			self::assertSame( $second['key'], $replacing( $second['build']->id ), 'read from the session' );
		} finally {
			WC()->cart = $cart;
		}

		// And another shopper, with a session of their own, is given nothing of this one's cart.
		$session       = WC()->session;
		WC()->cart     = null;
		WC()->session  = new \WC_Session_Handler();
		WC()->session->init();
		try {
			self::assertSame( '', $replacing( $second['build']->id ), 'another shopper\'s build' );
		} finally {
			WC()->cart    = $cart;
			WC()->session = $session;
		}
	}

	public function test_an_answer_that_is_not_words_is_no_answer(): void {
		add_filter( 'cbuild_arrival_estimate', '__return_null' );
		add_filter( 'cbuild_delivery_line', static fn(): array => array( 'not words' ) );
		self::assertSame(
			array(
				'arrival' => '',
				'line'    => '',
			),
			$this->service( Delivery::class )->said( $this->kit, true, 19800, 2 )
		);
		self::assertSame( '', $this->service( Delivery::class )->said( 0, true, 0, 2 )['arrival'], 'a kit that does not read' );
	}
}
