<?php
/**
 * Action Scheduler.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's jobs in Action Scheduler's `creative-skateboard-builder` group (docs/STANDARDS.md: Background work).
 * `as_enqueue_async_action()` takes a unique flag (WooCommerce 11.2.2, packages/action-scheduler/functions.php:19),
 * which drops a second action while one with the same hook and group is pending. Without Action Scheduler (WooCommerce
 * missing) nothing is queued; the migration runner then runs on the next admin page instead.
 */
final class ActionSchedulerQueue implements Queue {

	/**
	 * Run an action as soon as the queue can, once.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $hook The action.
	 * @param array<string, mixed> $args Its arguments.
	 */
	public function enqueueUnique( string $hook, array $args = array() ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		as_enqueue_async_action( $hook, $args, Options::QUEUE_GROUP, true );
	}

	/**
	 * Run an action at a moment, once.
	 *
	 * @since 0.1.0
	 *
	 * @param int                  $timestamp When, as a UTC timestamp.
	 * @param string               $hook      The action.
	 * @param array<string, mixed> $args      Its arguments.
	 */
	public function scheduleUnique( int $timestamp, string $hook, array $args = array() ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		as_schedule_single_action( $timestamp, $hook, $args, Options::QUEUE_GROUP, true );
	}

	/**
	 * Whether an action is pending or running.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook The action.
	 */
	public function isPending( string $hook ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}
		return as_has_scheduled_action( $hook, null, Options::QUEUE_GROUP );
	}

	/**
	 * Cancel every pending action with this hook.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook The action.
	 */
	public function cancelAll( string $hook ): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( $hook, array(), Options::QUEUE_GROUP );
	}
}
