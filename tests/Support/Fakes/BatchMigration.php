<?php
/**
 * A migration that works in batches.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Migration;

/**
 * Writes the numbers 1 to $total into a shared list, $perBatch at a time, from the cursor: the shape of a backfill.
 * An uninterrupted run and a run stopped and resumed must leave the same list, with no duplicate.
 */
final class BatchMigration implements Migration {

	/**
	 * The rows written.
	 *
	 * @var list<int>
	 */
	public array $rows = array();

	public int $batches = 0;

	/**
	 * Constructor.
	 *
	 * @param int    $total    Rows to write.
	 * @param int    $perBatch Rows per batch.
	 * @param string $key      Key.
	 */
	public function __construct( private int $total = 10, private int $perBatch = 3, private string $key = 'batch_1' ) {}

	/**
	 * Key.
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * One batch from the cursor.
	 *
	 * @param Cursor $cursor Cursor: the last number written.
	 */
	public function run( Cursor $cursor ): Cursor {
		++$this->batches;
		$from = is_int( $cursor->position ) ? $cursor->position : 0;
		$to   = min( $from + $this->perBatch, $this->total );
		for ( $i = $from + 1; $i <= $to; $i++ ) {
			$this->rows[] = $i;
		}
		return $to >= $this->total ? Cursor::done() : Cursor::at( $to );
	}
}
