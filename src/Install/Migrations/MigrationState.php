<?php
/**
 * One migration's recorded state.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What `cbuild_migrations` holds for one migration: its status, its cursor, when it started and finished, how many
 * runs it has had and the last error. Immutable; each change returns a new state for the store to save.
 */
final class MigrationState {

	public const PENDING = 'pending';
	public const RUNNING = 'running';
	public const DONE    = 'done';
	public const FAILED  = 'failed';

	/**
	 * Constructor.
	 *
	 * @param string   $key        The migration's key.
	 * @param string   $status     One of the status constants.
	 * @param Cursor   $cursor     Where the next batch starts.
	 * @param int|null $startedAt  When the first run began, UTC timestamp.
	 * @param int|null $finishedAt When it finished, UTC timestamp.
	 * @param int      $attempts   How many runs have started it.
	 * @param string   $error      The last failure's message, '' when none.
	 * @param int|null $retryAt    After a failure, the moment before which it is not tried again, UTC timestamp.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $status,
		public readonly Cursor $cursor,
		public readonly ?int $startedAt = null,
		public readonly ?int $finishedAt = null,
		public readonly int $attempts = 0,
		public readonly string $error = '',
		public readonly ?int $retryAt = null
	) {}

	/**
	 * A migration that has never run.
	 *
	 * @param string $key The migration's key.
	 */
	public static function pending( string $key ): self {
		return new self( $key, self::PENDING, Cursor::start() );
	}

	/**
	 * Whether it finished.
	 */
	public function isDone(): bool {
		return self::DONE === $this->status;
	}

	/**
	 * A run begins: running, one more attempt, the start kept from the first run.
	 *
	 * @param int $now UTC timestamp.
	 */
	public function started( int $now ): self {
		return new self( $this->key, self::RUNNING, $this->cursor, $this->startedAt ?? $now, null, $this->attempts + 1, '' );
	}

	/**
	 * A batch ended with a new cursor.
	 *
	 * @param Cursor $cursor Where the next batch starts.
	 */
	public function advanced( Cursor $cursor ): self {
		return new self( $this->key, self::RUNNING, $cursor, $this->startedAt, null, $this->attempts, '' );
	}

	/**
	 * Finished.
	 *
	 * @param int $now UTC timestamp.
	 */
	public function finished( int $now ): self {
		return new self( $this->key, self::DONE, Cursor::done(), $this->startedAt, $now, $this->attempts, '' );
	}

	/**
	 * Failed; the cursor stays, so the next run resumes from it, and not before `$retryAt`.
	 *
	 * @param string $error   The failure's message.
	 * @param int    $retryAt The moment before which it is not tried again, UTC timestamp.
	 */
	public function failed( string $error, int $retryAt ): self {
		return new self( $this->key, self::FAILED, $this->cursor, $this->startedAt, null, $this->attempts, $error, $retryAt );
	}

	/**
	 * Whether it failed and its wait has not passed.
	 *
	 * @param int $now UTC timestamp.
	 */
	public function isWaiting( int $now ): bool {
		return self::FAILED === $this->status && null !== $this->retryAt && $now < $this->retryAt;
	}

	/**
	 * For the store.
	 *
	 * @return array{status: string, cursor: array{position: int|string|null, done: bool}, started_at: int|null, finished_at: int|null, attempts: int, error: string, retry_at: int|null}
	 */
	public function toArray(): array {
		return array(
			'status'      => $this->status,
			'cursor'      => $this->cursor->toArray(),
			'started_at'  => $this->startedAt,
			'finished_at' => $this->finishedAt,
			'attempts'    => $this->attempts,
			'error'       => $this->error,
			'retry_at'    => $this->retryAt,
		);
	}

	/**
	 * From the store; anything malformed reads as pending.
	 *
	 * @param string $key  The migration's key.
	 * @param mixed  $data What was stored.
	 */
	public static function fromArray( string $key, mixed $data ): self {
		if ( ! is_array( $data ) ) {
			return self::pending( $key );
		}
		$status = $data['status'] ?? self::PENDING;
		if ( ! in_array( $status, array( self::PENDING, self::RUNNING, self::DONE, self::FAILED ), true ) ) {
			$status = self::PENDING;
		}
		$int = static fn( mixed $v ): ?int => is_int( $v ) ? $v : null;
		return new self(
			$key,
			$status,
			Cursor::fromArray( $data['cursor'] ?? null ),
			$int( $data['started_at'] ?? null ),
			$int( $data['finished_at'] ?? null ),
			$int( $data['attempts'] ?? null ) ?? 0,
			isset( $data['error'] ) && is_string( $data['error'] ) ? $data['error'] : '',
			$int( $data['retry_at'] ?? null )
		);
	}
}
