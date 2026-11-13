<?php
/**
 * WooCommerce's shipping zones, for the shopper's address.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\ShippingRates;
use CreativeBranding\SkateboardBuilder\Port\ShippingZones;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WC_Customer;
use WC_Shipping_Flat_Rate;
use WC_Shipping_Free_Shipping;
use WC_Shipping_Zones;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the zone that matches the address WooCommerce holds for the shopper (D-166).
 *
 * - **An address held,** not a default: a signed-in customer's saved shipping country, or a guest's who has worked
 *   out shipping in the cart (`has_calculated_shipping()`). A guest with no session is not given one: no session, no
 *   customer, no geolocation for a line the shopper did not ask for.
 * - **Free shipping** that asks nothing, or a least amount (`min_amount`, alone or "either"). One that asks for a
 *   coupon is not said: the builder cannot know of one.
 * - **A flat rate** whose cost is a plain amount, as a shopper pays it: where prices are shown with tax and the rate
 *   is taxable, the amount set is not what is paid, so it is not said. A cost worked out from a formula is not said.
 *
 * @since 0.6.0
 */
final class WooShippingZones implements ShippingZones {

	/**
	 * The rates of the shopper's zone, or null where no address is held.
	 *
	 * @since 0.6.0
	 */
	public function rates(): ?ShippingRates {
		$customer = self::customer();
		if ( null === $customer ) {
			return null;
		}
		$country = $customer->get_shipping_country();
		$held    = $customer->has_calculated_shipping() || ( $customer->get_id() > 0 && '' !== $customer->get_shipping_country( 'edit' ) );
		if ( ! $held || '' === $country ) {
			return null;
		}
		$state    = $customer->get_shipping_state();
		$zone     = WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'   => $country,
					'state'     => $state,
					'postcode'  => $customer->get_shipping_postcode(),
					'city'      => $customer->get_shipping_city(),
					'address'   => '',
					'address_2' => '',
				),
			)
		);
		$decimals = wc_get_price_decimals();
		$minor    = static fn ( float $amount ): int => (int) round( $amount * 10 ** $decimals );
		$free     = false;
		$from     = null;
		$flat     = null;
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( $method instanceof WC_Shipping_Free_Shipping ) {
				$asks = Cast::str( $method->get_option( 'requires' ) );
				if ( '' === $asks ) {
					$free = true;
				} elseif ( in_array( $asks, array( 'min_amount', 'either' ), true ) && is_numeric( $method->get_option( 'min_amount' ) ) ) {
					$least = $minor( Cast::float( $method->get_option( 'min_amount' ) ) );
					$from  = null === $from ? $least : min( $from, $least );
				}
			} elseif ( $method instanceof WC_Shipping_Flat_Rate ) {
				// WooCommerce keeps a cost with a decimal point; anything but a number is a formula.
				$cost  = trim( Cast::str( $method->get_option( 'cost' ) ) );
				$taxed = 'taxable' === $method->get_option( 'tax_status' ) && 'incl' === get_option( 'woocommerce_tax_display_cart' ) && wc_tax_enabled();
				if ( is_numeric( $cost ) && ! $taxed ) {
					$flat = null === $flat ? $minor( (float) $cost ) : min( $flat, $minor( (float) $cost ) );
				}
			}
		}
		return new ShippingRates( self::place( $country, $state ), $free, $from, $flat, $decimals );
	}

	/**
	 * The shopper's customer: the request's, or the session's where the shopper has one.
	 */
	private static function customer(): ?WC_Customer {
		$customer = get_object_vars( WC() )['customer'] ?? null;
		if ( $customer instanceof WC_Customer ) {
			return $customer;
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, asked as its session handler asks it.
		$cookie = Cast::str( apply_filters( 'woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH ) );
		// Only whether the shopper has a session: WooCommerce reads and checks the cookie itself.
		if ( ! is_user_logged_in() && ! isset( $_COOKIE[ $cookie ] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- the state route is never cached (D-148).
			return null;
		}
		WC()->initialize_session();
		WC()->customer = new WC_Customer( get_current_user_id(), true );
		return WC()->customer;
	}

	/**
	 * The province or state the shop names, else the country.
	 *
	 * @param string $country The country's code.
	 * @param string $state   The state's code, if any.
	 */
	private static function place( string $country, string $state ): string {
		$states = WC()->countries->get_states( $country );
		$name   = is_array( $states ) && '' !== $state ? ( $states[ $state ] ?? '' ) : '';
		if ( '' === $name ) {
			$name = WC()->countries->get_countries()[ $country ] ?? '';
		}
		return html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' );
	}
}
