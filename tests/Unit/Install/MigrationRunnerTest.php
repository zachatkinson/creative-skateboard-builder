<?php
/**
 * MigrationRunner.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Migration;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationState;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationStatus;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RunResult;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\BatchMigration;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\CountingMigration;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryMigrationStore;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\ThrowingMigration;
use PHPUnit\Framework\TestCase;

/**
 * The spec's guarantees (Upgrades and the public API; docs/ROADMAP.md, milestone 1): a migration runs once, the lock
 * keeps a second run out, a run stopped halfway resumes and matches an uninterrupted run, a throwing migration releases
 * the lock, is logged and leaves the version behind.
 */
final class MigrationRunnerTest extends TestCase {

	private InMemoryMigrationStore $store;
	private FakeClock $clock;
	private FakeLogger $logger;
	private FakeQueue $queue;

	protected function setUp(): void {
		$this->store  = new InMemoryMigrationStore();
		$this->clock  = new FakeClock();
		$this->logger = new FakeLogger();
		$this->queue  = new FakeQueue();
	}

	private function runner( Migration ...$migrations ): MigrationRunner {
		return new MigrationRunner( new MigrationRegistry( ...$migrations ), $this->store, $this->clock, $this->logger, $this->queue );
	}

	public function test_a_migration_runs_once(): void {
		$m      = new CountingMigration();
		$runner = $this->runner( $m );
		self::assertTrue( $runner->needed() );

		self::assertSame( RunResult::Finished, $runner->run() );
		self::assertSame( 1, $m->runs );
		self::assertSame( MigrationRegistry::VERSION, $this->store->version );
		self::assertTrue( $this->store->stateOf( 'counting_1' )?->isDone() );
		self::assertNull( $this->store->lock, 'released' );
		self::assertSame( 1, $this->store->releases );

		self::assertFalse( $runner->needed() );
		self::assertSame( RunResult::NotNeeded, $runner->run() );
		self::assertSame( 1, $m->runs, 'not run again' );
		self::assertSame( 1, $this->store->releases, 'no lock taken when not needed' );
	}

	public function test_migrations_run_in_order_and_done_ones_are_skipped(): void {
		$a = new CountingMigration( 'a' );
		$b = new CountingMigration( 'b' );
		$this->store->seed( MigrationState::pending( 'a' )->started( 1 )->finished( 2 ) );
		self::assertSame( RunResult::Finished, $this->runner( $a, $b )->run() );
		self::assertSame( 0, $a->runs, 'already done' );
		self::assertSame( 1, $b->runs );
	}

	public function test_a_held_lock_keeps_a_second_run_out(): void {
		$m = new CountingMigration();
		$this->store->acquireLock( $this->clock->timestamp() - 10, MigrationRunner::LOCK_TTL );
		self::assertSame( RunResult::Locked, $this->runner( $m )->run() );
		self::assertSame( 0, $m->runs );
		self::assertSame( '0', $this->store->version );
		self::assertSame( 0, $this->store->releases, 'a lock it does not hold is not released' );
	}

	public function test_an_expired_lock_is_taken_over(): void {
		$m = new CountingMigration();
		$this->store->acquireLock( $this->clock->timestamp() - MigrationRunner::LOCK_TTL - 1, MigrationRunner::LOCK_TTL );
		self::assertSame( RunResult::Finished, $this->runner( $m )->run() );
		self::assertSame( 1, $m->runs );
	}

	public function test_the_version_is_read_fresh_under_the_lock(): void {
		$m = new CountingMigration();
		// Another request finished between this one's needed() and its lock: the store now says current.
		$store = new class() extends InMemoryMigrationStore {
			public function acquireLock( int $now, int $ttl ): bool {
				$this->version = MigrationRegistry::VERSION;
				return parent::acquireLock( $now, $ttl );
			}
		};
		$runner = new MigrationRunner( new MigrationRegistry( $m ), $store, $this->clock, $this->logger, $this->queue );
		self::assertSame( RunResult::NotNeeded, $runner->run() );
		self::assertSame( 0, $m->runs );
		self::assertSame( 1, $store->freshReads );
		self::assertSame( 1, $store->releases, 'the lock it took is released' );
	}

