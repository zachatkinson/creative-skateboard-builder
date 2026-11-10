<?php
/**
 * Where a reindex has got to.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A reindex walks the index's own rows first, by part ID, so a part that is no longer a product loses its rows; then
 * the shop's parts, a page at a time. Immutable; each step returns the next state for the store.
 *
 * @since 0.3.0
 */
final readonly class ReindexState {

	public const ROWS      = 'rows';
	public const CATALOGUE = 'catalogue';
	public const DONE      = 'done';
	public const FAILED    = 'failed';

	/**
	 * How many times in a row a batch may fail before the reindex stops and waits to be asked again.
	 */
	public const MAX_FAILURES = 5;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $phase      One of the phase constants.
	 * @param int      $position   In `rows`, the last part ID done; in `catalogue`, the last page done.
	 * @param int      $parts      How many parts this run has indexed.
	 * @param int      $startedAt  When it was asked for, UTC timestamp.
	 * @param int|null $finishedAt When it finished, UTC timestamp.
	 * @param bool     $again      Whether something changed under it that a second pass must pick up.
	 * @param int      $failures   How many batches in a row have failed.
	 * @param string   $error      The last failure's message, '' when none.
	 * @param int      $run        Which request this is, counted from 1: a batch that finds another run's state
	 *                             leaves it alone.
	 */
	public function __construct(
		public string $phase,
		public int $position,
		public int $parts,
		public int $startedAt,
		public ?int $finishedAt = null,
		public bool $again = false,
		public int $failures = 0,
		public string $error = '',
		public int $run = 1
	) {}

	/**
	 * A reindex just asked for.
	 *
	 * @since 0.3.0
	 *
	 * @param int $now UTC timestamp.
	 * @param int $run Which request this is.
	 */
	public static function begin( int $now, int $run = 1 ): self {
		return new self( self::ROWS, 0, 0, $now, run: $run );
	}

	/**
	 * Whether there is a batch left to run.
	 *
	 * @since 0.3.0
	 */
	public function isRunning(): bool {
		return self::ROWS === $this->phase || self::CATALOGUE === $this->phase;
	}

	/**
	 * A batch done.
	 *
	 * @since 0.3.0
	 *
	 * @param int  $position The last part ID or page done.
	 * @param int  $indexed  How many parts the batch held.
	 * @param bool $last     Whether the batch was the phase's last.
	 * @param int  $now      UTC timestamp.
	 */
	public function advanced( int $position, int $indexed, bool $last, int $now ): self {
		$parts = $this->parts + $indexed;
		if ( ! $last ) {
			return new self( $this->phase, $position, $parts, $this->startedAt, null, $this->again, run: $this->run );
		}
		if ( self::ROWS === $this->phase ) {
			return new self( self::CATALOGUE, 0, $parts, $this->startedAt, null, $this->again, run: $this->run );
		}
		if ( $this->again ) {
			// Something was deleted while the pages were being walked, so a part may have slipped between two.
			return new self( self::ROWS, 0, $parts, $this->startedAt, run: $this->run );
		}
		return new self( self::DONE, 0, $parts, $this->startedAt, $now, run: $this->run );
	}

	/**
	 * Something changed that a second pass must pick up.
	 *
	 * @since 0.3.0
	 */
	public function disturbed(): self {
		return new self( $this->phase, $this->position, $this->parts, $this->startedAt, $this->finishedAt, true, $this->failures, $this->error, $this->run );
	}

	/**
	 * A batch failed; the place is kept. After too many in a row the reindex stops.
	 *
	 * @since 0.3.0
	 *
	 * @param string $error The failure's message.
	 */
	public function failed( string $error ): self {
		$failures = $this->failures + 1;
		$phase    = $failures >= self::MAX_FAILURES ? self::FAILED : $this->phase;
		return new self( $phase, $this->position, $this->parts, $this->startedAt, null, $this->again, $failures, $error, $this->run );
	}

	/**
	 * For the store.
	 *
	 * @since 0.3.0
	 *
	 * @return array{phase: string, position: int, parts: int, started_at: int, finished_at: int|null, again: bool, failures: int, error: string, run: int}
	 */
	public function toArray(): array {
		return array(
			'phase'       => $this->phase,
			'position'    => $this->position,
			'parts'       => $this->parts,
			'started_at'  => $this->startedAt,
			'finished_at' => $this->finishedAt,
			'again'       => $this->again,
			'failures'    => $this->failures,
			'error'       => $this->error,
			'run'         => $this->run,
		);
	}

	/**
	 * From the store, or null when what is stored is not a state.
	 *
	 * @since 0.3.0
	 *
	 * @param array<mixed>|null $data What was stored.
	 */
	public static function fromArray( ?array $data ): ?self {
		$phase = $data['phase'] ?? null;
		if ( ! in_array( $phase, array( self::ROWS, self::CATALOGUE, self::DONE, self::FAILED ), true ) ) {
			return null;
		}
		$int = static fn( mixed $value ): int => is_int( $value ) ? max( 0, $value ) : 0;
		return new self(
			$phase,
			$int( $data['position'] ?? null ),
			$int( $data['parts'] ?? null ),
			$int( $data['started_at'] ?? null ),
			is_int( $data['finished_at'] ?? null ) ? $data['finished_at'] : null,
			true === ( $data['again'] ?? null ),
			$int( $data['failures'] ?? null ),
			is_string( $data['error'] ?? null ) ? $data['error'] : '',
			max( 1, $int( $data['run'] ?? null ) )
		);
	}
}
