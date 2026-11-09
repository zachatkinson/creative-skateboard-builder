<?php
/**
 * The migration framework against the database.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Migration;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RunResult;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Repository\MigrationRepository;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationState;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\BatchMigration;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\ThrowingMigration;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * The repository's lock and versions as rows (D-021), and the runner over the real store, clock, queue and log: a
 * run stopped halfway resumes through Action Scheduler and matches an uninterrupted run; a failure is in
 * WooCommerce's log under the plugin's source (docs/ROADMAP.md, milestone 1: Migrations).
 */
final class MigrationTest extends IntegrationTestCase {

	private MigrationRepository $store;

	public function set_up(): void {
		parent::set_up();
		$this->store = $this->service( MigrationRepository::class );
	}

	/**
	 * A runner over the real adapters and the migrations given.
	 */
	private function runner( \CreativeBranding\SkateboardBuilder\Install\Migrations\Migration ...$migrations ): MigrationRunner {
		return new MigrationRunner( new MigrationRegistry( ...$migrations ), $this->store, $this->service( Clock::class ), $this->service( Logger::class ), $this->service( Queue::class ) );
	}

	public function test_the_lock_is_one_row_with_autoload_off_read_from_the_table(): void {
		global $wpdb;
		self::assertNull( $this->store->lockValue() );
		self::assertTrue( $this->store->acquireLock( 1_800_000_000, 300 ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT option_value, autoload FROM %i WHERE option_name = %s', $wpdb->options, Options::MIGRATION_LOCK ), ARRAY_A ); // phpcs:ignore
		self::assertIsArray( $row );
		self::assertSame( 'off', $row['autoload'] );
		self::assertMatchesRegularExpression( '/^1800000000:[0-9a-f]{16}$/', $row['option_value'], 'the moment, then the holder\'s token (D-033)' );
		self::assertFalse( $this->store->acquireLock( 1_800_000_100, 300 ), 'held' );
		self::assertFalse( $this->store->acquireLock( 1_800_000_300, 300 ), 'held until the TTL has passed' );

		self::assertTrue( $this->store->refreshLock( 1_800_000_200 ) );
		self::assertStringStartsWith( '1800000200:', (string) $this->store->lockValue() );
		self::assertSame( $this->store->lockValue(), get_option( Options::MIGRATION_LOCK ), 'the options cache is not stale' );
		self::assertTrue( $this->store->refreshLock( 1_800_000_200 ), 'the same second again: still held' );

		self::assertTrue( $this->store->acquireLock( 1_800_000_501, 300 ), 'taken over once expired' );
		self::assertStringStartsWith( '1800000501:', (string) $this->store->lockValue() );

		$this->store->releaseLock();
		self::assertNull( $this->store->lockValue() );
		self::assertFalse( get_option( Options::MIGRATION_LOCK ) );
	}

	public function test_a_lock_another_holder_took_over_is_neither_refreshed_nor_released(): void {
		global $wpdb;
		self::assertTrue( $this->store->acquireLock( 1_800_000_000, 300 ) );
		// Another process decides this one crashed and takes the lock: the row now carries its token.
		$theirs = '1800000400:' . str_repeat( 'f', 16 );
		$wpdb->update( $wpdb->options, array( 'option_value' => $theirs ), array( 'option_name' => Options::MIGRATION_LOCK ) );
		wp_cache_delete( Options::MIGRATION_LOCK, 'options' );

		self::assertFalse( $this->store->refreshLock( 1_800_000_450 ), 'no longer this process\'s' );
		self::assertSame( $theirs, $this->store->lockValue(), 'not written over' );
		$this->store->releaseLock();
		self::assertSame( $theirs, $this->store->lockValue(), 'not released' );
		self::assertFalse( $this->store->refreshLock( 1_800_000_460 ), 'and stays lost' );

		$this->store->clearLock();
		self::assertNull( $this->store->lockValue(), 'deactivation removes it whoever holds it' );
		self::assertFalse( get_option( Options::MIGRATION_LOCK ) );
	}

	public function test_a_lock_written_before_the_token_existed_expires_like_any_other(): void {
		global $wpdb;
		$wpdb->insert( $wpdb->options, array( 'option_name' => Options::MIGRATION_LOCK, 'option_value' => '1800000000', 'autoload' => 'off' ) );
		self::assertFalse( $this->store->acquireLock( 1_800_000_300, 300 ) );
		self::assertTrue( $this->store->acquireLock( 1_800_000_301, 300 ) );
	}

	public function test_a_table_that_could_not_be_created_fails_the_migration_and_is_tried_again(): void {
		global $wpdb;
		$specs = Tables::specs();
		$this->withRealTables(
			function () use ( $wpdb, $specs ): void {
				$wpdb->query( 'DROP TABLE IF EXISTS ' . $specs ); // phpcs:ignore
				delete_option( Options::DB_VERSION );
				delete_option( Options::MIGRATIONS );
				// The database refuses this one table, as it would without the CREATE privilege.
				$refuse = static fn( string $query ): string => str_contains( $query, 'CREATE TABLE ' . $specs ) ? 'CREATE TABLE ' . $specs . ' (broken' : $query;
				add_filter( 'query', $refuse );
				$wpdb->suppress_errors( true );
				$result = $this->service( MigrationRunner::class )->run();
				$wpdb->suppress_errors( false );
				remove_filter( 'query', $refuse );

				self::assertSame( RunResult::Failed, $result, 'dbDelta() does not throw; the migration checks its tables' );
				self::assertFalse( get_option( Options::DB_VERSION ), 'the version is left behind' );
				$state = MigrationState::fromArray( 'schema_1', $this->store->state( 'schema_1' ) );
				self::assertSame( MigrationState::FAILED, $state->status );
				self::assertStringContainsString( $specs, $state->error, 'the error names the table' );
				self::assertNull( $this->store->lockValue(), 'the lock is released' );
				self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'the next attempt is scheduled' );

				// Once the wait has passed and the database allows it, the same migration finishes.
				delete_option( Options::MIGRATIONS );
				self::assertSame( RunResult::Finished, $this->service( MigrationRunner::class )->run() );
				self::assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $specs ) ) );
			}
		);
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
	}

	public function test_a_lock_row_holding_junk_is_not_taken_over(): void {
		global $wpdb;
		$wpdb->insert( $wpdb->options, array( 'option_name' => Options::MIGRATION_LOCK, 'option_value' => 'not a time', 'autoload' => 'off' ) );
		self::assertFalse( $this->store->acquireLock( 1_900_000_000, 300 ), 'a value that is not a time is left to a person' );
	}

	public function test_two_real_processes_racing_for_the_lock_one_wins(): void {
		if ( ! self::hasWpCli() ) {
			self::markTestSkipped( 'Needs wp on the path (wp-env).' );
		}
		// Two calls in one PHPUnit process pass even with a broken lock, and the test transaction hides one
		// connection's writes from another (D-021), so two `wp` processes race on wp-env's tests site.
		self::wp( 'option', 'delete', Options::MIGRATION_LOCK );
		$this->raceForTheLock( 'fresh' );

		// An expired lock: both see it expired, one compare-and-swap wins.
		$old = self::wpEval( 'global $wpdb; $wpdb->insert( $wpdb->options, array( "option_name" => "' . Options::MIGRATION_LOCK . '", "option_value" => (string) ( time() - 301 ), "autoload" => "off" ) ); echo "seeded";' );
		self::assertSame( 'seeded', $old['out'], $old['err'] );
		$this->raceForTheLock( 'expired' );
	}

	/**
	 * Start two processes that take the lock at the same second, and assert exactly one did.
	 *
	 * @param string $case For the message.
	 */
	private function raceForTheLock( string $case ): void {
		$at    = time() + 4;
		$code  = 'use CreativeBranding\SkateboardBuilder\Plugin; use CreativeBranding\SkateboardBuilder\Port\MigrationStore;';
		$code .= 'while ( microtime( true ) < ' . $at . ' ) { usleep( 2000 ); }';
		$code .= 'echo Plugin::container()->get( MigrationStore::class )->acquireLock( ' . $at . ', 300 ) ? "won" : "lost";';
		$file  = tempnam( sys_get_temp_dir(), 'cbuild-race-' );
		file_put_contents( $file, "<?php\n" . $code ); // phpcs:ignore
		try {
			$a = self::startWp( array( 'eval-file', $file, '--skip-themes' ) );
			$b = self::startWp( array( 'eval-file', $file, '--skip-themes' ) );
			$x = self::finishWp( $a );
			$y = self::finishWp( $b );
		} finally {
			unlink( $file ); // phpcs:ignore
		}
		self::assertSame( 0, $x['code'], $x['err'] );
		self::assertSame( 0, $y['code'], $y['err'] );
		$outcomes = array( $x['out'], $y['out'] );
		sort( $outcomes );
		self::assertSame( array( 'lost', 'won' ), $outcomes, $case . ' lock: ' . implode( ', ', $outcomes ) );
		$value = self::wpEval( 'global $wpdb; echo (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = \'' . Options::MIGRATION_LOCK . '\'" );' );
		self::assertStringStartsWith( $at . ':', $value['out'], 'the winner\'s timestamp is in the row' );
		self::wp( 'option', 'delete', Options::MIGRATION_LOCK );
	}

	public function test_the_fresh_version_is_read_past_the_object_cache(): void {
		global $wpdb;
		update_option( Options::DB_VERSION, '1', true );
		self::assertSame( '1', $this->store->schemaVersion() );
		$wpdb->update( $wpdb->options, array( 'option_value' => '0' ), array( 'option_name' => Options::DB_VERSION ) );
		self::assertSame( '1', $this->store->schemaVersion(), 'the cached answer' );
		self::assertSame( '0', $this->store->freshSchemaVersion(), 'the table' );
		delete_option( Options::DB_VERSION );
		self::assertSame( '0', $this->store->freshSchemaVersion(), 'none is 0' );
		self::assertSame( '0', $this->store->schemaVersion() );
	}

	public function test_a_run_stopped_halfway_resumes_through_the_queue_and_matches_an_uninterrupted_run(): void {
		delete_option( Options::DB_VERSION );
		$whole = new BatchMigration( 10, 3 );
		self::assertSame( RunResult::Finished, $this->runner( $whole )->run() );
		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ), 'nothing queued when the budget holds' );

		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$stopped = new BatchMigration( 10, 3 );
		$runner  = $this->runner( $stopped );
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'one job queued' );
		self::assertSame( 3, get_option( Options::MIGRATIONS )['batch_1']['cursor']['position'], 'the cursor is in the row' );
		self::assertNull( $this->store->lockValue(), 'the lock is released between batches' );
		self::assertFalse( get_option( Options::DB_VERSION ), 'not current yet' );

		// The job, as Action Scheduler would run it, until done.
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'still one: the unique flag drops the second' );
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertSame( RunResult::Finished, $runner->run( 0 ) );
		self::assertSame( $whole->rows, $stopped->rows, 'the same rows, no duplicate' );
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		self::assertSame( 'done', get_option( Options::MIGRATIONS )['batch_1']['status'] );
		self::assertSame( RunResult::NotNeeded, $runner->run() );
	}

	/**
	 * Write the states' row as another process would: straight to the table, past this request's options cache.
	 *
	 * @param array<string, array<string, mixed>> $states States by migration.
	 */
	private function writeStatesRow( array $states ): void {
		global $wpdb;
		$wpdb->replace( $wpdb->options, array( 'option_name' => Options::MIGRATIONS, 'option_value' => serialize( $states ), 'autoload' => 'off' ) ); // phpcs:ignore
	}

	/**
	 * The states' row as the table holds it.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function statesRow(): array {
		global $wpdb;
		$row = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, Options::MIGRATIONS ) ); // phpcs:ignore
		return is_string( $row ) ? (array) unserialize( $row ) : array(); // phpcs:ignore
	}

	public function test_a_state_written_to_the_table_after_the_request_read_it_is_the_one_the_run_resumes_from(): void {
		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$m      = new BatchMigration( 10, 3 );
		$runner = $this->runner( $m );
		self::assertTrue( $runner->due(), 'init: this request reads the states, and finds none' );

		// Another process ran three batches, saved its cursor and released the lock.
		$this->writeStatesRow( array( 'batch_1' => MigrationState::pending( 'batch_1' )->started( 1 )->advanced( Cursor::at( 9 ) )->toArray() ) );

		self::assertSame( RunResult::Finished, $runner->run() );
		self::assertSame( array( 10 ), $m->rows, 'resumed from the cursor in the table: no row written twice' );
		self::assertSame( 1, $this->statesRow()['batch_1']['attempts'], 'a continued run, not a new attempt' );
	}

	public function test_a_wait_written_to_the_table_after_the_request_read_it_is_kept(): void {
		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$m      = new BatchMigration( 10, 3 );
		$runner = $this->runner( $m );
		self::assertTrue( $runner->due(), 'init: this request reads the states, and finds none' );

		// Another process failed and recorded a wait of five minutes.
		$failed = MigrationState::pending( 'batch_1' )->started( 1 )->failed( 'no', time() + 300 )->toArray(); // phpcs:ignore -- the test's own clock
		$this->writeStatesRow( array( 'batch_1' => $failed ) );

		self::assertSame( RunResult::Waiting, $runner->run() );
		self::assertSame( 0, $m->batches );
		self::assertSame( $failed, $this->statesRow()['batch_1'], 'the failure and its wait are not erased' );
		self::assertNull( $this->store->lockValue(), 'the lock taken to look is released' );
	}

	public function test_a_run_that_loses_its_lock_during_a_batch_writes_no_state(): void {
		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$theirs = array( 'slow_1' => MigrationState::pending( 'slow_1' )->started( 1 )->advanced( Cursor::at( 9 ) )->toArray() );
		$write  = function () use ( $theirs ): void {
			global $wpdb;
			// The batch outlived the lock: another process took it over and has already run further.
			$wpdb->update( $wpdb->options, array( 'option_value' => '1900000000:' . str_repeat( 'f', 16 ) ), array( 'option_name' => Options::MIGRATION_LOCK ) );
			$this->writeStatesRow( $theirs );
		};
		$slow   = new class( $write ) implements Migration {
			public function __construct( private \Closure $write ) {}
			public function key(): string {
				return 'slow_1';
			}
			public function run( Cursor $cursor ): Cursor {
				unset( $cursor );
				( $this->write )();
				return Cursor::at( 3 );
			}
		};

		self::assertSame( RunResult::Locked, $this->runner( $slow )->run() );
		self::assertSame( 9, $this->statesRow()['slow_1']['cursor']['position'], 'the new holder\'s cursor stands' );
		self::assertStringStartsWith( '1900000000:', (string) $this->store->lockValue(), 'and so does its lock' );
		$this->store->clearLock();
	}

	public function test_a_job_out_of_budget_queues_its_own_continuation_from_inside_action_schedulers_runner(): void {
		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$m      = new BatchMigration( 10, 3 );
		$runner = $this->runner( $m );
		// The job as the plugin hooks it, over a migration of four batches and with no budget: one batch a run.
		remove_all_actions( Options::JOB_MIGRATE );
		add_action(
			Options::JOB_MIGRATE,
			static function () use ( $runner ): void {
				$runner->run( 0, true );
			}
		);
		$runner->queue();
		$first = self::pendingJobs( Options::JOB_MIGRATE );
		self::assertCount( 1, $first );

		// Action Scheduler marks the action as running, then runs it: its unique check now matches the job itself.
		\ActionScheduler_QueueRunner::instance()->process_action( $first[0], 'test' );

		self::assertSame( 1, $m->batches, 'the job ran one batch' );
		$next = self::pendingJobs( Options::JOB_MIGRATE );
		self::assertCount( 1, $next, 'and queued the rest itself, without waiting for a request to notice' );
		self::assertNotSame( $first[0], $next[0] );

		// Until it is done: each run queues the next, and the last queues nothing.
		for ( $i = 0; $i < 3; $i++ ) {
			$pending = self::pendingJobs( Options::JOB_MIGRATE );
			self::assertCount( 1, $pending );
			\ActionScheduler_QueueRunner::instance()->process_action( $pending[0], 'test' );
		}
		self::assertSame( array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ), $m->rows );
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ) );
	}

	public function test_a_failure_is_logged_under_the_plugin_source_and_leaves_the_version_behind(): void {
		delete_option( Options::DB_VERSION );
		$entries = array();
		$logger  = new class( $entries ) implements \WC_Logger_Interface {
			/**
			 * @param array<int, array<string, mixed>> $entries By reference.
			 */
			public function __construct( public array &$entries ) {}
			public function add( $handle, $message, $level = \WC_Log_Levels::NOTICE ) {}
			public function log( $level, $message, $context = array() ) {
				$this->entries[] = array( 'level' => $level, 'message' => $message, 'context' => $context );
			}
			public function emergency( $message, $context = array() ) {}
			public function alert( $message, $context = array() ) {}
			public function critical( $message, $context = array() ) {}
			public function error( $message, $context = array() ) {}
			public function warning( $message, $context = array() ) {}
			public function notice( $message, $context = array() ) {}
			public function info( $message, $context = array() ) {}
			public function debug( $message, $context = array() ) {}
		};
		add_filter( 'woocommerce_logging_class', static fn() => $logger );

		self::assertSame( RunResult::Failed, $this->runner( new ThrowingMigration() )->run() );

		self::assertCount( 1, $logger->entries );
		self::assertSame( 'error', $logger->entries[0]['level'] );
		self::assertSame( Options::LOG_SOURCE, $logger->entries[0]['context']['source'], 'wc_get_logger() source' );
		self::assertSame( 'throwing_1', $logger->entries[0]['context']['migration'] );
		self::assertStringContainsString( 'the disk is full', $logger->entries[0]['message'] );
		self::assertFalse( get_option( Options::DB_VERSION ), 'left behind' );
		self::assertSame( 'failed', get_option( Options::MIGRATIONS )['throwing_1']['status'] );
		self::assertNull( $this->store->lockValue(), 'released' );
	}

	public function test_the_real_log_takes_the_entry(): void {
		// WooCommerce's own logger with the file or database handler: the call must be valid (level, source).
		$this->service( Logger::class )->log( Logger::INFO, 'Creative Skateboard Builder test entry', array( 'migration' => 'noop_1' ) );
		self::assertTrue( true, 'no notice, no doing_it_wrong' );
	}

	public function test_the_queue_is_action_scheduler_with_the_plugin_group_and_the_unique_flag(): void {
		$queue = $this->service( Queue::class );
		self::assertFalse( $queue->isPending( Options::JOB_MIGRATE ) );
		$queue->enqueueUnique( Options::JOB_MIGRATE );
		$queue->enqueueUnique( Options::JOB_MIGRATE );
		self::assertTrue( $queue->isPending( Options::JOB_MIGRATE ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'queued once' );
		$action = \ActionScheduler::store()->fetch_action( (string) self::pendingJobs( Options::JOB_MIGRATE )[0] );
		self::assertSame( Options::QUEUE_GROUP, $action->get_group() );
		$queue->cancelAll( Options::JOB_MIGRATE );
		self::assertFalse( $queue->isPending( Options::JOB_MIGRATE ) );
	}

	public function test_the_clock_is_utc(): void {
		$now = $this->service( Clock::class )->now();
		self::assertSame( 'UTC', $now->getTimezone()->getName() );
		self::assertEqualsWithDelta( time(), $now->getTimestamp(), 2 );
	}
}
