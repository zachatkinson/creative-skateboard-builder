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

	/**
	 * What used() was told, by name: the moment.
	 *
	 * @var array<string, int>
	 */
	public array $used = array();

	/**
	 * What prune() was asked: the moment and the size.
	 *
	 * @var list<array{int, int}>
	 */
	public array $pruned = array();

	/**
	 * Whether the folder can be written.
	 */
	public bool $writable = true;

	public function used( string $name, int $now ): void {
		$this->used[ $name ] = $now;
	}

	public function prune( int $before, int $mostBytes ): int {
		$this->pruned[] = array( $before, $mostBytes );
		return 2;
	}

	public function writable(): bool {
		return $this->writable;
	}
}
