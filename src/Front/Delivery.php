<?php
/**
 * What a shop says of a build's delivery.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Line;
use CreativeBranding\SkateboardBuilder\Port\Cart;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\ShippingZones;
use CreativeBranding\SkateboardBuilder\Service\Build;

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
	 * @since 1.4.0 Takes the cart, for the line an Edit build would replace (#168).
	 *
	 * @param Kits                   $kits  The shop's kits, for the lead time and the assembly time.
	 * @param ShippingZones          $zones What the shop charges to ship to the shopper's address.
	 * @param ShippingWords          $words The line, worded from those.
	 * @param (Closure(): Cart)|null $cart  The shopper's cart, asked only for a build that replaces one in it. None
	 *                                      in a copy an earlier release's container built, which says no line.
	 */
	public function __construct( private Kits $kits, private ShippingZones $zones, private ShippingWords $words, private ?Closure $cart = null ) {}

	/**
	 * What the shop says of a build's delivery, as plain text.
	 *
	 * @since 0.5.0
	 *
	 * @since 1.2.0 Takes the lines the total counts, for the parts and their quantities in the filters' context (#142).
	 *
	 * @since 1.4.0 Takes the build an Edit build replaces, for the filters' context (#168).
	 *
	 * @param int        $kit      The kit product's ID.
	 * @param bool       $assembly Whether the shop assembles the build.
	 * @param int        $total    The build's total, in whole minor units.
	 * @param int        $decimals How many decimals the minor unit is.
	 * @param list<Line> $lines    The parts the total counts, a line each.
	 * @param string     $replace  The identifier of the build in the cart this one would replace (`_replace`), or empty.
	 * @return array{arrival: string, line: string} Each empty where nobody answered.
	 */
	public function said( int $kit, bool $assembly, int $total, int $decimals, array $lines = array(), string $replace = '' ): array {
		$read       = $assembly ? $this->kits->kit( $kit ) : null;
		$lead       = $read?->assembly->leadTime ?? '';
		$parts      = array();
		$quantities = array();
		foreach ( $lines as $line ) {
			$parts[ $line->slot ]      = $line->part;
			$quantities[ $line->slot ] = $line->quantity;
		}
		// From 1.2.0 the keys after 1.1.0's are added at the end, and no earlier key changes (#142).
		$context = array(
			'kit'        => $kit,
			'assembly'   => $assembly,
			'lead_time'  => '' === $lead ? '' : StateAnswer::text( $lead, 'lead_time', $kit ),
			'total'      => $total,
			'decimals'   => $decimals,
			'lead_days'  => $read?->settings->leadDays?->stored() ?? '',
			'parts'      => $parts,
			'quantities' => $quantities,
			// From 1.4.0 (#168): the cart key of the kit's line an Edit build would replace, so a plugin counting the
			// cart leaves that build out rather than counting it beside this one.
			'replace'    => $this->replaced( $replace ),
		);
		/**
		 * When a build arrives, in words a shopper reads under the total, such as "Arrives by Thu, Nov 19,
		 * assembled". The plugin has no answer of its own.
		 *
		 * @since 0.5.0
		 * @since 1.2.0 `$context` also has `lead_days`, `parts` and `quantities` (#142).
		 *
		 * @param string                                                                                                                                                      $estimate The words so far: empty.
		 * @param array{kit: int, assembly: bool, lead_time: string, total: int, decimals: int, lead_days: string, parts: array<string, int>, quantities: array<string, int>, replace: string} $context  The kit product's ID; whether the shop assembles the build; the kit's lead time in the owner's words, empty for a build that is not assembled; the build's total in whole minor units, with how many decimals that is; the kit's assembly time in business days, "2" or "2-4", empty for a build that is not assembled or a kit with none; the parts the total counts, slot key to part ID; and how many of each one build takes, by the same keys. A slot left empty is in neither. And the cart key of the kit's line of the build this one would replace, from Edit build, or empty.
		 */
		$arrival = apply_filters( 'cbuild_arrival_estimate', '', $context );
		/**
		 * What a build's shipping costs, or how far it is from free shipping, in words a shopper reads under the
		 * total. With no answer the builder words the line from WooCommerce's shipping zones.
		 *
		 * @since 0.5.0
		 * @since 1.2.0 `$context` also has `lead_days`, `parts` and `quantities` (#142).
		 *
		 * @param string                                                                                                                                                      $line    The words so far: empty.
		 * @param array{kit: int, assembly: bool, lead_time: string, total: int, decimals: int, lead_days: string, parts: array<string, int>, quantities: array<string, int>, replace: string} $context As `cbuild_arrival_estimate` is given.
		 */
		$line = apply_filters( 'cbuild_delivery_line', '', $context );
		$line = is_string( $line ) ? trim( wp_strip_all_tags( $line ) ) : '';
		return array(
			'arrival' => is_string( $arrival ) ? trim( wp_strip_all_tags( $arrival ) ) : '',
			// With no answer, the builder words it from the shop's shipping zones (D-166).
			'line'    => '' === $line ? $this->words->line( $this->zones->rates(), $total ) : $line,
		);
	}

	/**
	 * The cart key of the kit's line of the build in the shopper's cart with this identifier, or empty: no build is
	 * replaced, the request has no cart, or no line of this shopper's holds it. A key is the shopper's own, and only
	 * a plugin's filter is given it.
	 *
	 * @param string $id The identifier `_replace` names, or empty.
	 */
	private function replaced( string $id ): string {
		if ( '' === $id || null === $this->cart ) {
			return '';
		}
		foreach ( ( $this->cart )()->held() as $key => $line ) {
			if ( Build::read( $line->build )?->id === $id ) {
				return $key;
			}
		}
		return '';
	}
}
