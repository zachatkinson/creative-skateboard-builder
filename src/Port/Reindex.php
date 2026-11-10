<?php
/**
 * Port: asking for every part to be read again.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the installer may ask of the index without knowing how it is built: after an install, an upgrade or an
 * activation, the index holds nothing, or what the parts held before the plugin was switched off.
 *
 * @since 0.3.0
 */
interface Reindex {

	/**
	 * Ask for everything to be indexed again, from the start. Asked twice, it starts once.
	 *
	 * @since 0.3.0
	 */
	public function requestReindex(): void;
}
