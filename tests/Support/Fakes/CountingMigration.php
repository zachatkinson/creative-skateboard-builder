<?php
/**
 * A migration that counts its runs.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Migration;

/**
 * Finishes in one batch and remembers how many times it was asked to.
 */
final class CountingMigration implements Migration {

	public int $runs = 0;

	/**
	 * Constructor.
	 *
	 * @param string $key Key.
	 */
	public function __construct( private string $key = 'counting_1' ) {}

	/**
	 * Key.
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Run.
	 *
	 * @param Cursor $cursor Cursor.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		++$this->runs;
		return Cursor::done();
	}
}
