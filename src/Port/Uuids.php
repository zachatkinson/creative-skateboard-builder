<?php
/**
 * New identifiers.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A build in the cart is told from another of the same parts by an identifier of its own (spec: Data model).
 *
 * @since 0.5.0
 */
interface Uuids {

	/**
	 * A new version 4 UUID.
	 *
	 * @since 0.5.0
	 */
	public function next(): string;
}
