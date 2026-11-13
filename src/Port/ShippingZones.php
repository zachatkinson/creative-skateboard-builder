<?php
/**
 * The shop's shipping zones, for the shopper's address.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the shop charges to ship to the address it holds for the shopper (D-166).
 *
 * @since 0.6.0
 */
interface ShippingZones {

	/**
	 * The rates of the zone that matches the shopper's shipping address, or null where the shop holds no address for
	 * the shopper: a guest who has not given one is not guessed at.
	 *
	 * @since 0.6.0
	 */
	public function rates(): ?ShippingRates;
}
