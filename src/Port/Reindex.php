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

	/**
	 * Queue the index job of every part the picture draws whose layer image has not one resized copy, each job making
	 * its part's copies as a save's does. A job already waiting is not queued twice (D-214).
	 *
	 * @since 0.8.0
	 *
	 * @return int How many parts' jobs were asked for.
	 */
	public function requestRenditions(): int;
}
