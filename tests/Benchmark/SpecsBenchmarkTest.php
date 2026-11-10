<?php
/**
 * Milestone 3's server work at a catalogue's size, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Benchmark;

use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Service\SpecExport;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Service\SpecSheet;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Spec, Performance: the specs index is rebuilt in the background, in jobs of 100, and 2,000 parts are recorded
 * (docs/BENCHMARK.md). The export, the Catalogue check and a full dry run each answer inside one request, so each has
 * a ceiling well under PHP's usual 30 seconds. Run by `composer test:benchmark:integration`, with Xdebug off; it is
 * outside the integration suite, which runs with coverage.
 */
final class SpecsBenchmarkTest extends IntegrationTestCase {

	private const PARTS = 2000;

	/**
	 * What one request may take, in seconds, on the slowest machine this runs on (CI's runner).
	 */
	private const REQUEST = 10.0;

	public function tear_down(): void {
		delete_option( Options::REINDEX );
		delete_option( Options::IMPORT );
		parent::tear_down();
	}

	/**
	 * Decks, each with a width of its own and a SKU, made as cheaply as WordPress allows.
	 *
	 * @return list<int>
	 */
	private static function parts(): array {
		global $wpdb;
		$ids = array();
		for ( $at = 0; $at < self::PARTS; $at++ ) {
			$id = (int) wp_insert_post(
				array(
					'post_type'   => 'product',
					'post_status' => 'publish',
					'post_title'  => 'Deck ' . $at,
				)
			);
			update_post_meta( $id, '_sku', 'BENCH-' . $at );
			// WooCommerce finds a product by its SKU in its lookup table, which its own save fills.
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A test's fixture.
				$wpdb->wc_product_meta_lookup,
				array(
					'product_id' => $id,
					'sku'        => 'BENCH-' . $at,
				)
			);
			update_post_meta(
				$id,
				Options::META_SPECS,
				array(
					'deck_width'  => number_format( 7.5 + ( $at % 9 ) / 8, 4, '.', '' ),
					'deck_length' => '32.0000',
					'wheelbase'   => '14.2500',
				)
			);
			$ids[] = $id;
		}
		return $ids;
	}

	/**
	 * Time a piece of work, with the queries it made.
	 *
	 * @param callable(): mixed $work The work.
	 * @return array{0: float, 1: int, 2: mixed} Seconds, queries and what it gave back.
	 */
	private static function timed( callable $work ): array {
		// A request starts with nothing in WordPress's cache; the products made above would otherwise answer from it.
		wp_cache_flush();
		$queries = get_num_queries();
		$start   = hrtime( true );
		$gave    = $work();
		return array( ( hrtime( true ) - $start ) / 1e9, get_num_queries() - $queries, $gave );
	}

	private static function say( string $line ): void {
		fwrite( STDERR, "\n[benchmark] " . $line ); // phpcs:ignore
	}

	public function test_two_thousand_parts_are_indexed_exported_checked_and_dry_run_within_a_request_each(): void {
		$ids  = self::parts();
		$jobs = $this->service( IndexJobs::class );

		// The reindex, batch by batch as the queue runs it.
		$jobs->requestReindex();
		$batches = array();
		do {
			[ $took, $queries ] = self::timed( static fn() => $jobs->runReindex() );
			$batches[]          = array( $took, $queries );
			$state              = $jobs->state();
		} while ( null !== $state && $state->isRunning() );
		self::assertSame( ReindexState::DONE, $state?->phase );
		self::assertGreaterThanOrEqual( self::PARTS, $state->parts );
		$times = array_column( $batches, 0 );
		$asked = array_column( $batches, 1 );
		self::say( sprintf( 'reindex: %d parts in %d batches, %.2f s in all; a batch %.0f ms at its slowest, %d queries at its most', $state->parts, count( $batches ), array_sum( $times ), 1000 * max( $times ), max( $asked ) ) );
		self::assertGreaterThanOrEqual( self::PARTS / IndexJobs::BATCH, count( $batches ), 'in jobs of 100' );
		self::assertLessThan( self::REQUEST, max( $times ), 'a batch is one request of the queue\'s' );
		self::assertLessThan( IndexJobs::BATCH * 4, max( $asked ), 'a batch of 100 parts' );
		// A batch late in the catalogue asks what an early one does: the walk is by ID, not by offset.
		$full = array_values( array_filter( $batches, static fn( array $batch ): bool => $batch[1] > 100 ) );
		self::assertGreaterThanOrEqual( 20, count( $full ) );
		self::assertLessThanOrEqual( $full[1][1] * 1.1 + 5, $full[ count( $full ) - 1 ][1], 'the last full batch against the second' );

		// The export, whole.
		[ $took, $queries, $sheet ] = self::timed( fn() => $this->service( SpecExport::class )->text() );
		self::say( sprintf( 'export: %d rows, %d kB, %.2f s, %d queries', substr_count( (string) $sheet, "\n" ) - 1, strlen( (string) $sheet ) / 1024, $took, $queries ) );
		self::assertGreaterThanOrEqual( self::PARTS, substr_count( (string) $sheet, "\n" ) - 1 );
		self::assertLessThan( self::REQUEST, $took, 'the export answers in one request' );
		self::assertLessThan( self::PARTS * 1.1 + 60, $queries, 'one query a product, which is WooCommerce reading its meta, and a page\'s own' );

		// The Catalogue check, in the request that asks for it.
		[ $took, $queries, $found ] = self::timed( fn() => $this->service( CatalogueCheck::class )->run() );
		self::say( sprintf( 'check: %.2f s, %d queries, %d findings, peak memory %d MB', $took, $queries, count( (array) $found ), memory_get_peak_usage( true ) / 1048576 ) );
		self::assertSame( array(), $found );
		self::assertLessThan( self::REQUEST, $took, 'the check answers in one request (D-077)' );
		self::assertLessThan( self::PARTS * 1.1 + 60, $queries );

		// The check again with something to find (#19): one part in ten holds a width that cannot be read, so the
		// check builds a finding for each as well as reading it.
		$wrong = array_values( array_filter( $ids, static fn( int $at ): bool => 0 === $at % 10, ARRAY_FILTER_USE_KEY ) );
		foreach ( $wrong as $id ) {
			update_post_meta( $id, Options::META_SPECS, array( 'deck_width' => 'wide' ) );
		}
		[ $took, $queries, $found ] = self::timed( fn() => $this->service( CatalogueCheck::class )->run() );
		self::say( sprintf( 'check with findings: %.2f s, %d queries, %d findings in %d parts', $took, $queries, count( (array) $found ), count( $wrong ) ) );
		self::assertGreaterThanOrEqual( count( $wrong ), count( (array) $found ) );
		self::assertLessThan( self::REQUEST, $took, 'the check answers in one request when it finds something too' );
		self::assertLessThan( self::PARTS * 1.1 + 60, $queries, 'a finding costs no query of its own' );
		foreach ( $wrong as $id ) {
			update_post_meta(
				$id,
				Options::META_SPECS,
				array(
					'deck_width'  => '8.0000',
					'deck_length' => '32.0000',
					'wheelbase'   => '14.2500',
				)
			);
		}

		// A file matched by SKU (#19): 2,000 rows, each SKU looked up, read and planned as the screen does.
		$text = "sku,deck_width\n";
		foreach ( array_keys( $ids ) as $at ) {
			$text .= 'BENCH-' . $at . ",8.5\n";
		}
		$import               = $this->service( SpecImport::class );
		[ $took, $queries ]   = self::timed( static fn() => $import->read( $text, SpecSheet::SKU ) );
		$state                = $import->state();
		self::assertInstanceOf( ImportState::class, $state );
		[ $planned, $asked2, $plan ] = self::timed( static fn() => $import->plan( $state, 0, $state->total ) );
		$summary              = SpecImport::summary( (array) $plan );
		self::say( sprintf( 'dry run by SKU: %d rows read in %.2f s (%d queries), planned in %.2f s (%d queries), %d would change', self::PARTS, $took, $queries, $planned, $asked2, count( $summary['changed'] ) ) );
		self::assertSame( array(), $summary['refused'], 'every SKU names its part' );
		self::assertSame( self::PARTS, count( $summary['changed'] ) + $summary['same'] );
		self::assertLessThan( self::REQUEST, $took + $planned, 'a file matched by SKU is read and shown in one request' );
		self::assertLessThan( self::PARTS * 1.1 + 60, $queries, 'one query a SKU to find its product' );
		self::assertLessThan( self::PARTS * 1.1 + 60, $asked2, 'and the plan asks for no SKU again' );
		$import->discard();

		// The largest file an import takes: 5,000 rows, 2,000 of them parts, read and planned whole as the screen does.
		$text = "id,sku,deck_width\n";
		foreach ( $ids as $at => $id ) {
			$text .= $id . ',BENCH-' . $at . ",8.5\n";
		}
		for ( $at = 0; $at < SpecImport::MAX_ROWS - self::PARTS; $at++ ) {
			$text .= ( 90000000 + $at ) . ",,8.5\n";
		}
		[ $took, $queries ]   = self::timed( static fn() => $import->read( $text ) );
		$state                = $import->state();
		self::assertInstanceOf( ImportState::class, $state );
		[ $planned, $asked2, $plan ] = self::timed( static fn() => $import->plan( $state, 0, $state->total ) );
		$changes              = count( array_filter( (array) $plan, static fn( array $row ): bool => array() !== $row['changes'] ) );
		self::say( sprintf( 'dry run: 5,000 rows read in %.2f s (%d queries), planned in %.2f s (%d queries), %d would change; the waiting import is %d kB', $took, $queries, $planned, $asked2, $changes, strlen( (string) wp_json_encode( $this->service( ImportStore::class )->load() ) ) / 1024 ) );
		self::assertGreaterThan( 0, $changes );
		self::assertLessThan( self::REQUEST, $took + $planned, 'a file is read and shown in one request' );
		self::assertLessThan( self::PARTS * 1.1 + 60, $asked2, 'a row that names no product costs no query of its own' );
		self::assertSame( '', $this->service( ReindexStore::class )->load()['error'] ?? null, 'no batch failed' );

		// The import confirmed: 100 rows a job, each a product written through WooCommerce and indexed. The first 20
		// jobs are the 2,000 rows that are parts, every one a write; the rest name nothing and write nothing.
		self::assertTrue( $import->confirm( $state->token ) );
		$batches = array();
		do {
			[ $took, $queries ] = self::timed( static fn() => $import->run( false ) );
			$batches[]          = array( $took, $queries );
			$state              = $import->state();
		} while ( ImportState::RUNNING === $state?->status );
		self::assertSame( ImportState::DONE, $state?->status );
		self::assertSame( $changes, $state->changed );
		$writing = array_slice( $batches, 0, (int) ceil( self::PARTS / SpecImport::BATCH ) );
		$times   = array_column( $writing, 0 );
		self::say( sprintf( 'import: %d parts written in %d jobs of %d, %.2f s in all; a job %.0f ms at its slowest and %d queries at its most; the place it saves is %d kB', $state->changed, count( $writing ), SpecImport::BATCH, array_sum( $times ), 1000 * max( $times ), max( array_column( $writing, 1 ) ), strlen( (string) wp_json_encode( get_option( Options::IMPORT ) ) ) / 1024 ) );
		self::assertLessThan( self::REQUEST, max( $times ), 'a job of 100 writes is one request of the queue\'s' );
		self::say( "done\n" );
	}
}
