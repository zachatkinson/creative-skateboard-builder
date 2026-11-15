<?php
/**
 * Composed pictures in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Port\PictureFiles;

/**
 * Keeps each drawing it is given by name, unless it has no library.
 */
final class InMemoryPictureFiles implements PictureFiles {

	/**
	 * What was drawn, by name.
	 *
	 * @var array<string, Composition>
	 */
	public array $drawn = array();

	/**
	 * How many times draw() was asked.
	 */
	public int $asked = 0;

	/**
	 * The library it says it has.
	 */
	public ?string $library = 'gd';

	public function draw( Composition $composition ): bool {
		++$this->asked;
		if ( null === $this->library || $composition->isEmpty() ) {
			return false;
		}
		$this->drawn[ $composition->name() ] = $composition;
		return true;
	}

	public function url( string $name ): ?string {
		return isset( $this->drawn[ $name ] ) ? 'https://example.com/pictures/' . $name : null;
	}

	public function library( string $type ): ?string {
		return $this->library;
	}
}
