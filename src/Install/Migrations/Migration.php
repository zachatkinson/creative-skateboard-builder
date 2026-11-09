<?php
/**
 * One migration.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A versioned, ordered, idempotent step (spec: Upgrades and the public API). The runner calls `run()` with the saved
 * cursor until the cursor says done, saving the cursor after each call, so a step cut off halfway resumes where it
 * stopped and ends as an uninterrupted run would. A step that fits in one call returns `Cursor::done()` at once.
 * Within a major release a migration only adds; it never drops or renames (docs/AUDIT.md: Upgrades).
 */
interface Migration {

	/**
	 * The step's key, unique and never changed once released: `schema_1`, `spec_tables_1`.
	 */
	public function key(): string;

	/**
	 * Do one batch of the work from the cursor and return the next cursor.
	 *
	 * @param Cursor $cursor Where the last batch stopped, or `Cursor::start()`.
	 */
	public function run( Cursor $cursor ): Cursor;
}