	public function test_a_run_stopped_halfway_resumes_and_matches_an_uninterrupted_run(): void {
		$whole = new BatchMigration( 10, 3 );
		self::assertSame( RunResult::Finished, $this->runner( $whole )->run() );
		self::assertSame( 4, $whole->batches );

		$this->setUp();
		$stopped = new BatchMigration( 10, 3 );
		$runner  = $this->runner( $stopped );
		// A budget of zero seconds: one batch, then the rest is queued.
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertSame( array( 1, 2, 3 ), $stopped->rows );
		self::assertSame( 3, $this->store->stateOf( 'batch_1' )?->cursor->position, 'the cursor is saved after the batch' );
		self::assertSame( MigrationStatus::Running, $this->store->stateOf( 'batch_1' )?->status );
		self::assertSame( 1, $this->queue->calls[ Options::JOB_MIGRATE ] ?? 0, 'one job queued' );
		self::assertNull( $this->store->lock, 'released between runs' );
		self::assertSame( '0', $this->store->version, 'not current until done' );
		self::assertTrue( $runner->needed() );

		// The job runs again, and again, each one batch.
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertSame( RunResult::Finished, $runner->run( 0 ) );
		self::assertSame( $whole->rows, $stopped->rows, 'the same rows, no duplicate' );
		self::assertSame( 4, $stopped->batches );
		self::assertSame( MigrationRegistry::VERSION, $this->store->version );
		self::assertTrue( $this->store->stateOf( 'batch_1' )?->isDone() );
		self::assertSame( 1, $this->store->stateOf( 'batch_1' )?->attempts, 'one attempt: a continued run is not a new one' );
	}

	public function test_the_lock_is_refreshed_after_every_batch_within_the_budget(): void {
		$m = new BatchMigration( 9, 3 );
		self::assertSame( RunResult::Finished, $this->runner( $m )->run() );
		self::assertSame( 3, $this->store->refreshes, 'after each unfinished batch, and once before the version is recorded' );
	}

	public function test_the_budget_is_measured_by_the_clock(): void {
		$m = new BatchMigration( 9, 3 );
		// Each batch takes 15 seconds; the budget is 20, so two batches run before the rest is queued.
		$clock = new class() extends FakeClock {
			public function now(): \DateTimeImmutable {
				$this->advance( 15 );
				return parent::now();
			}
		};
		$runner = new MigrationRunner( new MigrationRegistry( $m ), $this->store, $clock, $this->logger, $this->queue );
		self::assertSame( RunResult::Continued, $runner->run( 20 ) );
		self::assertSame( 2, $m->batches );
	}

	public function test_a_throwing_migration_releases_the_lock_is_logged_and_leaves_the_version_behind(): void {
		$before = new CountingMigration( 'before' );
		$after  = new CountingMigration( 'after' );
		$runner = $this->runner( $before, new ThrowingMigration(), $after );

		self::assertSame( RunResult::Failed, $runner->run() );
		self::assertSame( 1, $before->runs );
		self::assertSame( 0, $after->runs, 'nothing after the failure runs' );
		self::assertNull( $this->store->lock, 'released' );
		self::assertSame( '0', $this->store->version, 'left behind, so it runs again' );
		self::assertTrue( $runner->needed() );

		$state = $this->store->stateOf( 'throwing_1' );
		self::assertNotNull( $state );
		self::assertSame( MigrationStatus::Failed, $state->status );
		self::assertSame( 'the disk is full', $state->error );
		self::assertSame( 5, $state->cursor->position, 'the cursor of the batch that failed is kept' );
		self::assertSame( 1, $state->attempts );

		self::assertCount( 1, $this->logger->lines );
		$line = $this->logger->lines[0];
		self::assertSame( Logger::ERROR, $line['level'] );
		self::assertStringContainsString( 'throwing_1', $line['message'] );
		self::assertStringContainsString( 'cursor 5', $line['message'] );
		self::assertStringContainsString( 'the disk is full', $line['message'] );
		self::assertStringContainsString( 'attempt 1', $line['message'] );
		self::assertSame( 'throwing_1', $line['context']['migration'] );
		self::assertSame( 5, $line['context']['cursor'] );
		self::assertSame( \RuntimeException::class, $line['context']['exception'] );
		self::assertSame( array(), $this->queue->calls, 'a failure is not queued to run again at once' );
		self::assertSame( $line['context']['retry_at'], $this->queue->scheduled[ Options::JOB_MIGRATE ], 'it is scheduled for after its wait' );
	}

