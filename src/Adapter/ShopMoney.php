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
	 * How the shop writes a price, from WooCommerce's own settings and their filters (WooCommerce 11.3.0,
	 * includes/wc-core-functions.php:716, includes/wc-formatting-functions.php:522, 550, 560 and 571). The symbol is
	 * plain text: WooCommerce gives it as an HTML entity.
	 *
	 * @since 1.0.0
	 *
	 * @return array{code: string, symbol: string, decimals: int, decimal_separator: string, thousand_separator: string, format: string}
	 */
	public function settings(): array {
		$code = get_woocommerce_currency();
		return array(
			'code'               => $code,
			'symbol'             => html_entity_decode( get_woocommerce_currency_symbol( $code ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'decimals'           => max( 0, wc_get_price_decimals() ),
			'decimal_separator'  => wc_get_price_decimal_separator(),
			'thousand_separator' => wc_get_price_thousand_separator(),
			// Plain text as the symbol is: WooCommerce writes its space as an entity, for HTML (D-290).
			'format'             => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		);
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
