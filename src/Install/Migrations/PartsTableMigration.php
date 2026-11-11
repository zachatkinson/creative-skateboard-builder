<?php
/**
 * The parts table.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds `{prefix}cbuild_parts` to a site that has the first two tables (D-116; Zach, 10 November 2026: "yes, add
 * the table"). The same `dbDelta()` run as `schema_1`, which leaves the tables that are there as they are.
 *
 * @since 0.4.0
 */
final class PartsTableMigration implements Migration {

	/**
	 * The key.
	 *
	 * @since 0.4.0
	 */
	public function key(): string {
		return 'schema_2';
	}

	/**
	 * Create the table.
	 *
	 * @since 0.4.0
	 *
	 * @param Cursor $cursor Ignored: dbDelta() is one statement set.
	 */
	public function run( Cursor $cursor ): Cursor {
		return ( new SchemaMigration() )->run( $cursor );
	}
}
