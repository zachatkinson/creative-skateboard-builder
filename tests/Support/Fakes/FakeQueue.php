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
 * Keeps the pending hooks, deduplicated as Action Scheduler's unique flag would, and counts every call. Like Action
 * Scheduler's, the unique check also matches an action that is running (ActionScheduler_DBStore, WooCommerce 11.2.2,
 * build_where_clause_for_insert()), so a job cannot queue itself again that way.
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
		$this->calls[ $hook ] = ( $this->calls[ $hook ] ?? 0 ) + 1;
		if ( isset( $this->running[ $hook ] ) ) {
			return;
		}
		$this->pending[ $hook ] = true;
	}

	/**
	 * Hooks whose action is running now.
	 *
	 * @var array<string, true>
	 */
	public array $running = array();

	/**
	 * How many times enqueue() was called, by hook.
	 *
	 * @var array<string, int>
	 */
	public array $plain = array();

	/**
	 * Queue, whatever is pending or running.
	 *
	 * @param string               $hook Hook.
	 * @param array<string, mixed> $args Arguments.
	 */
	public function enqueue( string $hook, array $args = array() ): void {
		unset( $args );
		$this->plain[ $hook ]   = ( $this->plain[ $hook ] ?? 0 ) + 1;
		$this->pending[ $hook ] = true;
	}

	/**
	 * Queue for a moment, whatever is pending or running.
	 *
	 * @param int                  $timestamp When.
	 * @param string               $hook      Hook.
	 * @param array<string, mixed> $args      Arguments.
	 */
	public function schedule( int $timestamp, string $hook, array $args = array() ): void {
		$this->scheduledWith[ $hook ]  = $args;
		$this->scheduled[ $hook ]      = $timestamp;
		$this->pending[ $hook ]        = true;
		$this->plainScheduled[ $hook ] = ( $this->plainScheduled[ $hook ] ?? 0 ) + 1;
	}

	/**
	 * The arguments of the last schedule() call, by hook.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $scheduledWith = array();

	/**
	 * How many times schedule() was called, by hook: the plain kind, which is not dropped beside a pending action.
	 *
	 * @var array<string, int>
	 */
	public array $plainScheduled = array();

	/**
	 * When each hook is scheduled for, by hook.
	 *
	 * @var array<string, int>
	 */
	public array $scheduled = array();

	/**
	 * Queue once, for a moment.
	 *
	 * @param int                  $timestamp When.
	 * @param string               $hook      Hook.
	 * @param array<string, mixed> $args      Arguments.
	 */
	public function scheduleUnique( int $timestamp, string $hook, array $args = array() ): void {
		unset( $args );
		if ( isset( $this->pending[ $hook ] ) || isset( $this->running[ $hook ] ) ) {
			return;
		}
		$this->scheduled[ $hook ] = $timestamp;
		$this->pending[ $hook ]   = true;
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

	/**
	 * The actions queued with enqueueDistinct() and still waiting, each once.
	 *
	 * @var list<array{0: string, 1: array<string, mixed>}>
	 */
	public array $distinct = array();

	/**
	 * Queue unless the same hook and arguments wait.
	 *
	 * @param string               $hook Hook.
	 * @param array<string, mixed> $args Arguments.
	 */
	public function enqueueDistinct( string $hook, array $args ): void {
		if ( ! in_array( array( $hook, $args ), $this->distinct, true ) ) {
			$this->distinct[]       = array( $hook, $args );
			$this->pending[ $hook ] = true;
		}
	}

	/**
	 * The moment failed() was last asked to count from.
	 */
	public int $since = -1;

	/**
	 * What failed() answers.
	 */
	public int $failures = 0;

	public function failed( int $since = 0 ): int {
		$this->since = $since;
		return $this->failures;
	}
}
