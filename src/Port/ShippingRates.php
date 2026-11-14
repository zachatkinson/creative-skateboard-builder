<?php
/**
 * What a shop charges to ship to the shopper.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shipping zone that matches the address the shop holds for the shopper, as the builder's delivery line needs it
 * (D-166): the place's name, and the zone's free shipping and flat rate where the builder can read them as a shopper
 * would pay them.
 *
 * @since 0.6.0
 */
final readonly class ShippingRates {

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param string   $place    Where the shopper's shipping goes, in the shop's words: the province or state, else the country.
	 * @param bool     $free     Whether the zone ships free with nothing asked.
	 * @param int|null $freeFrom The least the zone's free shipping asks the order to come to, in whole minor units; null for none.
	 * @param int|null $flat     The zone's cheapest flat rate as a shopper pays it, in whole minor units; null for none the builder can read.
	 * @param int      $decimals How many decimals the minor unit is.
	 */
	public function __construct(
		public string $place,
		public bool $free,
		public ?int $freeFrom,
		public ?int $flat,
		public int $decimals,
	) {}
}
