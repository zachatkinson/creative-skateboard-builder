<?php
/**
 * The migration that fills the seeded charts' baseplates.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives a seeded chart made before 0.7.0 each maker's baseplate (issue #39, D-189). Only a size the seed names, and
 * only where the chart holds no baseplate: a value the shop typed is kept, and so is a size it added or renamed. The
 * row's source gains the page the baseplate was read from. A chart with something filled is saved once, which
 * re-indexes the parts that name it, and the log says which sizes, with the table's ID. Run again, it finds nothing.
 *
 * @since 0.7.0
 */
final class SeedBaseplatesMigration implements Migration {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param SpecTables $tables The shop's tables.
	 * @param Logger     $logger Says what was filled.
	 */
	public function __construct( private SpecTables $tables, private Logger $logger ) {}

	/**
	 * The key.
	 *
	 * @since 0.7.0
	 */
	public function key(): string {
		return 'spec_tables_2';
	}

	/**
	 * Fill each seeded chart's empty baseplates.
	 *
	 * @since 0.7.0
	 *
	 * @param Cursor $cursor Ignored: a handful of tables is one batch.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		foreach ( SeedSpecTables::BASEPLATES as $seed => $plates ) {
			$chart = $this->tables->seeded( $seed );
			if ( null === $chart ) {
				continue;
			}
			$rows   = array();
			$filled = array();
			foreach ( $chart['table']->rows as $row ) {
				$stored = $row->toArray();
				$plate  = $plates[ $row->size ] ?? null;
				if ( null !== $plate && ! isset( $row->values['baseplate'] ) ) {
					$stored['specs']['baseplate'] = $plate[0];
					$stored['source']             = SeedSpecTables::plateSource( $row->source, $plate[1] );
					$filled[]                     = $row->size;
				}
				$rows[] = $stored;
			}
			if ( array() === $filled ) {
				continue;
			}
			$this->tables->update( $chart['id'], $chart['name'], SpecTable::read( $rows ) );
			$this->logger->log(
				Logger::INFO,
				'Filled the baseplate of a seeded spec table\'s sizes from the maker\'s pages.',
				array(
					'table' => $chart['id'],
					'sizes' => $filled,
				)
			);
		}
		return Cursor::done();
	}
}
