<?php
/**
 * The phases of a request, timed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use Closure;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How long each named phase of a request took, in milliseconds, read from the Clock as every time is: for the state
 * route's Server-Timing header to a shop manager (D-304, #125, D-365). A phase run more than once in the request adds
 * up; one not run is not there.
 *
 * @since 1.2.0
 */
final class Phases {

	/**
	 * Each phase's time so far, in the order each first ran.
	 *
	 * @var array<string, float>
	 */
	private array $took = array();

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Clock $clock The clock.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * Run a phase's work and add its time to the phase's.
	 *
	 * @since 1.2.0
	 *
	 * @template T
	 * @param string       $phase Its name, a lowercase token.
	 * @param Closure(): T $work  The work.
	 * @return T
	 */
	public function run( string $phase, Closure $work ): mixed {
		$start = $this->clock->now();
		try {
			return $work();
		} finally {
			$this->took[ $phase ] = ( $this->took[ $phase ] ?? 0.0 ) + self::between( $start, $this->clock->now() );
		}
	}

	/**
	 * Each phase's time, in milliseconds, in the order each first ran.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, float>
	 */
	public function took(): array {
		return $this->took;
	}

	/**
	 * Forget every phase: a new request starts.
	 *
	 * @since 1.2.0
	 */
	public function forget(): void {
		$this->took = array();
	}

	/**
	 * The milliseconds from one moment to another, to the microsecond.
	 *
	 * @since 1.2.0
	 *
	 * @param DateTimeImmutable $start The first.
	 * @param DateTimeImmutable $end   The second.
	 */
	public static function between( DateTimeImmutable $start, DateTimeImmutable $end ): float {
		// The seconds and microseconds as integers, read from each moment rather than cast from its text.
		return ( $end->getTimestamp() - $start->getTimestamp() ) * 1000 + ( $end->getMicrosecond() - $start->getMicrosecond() ) / 1000;
	}
}
