<?php
/**
 * Money, through WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Money;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes an amount with `wc_price()`, so the symbol, its side, the separators and the decimals are the shop's own,
 * and takes the markup off: the state route's answer is data, and the page that prints it escapes it.
 *
 * @since 0.4.0
 */
final class ShopMoney implements Money {

	/**
	 * An amount as plain text.
	 *
	 * @since 0.4.0
	 *
	 * @param int $amount   The amount, in whole minor units.
	 * @param int $decimals How many decimals the minor unit is.
	 */
	public function text( int $amount, int $decimals ): string {
		return self::plain( wc_price( $amount / 10 ** $decimals ) );
	}

	/**
	 * The currency prices are shown in now: WooCommerce's, after its `woocommerce_currency` filter, which a currency
	 * switcher answers.
	 *
	 * @since 1.0.0
	 */
	public function currency(): string {
		return get_woocommerce_currency();
	}

	/**
	 * A price WooCommerce wrote, as plain text.
	 *
	 * @since 0.4.0
	 *
	 * @param string $price What `wc_price()` gave.
	 */
	public static function plain( string $price ): string {
		return html_entity_decode( wp_strip_all_tags( $price ), ENT_QUOTES, 'UTF-8' );
	}
}