	public function test_a_failed_migration_is_retried_on_the_next_run_from_its_cursor(): void {
		$this->store->seed( MigrationState::pending( 'counting_1' )->started( 1 )->advanced( \CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor::at( 2 ) )->failed( 'x', 2 ) );
		$m = new CountingMigration();
		self::assertSame( RunResult::Finished, $this->runner( $m )->run() );
		self::assertSame( 1, $m->runs );
		self::assertSame( 2, $this->store->stateOf( 'counting_1' )?->attempts );
		self::assertSame( '', $this->store->stateOf( 'counting_1' )?->error );
	}

	public function test_a_run_whose_lock_was_taken_over_stops_and_leaves_the_new_holders_lock(): void {
		$store = $this->store;
		$slow  = new class( $store ) implements Migration {
			public int $batches = 0;
			public function __construct( private InMemoryMigrationStore $store ) {}
			public function key(): string {
				return 'slow_1';
			}
			public function run( Cursor $cursor ): Cursor {
				unset( $cursor );
				++$this->batches;
				// The batch took longer than the lock lives: another request took the lock over meanwhile, and has
				// already run further.
				$this->store->takenOver( 1_900_000_000 );
				$this->store->saved['slow_1'] = MigrationState::pending( 'slow_1' )->started( 1_900_000_000 )->advanced( Cursor::at( 9 ) )->toArray();
				return Cursor::at( $this->batches );
			}
		};
		self::assertSame( RunResult::Locked, $this->runner( $slow )->run() );
		self::assertSame( 1, $slow->batches, 'it stops at once' );
		self::assertSame( 9, $this->store->stateOf( 'slow_1' )?->cursor->position, 'and writes nothing over the new holder\'s state' );
		self::assertSame( 1_900_000_000, $this->store->lock, 'the new holder\'s lock is neither refreshed over nor released' );
		self::assertSame( 0, $this->store->releases );
		self::assertSame( '0', $this->store->version );
	}

	public function test_a_failure_after_the_lock_was_taken_over_records_nothing(): void {
		$store   = $this->store;
		$failing = new class( $store ) implements Migration {
			public function __construct( private InMemoryMigrationStore $store ) {}
			public function key(): string {
				return 'failing_1';
			}
			public function run( Cursor $cursor ): Cursor {
				unset( $cursor );
				$this->store->takenOver( 1_900_000_000 );
				$this->store->saved['failing_1'] = MigrationState::pending( 'failing_1' )->started( 1_900_000_000 )->advanced( Cursor::at( 9 ) )->toArray();
				throw new \RuntimeException( 'too late' );
			}
		};
		self::assertSame( RunResult::Locked, $this->runner( $failing )->run() );
		self::assertSame( MigrationStatus::Running, $this->store->stateOf( 'failing_1' )?->status, 'the new holder\'s state stands' );
		self::assertSame( 9, $this->store->stateOf( 'failing_1' )?->cursor->position );
		self::assertSame( array(), $this->queue->scheduled, 'and no retry is scheduled over its run' );
		self::assertSame( 1_900_000_000, $this->store->lock );
	}

	public function test_under_the_lock_the_states_are_read_from_the_table_not_from_the_requests_earlier_copy(): void {
		$m = new BatchMigration( 10, 3 );
		// This request read the states on init. Since then another process ran three batches and released the lock.
		$this->store->cached = array();
		$this->store->saved  = array( 'batch_1' => MigrationState::pending( 'batch_1' )->started( 1 )->advanced( Cursor::at( 9 ) )->toArray() );

		self::assertSame( RunResult::Finished, $this->runner( $m )->run() );
		self::assertSame( array( 10 ), $m->rows, 'resumed from the cursor in the table: no row is written twice' );
		self::assertSame( 1, $this->store->stateOf( 'batch_1' )?->attempts, 'a continued run, not a new attempt' );
		self::assertSame( 1, $this->store->freshStateReads, 'one read of the table, under the lock' );
	}

	public function test_a_wait_recorded_since_the_request_first_read_is_kept(): void {
		$m = new CountingMigration();
		// This request read the states on init. Since then another process failed and recorded its wait.
		$this->store->cached = array();
		$failed              = MigrationState::pending( 'counting_1' )->started( 1 )->failed( 'no', $this->clock->timestamp() + 300 )->toArray();
		$this->store->saved  = array( 'counting_1' => $failed );

		self::assertSame( RunResult::Waiting, $this->runner( $m )->run() );
		self::assertSame( 0, $m->runs );
		self::assertSame( $failed, $this->store->saved['counting_1'], 'the failure and its wait are not erased' );
		self::assertNull( $this->store->lock, 'the lock taken to look is released' );
	}

