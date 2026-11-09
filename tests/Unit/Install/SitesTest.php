<?php
/**
 * Sites.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Sites;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The walk activation, deactivation and uninstall share, and the check for Action Scheduler's tables.
 */
final class SitesTest extends TestCase {

	/**
	 * What $wpdb held before the test.
	 *
	 * @var mixed
	 */
	private mixed $before = null;

	protected function setUp(): void {
		$this->before                 = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['cbuild_test_walk']  = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['cbuild_test_sites'] = array( 1, '4', 9 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $GLOBALS['cbuild_test_walk'], $GLOBALS['cbuild_test_sites'] );
	}

	/**
	 * A database that has the tables named, under the prefix given.
	 *
	 * @param string   $prefix The site's prefix.
	 * @param string[] $tables The tables that exist.
	 */
	private function database( string $prefix, array $tables ): object {
		return new class( $prefix, $tables ) {
			/** @var string[] */
			public array $asked = array();
			/**
			 * @param string[] $tables The tables that exist.
			 */
			public function __construct( public string $prefix, private array $tables ) {}
			public function prepare( string $query, string $table ): string {
				$this->asked[] = $query;
				return $table;
			}
			public function get_var( string $table ): ?string {
				return in_array( $table, $this->tables, true ) ? '1' : null;
			}
		};
	}

	public function test_the_work_runs_on_every_site_as_the_current_site_and_the_first_is_restored_each_time(): void {
		$ran = 0;
		Sites::each(
			static function () use ( &$ran ): void {
				++$ran;
				$GLOBALS['cbuild_test_walk'][] = 'work'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		);
		self::assertSame( 3, $ran );
		self::assertSame(
			array( 'get_sites:ids:0', 'switch:1', 'work', 'restore', 'switch:4', 'work', 'restore', 'switch:9', 'work', 'restore' ),
			$GLOBALS['cbuild_test_walk'],
			'every site, not the first hundred, and each switch undone'
		);
	}

	public function test_the_site_is_restored_when_the_work_throws(): void {
		try {
			Sites::each(
				static function (): void {
					throw new RuntimeException( 'no' );
				}
			);
			self::fail( 'the failure is not swallowed' );
		} catch ( RuntimeException $e ) {
			self::assertSame( 'no', $e->getMessage() );
		}
		self::assertSame( array( 'get_sites:ids:0', 'switch:1', 'restore' ), $GLOBALS['cbuild_test_walk'], 'restored, and the walk stops' );
	}

	public function test_a_network_with_no_site_runs_nothing(): void {
		$GLOBALS['cbuild_test_sites'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Sites::each(
			static function (): void {
				self::fail( 'no site, no work' );
			}
		);
		self::assertSame( array( 'get_sites:ids:0' ), $GLOBALS['cbuild_test_walk'] );
	}

	public function test_the_queue_tables_are_there_only_when_all_three_are(): void {
		$all             = array( 'wp_3_actionscheduler_actions', 'wp_3_actionscheduler_groups', 'wp_3_actionscheduler_logs' );
		$GLOBALS['wpdb'] = $this->database( 'wp_3_', $all ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		self::assertTrue( Sites::hasQueueTables() );
		self::assertCount( 3, $GLOBALS['wpdb']->asked );
		self::assertStringContainsString( 'TABLE_SCHEMA = DATABASE()', $GLOBALS['wpdb']->asked[0], 'this database only' );

		foreach ( $all as $missing ) {
			$GLOBALS['wpdb'] = $this->database( 'wp_3_', array_values( array_diff( $all, array( $missing ) ) ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			self::assertFalse( Sites::hasQueueTables(), "without {$missing}" );
		}
	}

	public function test_another_sites_queue_tables_do_not_count(): void {
		// A site of a network that was never loaded has none of its own; the main site's are not its.
		$GLOBALS['wpdb'] = $this->database( 'wp_3_', array( 'wp_actionscheduler_actions', 'wp_actionscheduler_groups', 'wp_actionscheduler_logs' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		self::assertFalse( Sites::hasQueueTables() );
	}
}
