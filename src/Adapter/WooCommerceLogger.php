<?php
/**
 * WooCommerce's log.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes through `wc_get_logger()` under the source `creative-skateboard-builder` (docs/STANDARDS.md: Prices, stock
 * and logs), so the lines show on WooCommerce > Status > Logs. Without WooCommerce there is nowhere to write, and
 * nothing is.
 */
final class WooCommerceLogger implements Logger {

	/**
	 * Write one line.
	 *
	 * @param string               $level   One of the level constants.
	 * @param string               $message The line.
	 * @param array<string, mixed> $context Values worth keeping beside it.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$context['source'] = Options::LOG_SOURCE;
		wc_get_logger()->log( $level, $message, $context );
	}
}
