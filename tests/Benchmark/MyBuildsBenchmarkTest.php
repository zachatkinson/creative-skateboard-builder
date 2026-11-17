<?php
/**
 * My builds with a customer's most builds.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Benchmark;

use CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\RunnerSpeed;

/**
 * The audit of milestone 8, should-fix 4: My builds at the 100 builds a customer may keep (D-217), each checked and
 * priced as the builder would, with the kit's parts read once for the page and the caches emptied before each view.
 */
final class MyBuildsBenchmarkTest extends CartTestCase {

	/**
	 * A view's budget, in milliseconds: an account page a customer opens now and then: about 1.6 times what 100 builds took when it was set (368 ms).
	 */
	private const BUDGET = 600.0;

	/**
	 * Views measured.
	 */
	private const VIEWS = 10;

	/**
	 * The most queries a view makes: those of one build and the page's own, however many builds there are. A shop
	 * has a placeholder image; the test site's, which WordPress cannot cache, is turned off.
	 */
	private const QUERIES = 130;

	private static function say( string $line ): void {
		fwrite( STDERR, "\n[benchmark] " . $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- The benchmark's report, to the runner's console.
	}

	public function test_my_builds_with_a_hundred_builds_is_inside_the_budget(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::builds() ) );
		update_option( 'woocommerce_placeholder_image', 0 );
		$user  = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		$saved = $this->service( SavedBuilds::class );
		for ( $n = 1; $n <= SavedBuilds::MOST; $n++ ) {
			self::assertSame( SavedBuilds::SAVED, $saved->save( $user, $this->kit, $this->picks(), 1 === $n % 2, 'Build ' . $n )['code'] );
		}
		wp_set_current_user( $user );
		$hooks = $this->service( SavedBuildHooks::class );
		$limit = RunnerSpeed::limit( self::BUDGET );
		self::say( RunnerSpeed::line( self::BUDGET ) );
		$times   = array();
		$queries = array();
		for ( $n = 0; $n < self::VIEWS; $n++ ) {
			wp_cache_flush();
			$this->service( Kits::class )->forget();
			$before = get_num_queries();
			$start  = hrtime( true );
			ob_start();
			$hooks->page();
			$html      = (string) ob_get_clean();
			$times[]   = ( hrtime( true ) - $start ) / 1e6;
			$queries[] = get_num_queries() - $before;
			self::assertSame( SavedBuilds::MOST, substr_count( $html, 'class="cbuild-my-builds__build"' ) );
		}
		sort( $times );
		$p95 = $times[ (int) ceil( 0.95 * count( $times ) ) - 1 ];
		self::say( sprintf( 'My builds, %d builds: p95 %.0f ms, median %.0f ms, %d queries at most', SavedBuilds::MOST, $p95, $times[ (int) floor( count( $times ) / 2 ) ], max( $queries ) ) );
		self::assertLessThan( $limit, $p95 );
		self::assertLessThanOrEqual( self::QUERIES, max( $queries ) );
	}
}
