<?php
/**
 * The parts the shop keeps out of the builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Not offered in the builder", ticked on a part's Part specs or on its product's (#116, D-351), read for the
 * Catalogue check.
 *
 * @since 1.2.0
 */
interface PartsKeptOut {

	/**
	 * The part's own box is ticked.
	 *
	 * @since 1.2.0
	 */
	public const OWN = 'part';

	/**
	 * A variation's product's box is ticked.
	 *
	 * @since 1.2.0
	 */
	public const PRODUCT = 'product';

	/**
	 * Which of these parts the shop keeps out, and by whose box, read together.
	 *
	 * @since 1.2.0
	 *
	 * @param int ...$parts Product or variation IDs.
	 * @return array<int, string> OWN or PRODUCT, by part, for the parts kept out.
	 */
	public function keptOut( int ...$parts ): array;
}
