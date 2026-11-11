<?php
/**
 * A tally kept in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Tally;

/**
 * Counts by key, and remembers how long each was asked to be kept.
 */
final class InMemoryTally implements Tally {

	/**
	 * The counts, by key.
	 *
	 * @var array<string, int>
	 */
	public array $counts = array();

	/**
	 * How long each key was last asked to be kept, in seconds.
	 *
	 * @var array<string, int>
	 */
	public array $kept = array();

	/**
	 * The secret.
	 *
	 * @var string
	 */
	public string $salt = 'a secret of the site';

	public function salt(): string {
		return $this->salt;
	}

	public function add( string $key, int $seconds ): int {
		$this->kept[ $key ]   = $seconds;
		$this->counts[ $key ] = ( $this->counts[ $key ] ?? 0 ) + 1;
		return $this->counts[ $key ];
	}
}
