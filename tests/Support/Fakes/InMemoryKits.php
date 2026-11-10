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

	public function all(): array {
		return $this->kits;
	}

	public function candidates( int $kit, string $slot ): array {
		return $this->parts[ $kit ][ $slot ] ?? array();
	}
}
