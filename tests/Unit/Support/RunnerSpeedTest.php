<?php
/**
 * Tests of how the benchmarks time the runner.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Support;

use CreativeBranding\SkateboardBuilder\Tests\Support\RunnerSpeed;
use PHPUnit\Framework\TestCase;

/**
 * D-171 and D-187: the limit a benchmark is held to on a shared runner.
 */
final class RunnerSpeedTest extends TestCase {

	/**
	 * What the timer gives, one figure a call, the last repeated.
	 *
	 * @var list<float>
	 */
	private array $figures = array();

	protected function setUp(): void {
		parent::setUp();
		putenv( 'CBUILD_BENCHMARK_SHARED=1' );
		RunnerSpeed::timeWith(
			function (): float {
				return count( $this->figures ) > 1 ? (float) array_shift( $this->figures ) : (float) ( $this->figures[0] ?? 0.0 );
			}
		);
	}

	protected function tearDown(): void {
		putenv( 'CBUILD_BENCHMARK_SHARED' );
		RunnerSpeed::timeWith( null );
		parent::tearDown();
	}

	/**
	 * Sets the work's time for the next timing: two warming runs and 21 timed.
	 *
	 * @param float $ms The work's time, in milliseconds.
	 */
	private function runs( float $ms ): void {
		$this->figures = array_merge( $this->figures, array_fill( 0, 23, $ms ) );
	}

	public function test_a_runner_as_fast_as_the_mac_holds_the_budget_itself(): void {
		$this->runs( RunnerSpeed::REFERENCE );
		self::assertSame( 80.0, RunnerSpeed::limit( 80.0 ) );
	}

	public function test_a_runner_that_slows_after_it_was_first_timed_is_held_to_its_slower_figure(): void {
		$this->runs( RunnerSpeed::REFERENCE );
		self::assertSame( 1.0, RunnerSpeed::scale() );
		$this->runs( RunnerSpeed::REFERENCE * 1.5 );
		self::assertEqualsWithDelta( 120.0, RunnerSpeed::limit( 80.0 ), 0.001 );
	}

	public function test_a_runner_that_speeds_up_after_is_held_to_the_slowest_figure_so_far(): void {
		$this->runs( RunnerSpeed::REFERENCE * 2 );
		self::assertEqualsWithDelta( 160.0, RunnerSpeed::limit( 80.0 ), 0.001 );
		$this->runs( RunnerSpeed::REFERENCE );
		self::assertEqualsWithDelta( 160.0, RunnerSpeed::limit( 80.0 ), 0.001 );
	}

	public function test_the_scale_stops_at_its_cap_however_slow_the_runner(): void {
		$this->runs( RunnerSpeed::REFERENCE * 10 );
		self::assertSame( 80.0 * RunnerSpeed::MOST, RunnerSpeed::limit( 80.0 ) );
	}

	public function test_off_a_shared_runner_the_budget_holds_and_nothing_is_timed(): void {
		putenv( 'CBUILD_BENCHMARK_SHARED' );
		$this->runs( RunnerSpeed::REFERENCE * 2 );
		self::assertSame( 80.0, RunnerSpeed::limit( 80.0 ) );
		self::assertCount( 23, $this->figures );
	}
}
