<?php
/**
 * Runs the migrations.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Support\Options;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * When `cbuild_db_version` is behind `MigrationRegistry::VERSION`: takes the lock, runs each migration not yet done
 * in order, one batch at a time, saving the cursor after every batch and refreshing the lock, until all are done or
 * the time budget runs out, in which case a `cbuild_migrate` job continues from the saved cursors (spec: Upgrades and
 * the public API; D-021, D-022). A migration that throws is recorded as failed with its error and cursor, logged,
 * and left for the next run; the version is recorded only when every migration is done, so a half-finished upgrade
 * runs again. Pure PHP: the database, the clock, the log and the queue come in through ports.
 */
final class MigrationRunner {

	/**
	 * Seconds after which a held lock counts as abandoned by a crashed request.
	 */
	public const LOCK_TTL = 300;

	/**
	 * Seconds a run works before queuing the rest. Well under PHP's default execution time and Action Scheduler's.
	 */
	public const BUDGET = 20;

	/**
	 * Constructor.
	 *
	 * @param MigrationRegistry $registry The migrations, oldest first.
	 * @param MigrationStore    $store    Versions, states and the lock.
	 * @param Clock             $clock    The clock.
	 * @param Logger            $logger   The log.
	 * @param Queue             $queue    The queue a long run continues on.
	 */
	public function __construct(
		private MigrationRegistry $registry,
		private MigrationStore $store,
		private Clock $clock,
		private Logger $logger,
		private Queue $queue
	) {}

	/**
	 * Whether the stored schema version is behind the code's.
	 *
	 * @phpstan-impure A run in between changes the answer.
	 */
	public function needed(): bool {
		return version_compare( $this->store->schemaVersion(), MigrationRegistry::VERSION, '<' );
	}

	/**
	 * Queue one job to run the migrations off this request (D-022). The queue drops a second while one is pending.
	 */
	public function queue(): void {
		$this->queue->enqueueUnique( Options::JOB_MIGRATE );
	}

	/**
	 * Run what is pending, within the budget.
	 *
	 * @param int $budgetSeconds Seconds to work before queuing the rest; 0 runs one batch.
	 */
	public function run( int $budgetSeconds = self::BUDGET ): RunResult {
		if ( ! $this->needed() ) {
			return RunResult::NotNeeded;
		}
		$now = $this->clock->now()->getTimestamp();
		if ( ! $this->store->acquireLock( $now, self::LOCK_TTL ) ) {
			return RunResult::Locked;
		}
		try {
			// The version this request loaded may be stale: another request may have finished before the lock was taken.
			if ( version_compare( $this->store->freshSchemaVersion(), MigrationRegistry::VERSION, '>=' ) ) {
				return RunResult::NotNeeded;
			}
			$deadline = $now + $budgetSeconds;
			foreach ( $this->registry->all() as $migration ) {
				$state = MigrationState::fromArray( $migration->key(), $this->store->state( $migration->key() ) );
				if ( $state->isDone() ) {
					continue;
				}
				if ( MigrationState::RUNNING !== $state->status ) {
					// Pending or failed: a new attempt. A running one is a continued run, resumed from its cursor.
					$state = $state->started( $now );
					$this->store->save( $state->key, $state->toArray() );
				}
				try {
					while ( true ) {
						$cursor = $migration->run( $state->cursor );
						$now    = $this->clock->now()->getTimestamp();
						if ( $cursor->done ) {
							$state = $state->finished( $now );
							$this->store->save( $state->key, $state->toArray() );
							break;
						}
						$state = $state->advanced( $cursor );
						$this->store->save( $state->key, $state->toArray() );
						$this->store->refreshLock( $now );
						if ( $now >= $deadline ) {
							$this->queue();
							return RunResult::Continued;
						}
					}
				} catch ( Throwable $e ) {
					$this->store->save( $state->key, $state->failed( $e->getMessage() )->toArray() );
					$this->logger->log(
						Logger::ERROR,
						sprintf( 'Migration %s failed on attempt %d at cursor %s: %s', $migration->key(), $state->attempts, null === $state->cursor->position ? 'start' : (string) $state->cursor->position, $e->getMessage() ),
						array(
							'migration' => $migration->key(),
							'attempt'   => $state->attempts,
							'cursor'    => $state->cursor->position,
							'exception' => $e::class,
						)
					);
					return RunResult::Failed;
				}
			}
			$this->store->recordSchemaVersion( MigrationRegistry::VERSION );
			return RunResult::Finished;
		} finally {
			$this->store->releaseLock();
		}
	}
}
