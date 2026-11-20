<?php
/**
 * Money as the shop writes it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An amount in the shop's currency, written the way the shop writes its prices.
 *
 * @since 0.4.0
 */
interface Money {

	/**
	 * An amount as plain text: "$1,234.50". Whatever prints it escapes it.
	 *
	 * @since 0.4.0
	 *
	 * @param int $amount   The amount, in whole minor units.
	 * @param int $decimals How many decimals the minor unit is.
	 */
	public function text( int $amount, int $decimals ): string;

	/**
	 * The currency prices are shown in now, as its ISO 4217 code: the shop's, or the one a currency switcher chose.
	 *
	 * @since 1.0.0
	 */
	public function currency(): string;

	/**
	 * How the shop writes a price: the currency's code and symbol, the decimals, the two separators and the pattern
	 * that puts the symbol beside the amount (`%1$s` the symbol, `%2$s` the amount), for a page that formats a total
	 * itself.
	 *
	 * @since 1.0.0
	 *
	 * @return array{code: string, symbol: string, decimals: int, decimal_separator: string, thousand_separator: string, format: string}
	 */
	public function settings(): array;
}
