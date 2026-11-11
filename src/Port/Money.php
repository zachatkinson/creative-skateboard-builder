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
}