	public function test_inside_the_job_a_run_out_of_budget_queues_its_continuation_past_its_own_running_action(): void {
		// Action Scheduler's unique check sees the action that is running this very job, so a unique action is dropped.
		$this->queue->running[ Options::JOB_MIGRATE ] = true;
		$runner                                       = $this->runner( new BatchMigration( 10, 3 ) );

		self::assertSame( RunResult::Continued, $runner->run( 0, true ) );
		self::assertTrue( $this->queue->isPending( Options::JOB_MIGRATE ), 'the rest is queued' );
		self::assertSame( 1, $this->queue->plain[ Options::JOB_MIGRATE ] ?? 0, 'by one plain action' );
		self::assertSame( array(), $this->queue->calls, 'and no unique one is asked for as well' );
	}

	public function test_inside_the_job_a_failure_schedules_its_retry_past_its_own_running_action(): void {
		$this->queue->running[ Options::JOB_MIGRATE ] = true;
		$start                                        = $this->clock->timestamp();

		self::assertSame( RunResult::Failed, $this->runner( new ThrowingMigration() )->run( MigrationRunner::BUDGET, true ) );
		self::assertSame( $start + 60, $this->queue->scheduled[ Options::JOB_MIGRATE ] ?? null, 'the retry is scheduled' );
		self::assertSame( 1, $this->queue->plainScheduled[ Options::JOB_MIGRATE ] ?? 0, 'by one plain action' );
	}

	public function test_outside_the_job_the_continuation_and_the_retry_stay_unique(): void {
		self::assertSame( RunResult::Continued, $this->runner( new BatchMigration( 10, 3 ) )->run( 0 ) );
		self::assertSame( array(), $this->queue->plain, 'a request never adds a second job beside a pending one' );
		self::assertSame( 1, $this->queue->calls[ Options::JOB_MIGRATE ] );

		$this->setUp();
		$start = $this->clock->timestamp();
		self::assertSame( RunResult::Failed, $this->runner( new ThrowingMigration() )->run() );
		self::assertSame( $start + 60, $this->queue->scheduled[ Options::JOB_MIGRATE ] ?? null, 'the retry is scheduled' );
		self::assertSame( array(), $this->queue->plainScheduled, 'as a unique action' );
	}

	public function test_a_lock_lost_during_the_last_batch_records_no_version(): void {
		$store = $this->store;
		$last  = new class( $store ) implements Migration {
			public function __construct( private InMemoryMigrationStore $store ) {}
			public function key(): string {
				return 'last_1';
			}
			public function run( Cursor $cursor ): Cursor {
				unset( $cursor );
				$this->store->takenOver( 1_900_000_000 );
				return Cursor::done();
			}
		};
		self::assertSame( RunResult::Locked, $this->runner( $last )->run() );
		self::assertSame( '0', $this->store->version, 'the new holder records it' );
		self::assertSame( 1_900_000_000, $this->store->lock );
	}

	public function test_the_lock_is_refreshed_and_the_budget_checked_between_migrations(): void {
		$first  = new CountingMigration( 'first' );
		$second = new CountingMigration( 'second' );
		// Every reading of the clock is 25 seconds on, so the first migration alone uses up a budget of 20.
		$clock = new class() extends FakeClock {
			public function now(): \DateTimeImmutable {
				$this->advance( 25 );
				return parent::now();
			}
		};
		$runner = new MigrationRunner( new MigrationRegistry( $first, $second ), $this->store, $clock, $this->logger, $this->queue );
		self::assertSame( RunResult::Continued, $runner->run( 20 ) );
		self::assertSame( 1, $first->runs );
		self::assertSame( 0, $second->runs, 'left for the queued job' );
		self::assertSame( 1, $this->store->refreshes, 'a migration that ends in one batch refreshes the lock too' );
		self::assertSame( 1, $this->queue->calls[ Options::JOB_MIGRATE ] );
		self::assertSame( '0', $this->store->version );
	}

	public function test_a_budget_used_to_the_second_stops_before_the_next_migration(): void {
		$first  = new CountingMigration( 'first' );
		$second = new CountingMigration( 'second' );
		// A budget of 0 is used up the moment the first migration ends, with the clock standing still.
		self::assertSame( RunResult::Continued, $this->runner( $first, $second )->run( 0 ) );
		self::assertSame( 1, $first->runs );
		self::assertSame( 0, $second->runs );
	}

