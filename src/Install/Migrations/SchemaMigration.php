<?php
/**
 * The tables.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Install\Schema;
use CreativeBranding\SkateboardBuilder\Support\PluginException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the two tables through `dbDelta()`, in one batch, and fails when either is not there afterwards, so the
 * runner logs it and tries again. Idempotent: a second run reports no change. A later
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
	 * @throws PluginException When a table is still missing afterwards: dbDelta() does not say when a CREATE TABLE fails.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		Schema::install();
		$missing = Schema::missing();
		if ( array() !== $missing ) {
			// The message goes to the log and the migration's state, never to a page.
			throw new PluginException( esc_html( sprintf( 'dbDelta() did not create %s.', implode( ', ', $missing ) ) ) );
		}
		return Cursor::done();
	}
}
