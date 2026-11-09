<?php
/**
 * A migration that fails.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Migration;
use RuntimeException;

/**
 * Advances once, then throws on its second batch.
 */
final class ThrowingMigration implements Migration {

	/**
	 * Key.
	 */
	public function key(): string {
		return 'throwing_1';
	}

	/**
	 * Run.
	 *
	 * @param Cursor $cursor Cursor.
	 * @throws RuntimeException On the second batch.
	 */
	public function run( Cursor $cursor ): Cursor {
		if ( null === $cursor->position ) {
			return Cursor::at( 5 );
		}
		throw new RuntimeException( 'the disk is full' );
	}
}
