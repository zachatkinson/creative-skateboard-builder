<?php
/**
 * The delivery line, worded from the shop's shipping zones.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\ShippingRates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Words the line under the total where no plugin answers `cbuild_delivery_line` (spec: The builder, The total; D-166):
 * how far the build is from the zone's free shipping, that it ships free, or the zone's flat rate. Nothing where no
 * address is held or the zone has nothing the builder can read.
 *
 * @since 0.6.0
 */
final class ShippingWords {

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param Money $money Amounts as the shop writes them.
	 */
	public function __construct( private Money $money ) {}

	/**
	 * The line, as plain text, or an empty string.
	 *
	 * @since 0.6.0
	 *
	 * @param ShippingRates|null $rates What the shopper's zone charges, or null where no address is held.
	 * @param int                $total The build's total, in whole minor units.
	 */
	public function line( ?ShippingRates $rates, int $total ): string {
		if ( null === $rates || '' === $rates->place ) {
			return '';
		}
		if ( $rates->free || 0 === $rates->flat || ( null !== $rates->freeFrom && $total >= $rates->freeFrom ) ) {
			/* translators: %s: the province, state or country the build ships to. */
			return sprintf( __( 'Free shipping to %s.', 'creative-skateboard-builder' ), $rates->place );
		}
		if ( null !== $rates->freeFrom ) {
			return sprintf(
				/* translators: 1: how much more the order needs, such as $18.00; 2: the province, state or country. */
				__( 'Spend %1$s more for free shipping to %2$s.', 'creative-skateboard-builder' ),
				$this->money->text( $rates->freeFrom - $total, $rates->decimals ),
				$rates->place
			);
		}
		if ( null !== $rates->flat ) {
			return sprintf(
				/* translators: 1: the province, state or country; 2: what shipping costs, such as $12.00. */
				__( 'Shipping to %1$s: %2$s.', 'creative-skateboard-builder' ),
				$rates->place,
				$this->money->text( $rates->flat, $rates->decimals )
			);
		}
		return '';
	}
}
