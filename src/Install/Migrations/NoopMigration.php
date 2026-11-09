<?php
/**
 * The first migration.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Does nothing, and proves the path: it is recorded, locked around and resumed like every migration after it
 * (docs/ROADMAP.md, milestone 1).
 */
final class NoopMigration implements Migration {

	/**
	 * The key.
	 *
	 * @since 0.1.0
	 */
	public function key(): string {
		return 'noop_1';
	}

	/**
	 * Nothing to do.
	 *
	 * @since 0.1.0
	 *
	 * @param Cursor $cursor Ignored.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		return Cursor::done();
	}
}
