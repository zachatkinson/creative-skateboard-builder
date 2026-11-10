<?php
/**
 * Where the release manifest comes from.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The latest release's manifest. The one remote call the plugin makes sits behind this, so tests never touch the
 * network.
 */
interface ManifestSource {

	/**
	 * The latest release, or null when it can't be read.
	 *
	 * @since 0.1.0
	 */
	public function latest(): ?Manifest;

	/**
	 * The latest release read again now, past any cache, or null when it can't be read.
	 *
	 * @since 0.1.0
	 */
	public function fresh(): ?Manifest;
}
