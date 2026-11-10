<?php
/**
 * The migration that makes the seed spec tables.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Port\SpecTables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes Independent's, Thunder's and Venture's tables, so an upgraded site gets them as a new one does (spec: Admin,
 * Spec tables). A table carries the seed it came from, and a seed that already has a table is passed over, whatever
 * the shop did to it: run twice, or again after being cut off between two tables, it makes each once and overwrites
 * none.
 *
 * @since 0.3.0
 */
final class SpecTablesMigration implements Migration {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecTables $tables The shop's tables.
	 */
	public function __construct( private SpecTables $tables ) {}

	/**
	 * The key.
	 *
	 * @since 0.3.0
	 */
	public function key(): string {
		return 'spec_tables_1';
	}

	/**
	 * Make each seed's table that is not there.
	 *
	 * @since 0.3.0
	 *
	 * @param Cursor $cursor Ignored: three tables are one batch.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		foreach ( SeedSpecTables::all() as $seed => $chart ) {
			if ( ! $this->tables->hasSeed( $seed ) ) {
				$this->tables->create( $chart['name'], $chart['table'], $seed );
			}
		}
		return Cursor::done();
	}
}