	public function test_an_attempt_is_recorded_as_running_before_its_first_batch(): void {
		$store = $this->store;
		$seen  = new class( $store ) implements Migration {
			public ?MigrationState $during = null;

			public function __construct( private InMemoryMigrationStore $store ) {}

			public function key(): string {
				return 'seen_1';
			}

			public function run( Cursor $cursor ): Cursor {
				$this->during = $this->store->stateOf( 'seen_1' );
				return Cursor::done();
			}
		};
		self::assertSame( RunResult::Finished, $this->runner( $seen )->run() );
		self::assertSame( MigrationStatus::Running, $seen->during?->status, 'a crash in the batch leaves the attempt on record' );
		self::assertSame( 1, $seen->during?->attempts );
	}

	public function test_a_failure_waits_before_the_next_attempt(): void {
		$throwing = new ThrowingMigration();
		$runner   = $this->runner( $throwing );
		$start    = $this->clock->timestamp();

		self::assertSame( RunResult::Failed, $runner->run() );
		self::assertSame( $start + 60, $this->store->stateOf( 'throwing_1' )?->retryAt );
		self::assertSame( $start + 60, $this->queue->scheduled[ Options::JOB_MIGRATE ], 'the retry is scheduled for then' );
		self::assertTrue( $runner->needed() );
		self::assertFalse( $runner->due(), 'behind, but not yet' );

		$this->clock->advance( 59 );
		self::assertSame( RunResult::Waiting, $runner->run() );
		self::assertCount( 1, $this->logger->lines, 'nothing ran, so nothing more is logged' );
		self::assertSame( 1, $this->store->stateOf( 'throwing_1' )?->attempts );
		self::assertNull( $this->store->lock, 'and no lock is taken to wait' );
		self::assertSame( 1, $this->store->releases, 'none taken, so none released: the one release is the failed run\'s' );
		self::assertSame(
			array(
				'migration' => 'throwing_1',
				'attempt'   => 1,
				'cursor'    => 5,
				'exception' => 'RuntimeException',
				'retry_at'  => $start + 60,
			),
			$this->logger->lines[0]['context'],
			'the log line carries what a shop owner needs to act on'
		);
		self::assertStringContainsString( 'failed on attempt 1 at cursor 5', $this->logger->lines[0]['message'] );

		$this->clock->advance( 1 );
		self::assertTrue( $runner->due() );
		$this->queue->cancelAll( Options::JOB_MIGRATE );
		self::assertSame( RunResult::Failed, $runner->run() );
		self::assertSame( 2, $this->store->stateOf( 'throwing_1' )?->attempts );
		self::assertSame( $start + 60 + 300, $this->store->stateOf( 'throwing_1' )?->retryAt, 'the second wait is longer' );
	}

	public function test_the_wait_grows_with_the_attempts_and_is_capped_at_an_hour(): void {
		self::assertSame( 60, MigrationRunner::retryDelay( 1 ) );
		self::assertSame( 300, MigrationRunner::retryDelay( 2 ) );
		self::assertSame( 1800, MigrationRunner::retryDelay( 3 ) );
		self::assertSame( 3600, MigrationRunner::retryDelay( 4 ) );
		self::assertSame( 3600, MigrationRunner::retryDelay( 40 ) );
		self::assertSame( 60, MigrationRunner::retryDelay( 0 ) );
	}

	public function test_a_current_schema_is_never_due(): void {
		$this->store->version = MigrationRegistry::VERSION;
		self::assertFalse( $this->runner( new CountingMigration() )->due() );
	}

	public function test_queue_asks_the_queue_each_time_and_the_queue_keeps_one(): void {
		$this->runner( new CountingMigration() )->queue();
		$this->runner( new CountingMigration() )->queue();
		self::assertSame( 2, $this->queue->calls[ Options::JOB_MIGRATE ] );
		self::assertTrue( $this->queue->isPending( Options::JOB_MIGRATE ) );
	}

	public function test_run_result_values_are_stable_for_the_log(): void {
		self::assertSame( 'not_needed', RunResult::NotNeeded->value );
		self::assertSame( 'locked', RunResult::Locked->value );
		self::assertSame( 'finished', RunResult::Finished->value );
		self::assertSame( 'continued', RunResult::Continued->value );
		self::assertSame( 'failed', RunResult::Failed->value );
		self::assertSame( 'waiting', RunResult::Waiting->value );
	}
}
