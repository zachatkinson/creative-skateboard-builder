<?php
/**
 * Uninstall tests.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Uninstaller;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Uninstall leaves nothing: no table, option, transient, scheduled action or cache entry, with WooCommerce on or off
 * (D-024), and keeps the shop's own records.
 */
final class UninstallTest extends IntegrationTestCase {

	/**
	 * Rows of the plugin's group in Action Scheduler's tables, whatever their status, and their log lines.
	 *
	 * @return array{actions: int, logs: int, groups: int}
	 */
	private static function actionRows(): array {
		global $wpdb;
		$actions = $wpdb->prefix . 'actionscheduler_actions';
		$groups  = $wpdb->prefix . 'actionscheduler_groups';
		$logs    = $wpdb->prefix . 'actionscheduler_logs';
		return array(
			'actions' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i a INNER JOIN %i g ON g.group_id = a.group_id WHERE g.slug = %s', $actions, $groups, Options::QUEUE_GROUP ) ), // phpcs:ignore
			'logs'    => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i l INNER JOIN %i a ON a.action_id = l.action_id INNER JOIN %i g ON g.group_id = a.group_id WHERE g.slug = %s', $logs, $actions, $groups, Options::QUEUE_GROUP ) ), // phpcs:ignore
			'groups'  => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE slug = %s', $groups, Options::QUEUE_GROUP ) ), // phpcs:ignore
		);
	}

	/**
	 * An order with one line carrying a builder meta key, and a product: the shop's records.
	 *
	 * @return array{order: int, item: int, product: int}
	 */
	private static function shopRecords(): array {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Deck' );
		$product->set_regular_price( '10' );
		$product->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.2500' ) );
		$product->update_meta_data( Options::META_LAYER, '41' );
		$product->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$product->save();
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		// The keys the checkout writes on a build's lines (spec: Uninstall), as it writes them.
		$item->add_meta_data( Options::ORDER_BUILD, 'c0ffee', true );
		$item->add_meta_data( Options::ORDER_ROLE, Options::ROLE_KIT, true );
		$item->add_meta_data( Options::ORDER_SNAPSHOT, array( 'kit' => 1, 'picks' => array() ), true );
		$order->add_item( $item );
		$order->save();
		$items = $order->get_items();
		return array(
			'order'   => $order->get_id(),
			'item'    => (int) array_key_first( $items ),
			'product' => $product->get_id(),
		);
	}

	public function test_uninstall_leaves_nothing_and_keeps_the_shop_records(): void {
		global $wpdb;

		// Make the plugin's footprint as big as it ever gets.
		update_option( Options::DB_VERSION, '1' );
		update_option( Options::MIGRATIONS, array( 'x' => array() ) );
		update_option( Options::MIGRATION_LOCK, '1' );
		update_option( Options::SETTINGS, array( Options::SETTING_SOURCE_ORDER => array( 'field', 'table', 'attribute' ) ) );
		self::assertIsArray( get_option( Options::REINDEX ), 'saving the settings started a reindex, with its state and its job' );
		update_option( Options::IMPORT, array( 'status' => 'waiting' ), false );
		self::assertTrue( as_has_scheduled_action( Options::JOB_REINDEX, null, Options::QUEUE_GROUP ) );
		set_transient( Options::TRANSIENT_PREFIX . 'notice_1', 'x', HOUR_IN_SECONDS );
		set_transient( Options::TRANSIENT_PREFIX . 'forever', 'x' );
		// A section of a kit's Build tab that was not saved, kept for the user who typed it (D-108).
		set_transient( Options::TRANSIENT_PREFIX . 'kit_draft_12_1', array( 'refused' => array() ), HOUR_IN_SECONDS );
		set_transient( Options::RENDITIONS_FAILED, 1, 30 * DAY_IN_SECONDS );
		set_site_transient( Options::UPDATE_MANIFEST, array( 'data' => null ), HOUR_IN_SECONDS );
		wp_cache_set( 'fit:1', array( 1 ), Options::CACHE_GROUP );
		$wpdb->insert( Tables::specs(), array( 'part_id' => 1, 'spec' => 'width', 'number' => '8.25', 'source' => 'attribute', 'indexed_at' => '2026-11-09 00:00:00' ) );
		$spec_table = $this->service( \CreativeBranding\SkateboardBuilder\Port\SpecTables::class )->create( 'Independent', \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable::read( array( array( 'size' => '149' ) ) ), 'independent' );
		$binned     = $this->service( \CreativeBranding\SkateboardBuilder\Port\SpecTables::class )->create( 'Binned', \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable::read( array() ) );
		wp_trash_post( $binned );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$records  = self::shopRecords();
		$pending  = as_schedule_single_action( 1_900_000_000, Options::JOB_MIGRATE, array(), Options::QUEUE_GROUP );
		$complete = as_enqueue_async_action( Options::JOB_MIGRATE, array( 'a' => 1 ), Options::QUEUE_GROUP );
		\ActionScheduler::store()->mark_complete( $complete );
		\ActionScheduler::logger()->log( $complete, 'done' );
		self::assertGreaterThan( 0, $pending );
		self::assertGreaterThan( 0, self::actionRows()['logs'] );

		$uploads = wp_get_upload_dir()['basedir'];
		foreach ( array( 'layers/abc-105x407.webp', 'pictures/0123456789abcdef0123456789abcdef.jpg' ) as $made ) {
			wp_mkdir_p( dirname( $uploads . '/' . Options::UPLOADS_FOLDER . '/' . $made ) );
			file_put_contents( $uploads . '/' . Options::UPLOADS_FOLDER . '/' . $made, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A file the plugin would have made.
		}
		file_put_contents( $uploads . '/cbuild-shop-own.txt', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- The shop's own upload beside it.

		// A customer's saved build (milestone 8): personal data, which goes with the plugin.
		$this->withRealTables(
			static function () use ( $wpdb ): void {
				$wpdb->insert( Tables::builds(), array( 'uuid' => '00000000-0000-4000-8000-0000000000aa', 'user_id' => 1, 'kit_id' => 1, 'picks' => '{}', 'assembly' => 0, 'name' => 'Kept?', 'created_at' => '2026-11-17 00:00:00', 'updated_at' => '2026-11-17 00:00:00' ) );
				self::assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Tables::builds() ) ) );
				Uninstaller::run();
			}
		);

