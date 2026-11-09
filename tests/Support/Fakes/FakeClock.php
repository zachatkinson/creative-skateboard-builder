<?php
/**
 * A clock the tests set.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Starts at a fixed moment and moves only when told to.
 */
class FakeClock implements Clock {

	/**
	 * Constructor.
	 *
	 * @param int $timestamp The starting moment, UTC.
	 */
	public function __construct( private int $timestamp = 1_800_000_000 ) {}

	/**
	 * The current moment.
	 */
	public function now(): DateTimeImmutable {
		return ( new DateTimeImmutable( '@' . $this->timestamp ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Move forward.
	 *
	 * @param int $seconds Seconds.
	 */
	public function advance( int $seconds ): void {
		$this->timestamp += $seconds;
	}

	/**
	 * The current timestamp.
	 */
	public function timestamp(): int {
		return $this->timestamp;
	}
}
