<?php
/**
 * The state route's budget.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Benchmark;

use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Rest\StateRoute;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Product_Simple;
use WP_REST_Request;

/**
 * Spec, Quality bar: p95 server time for the state route under 80 ms with 500 candidate parts. Spec, Testing: on a
 * generated catalogue of 2,000 parts with 500 candidates in one slot, cold and warm (docs/BENCHMARK.md). Run by
 * `composer test:benchmark:integration`, with Xdebug off; `composer test:integration:object-cache` gives the same
 * run a persistent object cache.
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
		$kit = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
		self::assertSame( 2000, array_sum( array_map( 'count', $ids ) ) );

		$ask = static function ( int $deck ) use ( $kit ): array {
			$request = new WP_REST_Request( 'GET', "/cbuild/v1/kits/{$kit}/state" );
			$request->set_query_params(
				array(
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

		foreach ( array( 'cold' => true, 'warm' => false ) as $run => $flush ) {
			$times   = array();
			$queries = array();
			for ( $request = 0; $request < self::REQUESTS; $request++ ) {
				if ( $flush ) {
					// A request on a site with no persistent object cache starts with nothing kept.
					wp_cache_flush();
				}
				[ $took, $asked ] = $ask( $ids['deck'][ $request % 7 ] );
				$times[]          = $took;
				$queries[]        = $asked;
			}
			$p95 = self::p95( $times );
			sort( $times );
			self::say( sprintf( 'state route, %s, 500 candidates in the step, 2,000 parts in the kit: p95 %.1f ms, median %.1f ms, slowest %.1f ms, %d queries at most', $run, $p95, $times[ (int) ( count( $times ) / 2 ) ], max( $times ), max( $queries ) ) );
			self::assertLessThan( self::BUDGET, $p95, "The state route, {$run}." );
		}

		// A part moves, as an order's stock does: its row alone is read again, inside the budget (D-116).
		$times = array();
		foreach ( array_slice( $ids['trucks'], 0, 20 ) as $truck ) {
			wc_update_product_stock( $truck, 3, 'set' );
			wp_cache_flush();
			$times[] = $ask( $ids['deck'][0] )[0];
		}
		self::say( sprintf( 'state route, the first request after one part moves: p95 %.1f ms, slowest %.1f ms', self::p95( $times ), max( $times ) ) );
		self::assertLessThan( self::BUDGET, self::p95( $times ), 'The state route, after one part moves.' );

		// Not held to the budget, and printed so that it is known: the first request in a price context no one has
		// asked in, or after a plugin says every part changed, reads every part from the shop.
		$this->service( Kits::class )->forget();
		wp_cache_flush();
		[ $took, $asked ] = $ask( $ids['deck'][0] );
		self::say( sprintf( 'state route, with nothing kept, 2,000 parts read from the shop: %.1f ms, %d queries', $took, $asked ) );
		wp_cache_flush();
		self::assertLessThan( self::BUDGET, $ask( $ids['deck'][0] )[0], 'The request after it.' );
		unset( $_SERVER['REMOTE_ADDR'] );
	}
}
