<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\ImportStore;

/**
 * Holds the last import saved.
 */
final class InMemoryImportStore implements ImportStore {

	/**
	 * The import.
	 *
	 * @var array<mixed>|null
	 */
	public ?array $state = null;

	public function load(): ?array {
		return $this->state;
	}

	public function save( array $state ): void {
		$this->state = $state;
	}

	public function clear(): void {
		$this->state = null;
	}
}
