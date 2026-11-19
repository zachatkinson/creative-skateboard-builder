<?php
/**
 * What parts' looks are read from.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, for a step's parts, what groups them into looks and orders them (D-240, D-243). Read from the products
 * themselves, kept with each part like its card, so the parts index needs no new column.
 *
 * @since 1.0.0
 */
interface PartLooks {

	/**
	 * The facts of these parts, by ID, in the order asked. An ID that is no product or variation has none.
	 *
	 * @since 1.0.0
	 *
	 * @param list<int> $ids Parts' IDs.
	 * @return array<int, LookFact>
	 */
	public function looks( array $ids ): array;
}
