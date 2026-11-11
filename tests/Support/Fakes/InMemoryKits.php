<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
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
		// The shop changed, and its hooks said so.
		$this->kept = array();
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
	 * What keep() was given, by kit and slot.
	 *
	 * @var array<string, mixed>
	 */
	public array $kept = array();

	/**
	 * How many times candidates() was asked, and forget().
	 */
	public int $found_parts = 0;
	public int $forgotten   = 0;

	public function kept( int $kit, string $slot ): mixed {
		return $this->kept[ $kit . '|' . $slot ] ?? null;
	}

	public function keep( int $kit, string $slot, array $parts ): void {
		$this->kept[ $kit . '|' . $slot ] = $parts;
	}

	public function forget(): void {
		++$this->forgotten;
		$this->kept = array();
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
		++$this->found_parts;
		return $this->parts[ $kit ][ $slot ] ?? array();
	}

	public int $decimals = 2;

	public function decimals(): int {
		return $this->decimals;
	}
}
