<?php
/**
 * The live clock.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The system time, in UTC. The only place the plugin reads the time (tools/gates.sh, gate 3).
 */
final class SystemClock implements Clock {

	/**
	 * The current moment, in UTC.
	 *
	 * @since 0.1.0
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
