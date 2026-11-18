<?php
/**
 * Where migrations run.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Hooks\InstallHooks;
use CreativeBranding\SkateboardBuilder\Install\Migrations\InlineRule;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Install\Activator;
use CreativeBranding\SkateboardBuilder\Install\NetworkFile;
use CreativeBranding\SkateboardBuilder\Port\Reindex;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\BatchMigration;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationState;
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

	public function test_after_a_failure_no_request_queues_or_runs_anything_until_the_wait_has_passed(): void {
		delete_option( Options::DB_VERSION );
		$retryAt = time() + 300; // phpcs:ignore -- the test's own clock
		update_option( Options::MIGRATIONS, array( 'schema_1' => MigrationState::pending( 'schema_1' )->started( 1 )->failed( 'no', $retryAt )->toArray() ), false );

		$this->hooks->queueWhenBehind();
		$this->hooks->queueWhenBehind();
		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ), 'a front-end request queues nothing while a failed step waits' );

		$this->asManagerInAdmin();
		$this->hooks->runWhenBehind();
		self::assertFalse( get_option( Options::DB_VERSION ), 'and a manager\'s admin page does not run it again' );
		$state = get_option( Options::MIGRATIONS );
		self::assertSame( 1, $state['schema_1']['attempts'], 'no new attempt' );

		// The wait over: the next front-end request queues the job again.
		$state['schema_1']['retry_at'] = time() - 1; // phpcs:ignore -- the test's own clock
		update_option( Options::MIGRATIONS, $state, false );
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		$this->hooks->queueWhenBehind();
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ) );
	}

	public function test_a_manager_on_an_admin_page_runs_the_migrations_inline_and_queues_nothing(): void {
		delete_option( Options::DB_VERSION );
		$this->asManagerInAdmin();
		self::assertTrue( $this->service( InlineRule::class )->allows() );

		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();

		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ) );
	}

	public function test_an_upgrade_that_finishes_its_migrations_asks_for_every_part_to_be_read_again(): void {
		delete_option( Options::REINDEX );
		$this->asManagerInAdmin();
		$this->hooks->runWhenBehind();
		self::assertFalse( get_option( Options::REINDEX ), 'nothing to migrate, nothing asked' );

		// An upgrade arrives with no activation: on a manager's admin page, and in the job.
		delete_option( Options::DB_VERSION );
		$this->hooks->runWhenBehind();
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		// At least once: a run that seeds the makers' charts asks for each chart too.
		$asked = get_option( Options::REINDEX )['run'] ?? 0;
		self::assertGreaterThan( 0, $asked );
		self::assertCount( 1, self::pendingJobs( Options::JOB_REINDEX ) );

		$this->hooks->runJob();
		self::assertSame( $asked, get_option( Options::REINDEX )['run'], 'a job with nothing left to run asks nothing' );
		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$this->hooks->runJob();
		self::assertGreaterThan( $asked, get_option( Options::REINDEX )['run'] );
		self::assertCount( 1, self::pendingJobs( Options::JOB_REINDEX ), 'still one job' );
		delete_option( Options::REINDEX );
	}

	public function test_an_admin_page_without_the_capability_queues_instead(): void {
		delete_option( Options::DB_VERSION );
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'customer' ) ) );
		set_current_screen( 'dashboard' );
		self::assertFalse( $this->service( InlineRule::class )->allows() );

		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();

		self::assertFalse( get_option( Options::DB_VERSION ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ) );
	}

	public function test_ajax_cron_and_admin_post_requests_queue_instead(): void {
		$this->asManagerInAdmin();
		self::assertTrue( $this->service( InlineRule::class )->allows() );

		add_filter( 'wp_doing_ajax', '__return_true' );
		self::assertFalse( $this->service( InlineRule::class )->allows(), 'admin-ajax.php' );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		add_filter( 'wp_doing_cron', '__return_true' );
		self::assertFalse( $this->service( InlineRule::class )->allows(), 'cron' );
		remove_filter( 'wp_doing_cron', '__return_true' );

		$GLOBALS['pagenow'] = 'admin-post.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		self::assertFalse( $this->service( InlineRule::class )->allows(), 'admin-post.php' );

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

	public function test_the_job_schedules_its_own_retry_from_inside_action_schedulers_runner(): void {
		global $wpdb;
		$specs = Tables::specs();
		$this->withRealTables(
			function () use ( $wpdb, $specs ): void {
				$wpdb->query( 'DROP TABLE IF EXISTS ' . $specs ); // phpcs:ignore
				delete_option( Options::DB_VERSION );
				delete_option( Options::MIGRATIONS );
				$this->hooks->queueWhenBehind();
				$job = self::pendingJobs( Options::JOB_MIGRATE );
				self::assertCount( 1, $job, 'a front-end request queued the job' );

				// The database refuses the table, so the job fails while Action Scheduler holds it as running.
				$refuse = static fn( string $query ): string => str_contains( $query, 'CREATE TABLE ' . $specs ) ? 'CREATE TABLE ' . $specs . ' (broken' : $query;
				add_filter( 'query', $refuse );
				$wpdb->suppress_errors( true );
				$before = time(); // phpcs:ignore -- the test's own clock
				\ActionScheduler_QueueRunner::instance()->process_action( $job[0], 'test' );
				$wpdb->suppress_errors( false );
				remove_filter( 'query', $refuse );

				self::assertSame( 'failed', get_option( Options::MIGRATIONS )['schema_1']['status'] );
				$retry = self::pendingJobs( Options::JOB_MIGRATE );
				self::assertCount( 1, $retry, 'the job scheduled its own retry, past its own running action' );
				$when = as_next_scheduled_action( Options::JOB_MIGRATE, array(), Options::QUEUE_GROUP );
				self::assertIsInt( $when );
				self::assertGreaterThanOrEqual( $before + 60, $when, 'for after the first wait' );

				// Put the table back for the tests that follow.
				delete_option( Options::MIGRATIONS );
				self::assertSame( MigrationRegistry::VERSION, $this->service( MigrationRunner::class )->run()->value === 'finished' ? MigrationRegistry::VERSION : '' );
			}
		);
	}

	public function test_an_inline_run_on_an_admin_page_works_for_two_seconds_and_leaves_the_rest_to_the_job(): void {
		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$m = new BatchMigration( 10, 3 );
		// Every batch takes three seconds: more than an admin page should wait, far less than the job's budget.
		$clock = new class() implements Clock {
			private int $at = 1_800_000_000;
			public function now(): \DateTimeImmutable {
				$this->at += 3;
				return new \DateTimeImmutable( '@' . $this->at );
			}
		};
		$runner = new MigrationRunner( new MigrationRegistry( $m ), $this->service( MigrationStore::class ), $clock, $this->service( Logger::class ), $this->service( Queue::class ) );
		$hooks  = new InstallHooks( $runner, new InlineRule(), fn(): Reindex => $this->service( Reindex::class ) );
		$this->asManagerInAdmin();

		$hooks->runWhenBehind();

		self::assertSame( 1, $m->batches, 'one batch, then the page carries on' );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'the job takes the rest' );
		self::assertFalse( get_option( Options::DB_VERSION ) );
	}

	public function test_a_current_version_costs_no_query_on_any_request(): void {
		global $wpdb;
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		wp_load_alloptions();
		$before = $wpdb->num_queries;
		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();
		$this->hooks->askWhenOwed();
		self::assertSame( 0, $wpdb->num_queries - $before, 'a front-end request: the autoloaded version answers from memory' );
		// Signing the manager in costs queries of its own, so the count starts again after it.
		$this->asManagerInAdmin();
		current_user_can( 'manage_woocommerce' );
		$before = $wpdb->num_queries;
		$this->hooks->queueWhenBehind();
		$this->hooks->runWhenBehind();
		$this->hooks->askWhenOwed();
		self::assertSame( 0, $wpdb->num_queries - $before, 'an admin page for a manager' );
	}

	// D-095: activated while WooCommerce was missing, a site had no queue to put the reindex in.
	public function test_a_reindex_activation_could_not_ask_for_is_asked_for_on_the_first_request_with_a_queue(): void {
		delete_option( Options::REINDEX );
		$reindex = $this->service( Reindex::class );

		Activator::settle( false, $reindex );
		self::assertTrue( Activator::owesReindex() );
		self::assertFalse( get_option( Options::REINDEX ), 'nothing was asked for with no queue' );
		self::assertSame( array(), self::waiting( Options::JOB_REINDEX ) );
		self::assertContains( $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", Options::REINDEX_OWED ) ), array( 'yes', 'on', 'auto', 'auto-on' ), 'autoloaded, so asking whether it is owed costs no query' );

		// WooCommerce is back, and with it the queue.
		$this->hooks->askWhenOwed();
		self::assertFalse( Activator::owesReindex() );
		self::assertFalse( get_option( Options::REINDEX_OWED ) );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		self::assertIsArray( get_option( Options::REINDEX ) );

		// Paid once: the next request asks for nothing.
		$this->hooks->askWhenOwed();
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );

		// With a queue, activation asks at once and owes nothing.
		as_unschedule_all_actions( Options::JOB_REINDEX );
		Activator::settle( true, $reindex );
		self::assertFalse( Activator::owesReindex() );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		as_unschedule_all_actions( Options::JOB_REINDEX );
		delete_option( Options::REINDEX );
		self::assertContains( Options::REINDEX_OWED, Options::names(), 'so uninstall removes it' );
	}
	/**
	 * Cowork's 0.8.0 review, F5 (#57): on a network, the must-use file is written by a request from someone who could
	 * have installed it, a network admin on a wp-admin page, and by no visitor, site admin or admin-ajax.php call. A
	 * file that differs only in its line endings is the same file and is not written again.
	 */
	public function test_the_network_file_is_written_only_for_a_network_admin_off_ajax_and_not_for_line_endings(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		NetworkFile::remove();
		try {
			wp_set_current_user( 0 );
			$this->hooks->networkFile();
			self::assertFileDoesNotExist( NetworkFile::path(), 'a visitor' );

			$site_admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
			wp_set_current_user( $site_admin );
			$this->hooks->networkFile();
			self::assertFileDoesNotExist( NetworkFile::path(), 'a site\'s own administrator' );

			$network_admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
			grant_super_admin( $network_admin );
			wp_set_current_user( $network_admin );
			add_filter( 'wp_doing_ajax', '__return_true' );
			$this->hooks->networkFile();
			remove_filter( 'wp_doing_ajax', '__return_true' );
			self::assertFileDoesNotExist( NetworkFile::path(), 'admin-ajax.php, even for a network admin' );

			$this->hooks->networkFile();
			self::assertFileExists( NetworkFile::path(), 'a network admin on a wp-admin page' );
			self::assertTrue( NetworkFile::inPlace() );

			// As an SFTP deploy leaves it: the same file with Windows line endings.
			file_put_contents( NetworkFile::path(), str_replace( "\n", "\r\n", NetworkFile::content() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the test sets the file as a deploy would.
			$before = (int) filemtime( NetworkFile::path() );
			touch( NetworkFile::path(), $before - 60 );
			clearstatcache();
			self::assertTrue( NetworkFile::inPlace(), 'line endings are not a difference' );
			$this->hooks->networkFile();
			clearstatcache();
			self::assertSame( $before - 60, (int) filemtime( NetworkFile::path() ), 'not written again' );
		} finally {
			NetworkFile::remove();
		}
	}
}
