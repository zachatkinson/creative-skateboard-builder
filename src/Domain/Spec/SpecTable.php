<?php
/**
 * A maker's size chart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use function array_map;
use function is_array;
use function trim;

/**
 * A spec table: a row per size (spec: Admin, Spec tables). A part names a table and a size, and takes that row's
 * values.
 *
 * @since 0.3.0
 */
final readonly class SpecTable {

	/**
	 * Constructor.
	 *
	 * @param list<TableRow> $rows The rows, each size once.
	 */
	private function __construct( public array $rows ) {}

	/**
	 * A table as it was saved. What is not a row is left out, and of two rows with one size the first is kept, so
	 * whatever is stored reads as a table.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $rows What was stored: a list of rows.
	 */
	public static function read( mixed $rows ): self {
		$table = new self( array() );
		foreach ( is_array( $rows ) ? $rows : array() as $stored ) {
			$row = TableRow::read( $stored );
			if ( null !== $row && null === $table->row( $row->size ) ) {
				$table = new self( array( ...$table->rows, $row ) );
			}
		}
		return $table;
	}

	/**
	 * The row for a size, or null when the table has none.
	 *
	 * @since 0.3.0
	 *
	 * @param string $size The size's name.
	 */
	public function row( string $size ): ?TableRow {
		$size = trim( $size );
		foreach ( $this->rows as $row ) {
			if ( $row->size === $size ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * The table for the store.
	 *
	 * @since 0.3.0
	 *
	 * @return list<array{size: string, specs: array<string, string>, source: string, read_on: string}>
	 */
	public function toArray(): array {
		return array_map( static fn( TableRow $row ): array => $row->toArray(), $this->rows );
	}
}
