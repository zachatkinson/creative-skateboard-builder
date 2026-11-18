<?php
/**
 * Activation, deactivation and boot.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Container;
use CreativeBranding\SkateboardBuilder\Hooks\InstallHooks;
use CreativeBranding\SkateboardBuilder\Install\Activator;
use CreativeBranding\SkateboardBuilder\Install\Deactivator;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Schema;
use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Repository\MigrationRepository;
use CreativeBranding\SkateboardBuilder\Scope;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Requirements;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Updates\Updater;

/**
 * Activation creates the two tables and seeds nothing; deactivation leaves the data; the plugin boots once with its
 * headers, constants and declarations as the spec says (docs/ROADMAP.md, milestone 1: Lifecycle, Platform).
 */
final class ActivationTest extends IntegrationTestCase {

	/**
	 * Whether a table exists, read from the server.
	 *
	 * @param string $table Full table name.
	 */
	private static function tableExists( string $table ): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	public function test_activation_creates_the_tables_records_the_version_and_seeds_nothing(): void {
		global $wpdb;
		$this->withRealTables(
			function () use ( $wpdb ): void {
				foreach ( Tables::all() as $table ) {
					$wpdb->query( 'DROP TABLE IF EXISTS ' . Tables::name( $table ) ); // phpcs:ignore
				}
				delete_option( Options::DB_VERSION );
				delete_option( Options::MIGRATIONS );

				Activator::activate();

				foreach ( Tables::all() as $table ) {
					self::assertTrue( self::tableExists( Tables::name( $table ) ), Tables::name( $table ) . ' missing' );
					self::assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Tables::name( $table ) ), 'seeded nothing' ); // phpcs:ignore
				}
			}
		);
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		self::assertArrayHasKey( Options::DB_VERSION, wp_load_alloptions( true ), 'the version is autoloaded: read on every request' );
		$states = get_option( Options::MIGRATIONS );
		self::assertIsArray( $states );
		self::assertSame( array( 'schema_1', 'noop_1', 'spec_tables_1', 'schema_2', 'reserved_slot_keys_1', 'spec_tables_2', 'layer_renditions_1' ), array_keys( $states ), 'in order' );
		foreach ( $states as $key => $state ) {
			self::assertSame( 'done', $state['status'], $key );
			self::assertSame( 1, $state['attempts'], $key );
		}
		self::assertArrayNotHasKey( Options::MIGRATIONS, wp_load_alloptions( true ), 'the states are not autoloaded' );
		self::assertNull( $this->service( MigrationRepository::class )->lockValue(), 'the lock is released' );
	}

	public function test_dbdelta_a_second_time_changes_nothing(): void {
		$this->withRealTables(
			static function (): void {
				Schema::install();
				self::assertSame( array(), Schema::install(), 'dbDelta reports a change: a key or type is written differently from how MySQL reports it' );
			}
		);
	}

	public function test_reactivation_runs_nothing_more(): void {
		Activator::activate();
		$before = get_option( Options::MIGRATIONS );
		Activator::activate();
		self::assertSame( $before, get_option( Options::MIGRATIONS ), 'the same states: no second attempt' );
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
	}

	public function test_activation_asks_for_every_part_to_be_read_and_again_after_the_plugin_was_switched_off(): void {
		delete_option( Options::REINDEX );
		Activator::activate();
		$state = get_option( Options::REINDEX );
		self::assertIsArray( $state );
		self::assertSame( 1, $state['run'] );
		self::assertCount( 1, self::pendingJobs( Options::JOB_REINDEX ), 'one job, on a site whose migrations had already run' );
		self::work();
		self::assertSame( 'done', get_option( Options::REINDEX )['phase'] );

		// Switched off, its jobs are cancelled and no save is heard; switched on, the index is rebuilt.
		Deactivator::deactivate();
		Activator::activate();
		self::assertSame( 2, get_option( Options::REINDEX )['run'] );
		self::assertNotSame( 'done', get_option( Options::REINDEX )['phase'] );
		self::assertCount( 1, self::pendingJobs( Options::JOB_REINDEX ) );
		delete_option( Options::REINDEX );
	}

	/**
	 * Audit of milestone 8: switched off, the plugin forgets that its My builds address is in WordPress's rules, which
	 * anything may rebuild without it meanwhile (Settings > Permalinks); switched on, the next request puts it back.
	 */
	public function test_deactivation_forgets_the_endpoints_flag_so_my_builds_comes_back(): void {
		update_option( Options::ENDPOINTS, 'my-builds' );
		Deactivator::deactivate();
		self::assertFalse( get_option( Options::ENDPOINTS ) );
	}

	public function test_network_activation_installs_every_site(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		$site = (int) self::factory()->blog->create();
		$this->withRealTables(
			static function () use ( $site ): void {
				Activator::activate( true );
				switch_to_blog( $site );
				try {
					foreach ( Tables::all() as $table ) {
						self::assertTrue( self::tableExists( Tables::name( $table ) ), Tables::name( $table ) . ' missing on the new site' );
					}
					self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
				} finally {
					restore_current_blog();
				}
			}
		);
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ), 'the main site too' );
	}

	public function test_a_site_added_to_a_network_later_is_installed_on_its_first_admin_load(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		$site = (int) self::factory()->blog->create();
		switch_to_blog( $site );
		try {
			self::assertFalse( get_option( Options::DB_VERSION ), 'nothing installed yet' );
			$this->withRealTables(
				function (): void {
					wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
					grant_super_admin( get_current_user_id() );
					set_current_screen( 'dashboard' );
					$this->service( InstallHooks::class )->runWhenBehind();
				}
			);
			self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ), 'D-025' );
			foreach ( Tables::all() as $table ) {
				self::assertTrue( self::tableExists( Tables::name( $table ) ) );
			}
		} finally {
			restore_current_blog();
			set_current_screen( 'front' );
		}
	}

	public function test_deactivation_cancels_the_jobs_releases_the_lock_and_keeps_the_data(): void {
		Activator::activate();
		$states = array( 'noop_1' => array( 'status' => 'done' ) );
		update_option( Options::MIGRATIONS, $states, false );
		$store = $this->service( MigrationStore::class );
		as_enqueue_async_action( Options::JOB_MIGRATE, array(), Options::QUEUE_GROUP, true );
		self::assertTrue( $store->acquireLock( 1_800_000_000, 300 ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ) );

		Deactivator::deactivate();

		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ), 'pending jobs cancelled' );
		self::assertNull( $this->service( MigrationRepository::class )->lockValue(), 'lock released' );
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ), 'the version stays' );
		self::assertSame( $states, get_option( Options::MIGRATIONS ), 'the states stay' );
		foreach ( Tables::all() as $table ) {
			self::assertTrue( self::tableExists( Tables::name( $table ) ), 'the tables stay' );
		}
	}

	public function test_network_deactivation_cancels_the_jobs_and_releases_the_lock_on_every_site(): void {
		global $wpdb;
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		$with    = (int) self::factory()->blog->create();
		$without = (int) self::factory()->blog->create();
		$repo    = $this->service( MigrationRepository::class );

		// The first site and a second one each hold the lock and a pending job. Action Scheduler's tables follow
		// switch_to_blog() (ActionScheduler_Abstract_Schema.php:54 adds them to $wpdb->tables), and are made here
		// for the second site as its first admin load would. A third site has none: it was never loaded.
		as_enqueue_async_action( Options::JOB_MIGRATE, array(), Options::QUEUE_GROUP, true );
		self::assertTrue( $repo->acquireLock( 1_800_000_000, 300 ) );
		switch_to_blog( $with );
		( new \ActionScheduler_StoreSchema() )->register_tables( true );
		( new \ActionScheduler_LoggerSchema() )->register_tables( true );
		as_enqueue_async_action( Options::JOB_MIGRATE, array(), Options::QUEUE_GROUP, true );
		self::assertTrue( $repo->acquireLock( 1_800_000_000, 300 ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ) );
		update_option( Options::DB_VERSION, MigrationRegistry::VERSION );
		restore_current_blog();

		$wpdb->last_error = '';
		Deactivator::deactivate( true );
		self::assertSame( '', $wpdb->last_error, 'a site with no Action Scheduler tables raises nothing' );

		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ), 'the first site' );
		self::assertNull( $repo->lockValue(), 'the first site' );
		switch_to_blog( $with );
		try {
			self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ), 'the second site\'s job is cancelled' );
			self::assertNull( $repo->lockValue(), 'the second site\'s lock is released' );
			self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ), 'its data stays' );
		} finally {
			restore_current_blog();
		}
		self::assertGreaterThan( 1, $without );
	}

	public function test_deactivating_one_site_of_a_network_leaves_the_others(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		$other = (int) self::factory()->blog->create();
		$repo  = $this->service( MigrationRepository::class );
		switch_to_blog( $other );
		self::assertTrue( $repo->acquireLock( 1_800_000_000, 300 ) );
		restore_current_blog();

		Deactivator::deactivate( false );

		switch_to_blog( $other );
		try {
			self::assertStringStartsWith( '1800000000:', (string) $repo->lockValue(), 'another site\'s lock is its own' );
		} finally {
			restore_current_blog();
		}
	}

	public function test_plugin_booted_once_with_a_container_and_constants(): void {
		self::assertTrue( defined( 'CBUILD_VERSION' ) );
		self::assertSame( get_plugin_data( CBUILD_FILE, false, false )['Version'], CBUILD_VERSION, 'the constant and the header agree' );
		self::assertSame( CBUILD_PATH, plugin_dir_path( CBUILD_FILE ) );
		self::assertSame( CBUILD_URL, plugin_dir_url( CBUILD_FILE ) );
		self::assertInstanceOf( Container::class, Plugin::container() );
		self::assertSame( Plugin::container(), Plugin::container() );
		self::assertTrue( Plugin::container()->has( InstallHooks::class ), 'the install hooks, built at boot' );
		self::assertSame( array(), Plugin::container()->hookablesFor( Scope::Everywhere ), 'and registered once' );
		self::assertSame( 0, has_action( 'rest_api_init', array( Plugin::class, 'registerRest' ) ) );
		Plugin::registerRest();
		self::assertSame( 10, has_action( 'init', array( $this->service( InstallHooks::class ), 'queueWhenBehind' ) ), 'a REST request registers nothing twice' );
		$hooks = $this->service( InstallHooks::class );
		self::assertSame( 10, has_action( 'init', array( $hooks, 'queueWhenBehind' ) ) );
		self::assertSame( 10, has_action( 'admin_init', array( $hooks, 'runWhenBehind' ) ) );
		self::assertSame( 10, has_action( Options::JOB_MIGRATE, array( $hooks, 'runJob' ) ) );
		self::assertNotFalse( has_filter( 'wpmu_drop_tables' ) );
		self::assertNotFalse( has_filter( 'update_plugins_' . wp_parse_url( Updater::MANIFEST_URL, PHP_URL_HOST ) ), 'the updater is registered' );
		self::assertFalse( has_action( 'admin_notices', array( Plugin::class, 'floorsNotice' ) ), 'WooCommerce is here: no notice' );
	}

	public function test_plugin_headers_match_the_spec(): void {
		$data = get_plugin_data( CBUILD_FILE, false, false );
		self::assertSame( 'Creative Skateboard Builder', $data['Name'] );
		self::assertSame( 'creative-skateboard-builder', $data['TextDomain'] );
		self::assertSame( '/languages', $data['DomainPath'] );
		self::assertSame( Requirements::WORDPRESS, $data['RequiresWP'] );
		self::assertSame( Requirements::PHP, $data['RequiresPHP'] );
		self::assertSame( 'woocommerce', $data['RequiresPlugins'] );
		self::assertSame( Updater::MANIFEST_URL, $data['UpdateURI'] );
		self::assertSame( CBUILD_VERSION, $data['Version'] );
		self::assertLessThan( 140, mb_strlen( $data['Description'], 'UTF-8' ), 'under the Plugin Handbook limit' );
		$wc = get_file_data(
			CBUILD_FILE,
			array(
				'requires' => 'WC requires at least',
				'tested'   => 'WC tested up to',
			)
		);
		self::assertSame( Requirements::WOOCOMMERCE, $wc['requires'] );
		// The newest WooCommerce the suites run on, which wp-env pins and D-145 moves each milestone (Cowork's review,
		// minor 20: the header said 11.2 while CI's newest leg and the demo ran 11.3.0).
		$env = json_decode( (string) file_get_contents( dirname( CBUILD_FILE ) . '/.wp-env.json' ), true );
		self::assertIsArray( $env );
		self::assertSame( 1, preg_match( '#woocommerce\.(\d+\.\d+)\.\d+\.zip#', (string) wp_json_encode( $env ), $pinned ) );
		self::assertSame( $pinned[1], $wc['tested'] );
	}

	public function test_the_installed_woocommerce_meets_the_floor(): void {
		self::assertNull( Requirements::woocommerceShortfall( Plugin::woocommerceVersion() ) );
		self::assertSame( WC_VERSION, Plugin::woocommerceVersion() );
	}

	public function test_the_floors_notice_is_an_error_notice_naming_every_floor(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		ob_start();
		Plugin::floorsNotice();
		self::assertSame( '', (string) ob_get_clean(), 'not shown to someone who cannot manage plugins' );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
		ob_start();
		Plugin::floorsNotice();
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'notice-error', $html );
		self::assertStringContainsString( 'PHP 8.5', $html );
		self::assertStringContainsString( 'WordPress 7.0', $html );
		self::assertStringContainsString( 'WooCommerce 11.2', $html );
	}

	public function test_without_woocommerce_the_plugin_hooks_nothing_and_shows_the_notice(): void {
		if ( ! self::hasWpCli() ) {
			self::markTestSkipped( 'Needs wp on the path (wp-env).' );
		}
		// A fresh process with WooCommerce skipped: the floors check fails, the notice is hooked, nothing else is.
		$result = self::wpEval(
			'ob_start(); do_action( "admin_notices" ); $notice = ob_get_clean();' .
			'echo has_action( "' . Options::JOB_MIGRATE . '" ) ? "hooked" : "nothing", "|", has_filter( "update_plugins_raw.githubusercontent.com" ) ? "updater" : "no-updater", "|", $notice;',
			array( '--skip-plugins=woocommerce', '--skip-themes', '--user=1' )
		);
		self::assertSame( 0, $result['code'], $result['err'] );
		self::assertStringStartsWith( 'nothing|updater|', $result['out'], 'no hook of the plugin\'s but the updater (D-003)' );
		self::assertStringContainsString( 'notice-error', $result['out'] );
		self::assertStringContainsString( 'WooCommerce 11.2', $result['out'] );
	}

	public function test_compatibility_is_declared_for_the_features_that_exist(): void {
		$plugin = plugin_basename( CBUILD_FILE );
		$compat = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature( 'custom_order_tables' );
		self::assertContains( $plugin, $compat['compatible'] );
		$blocks = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature( 'cart_checkout_blocks' );
		self::assertContains( $plugin, $blocks['compatible'] );
		// Gone from WooCommerce 11.2, so nothing is declared about it (D-027). Should a later WooCommerce bring it back,
		// this fails and the declaration is added.
		self::assertArrayNotHasKey( 'product_block_editor', \Automattic\WooCommerce\Utilities\FeaturesUtil::get_features( true ) );
	}

	public function test_the_matrix_leg_is_the_one_asked_for(): void {
		// tools/integration.sh's switches must change what they say, or a leg proves nothing.
		$hpos = getenv( 'CBUILD_HPOS' );
		if ( 'off' === $hpos ) {
			self::assertFalse( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		}
		if ( 'sync' === $hpos ) {
			self::assertTrue( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
			self::assertSame( 'yes', get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) );
		}
		self::assertSame( 'persistent' === getenv( 'CBUILD_OBJECT_CACHE' ), (bool) wp_using_ext_object_cache() );
		self::assertSame( '1' === getenv( 'CBUILD_MULTISITE' ), is_multisite() );
		$host = getenv( 'WORDPRESS_DB_HOST' );
		self::assertSame( false === $host ? DB_HOST : $host, DB_HOST );
	}

	public function test_the_object_cache_leg_shares_a_value_between_two_processes(): void {
		if ( ! self::hasWpCli() ) {
			self::markTestSkipped( 'No WP-CLI outside wp-env.' );
		}
		// A flag alone makes WordPress take the object-cache paths in one process. What a shop has is a cache server
		// two processes share, and that is what the leg must run on (issue #4, D-066).
		$key = 'leg-' . bin2hex( random_bytes( 4 ) );
		$set = self::wpEval( "wp_cache_set( '{$key}', array( 'n' => 7 ), '" . Options::CACHE_GROUP . "', 60 ); echo 'set';" );
		self::assertSame( 'set', $set['out'], $set['err'] );
		$got = self::wpEval( "echo wp_json_encode( wp_cache_get( '{$key}', '" . Options::CACHE_GROUP . "' ) );" );
		self::assertSame( 0, $got['code'], $got['err'] );
		$expected = 'persistent' === getenv( 'CBUILD_OBJECT_CACHE' ) ? '{"n":7}' : 'false';
		self::assertSame( $expected, $got['out'], 'what a second process reads from the cache' );
		if ( 'persistent' === getenv( 'CBUILD_OBJECT_CACHE' ) ) {
			// The suite's own process is on the same server, through a drop-in that is there.
			self::assertFileExists( WP_CONTENT_DIR . '/object-cache.php' );
			self::assertTrue( wp_cache_supports( 'flush_group' ) );
		}
	}

	public function test_no_text_domain_is_loaded_by_hand(): void {
		// WordPress registers the Domain Path of an active plugin itself (wp-settings.php:589-592, D-028).
		self::assertStringNotContainsString( 'load_plugin_textdomain', (string) file_get_contents( CBUILD_FILE ) );
		// Every file under src/, at any depth: glob()'s ** is not recursive in PHP.
		$files = array();
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( CBUILD_PATH . 'src', \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$files[] = (string) $file;
		}
		self::assertGreaterThan( 20, count( $files ) );
		self::assertSame( array(), array_values( array_filter( $files, static fn( string $f ): bool => str_contains( (string) file_get_contents( $f ), 'load_plugin_textdomain' ) ) ) );
	}
}
