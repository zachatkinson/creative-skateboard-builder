<?php
/**
 * The parts table.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What is kept of each part, a row to a part and a price context (D-116). Derived from the shop and never the
 * record of anything: a row that is missing is read from WooCommerce again.
 *
 * @since 0.4.0
 */
interface PartFacts {

	/**
	 * The rows of these products and of their variations, by ID, in one price context.
	 *
	 * @since 0.4.0
	 *
	 * @param string $context The price context.
	 * @param int    ...$ids  Products' and variations' IDs.
	 * @return array<int, PartFact>
	 */
	public function of( string $context, int ...$ids ): array;

	/**
	 * Make the table hold exactly these rows for these products and their variations, in one price context: what
	 * it held of them goes first, so a variation that is gone leaves no row behind.
	 *
	 * @since 0.4.0
	 *
	 * @param string         $context The price context.
	 * @param list<int>      $ids     The products and variations read.
	 * @param list<PartFact> $facts   What was read of them and of their variations.
	 */
	public function replace( string $context, array $ids, array $facts ): void;

	/**
	 * Forget these products in every price context: their rows, their variations' rows, and the rows of the
	 * parents of those that are variations, which answer for which variations there are.
	 *
	 * @since 0.4.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function forget( int ...$ids ): void;

	/**
	 * Forget every row.
	 *
	 * @since 0.4.0
	 */
	public function forgetAll(): void;
}
