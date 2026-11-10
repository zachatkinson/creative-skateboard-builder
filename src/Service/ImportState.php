<?php
/**
 * An import, from the file read to what it did.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The rows of the file, not the file: read once, checked, and held until the import is confirmed and has run. Once
 * it has, the rows are dropped and the counts and the rows that were refused stay, for the screen to show.
 *
 * @since 0.3.0
 */
final readonly class ImportState {

	/**
	 * Where an import is.
	 */
	public const WAITING = 'waiting';
	public const RUNNING = 'running';
	public const DONE    = 'done';
	public const FAILED  = 'failed';

	/**
	 * How many times a batch is tried before the import stops.
	 */
	public const MAX_FAILURES = 4;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string                                                         $status    One of the four.
	 * @param string                                                         $token     Names this import, so a
	 *                                                                                  confirmation of one since
	 *                                                                                  replaced is refused.
	 * @param string                                                         $key       The column rows are matched
	 *                                                                                  by: `id` or `sku`.
	 * @param string                                                         $separator The shop's decimal separator
	 *                                                                                  when the file was read.
	 * @param int                                                            $at        When it was read, or when
	 *                                                                                  it finished.
	 * @param array<string, int>                                             $columns   Where each column sits.
	 * @param list<list<string>>                                             $rows      The rows under the heading.
	 * @param int                                                            $total     How many rows there were.
	 * @param int                                                            $cursor    How many have been done.
	 * @param int                                                            $changed   Parts written.
	 * @param int                                                            $same      Rows that changed nothing.
	 * @param list<array{row: int, key: string, code: string, text: string}> $refused   What was wrong, by row.
	 * @param int                                                            $failures  Batches that failed in a
	 *                                                                                  row.
	 * @param int                                                            $by        The user who sent the file,
	 *                                                                                  while it waits; 0 after.
	 * @param array<string, int>                                             $found     Matched by SKU: the product
	 *                                                                                  each SKU named when the
	 *                                                                                  file was read.
	 */
	public function __construct(
		public string $status,
		public string $token,
		public string $key,
		public string $separator,
		public int $at,
		public array $columns,
		public array $rows,
		public int $total,
		public int $cursor = 0,
		public int $changed = 0,
		public int $same = 0,
		public array $refused = array(),
		public int $failures = 0,
		public int $by = 0,
		public array $found = array()
	) {}

	/**
	 * The import as confirmed: running from its first row. Who sent it is no longer kept.
	 *
	 * @since 0.3.0
	 */
	public function started(): self {
		return new self( self::RUNNING, $this->token, $this->key, $this->separator, $this->at, $this->columns, $this->rows, $this->total, found: $this->found );
	}

	/**
	 * The import after a batch.
	 *
	 * @since 0.3.0
	 *
	 * @param int                                                            $done    Rows the batch covered.
	 * @param int                                                            $changed Parts it wrote.
	 * @param int                                                            $same    Rows that changed nothing.
	 * @param list<array{row: int, key: string, code: string, text: string}> $refused What was wrong.
	 * @param int                                                            $now     The time.
	 */
	public function advanced( int $done, int $changed, int $same, array $refused, int $now ): self {
		$cursor = $this->cursor + $done;
		$last   = $cursor >= $this->total;
		return new self(
			$last ? self::DONE : self::RUNNING,
			$this->token,
			$this->key,
			$this->separator,
			$last ? $now : $this->at,
			$this->columns,
			$last ? array() : $this->rows,
			$this->total,
			$cursor,
			$this->changed + $changed,
			$this->same + $same,
			array( ...$this->refused, ...$refused ),
			found: $last ? array() : $this->found
		);
	}

	/**
	 * The import after a batch failed: tried again, or stopped where it is once it has failed too often.
	 *
	 * @since 0.3.0
	 *
	 * @param int $now The time.
	 */
	public function failed( int $now ): self {
		$failures = $this->failures + 1;
		$stopped  = $failures >= self::MAX_FAILURES;
		return new self(
			$stopped ? self::FAILED : self::RUNNING,
			$this->token,
			$this->key,
			$this->separator,
			$stopped ? $now : $this->at,
			$this->columns,
			$stopped ? array() : $this->rows,
			$this->total,
			$this->cursor,
			$this->changed,
			$this->same,
			$this->refused,
			$failures,
			found: $stopped ? array() : $this->found
		);
	}

	/**
	 * As the store keeps it.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'status'    => $this->status,
			'token'     => $this->token,
			'key'       => $this->key,
			'separator' => $this->separator,
			'at'        => $this->at,
			'columns'   => $this->columns,
			'rows'      => $this->rows,
			'total'     => $this->total,
			'cursor'    => $this->cursor,
			'changed'   => $this->changed,
			'same'      => $this->same,
			'refused'   => $this->refused,
			'failures'  => $this->failures,
			'by'        => $this->by,
			'found'     => $this->found,
		);
	}

	/**
	 * From what the store kept. Anything that is not an import this version wrote is none.
	 *
	 * @since 0.3.0
	 *
	 * @param array<mixed>|null $data What was stored.
	 */
	public static function fromArray( ?array $data ): ?self {
		$status = $data['status'] ?? null;
		$key    = $data['key'] ?? null;
		if ( ! in_array( $status, array( self::WAITING, self::RUNNING, self::DONE, self::FAILED ), true ) || ! in_array( $key, array( SpecSheet::ID, SpecSheet::SKU ), true ) ) {
			return null;
		}
		$int     = static fn( mixed $value ): int => is_int( $value ) ? max( 0, $value ) : 0;
		$text    = static fn( mixed $value ): string => is_string( $value ) ? $value : '';
		$columns = array();
		foreach ( is_array( $data['columns'] ?? null ) ? $data['columns'] : array() as $name => $at ) {
			if ( is_string( $name ) && is_int( $at ) ) {
				$columns[ $name ] = $at;
			}
		}
		$rows = array();
		foreach ( is_array( $data['rows'] ?? null ) ? $data['rows'] : array() as $row ) {
			$rows[] = array_map( $text, array_values( is_array( $row ) ? $row : array() ) );
		}
		$refused = array();
		foreach ( is_array( $data['refused'] ?? null ) ? $data['refused'] : array() as $problem ) {
			if ( is_array( $problem ) ) {
				$refused[] = array(
					'row'  => $int( $problem['row'] ?? null ),
					'key'  => $text( $problem['key'] ?? null ),
					'code' => $text( $problem['code'] ?? null ),
					'text' => $text( $problem['text'] ?? null ),
				);
			}
		}
		$found = array();
		foreach ( is_array( $data['found'] ?? null ) ? $data['found'] : array() as $sku => $id ) {
			if ( is_int( $id ) && $id > 0 ) {
				$found[ (string) $sku ] = $id;
			}
		}
		return new self(
			$status,
			$text( $data['token'] ?? null ),
			$key,
			$text( $data['separator'] ?? null ),
			$int( $data['at'] ?? null ),
			$columns,
			$rows,
			$int( $data['total'] ?? null ),
			$int( $data['cursor'] ?? null ),
			$int( $data['changed'] ?? null ),
			$int( $data['same'] ?? null ),
			$refused,
			$int( $data['failures'] ?? null ),
			$int( $data['by'] ?? null ),
			$found
		);
	}
}
