<?php
/**
 * A queue the tests read.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Queue;

/**
 * Keeps the pending hooks, deduplicated as Action Scheduler's unique flag would, and counts every call.
 */
final class FakeQueue implements Queue {

	/**
	 * Pending hooks.
	 *
	 * @var array<string, true>
	 */
	public array $pending = array();

	/**
	 * How many times enqueueUnique() was called, by hook.
	 *
	 * @var array<string, int>
	 */
	public array $calls = array();

	/**
	 * Queue once.
	 *
	 * @param string               $hook Hook.
	 * @param array<string, mixed> $args Arguments.
	 */
	public function enqueueUnique( string $hook, array $args = array() ): void {
		unset( $args );
		$this->calls[ $hook ]   = ( $this->calls[ $hook ] ?? 0 ) + 1;
		$this->pending[ $hook ] = true;
	}

	/**
	 * Whether pending.
	 *
	 * @param string $hook Hook.
	 */
	public function isPending( string $hook ): bool {
		return isset( $this->pending[ $hook ] );
	}

	/**
	 * Cancel.
	 *
	 * @param string $hook Hook.
	 */
	public function cancelAll( string $hook ): void {
		unset( $this->pending[ $hook ] );
	}
}
