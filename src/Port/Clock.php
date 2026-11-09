<?php
/**
 * The clock port.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every time the plugin stores or compares comes from here, in UTC: never `date()`, `time()` or MySQL's `NOW()`
 * (CLAUDE.md: Time). Adapter\SystemClock is the live one; tests pass a frozen one.
 */
interface Clock {

	/**
	 * The current moment, in UTC.
	 *
	 * @since 0.1.0
	 */
	public function now(): DateTimeImmutable;
}
