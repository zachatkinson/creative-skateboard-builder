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
	 * Run an action as soon as the queue can, once: a call while one is pending or running is dropped.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $hook The action.
	 * @param array<string, mixed> $args Its arguments.
	 */
	public function enqueueUnique( string $hook, array $args = array() ): void;

	/**
	 * Run an action at a moment, once: dropped while one with this hook is pending or running.
	 *
	 * @since 0.1.0
	 *
	 * @param int                  $timestamp When, as a UTC timestamp.
	 * @param string               $hook      The action.
	 * @param array<string, mixed> $args      Its arguments.
	 */
	public function scheduleUnique( int $timestamp, string $hook, array $args = array() ): void;

	/**
	 * Run an action as soon as the queue can, whatever is pending or running. For a running job that queues its own
	 * continuation: the unique check would find the job itself and drop it (D-043).
	 *
	 * @since 0.1.0
	 *
	 * @param string               $hook The action.
	 * @param array<string, mixed> $args Its arguments.
	 */
	public function enqueue( string $hook, array $args = array() ): void;

	/**
	 * Run an action at a moment, whatever is pending or running. For a running job that schedules its own retry.
	 *
	 * @since 0.1.0
	 *
	 * @param int                  $timestamp When, as a UTC timestamp.
	 * @param string               $hook      The action.
	 * @param array<string, mixed> $args      Its arguments.
	 */
	public function schedule( int $timestamp, string $hook, array $args = array() ): void;

	/**
	 * Whether an action is pending or running.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook The action.
	 */
	public function isPending( string $hook ): bool;

	/**
	 * Cancel every pending action with this hook.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook The action.
	 */
	public function cancelAll( string $hook ): void;

	/**
	 * Queue an action unless one with the same hook and the same arguments is already waiting. Unlike
	 * enqueueUnique(), an action that is running does not stand in for this one: it may have read its data already,
	 * so a change made since needs a run of its own. Action Scheduler's unique flag counts a running action as well
	 * as a pending one (4.1.0 in WooCommerce 11.2.2, classes/data-stores/ActionScheduler_DBStore.php:218-221).
	 *
	 * @since 0.3.0
	 *
	 * @param string               $hook The action.
	 * @param array<string, mixed> $args Its arguments.
	 */
	public function enqueueDistinct( string $hook, array $args ): void;

	/**
	 * Schedule an action for a moment to come, unless one with the same hook and the same arguments already waits for
	 * a moment to come. One that is due now does not stand in for it: a runner an earlier release started before its
	 * files were replaced may claim that one, and run it with that release's code (D-378).
	 *
	 * @since 1.2.0
	 *
	 * @param int                  $timestamp When, a UTC timestamp.
	 * @param string               $hook      The action.
	 * @param array<string, mixed> $args      Its arguments.
	 */
	public function scheduleDistinct( int $timestamp, string $hook, array $args ): void;

	/**
	 * How many of the plugin's actions have failed and are still on the queue's record.
	 *
	 * @since 0.3.0
	 *
	 * @param int $since Count only actions scheduled after this moment, a UTC timestamp; 0 for all of them.
	 */
	public function failed( int $since = 0 ): int;

	/**
	 * How many of one of the plugin's actions wait to run, counted up to a limit: a caller that caps a queue asks
	 * only whether it is full.
	 *
	 * @since 0.7.0
	 *
	 * @param string $hook The action.
	 * @param int    $upTo The most to count.
	 */
	public function pending( string $hook, int $upTo ): int;

	/**
	 * Run an action again and again, a set time apart, unless it is already scheduled.
	 *
	 * @since 0.7.0
	 *
	 * @param int    $first    When it first runs, as a UTC timestamp.
	 * @param int    $interval The seconds between runs.
	 * @param string $hook     The action.
	 */
	public function recurringUnique( int $first, int $interval, string $hook ): void;
}
