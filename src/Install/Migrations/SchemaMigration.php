<?php
/**
 * The tables.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Install\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the two tables through `dbDelta()`, in one batch. Idempotent: a second run reports no change. A later
 * schema adds columns or keys through a `schema_<n>` migration that calls the same `Schema::install()`.
 */
final class SchemaMigration implements Migration {

	/**
	 * The key.
	 */
	public function key(): string {
		return 'schema_1';
	}

	/**
	 * Create or update the tables.
	 *
	 * @param Cursor $cursor Ignored: dbDelta() is one statement set.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		Schema::install();
		return Cursor::done();
	}
}
