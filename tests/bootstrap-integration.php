<?php
/**
 * Integration test bootstrap: the WordPress test suite inside wp-env, WooCommerce loaded, the plugin activated.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

$cbuild_plugin_dir = dirname( __DIR__ );

// PHPUnit measures coverage from the first test, so the lines that run while WordPress loads the plugin (the main
// file, Plugin::boot() and every hook's registration) would count as never run. When the merged coverage report is
// being made (tools/coverage-all.sh, issue #6), the coverage driver records them from here to the end of this file,
// and tools/coverage-merge.php adds them to the suite's report. The driver is the one PHPUnit picks: pcov when it is
// loaded and enabled (CBUILD_COVERAGE_DRIVER=pcov, tools/integration.sh), else Xdebug.
$cbuild_boot_driver = '';
if ( '1' === getenv( 'CBUILD_COVERAGE' ) ) {
	if ( extension_loaded( 'pcov' ) && (bool) ini_get( 'pcov.enabled' ) ) {
		$cbuild_boot_driver = 'pcov';
		\pcov\start();
	} elseif ( function_exists( 'xdebug_start_code_coverage' ) ) {
		$cbuild_boot_driver = 'xdebug';
		xdebug_start_code_coverage();
	}
}

$cbuild_loader = require $cbuild_plugin_dir . '/vendor/autoload.php';
// The suite runs on PHPUnit 9.6 from tools/integration/ (D-020; Creative Search's D-021 and D-023) while the plugin's
// own vendor tree holds PHPUnit 13 for the unit suite. Composer prepends a loader on registration, so the plugin's
// loader would otherwise answer for PHPUnit\* and sebastian\* classes first. Move it to the back: PHPUnit 9.6's loader
// wins for its classes, the plugin's loader still serves everything else. creative-skateboard-builder.php requiring
// the same autoload.php later returns this loader without registering it again.
$cbuild_loader->unregister();
$cbuild_loader->register( false );

$cbuild_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( false === $cbuild_tests_dir || '' === $cbuild_tests_dir ) {
	$cbuild_tests_dir = '/wordpress-phpunit';
}
if ( ! file_exists( $cbuild_tests_dir . '/includes/functions.php' ) ) {
	$cbuild_tests_dir = $cbuild_plugin_dir . '/vendor/wp-phpunit/wp-phpunit';
}
if ( ! file_exists( $cbuild_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test suite not found. Run through `composer test:integration` with wp-env started.\n" );
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $cbuild_plugin_dir . '/tools/integration/vendor/yoast/phpunit-polyfills' );
}

require_once $cbuild_tests_dir . '/includes/functions.php';

// The compatibility matrix's switches (spec: Compatibility), set by tools/integration.sh from the environment: HPOS
// off or in sync mode. The object-cache leg's switch is checked below.
$cbuild_hpos = getenv( 'CBUILD_HPOS' );
if ( 'off' === $cbuild_hpos ) {
	tests_add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', static fn(): string => 'no' );
}
if ( 'sync' === $cbuild_hpos ) {
	tests_add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', static fn(): string => 'yes' );
	tests_add_filter( 'pre_option_woocommerce_custom_orders_table_data_sync_enabled', static fn(): string => 'yes' );
}
// The object-cache leg has a real cache: tools/object-cache.sh put a Redis server on the network and Redis Object
// Cache's drop-in in wp-content, which WordPress loads by itself (D-066). A flag without them would prove nothing.
if ( 'persistent' === getenv( 'CBUILD_OBJECT_CACHE' ) && ! file_exists( '/var/www/html/wp-content/object-cache.php' ) ) {
	fwrite( STDERR, "CBUILD_OBJECT_CACHE=persistent needs the leg's cache: run composer test:integration:object-cache.\n" );
	exit( 1 );
}

// A shop that has finished Action Scheduler's one-time move to its own tables, which is every shop installed in years,
// runs ActionScheduler_DBStore. A site that has not runs ActionScheduler_HybridStore, which has no unique insert: the
// factory falls back to a check for pending actions only (ActionScheduler_ActionFactory::store_unique_action()), so a
// running action does not block a unique one there, and it does on a shop. The suite's freshly installed site would be
// the second kind, so it is told the move is done (D-043).
tests_add_filter( 'pre_option_action_scheduler_migration_status', static fn(): string => 'complete' );

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $cbuild_plugin_dir ): void {
		$wc = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		if ( ! file_exists( $wc ) ) {
			fwrite( STDERR, "WooCommerce not found at {$wc}.\n" );
			exit( 1 );
		}
		require $wc;
		// The Yoast leg: tools/yoast.sh put the pinned plugin in the site (D-102). A flag without it would prove nothing.
		if ( '1' === getenv( 'CBUILD_YOAST' ) ) {
			$yoast = WP_PLUGIN_DIR . '/wordpress-seo/wp-seo.php';
			if ( ! file_exists( $yoast ) ) {
				fwrite( STDERR, "CBUILD_YOAST=1 needs Yoast SEO: run composer test:integration:yoast.\n" );
				exit( 1 );
			}
			require $yoast;
		}
		require $cbuild_plugin_dir . '/creative-skateboard-builder.php';
	}
);

// WooCommerce keeps its product visibility term ids in a static unless its own test bootstrap is loaded. The core
// suite deletes every term after each test class, so a later class would read ids that no longer exist. Declaring the
// class turns that cache off, as WooCommerce's own suite does (Creative Search).
if ( ! class_exists( 'WC_Unit_Tests_Bootstrap' ) ) {
	final class WC_Unit_Tests_Bootstrap {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Classes.ClassFileName.NoMatch, Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
}

tests_add_filter(
	'setup_theme',
	static function (): void {
		// WooCommerce installs its tables on this hook in its own test suite.
		define( 'WP_UNINSTALL_PLUGIN', true );
		define( 'WC_REMOVE_ALL_DATA', true );
		include WP_PLUGIN_DIR . '/woocommerce/uninstall.php';
		// A leg that forces HPOS on through pre_option never passes through FeaturesController::change_feature_enable(),
		// which is what creates the order tables on a real shop (class-wc-install.php:1304), so they are made here, before
		// the install counts orders.
		if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$cbuild_sync = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
			if ( ! $cbuild_sync->check_orders_table_exists() ) {
				$cbuild_sync->create_database_tables();
			}
		}
		WC_Install::install();
		if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Install' ) ) {
			\Automattic\WooCommerce\Internal\Admin\Install::create_tables();
			\Automattic\WooCommerce\Internal\Admin\Install::create_events();
		}
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
		\CreativeBranding\SkateboardBuilder\Install\Activator::activate();
	}
);

require $cbuild_tests_dir . '/includes/bootstrap.php';

if ( '' !== $cbuild_boot_driver ) {
	if ( 'pcov' === $cbuild_boot_driver ) {
		// pcov reports every executable line of a file it is asked for, -1 for one that did not run, and keeps what it
		// recorded until it is cleared, so it is cleared here and PHPUnit's first test starts from nothing, as with Xdebug.
		\pcov\stop();
		$cbuild_boot_raw = \pcov\collect( \pcov\inclusive, \pcov\waiting() );
		\pcov\clear();
	} else {
		$cbuild_boot_raw = xdebug_get_code_coverage();
		xdebug_stop_code_coverage( true );
	}
	$cbuild_boot_lines = array();
	foreach ( $cbuild_boot_raw as $cbuild_boot_file => $cbuild_boot_ran ) {
		if ( str_starts_with( $cbuild_boot_file, $cbuild_plugin_dir . '/src/' ) ) {
			$cbuild_boot_lines[ substr( $cbuild_boot_file, strlen( $cbuild_plugin_dir ) + 1 ) ] = array_keys( array_filter( $cbuild_boot_ran, static fn( int $cbuild_hits ): bool => $cbuild_hits > 0 ) );
		}
	}
	file_put_contents( $cbuild_plugin_dir . '/tmp/coverage-bootstrap.json', (string) wp_json_encode( $cbuild_boot_lines ) );
}

require_once __DIR__ . '/Support/IntegrationTestCase.php';
