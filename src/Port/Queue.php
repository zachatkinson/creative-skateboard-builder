<?php
/**
 * The background queue port.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background work, in the plugin's group (docs/STANDARDS.md: Background work). Action Scheduler in the shop
 * (Adapter\ActionSchedulerQueue), a list in tests. Every action is idempotent; the unique ones are deduplicated by
 * the queue, so queuing twice costs nothing.
 */
interface Queue {

	/**
	 * Run an action as soon as the queue can, once: a second call while one is pending is dropped.
	 *
	 * @param string               $hook The action.
	 * @param array<string, mixed> $args Its arguments.
	 */
	public function enqueueUnique( string $hook, array $args = array() ): void;

	/**
	 * Whether an action is pending or running.
	 *
	 * @param string $hook The action.
	 */
	public function isPending( string $hook ): bool;

	/**
	 * Cancel every pending action with this hook.
	 *
	 * @param string $hook The action.
	 */
	public function cancelAll( string $hook ): void;
}