		self::assertDirectoryDoesNotExist( $uploads . '/' . Options::UPLOADS_FOLDER, 'the layers\' renditions and the composed pictures left behind' );
		self::assertFileExists( $uploads . '/cbuild-shop-own.txt', 'the shop\'s uploads are never touched' );
		wp_delete_file( $uploads . '/cbuild-shop-own.txt' );
		foreach ( Tables::all() as $table ) {
			self::assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Tables::name( $table ) ) ), Tables::name( $table ) . ' still exists' );
		}
		$options = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s', $wpdb->options, '%' . $wpdb->esc_like( 'cbuild' ) . '%', $wpdb->esc_like( '_transient_cbuild_' ) . '%', $wpdb->esc_like( '_transient_timeout_cbuild_' ) . '%' ) );
		self::assertSame( array(), $options, 'options or transients left behind' );
		self::assertFalse( get_option( Options::DB_VERSION ) );
		self::assertFalse( get_site_transient( Options::UPDATE_MANIFEST ), 'the update manifest cache left behind' );
		self::assertSame( array( 'actions' => 0, 'logs' => 0, 'groups' => 0 ), self::actionRows(), 'actions of any status, their logs or the group left behind' );
		self::assertSame( array(), as_get_scheduled_actions( array( 'group' => Options::QUEUE_GROUP, 'status' => '', 'per_page' => 5 ), 'ids' ) );
		self::assertFalse( wp_cache_get( 'fit:1', Options::CACHE_GROUP ), 'cache group not flushed' );

		self::assertFalse( get_option( Options::SETTINGS ), 'the settings left behind' );
		self::assertFalse( get_option( Options::REINDEX ), 'the reindex\'s state left behind' );
		self::assertFalse( get_option( Options::IMPORT ), 'a waiting import left behind' );
		self::assertNull( get_post( $spec_table ), 'a spec table left behind' );
		self::assertNull( get_post( $binned ), 'a spec table in the bin left behind' );
		self::assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type = %s', $wpdb->posts, Options::POST_TYPE_SPEC_TABLE ) ) );
		self::assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key LIKE %s', $wpdb->postmeta, $wpdb->esc_like( '_cbuild_' ) . '%' ) ), 'meta of the plugin\'s left behind' );
		self::assertNotNull( get_post( $page ), 'the shop\'s pages are never touched' );
		self::assertFalse( metadata_exists( 'post', $records['product'], Options::META_SPECS ), 'a part\'s specs left behind' );
		self::assertFalse( metadata_exists( 'post', $records['product'], Options::META_LAYER ), 'a part\'s layer image left behind' );
		self::assertFalse( metadata_exists( 'post', $records['product'], Options::META_NOT_OFFERED ), 'a part\'s Not offered box left behind' );
		self::assertNotEmpty( get_post( $records['product'] ), 'products are never touched' );
		$order = wc_get_order( $records['order'] );
		self::assertNotFalse( $order, 'orders are never touched' );
		$line = $order->get_item( $records['item'] );
		self::assertSame( 'c0ffee', (string) $line->get_meta( Options::ORDER_BUILD ), 'order item meta is part of the shop\'s records (spec: Uninstall)' );
		self::assertSame( Options::ROLE_KIT, (string) $line->get_meta( Options::ORDER_ROLE ) );
		self::assertSame( array( 'kit' => 1, 'picks' => array() ), $line->get_meta( Options::ORDER_SNAPSHOT ) );
	}

	public function test_delete_jobs_by_sql_removes_only_the_plugin_group(): void {
		$ours   = as_enqueue_async_action( Options::JOB_MIGRATE, array(), Options::QUEUE_GROUP );
		$theirs = as_enqueue_async_action( 'cbuild_test_other_plugin', array(), 'another-plugin' );
		\ActionScheduler::logger()->log( $ours, 'ours' );
		\ActionScheduler::logger()->log( $theirs, 'theirs' );
		// Two log lines: Action Scheduler's own "action created" and this one.
		self::assertSame( array( 'actions' => 1, 'logs' => 2, 'groups' => 1 ), self::actionRows() );
		$theirLogs = count( \ActionScheduler::logger()->get_logs( $theirs ) );

		Uninstaller::deleteJobsBySql();

		self::assertSame( array( 'actions' => 0, 'logs' => 0, 'groups' => 0 ), self::actionRows() );
		self::assertCount( 1, as_get_scheduled_actions( array( 'group' => 'another-plugin', 'status' => '', 'per_page' => 5 ), 'ids' ), 'the other plugin\'s action stays' );
		self::assertCount( $theirLogs, \ActionScheduler::logger()->get_logs( $theirs ), 'and its logs' );
	}

	public function test_uninstall_with_woocommerce_deactivated_removes_the_actions_too(): void {
		if ( ! self::hasWpCli() ) {
			self::markTestSkipped( 'Needs wp on the path (wp-env).' );
		}
		// On the integration site (its own tables), as a shop does it: deactivate the builder, then WooCommerce, then
		// delete the builder. uninstall.php runs with no Action Scheduler loaded (D-024).
		// The second site keeps its state between runs, so the test empties the plugin's queue there itself.
		// It is brought up to date first: a site with a migration pending queues that job itself on every request.
		self::wpEval( '\\CreativeBranding\\SkateboardBuilder\\Plugin::container()->get( \\CreativeBranding\\SkateboardBuilder\\Install\\Migrations\\MigrationRunner::class )->run(); \\CreativeBranding\\SkateboardBuilder\\Install\\Uninstaller::deleteJobs();' );
		$seed = self::wpEval( 'echo as_enqueue_async_action( "' . Options::JOB_MIGRATE . '", array(), "' . Options::QUEUE_GROUP . '", true ) > 0 ? "queued" : "not queued";' );
		self::assertSame( 'queued', $seed['out'], $seed['err'] );
		$count = 'global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions a INNER JOIN {$wpdb->prefix}actionscheduler_groups g ON g.group_id = a.group_id WHERE g.slug = \'' . Options::QUEUE_GROUP . '\'" ), "|", (int) $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE \'{$wpdb->prefix}cbuild_%\'" ), "|", (string) get_option( "' . Options::DB_VERSION . '", "none" );';
		self::assertSame( '1|3|' . MigrationRegistry::VERSION, self::wpEval( $count )['out'], 'one action, three tables, the version before' );
		try {
			$off = self::wp( 'plugin', 'deactivate', 'creative-skateboard-builder' );
			self::assertSame( 0, $off['code'], $off['err'] );
			$gone = self::wp( 'plugin', 'uninstall', 'creative-skateboard-builder', '--skip-delete', '--skip-plugins=woocommerce', '--skip-themes' );
			self::assertSame( 0, $gone['code'], $gone['err'] );
			self::assertStringNotContainsString( 'Fatal', $gone['err'] . $gone['out'] );
			self::assertSame( '0|0|none', self::wpEval( $count )['out'], 'no action, no table, no version after' );
		} finally {
			self::wp( 'plugin', 'activate', 'creative-skateboard-builder' );
		}
		// Activation asks for every part to be read again (D-082): the one action is that job.
		self::assertSame( '1|3|' . MigrationRegistry::VERSION, self::wpEval( $count )['out'], 'reactivated: the tables and the version are back, and the reindex waits' );
		self::assertSame( Options::JOB_REINDEX, self::wpEval( 'global $wpdb; echo $wpdb->get_var( "SELECT a.hook FROM {$wpdb->prefix}actionscheduler_actions a INNER JOIN {$wpdb->prefix}actionscheduler_groups g ON g.group_id = a.group_id WHERE g.slug = \'' . Options::QUEUE_GROUP . '\'" );' )['out'] );
		self::wpEval( '\\CreativeBranding\\SkateboardBuilder\\Install\\Uninstaller::deleteJobs(); delete_option( "' . Options::REINDEX . '" );' );
	}

	public function test_a_deleted_network_site_drops_the_plugin_tables(): void {
		$tables = apply_filters( 'wpmu_drop_tables', array( 'wp_posts' ), 1 );
		foreach ( Tables::all() as $table ) {
			self::assertContains( Tables::name( $table ), $tables );
		}
		self::assertSame( 'not a list', Uninstaller::siteTables( 'not a list' ) );
	}

	public function test_uninstall_on_a_network_clears_every_site(): void {
		global $wpdb;
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		$site = (int) self::factory()->blog->create();
		$this->withRealTables(
			static function () use ( $site, $wpdb ): void {
				\CreativeBranding\SkateboardBuilder\Install\Activator::activate( true );
				switch_to_blog( $site );
				$specs = Tables::specs();
				self::assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $specs ) ), 'installed on the second site first' );
				self::assertSame( MigrationRegistry::VERSION, (string) get_option( Options::DB_VERSION ) );
				set_transient( Options::TRANSIENT_PREFIX . 'x', 'x', HOUR_IN_SECONDS );
				restore_current_blog();

				self::assertTrue( \CreativeBranding\SkateboardBuilder\Install\NetworkFile::ensure(), 'the network\'s must-use file, written on activation' );
				$wpdb->last_error = '';
				Uninstaller::run();
				self::assertSame( '', $wpdb->last_error, 'a site with no Action Scheduler tables raises nothing' );
				self::assertFileDoesNotExist( \CreativeBranding\SkateboardBuilder\Install\NetworkFile::path(), 'and the must-use file goes with the plugin (#8)' );

				switch_to_blog( $site );
				try {
					foreach ( Tables::all() as $table ) {
						self::assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Tables::name( $table ) ) ), Tables::name( $table ) . ' still exists on the second site' );
					}
					self::assertStringContainsString( '_' . $site . '_', $specs, 'the second site has tables of its own' );
					foreach ( Options::names() as $option ) {
						self::assertFalse( get_option( $option ), $option . ' left on the second site' );
					}
					self::assertFalse( get_transient( Options::TRANSIENT_PREFIX . 'x' ) );
				} finally {
					restore_current_blog();
				}
			}
		);
		self::assertFalse( get_option( Options::DB_VERSION ), 'and the first site' );
	}

	public function test_uninstall_php_without_the_autoloader_loads_all_the_uninstaller_needs(): void {
		// One uninstaller: with no vendor/ folder uninstall.php requires the class files themselves. Every class of
		// the plugin's that the uninstaller, or a file it needs, names must be among them, or it would fatal there.
		$source = (string) file_get_contents( CBUILD_PATH . 'uninstall.php' );
		self::assertSame( 1, preg_match_all( '/Uninstaller::run\(\)/', $source ), 'and nothing of its own beside the call' );
		self::assertStringNotContainsString( '$wpdb', $source );
		preg_match_all( "#require_once __DIR__ \\. '/(src/[^']+)'#", $source, $required );
		$required = $required[1];
		self::assertContains( 'src/Install/Uninstaller.php', $required );
		foreach ( $required as $file ) {
			self::assertFileExists( CBUILD_PATH . $file );
			$code = (string) file_get_contents( CBUILD_PATH . $file );
			preg_match_all( '/^use CreativeBranding\\\\SkateboardBuilder\\\\([A-Za-z\\\\]+);/m', $code, $uses );
			foreach ( $uses[1] as $class ) {
				self::assertContains( 'src/' . str_replace( '\\', '/', $class ) . '.php', $required, "{$file} uses {$class}, which uninstall.php does not load" );
			}
			// A class of the file's own namespace needs no import, so every class called statically is looked for too.
			preg_match( '/^namespace CreativeBranding\\\\SkateboardBuilder\\\\([A-Za-z\\\\]+);/m', $code, $namespace );
			preg_match_all( '/(?<![\\\\A-Za-z_$>])([A-Z][A-Za-z]+)::/', $code, $called );
			foreach ( array_unique( $called[1] ) as $class ) {
				$own = 'src/' . str_replace( '\\', '/', ( $namespace[1] ?? '' ) . '\\' . $class ) . '.php';
				if ( file_exists( CBUILD_PATH . $own ) ) {
					self::assertContains( $own, $required, "{$file} calls {$class}, which uninstall.php does not load" );
				}
			}
			self::assertDoesNotMatchRegularExpression( '/\\\\CreativeBranding\\\\SkateboardBuilder\\\\(?!Install\\\\Uninstaller)/', $code, "{$file} names a class by its full name" );
			self::assertStringNotContainsString( 'Plugin::', $code, "{$file} reaches for the container" );
		}
	}

	public function test_uninstall_is_idempotent(): void {
		$this->withRealTables( static fn() => Uninstaller::run() );
		$this->withRealTables( static fn() => Uninstaller::run() );
		self::assertFalse( get_option( Options::DB_VERSION ), 'a second run on an empty site raises nothing' );
	}
}
