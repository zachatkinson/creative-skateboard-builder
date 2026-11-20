<?php
/**
 * The parts table's look and card columns.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives the parts table a column for each part's look and one for its card, so a request with no persistent object
 * cache reads them with the part's facts, not from the shop (D-289). dbDelta() adds the columns to the rows already
 * there, empty, and a row's next read fills them.
 *
 * @since 1.0.0
 */
final class PartsLooksMigration implements Migration {

	/**
	 * The migration's key.
	 *
	 * @since 1.0.0
	 */
	public function key(): string {
		return 'schema_3';
	}

	/**
	 * Run the schema again: dbDelta() adds what is missing and leaves the rest.
	 *
	 * @since 1.0.0
	 *
	 * @param Cursor $cursor Where it got to; the schema runs whole.
	 */
	public function run( Cursor $cursor ): Cursor {
		return ( new SchemaMigration() )->run( $cursor );
	}
}
