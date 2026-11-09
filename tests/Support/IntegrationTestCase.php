<?php
/**
 * Base class for integration tests.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

use CreativeBranding\SkateboardBuilder\Container;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Schema;
use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use WP_UnitTestCase;

/**
 * Makes sure the schema exists and is current, empties the plugin's tables and queue before each test, and hands out
 * the container. PHPUnit 9.6: set_up()/tear_down(), no attributes (docs/STANDARDS.md: Testing).
 */
abstract class IntegrationTestCase extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function set_up(): void {
		global $wpdb;
		// Before the test transaction opens: TRUNCATE and CREATE commit implicitly. A previous test may have
		// uninstalled, so the schema is put back first, as real tables (the WordPress suite rewrites CREATE TABLE to
		// CREATE TEMPORARY TABLE, which dbDelta cannot see on the next run). The lock is a row written around the
		// options API, so a test that took it and rolled back may still hold it.
		delete_option( Options::MIGRATION_LOCK );
		Schema::install();
		foreach ( Tables::all() as $table ) {
			$wpdb->query( 'TRUNCATE TABLE ' . Tables::name( $table ) ); // phpcs:ignore
		}
		if ( MigrationRegistry::VERSION !== get_option( Options::DB_VERSION ) ) {
			update_option( Options::DB_VERSION, MigrationRegistry::VERSION, true );
			delete_option( Options::MIGRATIONS );
		}
		// WooCommerce's Action Scheduler tables are not among the core tables the suite reinstalls, so an action a
		// test leaves behind would survive into the next run. Clear the plugin's group here, outside the transaction,
		// so every test starts with an empty queue (Creative Search, reviews/M1.md).
		if ( isset( $wpdb->actionscheduler_actions, $wpdb->actionscheduler_groups ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE a FROM %i a INNER JOIN %i g ON g.group_id = a.group_id WHERE g.slug = %s', $wpdb->actionscheduler_actions, $wpdb->actionscheduler_groups, Options::QUEUE_GROUP ) ); // phpcs:ignore
		}
		parent::set_up();
		wp_cache_flush();
	}

	/**
	 * Run a callback with the WordPress test suite's temporary-table rewrites off, so CREATE TABLE and DROP TABLE
	 * act on real tables (the plugin's schema and uninstall are what is under test). set_up() here runs the schema
	 * before parent::set_up(), so the tables are always real.
	 *
	 * @param callable $callback Work.
	 */
	protected function withRealTables( callable $callback ): void {
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		try {
			$callback();
		} finally {
			add_filter( 'query', array( $this, '_create_temporary_tables' ) );
			add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		}
	}

	/**
	 * The plugin's container.
	 */
	protected function container(): Container {
		return Plugin::container();
	}

	/**
	 * Resolve a service.
	 *
	 * @template T of object
	 * @param class-string<T> $id Class.
	 * @return T
	 */
	protected function service( string $id ) {
		return $this->container()->get( $id );
	}

	/**
	 * Whether the second site is ready, once it has been checked.
	 *
	 * @var bool|null
	 */
	private static ?bool $secondSite = null;

	/**
	 * Whether a second PHP process can run against a real site: the tests that need one (the lock, uninstall without
	 * WooCommerce, boot without WooCommerce) spawn WP-CLI on wp-env's tests site, whose tables (`wp_`) are not this
	 * suite's (`wptests_`, set by tools/integration.sh; D-031). False only where there is no `wp` on the path. On a
	 * matrix leg's empty database server the site is installed first, so the race runs on that server too.
	 */
	protected static function hasWpCli(): bool {
		if ( null !== self::$secondSite ) {
			return self::$secondSite;
		}
		$found = shell_exec( 'command -v wp 2>/dev/null' ); // phpcs:ignore
		if ( ! is_string( $found ) || '' === trim( $found ) ) {
			self::$secondSite = false;
			return false;
		}
		if ( 0 !== self::wp( 'core', 'is-installed' )['code'] ) {
			$install = self::wp( 'core', 'install', '--url=http://localhost:8899', '--title=Tests', '--admin_user=admin', '--admin_password=password', '--admin_email=shopper+1@example.com', '--skip-email' );
			if ( 0 !== $install['code'] ) {
				throw new \RuntimeException( 'The second site could not be installed: ' . $install['err'] );
			}
		}
		$active = self::wp( 'plugin', 'activate', 'woocommerce', 'creative-skateboard-builder' );
		if ( 0 !== $active['code'] ) {
			throw new \RuntimeException( 'The second site could not activate the plugins: ' . $active['err'] );
		}
		self::$secondSite = true;
		return true;
	}

	/**
	 * Start a `wp` command without waiting for it.
	 *
	 * @param string[] $args Arguments after `wp`.
	 * @return array{0: resource, 1: array<int, resource>} The process and its pipes.
	 */
	protected static function startWp( array $args ): array {
		$command = 'wp ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
		$pipes   = array();
		// The child reads wp-config.php's own prefix and is a single site, whatever this suite runs as.
		$env = getenv();
		unset( $env['WORDPRESS_TABLE_PREFIX'], $env['WP_MULTISITE'] );
		$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env ); // phpcs:ignore
		if ( ! is_resource( $process ) ) {
			throw new \RuntimeException( 'Could not start ' . $command );
		}
		return array( $process, $pipes );
	}

	/**
	 * Wait for a started `wp` command.
	 *
	 * @param array{0: resource, 1: array<int, resource>} $started From startWp().
	 * @return array{code: int, out: string, err: string}
	 */
	protected static function finishWp( array $started ): array {
		[ $process, $pipes ] = $started;
		$out                 = (string) stream_get_contents( $pipes[1] );
		$err                 = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		return array(
			'code' => proc_close( $process ),
			'out'  => trim( $out ),
			'err'  => trim( $err ),
		);
	}

	/**
	 * Run a `wp` command and wait.
	 *
	 * @param string ...$args Arguments after `wp`.
	 * @return array{code: int, out: string, err: string}
	 */
	protected static function wp( string ...$args ): array {
		return self::finishWp( self::startWp( $args ) );
	}

	/**
	 * Run PHP in a fresh WordPress process on the tests site, with the plugin loaded as an active plugin.
	 *
	 * @param string   $code  PHP, without the opening tag.
	 * @param string[] $flags Extra `wp` flags, such as --skip-plugins=woocommerce.
	 * @return array{code: int, out: string, err: string}
	 */
	protected static function wpEval( string $code, array $flags = array() ): array {
		$file = tempnam( sys_get_temp_dir(), 'cbuild-eval-' );
		file_put_contents( $file, "<?php\n" . $code ); // phpcs:ignore
		try {
			return self::wp( ...array_merge( array( 'eval-file', $file ), $flags ) );
		} finally {
			unlink( $file ); // phpcs:ignore
		}
	}

	/**
	 * Pending actions of one hook in the plugin's group.
	 *
	 * @param string $hook Hook.
	 * @return int[]
	 */
	protected static function pendingJobs( string $hook ): array {
		return array_map(
			'intval',
			as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'group'    => Options::QUEUE_GROUP,
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => 50,
				),
				'ids'
			)
		);
	}
}
