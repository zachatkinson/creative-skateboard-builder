<?php
/**
 * The log port.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where failures go: WooCommerce's log under the source `creative-skateboard-builder` in the shop
 * (Adapter\WooCommerceLogger), a list in tests. The levels are PSR-3's, which WooCommerce shares. Messages are
 * plain English for the log, never shown to a shopper, and carry no personal data (docs/PRIVACY.md).
 */
interface Logger {

	public const EMERGENCY = 'emergency';
	public const ALERT     = 'alert';
	public const CRITICAL  = 'critical';
	public const ERROR     = 'error';
	public const WARNING   = 'warning';
	public const NOTICE    = 'notice';
	public const INFO      = 'info';
	public const DEBUG     = 'debug';

	/**
	 * Write one line.
	 *
	 * @param string               $level   One of the level constants.
	 * @param string               $message The line.
	 * @param array<string, mixed> $context Values worth keeping beside it.
	 */
	public function log( string $level, string $message, array $context = array() ): void;
}
