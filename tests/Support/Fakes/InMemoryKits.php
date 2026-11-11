<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotSources;
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

	public function kit( int $id ): ?Kit {
		++$this->read;
		return $this->kits[ $id ]['kit'] ?? null;
	}

	/**
	 * How many times offered() was asked, and what forget() and termMoved() were given.
	 */
	public int $offered = 0;

	public array $forgotten = array();

	public array $terms = array();

	public function offered( int $kit, Slot $slot ): array {
		++$this->offered;
		return $this->parts[ $kit ][ $slot->key ] ?? array();
	}

	public function forget( int ...$ids ): void {
		$this->forgotten[] = $ids;
	}

	public function termMoved( string $taxonomy ): void {
		$this->terms[] = $taxonomy;
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

	/**
	 * The parts sources find, by the sources' categories joined with commas.
	 *
	 * @var array<string, list<Part>>
	 */
	public array $found = array();

	/**
	 * The kits a visitor may not see.
	 *
	 * @var list<int>
	 */
	public array $hidden = array();

	public function isPublic( int $kit ): bool {
		return isset( $this->kits[ $kit ] ) && ! in_array( $kit, $this->hidden, true );
	}

	/**
	 * The sources parts were asked for, each time, by their categories.
	 *
	 * @var list<string>
	 */
	public array $asked = array();

	public function parts( SlotSources $sources ): array {
		$this->asked[] = implode( ',', $sources->categories );
		return $this->found[ implode( ',', $sources->categories ) ] ?? array();
	}

	public function candidates( int $kit, string $slot ): array {
		return $this->parts[ $kit ][ $slot ] ?? array();
	}

	public int $decimals = 2;

	public function decimals(): int {
		return $this->decimals;
	}
}
