<?php
/**
 * The cart's budget.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Benchmark;

use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use ReflectionMethod;
use ReflectionProperty;
use WC_Session_Handler;
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
		'add'     => 54,
		'read'    => 30,
		'session' => 1,
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

	// Issue #27, D-135: the state route asks WooCommerce for the shopper, which starts a session and a cart on a
	// request that had neither. What that costs a visitor whose session holds a build is held here: the one query
	// that reads the session, and no cart (D-138).
	public function test_the_state_route_reads_a_shoppers_session_for_what_is_printed_and_never_their_cart(): void {
		self::timed( $this->adding(), 201 );
		$session = WC()->session;
		self::assertInstanceOf( WC_Session_Handler::class, $session );
		// The cookie WooCommerce would have sent this guest, made as it makes it
		// (includes/class-wc-session-handler.php:342): the test's output has gone, so no header can be.
		$read    = static fn( string $name ): mixed => ( new ReflectionProperty( WC_Session_Handler::class, $name ) )->getValue( $session );
		$signed  = $session->get_customer_id() . '|' . $read( '_session_expiration' );
		$cookie  = $read( '_cookie' );
		$hash    = ( new ReflectionMethod( WC_Session_Handler::class, 'hash' ) )->invoke( $session, $signed );
		$_COOKIE[ $cookie ] = $signed . '|' . $read( '_session_expiring' ) . '|' . $hash;
		WC()->cart->set_session();
		$session->save_data();

		$state = function (): WP_REST_Request {
			$request = new WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
			$request->set_query_params( array( 'picks' => $this->picks() ) );
			return $request;
		};
		// The parts' rows are kept by the first request after the fixture's saves (D-116), which is not what is measured.
		self::timed( $state(), 200 );
		$kept = array( WC()->customer, WC()->cart, WC()->session );
		$with = array();
		$bare = array();
		$read = did_action( 'woocommerce_cart_loaded_from_session' );
		try {
			for ( $n = 0; $n < self::REQUESTS; $n++ ) {
				// As a request to the plugin's route starts: no customer, no session and no cart
				// (includes/class-woocommerce.php:347).
				WC()->customer = null;
				WC()->cart     = null;
				WC()->session  = null;
				$with[]        = self::timed( $state(), 200 );
				self::assertInstanceOf( WC_Session_Handler::class, WC()->session );
				self::assertSame( $session->get_customer_id(), WC()->session->get_customer_id(), 'the session is the visitor\'s own' );
				// The same request with the shopper known already, which is what the route cost before it asked.
				$bare[] = self::timed( $state(), 200 );
			}
			self::assertSame( $read, did_action( 'woocommerce_cart_loaded_from_session' ), 'the cart in the session is never read: the route asks for the shopper alone' );
				self::assertNull( WC()->cart, 'no cart is made, so none is read when the request shuts down' );
		} finally {
			unset( $_COOKIE[ $cookie ] );
			[ WC()->customer, WC()->cart, WC()->session ] = $kept;
		}
		$more = max( array_column( $with, 1 ) ) - max( array_column( $bare, 1 ) );
		self::say(
			sprintf(
				'state route, a visitor whose session holds a 7-part build: p95 %.1f ms, %d queries at most; the same with the shopper already known: p95 %.1f ms, %d queries at most',
				self::p95( array_column( $with, 0 ) ),
				max( array_column( $with, 1 ) ),
				self::p95( array_column( $bare, 0 ) ),
				max( array_column( $bare, 1 ) )
			)
		);
		self::assertLessThanOrEqual( self::QUERIES['session'], $more, 'The queries that finding the shopper adds to the state route.' );
	}
}
