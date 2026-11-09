<?php
/**
 * MigrationRunner.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Migration;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationState;
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
		self::assertSame( MigrationState::RUNNING, $this->store->stateOf( 'batch_1' )?->status );
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
		self::assertSame( 2, $this->store->refreshes, 'after each unfinished batch' );
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
		self::assertSame( MigrationState::FAILED, $state->status );
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
		self::assertSame( array(), $this->queue->calls, 'a failure is not queued again by the runner' );
	}

	public function test_a_failed_migration_is_retried_on_the_next_run_from_its_cursor(): void {
		$this->store->seed( MigrationState::pending( 'counting_1' )->started( 1 )->advanced( \CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor::at( 2 ) )->failed( 'x' ) );
		$m = new CountingMigration();
		self::assertSame( RunResult::Finished, $this->runner( $m )->run() );
		self::assertSame( 1, $m->runs );
		self::assertSame( 2, $this->store->stateOf( 'counting_1' )?->attempts );
		self::assertSame( '', $this->store->stateOf( 'counting_1' )?->error );
	}

	public function test_queue_queues_one_job(): void {
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
	}
}
