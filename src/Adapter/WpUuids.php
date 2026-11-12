<?php
/**
 * New identifiers, from WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Uuids;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress's own version 4 UUIDs.
 *
 * @since 0.5.0
 */
final class WpUuids implements Uuids {

	/**
	 * A new version 4 UUID.
	 *
	 * @since 0.5.0
	 */
	public function next(): string {
		return wp_generate_uuid4();
	}
}
