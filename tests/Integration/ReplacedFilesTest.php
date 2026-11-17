<?php
/**
 * A request that replaces the plugin's files stops using them.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use ReflectionFunction;
use WP_Hook;

/**
 * Cowork's M7 review, F3: updating 0.6.0 by uploading 0.7.0's zip ended in a fatal error, when 0.6.0's `deleted_post`
 * callback, run as WordPress deleted the uploaded zip, built 0.7.0's IndexJobs from 0.6.0's bindings. From 0.7.0, the
 * moment WordPress empties this plugin's folder for the new files, every callback of this release comes off every
 * hook, so the rest of the request builds nothing from files that are another release's (D-208). WordPress's test case
 * puts the hooks back after each test.
 */
final class ReplacedFilesTest extends IntegrationTestCase {

	/**
	 * The plugin's callbacks on every hook, as `hook@priority` with the callback's class or file, found here without
	 * the plugin's own test of what is its.
	 *
	 * @return list<string>
	 */
	private static function ours(): array {
		global $wp_filter;
		$found = array();
		$path  = wp_normalize_path( CBUILD_PATH );
		foreach ( $wp_filter as $name => $hook ) {
			if ( ! $hook instanceof WP_Hook ) {
				continue;
			}
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					$owner    = '';
					if ( is_array( $function ) ) {
						$owner = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					} elseif ( $function instanceof \Closure ) {
						$owner = wp_normalize_path( (string) ( new ReflectionFunction( $function ) )->getFileName() );
					} elseif ( is_string( $function ) ) {
						$owner = $function;
					}
					if ( str_starts_with( $owner, 'CreativeBranding\\SkateboardBuilder\\' ) || str_starts_with( $owner, $path ) ) {
						$found[] = $name . '@' . $priority . ' ' . $owner;
					}
				}
			}
		}
		return $found;
	}

	public function test_emptying_this_plugins_folder_takes_every_callback_of_it_off_every_hook(): void {
		self::assertContains( 'deleted_post@10 CreativeBranding\\SkateboardBuilder\\Hooks\\IndexHooks', self::ours(), 'the callback that failed in F3 is there to start with' );
		self::assertGreaterThan( 20, count( self::ours() ) );

		// Another plugin's folder, emptied: nothing changes.
		$before = self::ours();
		self::assertTrue( apply_filters( 'upgrader_clear_destination', true, WP_PLUGIN_DIR, '/srv/ftp/wp-content/plugins/creative-search/', array() ) );
		self::assertSame( $before, self::ours() );

		// This plugin's, as a remote filesystem names it: the folder's name under the plugins folder.
		$error = new \WP_Error( 'kept', 'Passed on as it came.' );
		self::assertSame( $error, apply_filters( 'upgrader_clear_destination', $error, WP_PLUGIN_DIR, '/srv/ftp/wp-content/plugins/' . basename( CBUILD_PATH ) . '/', array() ) );
		self::assertSame( array(), self::ours() );
	}

	public function test_files_copied_into_this_plugins_folder_take_its_callbacks_off_and_another_plugins_do_not(): void {
		$before = self::ours();
		self::assertSame( 'kept', apply_filters( 'upgrader_post_install', 'kept', array(), array( 'destination' => trailingslashit( WP_PLUGIN_DIR ) . 'creative-search/' ) ) );
		self::assertSame( $before, self::ours() );
		self::assertSame( 'kept', apply_filters( 'upgrader_post_install', 'kept', array(), array( 'destination' => untrailingslashit( CBUILD_PATH ) ) ) );
		self::assertSame( array(), self::ours() );
	}

	public function test_an_upgrader_that_finished_in_this_plugins_folder_takes_its_callbacks_off(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader         = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$extra            = array(
			'action' => 'install',
			'type'   => 'plugin',
		);
		$upgrader->result = array( 'destination' => trailingslashit( WP_PLUGIN_DIR ) . 'creative-search/' );
		$before           = self::ours();
		do_action( 'upgrader_process_complete', $upgrader, $extra );
		self::assertSame( $before, self::ours() );

		$upgrader->result = array( 'destination' => CBUILD_PATH );
		do_action( 'upgrader_process_complete', $upgrader, $extra );
		self::assertSame( array(), self::ours() );
	}

	public function test_the_queue_runs_none_of_its_jobs_in_the_request_that_replaced_the_files(): void {
		// The audit of the fixes: an automatic update runs in wp-cron.php, which runs Action Scheduler's queue after it. A job
		// of this plugin's, its callback taken off, would be marked failed ("no callbacks are registered"). The queue
		// claims nothing more in that request, and the job waits for the next, with the new release's callbacks.
		$action = as_enqueue_async_action( Options::JOB_INDEX_PART, array( 'product' => 101, 'attempt' => 1 ), Options::QUEUE_GROUP );
		self::assertGreaterThan( 0, $action );
		apply_filters( 'upgrader_post_install', true, array(), array( 'destination' => untrailingslashit( CBUILD_PATH ) ) );
		\ActionScheduler_QueueRunner::instance()->run( 'WP CLI' );
		self::assertSame( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler::store()->get_status( (string) $action ) );
		as_unschedule_all_actions( Options::JOB_INDEX_PART, array(), Options::QUEUE_GROUP );
	}
}
