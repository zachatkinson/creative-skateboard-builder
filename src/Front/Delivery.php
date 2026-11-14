<?php
/**
 * What a shop says of a build's delivery.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\ShippingZones;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asks the shop for the line under a build's total (spec: The builder, The total): when it arrives, and what its
 * shipping costs or how far it is from free shipping. The plugin knows neither a shop's cut-off and transit days
 * nor its free-shipping meter, so each is a filter another plugin answers, and an empty answer is no answer. The
 * builder's summary shows them from milestone 6, and words the shipping itself where nobody answers.
 *
 * @since 0.5.0
 */
final class Delivery {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @since 0.6.0 Takes the shop's shipping zones, and their words, for the line nobody answers.
	 *
	 * @param Kits          $kits  The shop's kits, for the lead time.
	 * @param ShippingZones $zones What the shop charges to ship to the shopper's address.
	 * @param ShippingWords $words The line, worded from those.
	 */
	public function __construct( private Kits $kits, private ShippingZones $zones, private ShippingWords $words ) {}

	/**
	 * What the shop says of a build's delivery, as plain text.
	 *
	 * @since 0.5.0
	 *
	 * @param int  $kit      The kit product's ID.
	 * @param bool $assembly Whether the shop assembles the build.
	 * @param int  $total    The build's total, in whole minor units.
	 * @param int  $decimals How many decimals the minor unit is.
	 * @return array{arrival: string, line: string} Each empty where nobody answered.
	 */
	public function said( int $kit, bool $assembly, int $total, int $decimals ): array {
		$lead    = $assembly ? ( $this->kits->kit( $kit )?->assembly->leadTime ?? '' ) : '';
		$context = array(
			'kit'       => $kit,
			'assembly'  => $assembly,
			'lead_time' => '' === $lead ? '' : StateAnswer::text( $lead, 'lead_time', $kit ),
			'total'     => $total,
			'decimals'  => $decimals,
		);
		/**
		 * When a build arrives, in words a shopper reads under the total, such as "Arrives by Thu, Nov 19,
		 * assembled". The plugin has no answer of its own.
		 *
		 * @since 0.5.0
		 *
		 * @param string                                                                          $estimate The words so far: empty.
		 * @param array{kit: int, assembly: bool, lead_time: string, total: int, decimals: int} $context  The kit product's ID; whether the shop assembles the build; the kit's lead time in the owner's words, empty for a build that is not assembled; and the build's total in whole minor units, with how many decimals that is.
		 */
		$arrival = apply_filters( 'cbuild_arrival_estimate', '', $context );
		/**
		 * What a build's shipping costs, or how far it is from free shipping, in words a shopper reads under the
		 * total. With no answer the builder words the line from WooCommerce's shipping zones.
		 *
		 * @since 0.5.0
		 *
		 * @param string                                                                          $line    The words so far: empty.
		 * @param array{kit: int, assembly: bool, lead_time: string, total: int, decimals: int} $context As `cbuild_arrival_estimate` is given.
		 */
		$line = apply_filters( 'cbuild_delivery_line', '', $context );
		$line = is_string( $line ) ? trim( wp_strip_all_tags( $line ) ) : '';
		return array(
			'arrival' => is_string( $arrival ) ? trim( wp_strip_all_tags( $arrival ) ) : '',
			// With no answer, the builder words it from the shop's shipping zones (D-166).
			'line'    => '' === $line ? $this->words->line( $this->zones->rates(), $total ) : $line,
		);
	}
}
