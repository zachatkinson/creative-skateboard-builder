<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Port\Kits;

/**
 * Kits made from documents, each slot's parts given.
 */
final class InMemoryKits implements Kits {

	/**
	 * The kits, by ID.
	 *
	 * @var array<int, array{name: string, kit: Kit}>
	 */
	public array $kits = array();

	/**
	 * Each kit's parts, by slot.
	 *
	 * @var array<int, array<string, list<Part>>>
	 */
	public array $parts = array();

	/**
	 * Add a kit.
	 *
	 * @param array<string, list<Part>> $parts Its slots' parts.
	 */
	public function add( int $id, Kit $kit, array $parts, string $name = 'Complete' ): void {
		$this->kits[ $id ]  = compact( 'name', 'kit' );
		$this->parts[ $id ] = $parts;
	}

	/**
	 * How many times the kits were read.
	 */
	public int $read = 0;

	public function all(): array {
		++$this->read;
		return $this->kits;
	}

	public function ids(): array {
		return array_keys( $this->kits );
	}

	/**
	 * The slots each product is said to fill.
	 *
	 * @var array<int, list<string>>
	 */
	public array $slotsOf = array();

	public function slotsOf( int $product, Kit ...$kits ): array {
		return $this->slotsOf[ $product ] ?? array();
	}

	public function candidates( int $kit, string $slot ): array {
		return $this->parts[ $kit ][ $slot ] ?? array();
	}
}
