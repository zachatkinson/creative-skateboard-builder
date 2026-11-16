<?php
/**
 * The state route's budget.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Benchmark;

use CreativeBranding\SkateboardBuilder\Adapter\WooKits;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Rest\StateRoute;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\PartRenditions;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;
use CreativeBranding\SkateboardBuilder\Tests\Support\RunnerSpeed;
use WC_Product_Simple;
use WP_REST_Request;

/**
 * Spec, Quality bar: p95 server time for the state route under 80 ms with 500 candidate parts. Spec, Testing: on a
 * generated catalogue of 2,000 parts with 500 candidates in one slot, cold and warm (docs/BENCHMARK.md). Run by
 * `composer test:benchmark:integration`, with Xdebug off. It is not yet run with a persistent object cache: that leg
 * runs `tests/Integration` alone, and the benchmark's own is owed (the review's For Zach).
 *
 * Cold is a request that starts with an empty object cache, as every request does on a site with no persistent
 * one; warm is a request that finds the cache as the last left it. Both find a row kept of each part (D-116).
 */
final class StateRouteBenchmarkTest extends IntegrationTestCase {

	/**
	 * The budget, in milliseconds, and how many requests each run times.
	 */
	private const BUDGET   = 80.0;
	private const REQUESTS = 40;

	/**
	 * The queries a request may ask, by run. These do not move with the machine.
	 */
	private const QUERIES = array(
		'cold'  => 45,
		'warm'  => 18,
		'moved' => 40,
	);

	/**
	 * The time a request is held to here: the budget, scaled by the runner's speed on a shared runner (D-124, D-171).
	 */
	private static function limit(): float {
		$limit = RunnerSpeed::limit( self::BUDGET );
		self::say( RunnerSpeed::line( self::BUDGET ) );
		return $limit;
	}

	/**
	 * How many parts each slot has: 2,000 in all, 500 of them trucks.
	 */
	private const PARTS = array(
		'deck'     => 250,
		'trucks'   => 500,
		'wheels'   => 250,
		'risers'   => 250,
		'bearings' => 250,
		'hardware' => 250,
		'grip'     => 250,
	);

	/**
	 * A slot's parts' specs, the same for each but the size, which goes round a few.
	 *
	 * @return array<string, string>
	 */
	private static function specs( string $slot, int $at ): array {
		$width = number_format( 7.75 + ( $at % 7 ) / 8, 4, '.', '' );
		switch ( $slot ) {
			case 'deck':
				return array(
					'deck_width'   => $width,
					'deck_length'  => '32.0000',
					'hole_pattern' => 'new_school',
				);
			case 'trucks':
				return array(
					'axle_width'    => $width,
					'deck_min'      => number_format( (float) $width - 0.125, 4, '.', '' ),
					'deck_max'      => number_format( (float) $width + 0.125, 4, '.', '' ),
					'baseplate'     => 'new_school',
					'max_wheel'     => '56.0000',
					'axle_diameter' => '8.0000',
					'set_size'      => '2',
				);
			case 'wheels':
				return array(
					'wheel_diameter' => number_format( 50 + $at % 8, 4, '.', '' ),
					'set_size'       => '4',
				);
			case 'risers':
				return array(
					'riser_thickness' => 0 === $at % 2 ? '0.1250' : '0.2500',
					'set_size'        => '2',
				);
			case 'bearings':
				return array(
					'bearing_bore' => '8.0000',
					'set_size'     => '8',
				);
			case 'hardware':
				return array(
					'bolt_length' => 0 === $at % 2 ? '1.0000' : '1.2500',
					'set_size'    => '8',
				);
			default:
				return array(
					'grip_width'  => '9.0000',
					'grip_length' => '33.0000',
				);
		}
	}

	private static function say( string $line ): void {
		fwrite( STDERR, "\n[benchmark] " . $line ); // phpcs:ignore
	}

	/**
	 * The 95th of a hundred, of times in milliseconds.
	 *
	 * @param list<float> $times The times.
	 */
	private static function p95( array $times ): float {
		sort( $times );
		return $times[ (int) ceil( 0.95 * count( $times ) ) - 1 ];
	}

	public function test_the_state_route_answers_within_80_ms_at_p95_with_500_candidates_in_a_slot(): void {
		self::say( RunnerSpeed::line( self::BUDGET ) );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		rest_get_server();
		$this->service( StateRoute::class )->register();
		$this->service( \CreativeBranding\SkateboardBuilder\Hooks\CardHooks::class )->register();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );

