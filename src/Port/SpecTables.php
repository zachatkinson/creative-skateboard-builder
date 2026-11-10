<?php
/**
 * The shop's spec tables.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The makers' size charts a shop keeps (spec: Admin, Spec tables). A part names one by its ID.
 *
 * @since 0.3.0
 */
interface SpecTables {

	/**
	 * The tables with these IDs that are in use, by ID. One that is gone, in the bin or still a draft is not in the
	 * answer.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$ids Tables' IDs.
	 * @return array<int, SpecTable>
	 */
	public function find( int ...$ids ): array;

	/**
	 * Make a table, in use at once.
	 *
	 * @since 0.3.0
	 *
	 * @param string    $name  The maker's name.
	 * @param SpecTable $table Its rows.
	 * @param string    $seed  The seed it was made from, '' for a table the shop made.
	 * @return int The table's ID.
	 */
	public function create( string $name, SpecTable $table, string $seed = '' ): int;

	/**
	 * Whether a table made from this seed exists, in whatever state the shop left it.
	 *
	 * @since 0.3.0
	 *
	 * @param string $seed The seed's name.
	 */
	public function hasSeed( string $seed ): bool;

	/**
	 * Every table that feeds the index, by ID, in the order of their names.
	 *
	 * @since 0.3.0
	 *
	 * @return array<int, array{name: string, table: SpecTable}>
	 */
	public function all(): array;
}
