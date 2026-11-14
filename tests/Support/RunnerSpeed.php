<?php
/**
 * How fast the machine a benchmark runs on is, against the one its budgets are measured on.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

use Closure;

/**
 * D-171: on a shared runner a benchmark's budget is scaled by how much slower the runner is than the Mac the
 * figures in docs/BENCHMARK.md come from. The runner's speed is timed on a fixed piece of work of the route's kind
 * (PHP building, sorting and encoding arrays, and prepared queries of the database) before the benchmark times
 * anything. On the Mac, and wherever CBUILD_BENCHMARK_SHARED is not 1, the budget is held as it is.
 *
 * The scale never goes below 1, so a fast runner holds the budget itself, and never above MOST, so a runner slow
 * past that, or code slow past it, still fails.
 *
 * D-187: a shared runner can slow after it is timed, so on one the work is timed again each time a limit is asked
 * for, right after the series it holds, and the slowest timing so far is the runner's (CI run 43546483925).
 */
final class RunnerSpeed {

	/**
	 * The work's median, in milliseconds, on the Mac the budgets are measured on (13 November 2026, wp-env).
	 */
	public const REFERENCE = 7.05;

	/**
	 * The most the budget is scaled by.
	 */
	public const MOST = 2.5;

	/**
	 * How many times the work is timed, after two runs that warm it.
	 */
	private const ROUNDS = 21;

	/**
	 * The work's slowest median here so far.
	 *
	 * @var float|null
	 */
	private static ?float $took = null;

	/**
	 * What times the work once, in milliseconds, where a test gives one; otherwise the work itself is timed.
	 *
	 * @var (Closure(): float)|null
	 */
	private static ?Closure $timer = null;

	/**
	 * Sets what times the work, and forgets the timings so far. For the class's own tests.
	 *
	 * @param (Closure(): float)|null $timer What times the work once, or null for the work itself.
	 */
	public static function timeWith( ?Closure $timer ): void {
		self::$timer = $timer;
		self::$took  = null;
	}

	/**
	 * Whether the run says it is on a shared runner.
	 */
	public static function shared(): bool {
		return '1' === getenv( 'CBUILD_BENCHMARK_SHARED' );
	}

	/**
	 * The work's slowest median here so far, in milliseconds, timed once if it has not been.
	 */
	public static function took(): float {
		return self::$took ?? self::again();
	}

	/**
	 * Times the work again and keeps the slower of that and the timings before it.
	 */
	public static function again(): float {
		$timer = self::$timer ?? static function (): float {
			$start = hrtime( true );
			self::work();
			return ( hrtime( true ) - $start ) / 1e6;
		};
		$timer();
		$timer();
		$times = array();
		for ( $round = 0; $round < self::ROUNDS; $round++ ) {
			$times[] = $timer();
		}
		sort( $times );
		self::$took = max( self::$took ?? 0.0, $times[ intdiv( self::ROUNDS, 2 ) ] );
		return self::$took;
	}

	/**
	 * How much slower than the Mac this machine is, between 1 and MOST.
	 */
	public static function scale(): float {
		return min( self::MOST, max( 1.0, self::took() / self::REFERENCE ) );
	}

	/**
	 * The time a benchmark is held to here: the budget, scaled on a shared runner.
	 *
	 * @param float $budget The budget, in milliseconds, as the spec or docs/BENCHMARK.md sets it.
	 */
	public static function limit( float $budget ): float {
		if ( ! self::shared() ) {
			return $budget;
		}
		self::again();
		return $budget * self::scale();
	}

	/**
	 * A line for the run's log: the work's slowest time here so far and on the Mac, and the limit it gives. It times
	 * nothing new.
	 *
	 * @param float $budget The budget, in milliseconds.
	 */
	public static function line( float $budget ): string {
		return sprintf(
			'runner: the fixed work %.2f ms here, %.2f ms on the Mac, scale %.2f; the %.0f ms budget held to %.1f ms%s',
			self::took(),
			self::REFERENCE,
			self::scale(),
			$budget,
			self::shared() ? $budget * self::scale() : $budget,
			self::shared() ? '' : ' (not a shared runner, so not scaled)'
		);
	}

	/**
	 * The fixed work: 2,000 rows built, sorted and encoded, as a kit's parts are, and 120 prepared reads.
	 */
	private static function work(): void {
		global $wpdb;
		$rows = array();
		for ( $part = 0; $part < 2000; $part++ ) {
			$rows[] = array(
				'id'    => $part,
				'name'  => 'Part ' . ( ( $part * 7919 ) % 2000 ),
				'width' => ( ( $part * 31 ) % 40 ) / 4,
				'specs' => array(
					'a' => $part % 3,
					'b' => (string) $part,
				),
			);
		}
		usort( $rows, static fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) ?: $a['id'] <=> $b['id'] );
		$copy = json_decode( (string) wp_json_encode( $rows ), true );
		unset( $copy );
		for ( $read = 0; $read < 120; $read++ ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, 'siteurl' ) );
		}
	}
}
