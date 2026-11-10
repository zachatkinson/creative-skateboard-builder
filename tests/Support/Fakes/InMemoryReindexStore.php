<?php
/**
 * The reindex's place, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\ReindexStore;

/**
 * Holds the last state saved and counts the saves.
 */
final class InMemoryReindexStore implements ReindexStore {

	/**
	 * The state.
	 *
	 * @var array<mixed>|null
	 */
	public ?array $state = null;

	public int $saves = 0;

	public function load(): ?array {
		return $this->state;
	}

	public function save( array $state ): void {
		$this->state = $state;
		++$this->saves;
	}
}
