<?php
/**
 * Where migrations run.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Hooks\InstallHooks;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * D-022: inline only for a user who may, on a plain admin page; one unique job everywhere else; no query when the
 * version is current.
 */
final class InstallHooksTest extends IntegrationTestCase {

	private InstallHooks $hooks;

	public function set_up(): void {
		parent::set_up();
		$this->hooks = $this->service( InstallHooks::class );
		set_current_screen( 'front' );
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		unset( $GLOBALS['pagenow'] );
		parent::tear_down();
	}

	/**
	 * A shop manager on the dashboard.
	 */
	private function asManagerInAdmin(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		set_current_screen( 'dashboard' );
		$GLOBALS['pagenow'] = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function test_a_front_end_request_with_the_version_behind_queues_one_job_and_runs_nothing(): void {
		delete_option( Options::DB_VERSION );
		self::assertFalse( is_admin() );

		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();
		$this->hooks->queueWhenBehind();

		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'one job, however many requests' );
		self::assertFalse( get_option( Options::DB_VERSION ), 'nothing ran on the request' );
	}

	public function test_a_manager_on_an_admin_page_runs_the_migrations_inline_and_queues_nothing(): void {
		delete_option( Options::DB_VERSION );
		$this->asManagerInAdmin();
		self::assertTrue( InstallHooks::inlineAllowed() );

		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();

		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ) );
	}

	public function test_an_admin_page_without_the_capability_queues_instead(): void {
		delete_option( Options::DB_VERSION );
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'customer' ) ) );
		set_current_screen( 'dashboard' );
		self::assertFalse( InstallHooks::inlineAllowed() );

		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();

		self::assertFalse( get_option( Options::DB_VERSION ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ) );
	}

	public function test_ajax_cron_and_admin_post_requests_queue_instead(): void {
		$this->asManagerInAdmin();
		self::assertTrue( InstallHooks::inlineAllowed() );

		add_filter( 'wp_doing_ajax', '__return_true' );
		self::assertFalse( InstallHooks::inlineAllowed(), 'admin-ajax.php' );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		add_filter( 'wp_doing_cron', '__return_true' );
		self::assertFalse( InstallHooks::inlineAllowed(), 'cron' );
		remove_filter( 'wp_doing_cron', '__return_true' );

		$GLOBALS['pagenow'] = 'admin-post.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		self::assertFalse( InstallHooks::inlineAllowed(), 'admin-post.php' );

		delete_option( Options::DB_VERSION );
		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();
		self::assertFalse( get_option( Options::DB_VERSION ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ) );
	}

	public function test_the_job_runs_the_migrations(): void {
		delete_option( Options::DB_VERSION );
		$this->hooks->runJob();
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
	}

	public function test_a_current_version_costs_no_query_on_any_request(): void {
		global $wpdb;
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		wp_load_alloptions();
		$before = $wpdb->num_queries;
		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();
		self::assertSame( 0, $wpdb->num_queries - $before, 'a front-end request: the autoloaded version answers from memory' );
		// Signing the manager in costs queries of its own, so the count starts again after it.
		$this->asManagerInAdmin();
		current_user_can( 'manage_woocommerce' );
		$before = $wpdb->num_queries;
		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();
		self::assertSame( 0, $wpdb->num_queries - $before, 'an admin page for a manager' );
	}
}