		$slots = KitDocuments::slots();
		$ids   = array();
		foreach ( $slots['slots'] as $at => $slot ) {
			$category                        = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
			$slots['slots'][ $at ]['sources'] = array( 'categories' => array( $category ) );
			for ( $n = 0; $n < self::PARTS[ $slot['key'] ]; $n++ ) {
				$product = new WC_Product_Simple();
				$product->set_name( ucfirst( $slot['key'] ) . ' ' . $n );
				$product->set_status( 'publish' );
				$product->set_regular_price( (string) ( 20 + $n % 60 ) );
				$product->update_meta_data( Options::META_SPECS, self::specs( $slot['key'], $n ) );
				$product->save();
				wp_set_object_terms( $product->get_id(), array( $category ), 'product_cat' );
				$this->service( SpecIndexer::class )->index( $product->get_id() );
				$ids[ $slot['key'] ][] = $product->get_id();
			}
		}
		// The most presets a kit has, six, each worked out as a build on every answer (D-151).
		$presets = array();
		for ( $n = 0; $n < 6; $n++ ) {
			$picks = array();
			foreach ( $ids as $slot => $parts ) {
				if ( 'risers' !== $slot ) {
					$picks[ $slot ] = $parts[ $n ];
				}
			}
			$presets[] = array(
				'name'  => 'Preset ' . $n,
				'picks' => $picks,
			);
		}
		$kit = KitFactory::kit(
			'Complete',
			array(
				Options::META_KIT_SLOTS   => $slots,
				Options::META_KIT_PRESETS => array(
					'schema_version' => 1,
					'presets'        => $presets,
				),
			)
		)->get_id();
		self::assertSame( 2000, array_sum( array_map( 'count', $ids ) ) );
		// Milestone 7's plan: "the route's benchmark with layers held to 80 ms". The decks the runs pick have layer
		// images and their renditions, so each answer draws the board and reads its attachments.
		foreach ( array_slice( $ids['deck'], 0, 7 ) as $deck ) {
			$product = wc_get_product( $deck );
			$product->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 420, 1626 ) );
			$product->save();
		}
		$this->service( PartRenditions::class )->make( ...array_slice( $ids['deck'], 0, 7 ) );

		// A move asks as the store does (audit 13, milestone 6): the build's own fields and the page, which add the
		// view, its brand lookup, the presets and the page's blocks. The rest ask with picks alone.
		$ask = static function ( int $deck, bool $move = false ) use ( $kit ): array {
			$request = new WP_REST_Request( 'GET', "/cbuild/v1/kits/{$kit}/state" );
			$request->set_query_params(
				$move ? array(
					'cbuild' => array(
						'deck' => (string) $deck,
						'step' => 'trucks',
					),
					'page'   => $kit,
				) : array(
					'picks' => array( 'deck' => $deck ),
					'step'  => 'trucks',
				)
			);
			$queries  = get_num_queries();
			$start    = hrtime( true );
			$response = rest_get_server()->dispatch( $request );
			$took     = ( hrtime( true ) - $start ) / 1e6;
			self::assertSame( 200, $response->get_status() );
			return array( $took, get_num_queries() - $queries, $response->get_data() );
		};

		// The answer is the one the budget is about: 500 trucks judged, some offered and the rest hidden with why.
		$first = $ask( $ids['deck'][0] )[2];
		self::assertSame( 500, $first['steps'][1]['offered'] + $first['steps'][1]['hidden'] );
		self::assertGreaterThan( 24, $first['steps'][1]['offered'] );
		self::assertGreaterThan( 24, $first['steps'][1]['hidden'] );
		self::assertCount( 24, $first['options'] );
		self::assertNotNull( $first['options'][0]['card'] );
		self::assertCount( 6, $first['presets'] );
		self::assertGreaterThan( 0, $first['presets'][0]['total']['amount'] );
		self::assertCount( 1, $first['picture']['layers'], 'the deck drawn as a layer' );
		self::assertNotSame( array(), $first['picture']['layers'][0]['image']['sources'] ?? array(), 'from its renditions' );

		$view = $ask( $ids['deck'][0], true )[2];
		self::assertSame( 'Step 2 of 7: Trucks', $view['view']['title'] ?? null, 'a move answers with the view' );
		foreach ( array( false, true ) as $move ) {
			// Each shape from an address of its own: the two runs are as many requests as one address may make in a minute.
			$_SERVER['REMOTE_ADDR'] = $move ? '203.0.113.9' : '203.0.113.7';
			foreach ( array( 'cold' => true, 'warm' => false ) as $run => $flush ) {
				$times   = array();
				$queries = array();
				for ( $request = 0; $request < self::REQUESTS; $request++ ) {
					if ( $flush ) {
						// A request on a site with no persistent object cache starts with nothing kept.
						wp_cache_flush();
					}
					[ $took, $asked ] = $ask( $ids['deck'][ $request % 7 ], $move );
					$times[]          = $took;
					$queries[]        = $asked;
				}
				$p95 = self::p95( $times );
				sort( $times );
				$name = ( $move ? 'a move, ' : '' ) . $run;
				self::say( sprintf( 'state route, %s, 500 candidates in the step, 2,000 parts in the kit: p95 %.1f ms, median %.1f ms, slowest %.1f ms, %d queries at most', $name, $p95, $times[ (int) ( count( $times ) / 2 ) ], max( $times ), max( $queries ) ) );
				self::assertLessThanOrEqual( self::QUERIES[ $run ], max( $queries ), "The state route's queries, {$name}." );
				self::assertLessThan( self::limit(), $p95, "The state route, {$name}." );
			}
		}

		// A part moves, as an order's stock does: its row alone is read again, inside the budget (D-116).
		// Another visitor's address: the three runs together are more requests than one address may make in a minute.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.8';
		$times = array();
		$moved = array();
		// As many requests as the two runs above, so that its 95th percentile is the same statistic as theirs: of 20, it
		// was the second slowest, and one slow request on a shared runner decided it (D-123).
		foreach ( array_slice( $ids['trucks'], 0, self::REQUESTS ) as $truck ) {
			wc_update_product_stock( $truck, 3, 'set' );
			wp_cache_flush();
			[ $took, $asked ] = $ask( $ids['deck'][0] );
			$times[]          = $took;
			$moved[]          = $asked;
		}
		sort( $times );
		self::say( sprintf( 'state route, the first request after one part moves: p95 %.1f ms, median %.1f ms, slowest %.1f ms, %d queries at most', self::p95( $times ), $times[ (int) ( count( $times ) / 2 ) ], max( $times ), max( $moved ) ) );
		self::assertLessThanOrEqual( self::QUERIES['moved'], max( $moved ), 'The state route\'s queries, after one part moves.' );
		self::assertLessThan( self::limit(), self::p95( $times ), 'The state route, after one part moves.' );

		// Not held to the budget, and printed so that it is known: the first request in a price context no one has
		// asked in, or after a plugin says every part changed, reads every part from the shop.
		$this->service( Kits::class )->forget();
		wp_cache_flush();
		[ $took, $asked ] = $ask( $ids['deck'][0] );
		self::say( sprintf( 'state route, with nothing kept, 2,000 parts read from the shop: %.1f ms, %d queries', $took, $asked ) );
		wp_cache_flush();
		self::assertLessThan( self::limit(), $ask( $ids['deck'][0] )[0], 'The request after it.' );

		// Issue #28: a reindex forgets every part's row, a batch at a time, and writes each batch's rows again itself.
		// The first request after it is held as any other is: it reads rows, and loads no product for a slot's parts.
		$jobs = $this->service( IndexJobs::class );
		$jobs->requestReindex();
		$batches = array();
		do {
			wp_cache_flush();
			$start     = hrtime( true );
			$jobs->runReindex();
			$batches[] = ( hrtime( true ) - $start ) / 1e6;
			$state     = $jobs->state();
		} while ( null !== $state && $state->isRunning() );
		self::assertSame( ReindexState::DONE, $state?->phase );
		// As the request the batch ran in ends.
		$kits = $this->service( Kits::class );
		self::assertInstanceOf( WooKits::class, $kits );
		$kits->settle();
		wp_cache_flush();
		[ $took, $asked ] = $ask( $ids['deck'][0] );
		self::say( sprintf( 'state route, the first request after a reindex of 2,000 parts: %.1f ms, %d queries; the reindex wrote the rows in %d batches, %.2f s in all, a batch %.0f ms at its slowest', $took, $asked, count( $batches ), array_sum( $batches ) / 1000, max( $batches ) ) );
		self::assertLessThanOrEqual( self::QUERIES['cold'], $asked, 'The state route\'s queries, first after a reindex.' );
		self::assertLessThan( self::limit(), $took, 'The state route, first after a reindex.' );
		self::assertLessThan( 1000, max( $batches ), 'a batch is one request of the queue\'s' );
		unset( $_SERVER['REMOTE_ADDR'] );
	}
}
