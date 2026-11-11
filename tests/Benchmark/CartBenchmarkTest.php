<?php
/**
 * The cart's budget.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Benchmark;

use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WP_REST_Request;

/**
 * Spec, Quality bar: adding a 7-part build to the cart, under 300 ms on the server (docs/BENCHMARK.md). Run by
 * `composer test:benchmark:integration`, with Xdebug off.
 *
 * The add is the Store API's, as the builder sends it: the build judged, the kit's line and seven parts' lines
 * added, the totals worked out, and the cart read back for the answer. Each request starts with an empty object
 * cache, as every request does on a site with no persistent one.
 *
 * The second measure is what D-129 owed: WooCommerce checks the cart for every Store API cart response, and each
 * build in it is judged again there. It is printed beside the same read of a cart with no build in it, and its
 * queries are held.
 *
 * As the state route's (D-124), the time is held to the budget on the machine the figures come from, and to a
 * ceiling on a shared runner; the queries are held everywhere.
 */
final class CartBenchmarkTest extends CartTestCase {

	/**
	 * The budget and the shared runner's ceiling, in milliseconds, and how many requests each run times.
	 */
	private const BUDGET   = 300.0;
	private const SHARED   = 450.0;
	private const REQUESTS = 40;

	/**
	 * The queries a request may ask. These do not move with the machine.
	 */
	private const QUERIES = array(
		'add'  => 54,
		'read' => 30,
	);

	/**
	 * The time an add is held to here.
	 */
	private static function limit(): float {
		return '1' === getenv( 'CBUILD_BENCHMARK_SHARED' ) ? self::SHARED : self::BUDGET;
	}

	/**
	 * The 95th percentile of some times.
	 *
	 * @param list<float> $times The times.
	 */
	private static function p95( array $times ): float {
		sort( $times );
		return $times[ (int) ceil( 0.95 * count( $times ) ) - 1 ];
	}

	/**
	 * Print a line of the run, for docs/BENCHMARK.md.
	 *
	 * @param string $line The line.
	 */
	private static function say( string $line ): void {
		fwrite( STDERR, "\n[benchmark] " . $line ); // phpcs:ignore
	}

	/**
	 * Time one request, from an empty object cache.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param int             $status  The status it must answer with.
	 * @return array{float, int} Milliseconds and queries.
	 */
	private static function timed( WP_REST_Request $request, int $status ): array {
		wp_cache_flush();
		$queries  = get_num_queries();
		$start    = hrtime( true );
		$response = rest_get_server()->dispatch( $request );
		$took     = ( hrtime( true ) - $start ) / 1e6;
		self::assertSame( $status, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return array( $took, get_num_queries() - $queries );
	}

	/**
	 * The request that adds the fixture's build with every slot filled: seven parts.
	 */
	private function adding(): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/add-item' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'id'       => $this->kit,
					'quantity' => 1,
					'cbuild'   => array(
						'picks'    => $this->parts,
						'assembly' => true,
					),
				)
			)
		);
		return $request;
	}

	public function test_adding_a_seven_part_build_is_inside_the_budget(): void {
		self::assertCount( 7, $this->parts );
		$times   = array();
		$queries = array();
		for ( $n = 0; $n < self::REQUESTS; $n++ ) {
			WC()->cart->empty_cart();
			[ $took, $asked ] = self::timed( $this->adding(), 201 );
			self::assertCount( 8, WC()->cart->get_cart(), 'the kit and seven parts' );
			$times[]   = $took;
			$queries[] = $asked;
		}
		$p95 = self::p95( $times );
		sort( $times );
		self::say( sprintf( 'cart, adding a 7-part build by the Store API: p95 %.1f ms, median %.1f ms, slowest %.1f ms, %d queries at most', $p95, $times[ (int) ( count( $times ) / 2 ) ], max( $times ), max( $queries ) ) );
		self::assertLessThanOrEqual( self::QUERIES['add'], max( $queries ), 'The add\'s queries.' );
		self::assertLessThan( self::limit(), $p95, 'Adding a 7-part build.' );
	}

	public function test_reading_a_cart_with_a_build_in_it_costs_what_is_printed(): void {
		$read  = static fn(): WP_REST_Request => new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$plain = array();
		WC()->cart->empty_cart();
		foreach ( $this->parts as $part ) {
			WC()->cart->add_to_cart( $part );
		}
		for ( $n = 0; $n < self::REQUESTS; $n++ ) {
			$plain[] = self::timed( $read(), 200 );
		}

		WC()->cart->empty_cart();
		self::timed( $this->adding(), 201 );
		$build = array();
		for ( $n = 0; $n < self::REQUESTS; $n++ ) {
			$build[] = self::timed( $read(), 200 );
		}
		self::assertCount( 8, WC()->cart->get_cart(), 'the check left the build whole' );

		$times = array_column( $build, 0 );
		self::say(
			sprintf(
				'cart, a Store API read with one 7-part build in it: p95 %.1f ms, %d queries at most; the same seven products with no build: p95 %.1f ms, %d queries at most',
				self::p95( $times ),
				max( array_column( $build, 1 ) ),
				self::p95( array_column( $plain, 0 ) ),
				max( array_column( $plain, 1 ) )
			)
		);
		self::assertLessThanOrEqual( self::QUERIES['read'], max( array_column( $build, 1 ) ), 'The queries of a read with a build in the cart.' );
		self::assertLessThan( self::limit(), self::p95( $times ), 'Reading a cart with a build in it.' );
	}
}
