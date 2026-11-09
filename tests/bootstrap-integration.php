<?php
/**
 * Integration test bootstrap: the WordPress test suite inside wp-env, WooCommerce loaded, the plugin activated.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

$cbuild_plugin_dir = dirname( __DIR__ );

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
// off or in sync mode, and WordPress told a persistent object cache is present, so transients and the plugin's caches
// take the object-cache path.
$cbuild_hpos = getenv( 'CBUILD_HPOS' );
if ( 'off' === $cbuild_hpos ) {
	tests_add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', static fn(): string => 'no' );
}
if ( 'sync' === $cbuild_hpos ) {
	tests_add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', static fn(): string => 'yes' );
	tests_add_filter( 'pre_option_woocommerce_custom_orders_table_data_sync_enabled', static fn(): string => 'yes' );
}
if ( 'persistent' === getenv( 'CBUILD_OBJECT_CACHE' ) ) {
	tests_add_filter( 'muplugins_loaded', static fn() => wp_using_ext_object_cache( true ), 0 );
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $cbuild_plugin_dir ): void {
		$wc = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		if ( ! file_exists( $wc ) ) {
			fwrite( STDERR, "WooCommerce not found at {$wc}.\n" );
			exit( 1 );
		}
		require $wc;
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

require_once __DIR__ . '/Support/IntegrationTestCase.php';
