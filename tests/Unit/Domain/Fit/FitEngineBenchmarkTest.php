<?php
/**
 * The fit engine's budget.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Catalogue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The engine's time grows in step with the candidates (spec: Quality bar, Performance; docs/BENCHMARK.md). Each
 * slot of a generated catalogue is judged against a complete build, at 500, 1,000 and 2,000 candidates a slot. The
 * figures go to tmp/benchmark-engine.txt for docs/BENCHMARK.md.
 */
#[Group( 'benchmark' )]
final class FitEngineBenchmarkTest extends TestCase {

	/**
	 * The engine's share of the state route's 80 ms, for the slowest slot at 500 candidates.
	 */
	private const float SLOT_BUDGET_MS = 40.0;

	/**
	 * Four times the candidates may take this many times as long. In step is 4, and a search that went quadratic 16.
	 */
	private const float GROWTH = 6.0;

	/**
	 * The quickest of five runs of every slot's options, in milliseconds by slot.
	 *
	 * @param Kit                       $kit       The kit.
	 * @param Picks                     $picks     The build each slot is judged against.
	 * @param array<string, list<Part>> $catalogue The candidates by slot.
	 * @return array<string, float>
	 */
	private static function time( Kit $kit, Picks $picks, array $catalogue ): array {
		$engine = FitEngine::standard();
		$best   = array();
		for ( $run = 0; $run < 5; $run++ ) {
			foreach ( $catalogue as $slot => $candidates ) {
				$from          = hrtime( true );
				$options       = $engine->options( $kit, $slot, $picks, $candidates );
				$took          = ( hrtime( true ) - $from ) / 1e6;
				$best[ $slot ] = min( $best[ $slot ] ?? $took, $took );
				self::assertCount( count( $candidates ), array( ...$options->offered, ...$options->hidden ) );
			}
		}
		return $best;
	}

	public function test_the_engines_time_grows_in_step_with_the_candidates(): void {
		$kit    = KitDocuments::kit();
		$picks  = Builds::complete();
		$totals = array();
		$report = array();
		foreach ( array( 500, 1000, 2000 ) as $candidates ) {
			$times                 = self::time( $kit, $picks, Catalogue::generate( 7, $candidates ) );
			$totals[ $candidates ] = array_sum( $times );
			$slowest               = max( $times );
			$report[]              = sprintf( '%d candidates a slot: slowest slot (%s) %.2f ms, all %d slots %.2f ms', $candidates, (string) array_search( $slowest, $times, true ), $slowest, count( $times ), $totals[ $candidates ] );
			if ( 500 === $candidates ) {
				self::assertLessThan( self::SLOT_BUDGET_MS, $slowest, $report[0] );
			}
		}
		$tmp = dirname( __DIR__, 4 ) . '/tmp';
		if ( ! is_dir( $tmp ) ) {
			mkdir( $tmp );
		}
		file_put_contents( $tmp . '/benchmark-engine.txt', implode( "\n", $report ) . "\n" );

		self::assertLessThan( self::GROWTH, $totals[2000] / $totals[500], implode( '; ', $report ) );
		self::assertLessThan( self::GROWTH / 2, $totals[1000] / $totals[500], implode( '; ', $report ) );
	}
}
