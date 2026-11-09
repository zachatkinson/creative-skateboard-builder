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
 * in order, one batch at a time, refreshing the lock and then saving the cursor after every batch, until all are done or
 * the time budget runs out, in which case a `cbuild_migrate` job continues from the saved cursors (spec: Upgrades and
 * the public API; D-021, D-022). A migration that throws is recorded as failed with its error and cursor, logged,
 * and tried again after a wait that grows with each attempt; the version is recorded only when every migration is
 * done, so a half-finished upgrade runs again. A run that finds its lock taken over stops at once and leaves the lock
 * to its new holder (D-033). Pure PHP: the database, the clock, the log and the queue come in through ports.
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
	 * Seconds an inline run on a manager's admin page works before queuing the rest.
	 */
	public const INLINE_BUDGET = 2;

	/**
	 * Seconds to wait after a failed attempt, by attempt; the last holds for every attempt after it.
	 */
	public const RETRY_DELAYS = array( 60, 300, 1800, 3600 );

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @phpstan-impure A run in between changes the answer.
	 */
	public function needed(): bool {
		return version_compare( $this->store->schemaVersion(), MigrationRegistry::VERSION, '<' );
	}

	/**
	 * Whether there is work to do now: the version is behind and no failed migration is still waiting. One read of
	 * the states, and only while the version is behind.
	 *
	 * @since 0.1.0
	 *
	 * @phpstan-impure The clock and a run in between change the answer.
	 */
	public function due(): bool {
		return $this->needed() && ! $this->waiting( $this->clock->now()->getTimestamp() );
	}

	/**
	 * Seconds to wait after a failed attempt.
	 *
	 * @since 0.1.0
	 *
	 * @param int $attempts How many attempts have been made.
	 */
	public static function retryDelay( int $attempts ): int {
		return self::RETRY_DELAYS[ min( max( $attempts, 1 ), count( self::RETRY_DELAYS ) ) - 1 ];
	}

	/**
	 * Whether a failed migration's wait has not passed, by the copy of the states this request already holds.
	 *
	 * @param int $now UTC timestamp.
	 */
	private function waiting( int $now ): bool {
		return self::anyWaiting( $this->store->states(), $now );
	}

	/**
	 * Whether any of these states is a failure whose wait has not passed.
	 *
	 * @param array<string, array<string, mixed>> $states States by migration.
	 * @param int                                 $now    UTC timestamp.
	 */
	private static function anyWaiting( array $states, int $now ): bool {
		foreach ( $states as $key => $data ) {
			if ( MigrationState::fromArray( $key, $data )->isWaiting( $now ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Queue one job to run the migrations off this request (D-022). The queue drops a second while one is pending.
	 *
	 * @since 0.1.0
	 */
	public function queue(): void {
		$this->queue->enqueueUnique( Options::JOB_MIGRATE );
	}

	/**
	 * Run what is pending, within the budget.
	 *
	 * @since 0.1.0
	 *
	 * @param int  $budgetSeconds Seconds to work before queuing the rest; 0 runs one batch.
	 * @param bool $insideJob     Whether this run is the `cbuild_migrate` action itself. Action Scheduler's unique
	 *                            check also matches the action that is running, so from inside it the rest, or the
	 *                            retry, is queued as a plain action; the lock keeps two runs apart (D-043).
	 */
	public function run( int $budgetSeconds = self::BUDGET, bool $insideJob = false ): RunResult {
		if ( ! $this->needed() ) {
			return RunResult::NotNeeded;
		}
		$now = $this->clock->now()->getTimestamp();
		if ( $this->waiting( $now ) ) {
			return RunResult::Waiting;
		}
		if ( ! $this->store->acquireLock( $now, self::LOCK_TTL ) ) {
			return RunResult::Locked;
		}
		try {
			// What this request loaded may be stale: another process may have finished, moved a cursor on or recorded
			// a failure before the lock was taken. Under the lock the version and the states come from the table, and
			// only this run writes them (D-043).
			if ( version_compare( $this->store->freshSchemaVersion(), MigrationRegistry::VERSION, '>=' ) ) {
				return RunResult::NotNeeded;
			}
			$states = $this->store->freshStates();
			if ( self::anyWaiting( $states, $now ) ) {
				return RunResult::Waiting;
			}
			$deadline = $now + $budgetSeconds;
			$worked   = false;
			foreach ( $this->registry->all() as $migration ) {
				$state = MigrationState::fromArray( $migration->key(), $states[ $migration->key() ] ?? null );
				if ( $state->isDone() ) {
					continue;
				}
				// Between migrations, as between batches, the budget must still be open. The lock was refreshed when
				// the migration before this one was saved.
				if ( $worked && $now >= $deadline ) {
					$this->continueLater( $insideJob );
					return RunResult::Continued;
				}
				$worked = true;
				if ( MigrationState::RUNNING !== $state->status ) {
					// Pending or failed: a new attempt. A running one is a continued run, resumed from its cursor.
					$state = $state->started( $now );
					$this->store->save( $state->key, $state->toArray() );
				}
				try {
					while ( true ) {
						$cursor = $migration->run( $state->cursor );
						$now    = $this->clock->now()->getTimestamp();
						// The lock before the state: a run whose batch outlived its lock was taken over, and writes
						// nothing over the new holder's cursor, nor the version, which is the new holder's to record.
						if ( ! $this->store->refreshLock( $now ) ) {
							return RunResult::Locked;
						}
						if ( $cursor->done ) {
							$state = $state->finished( $now );
							$this->store->save( $state->key, $state->toArray() );
							break;
						}
						$state = $state->advanced( $cursor );
						$this->store->save( $state->key, $state->toArray() );
						if ( $now >= $deadline ) {
							$this->continueLater( $insideJob );
							return RunResult::Continued;
						}
					}
				} catch ( Throwable $e ) {
					$failedAt = $this->clock->now()->getTimestamp();
					$retryAt  = $failedAt + self::retryDelay( $state->attempts );
					$this->logger->log(
						Logger::ERROR,
						sprintf( 'Migration %s failed on attempt %d at cursor %s: %s', $migration->key(), $state->attempts, null === $state->cursor->position ? 'start' : (string) $state->cursor->position, $e->getMessage() ),
						array(
							'migration' => $migration->key(),
							'attempt'   => $state->attempts,
							'cursor'    => $state->cursor->position,
							'exception' => $e::class,
							'retry_at'  => $retryAt,
						)
					);
					// As after a batch: a run that was taken over records nothing and schedules nothing.
					if ( ! $this->store->refreshLock( $failedAt ) ) {
						return RunResult::Locked;
					}
					$this->store->save( $state->key, $state->failed( $e->getMessage(), $retryAt )->toArray() );
					if ( $insideJob ) {
						$this->queue->schedule( $retryAt, Options::JOB_MIGRATE );
					} else {
						$this->queue->scheduleUnique( $retryAt, Options::JOB_MIGRATE );
					}
					return RunResult::Failed;
				}
			}
			$this->store->recordSchemaVersion( MigrationRegistry::VERSION );
			return RunResult::Finished;
		} finally {
			$this->store->releaseLock();
		}
	}

	/**
	 * Queue the rest of a run that used up its budget.
	 *
	 * @param bool $insideJob Whether the run is the queued action itself.
	 */
	private function continueLater( bool $insideJob ): void {
		if ( $insideJob ) {
			$this->queue->enqueue( Options::JOB_MIGRATE );
			return;
		}
		$this->queue();
	}
}
