<?php
/**
 * Action Scheduler.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's jobs in Action Scheduler's `creative-skateboard-builder` group (docs/STANDARDS.md: Background work).
 * `as_enqueue_async_action()` takes a unique flag (WooCommerce 11.2.2, packages/action-scheduler/functions.php:19),
 * which drops a second action while one with the same hook, group and arguments is pending or running
 * (ActionScheduler_DBStore::build_where_clause_for_insert(), classes/data-stores/ActionScheduler_DBStore.php:212). A
 * running job therefore queues its own continuation with the plain calls (D-043). Without Action Scheduler
 * (WooCommerce missing) nothing is queued; the migration runner then runs on the next admin page instead.
 */
final class ActionSchedulerQueue implements Queue {

	/**
	 * The most failed actions counted: past this the answer is "many".
	 */
	public const MOST_FAILED = 100;

	/**
	 * Constructor.
	 *
	 * @param Clock $clock What "a moment to come" is measured from, as the services that give a job its time.
	 */
	public function __construct( private Clock $clock ) {}

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
	 * Run an action as soon as the queue can, whatever is pending or running.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $hook The action.
	 * @param array<string, mixed> $args Its arguments.
	 */
	public function enqueue( string $hook, array $args = array() ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		as_enqueue_async_action( $hook, $args, Options::QUEUE_GROUP, false );
	}

	/**
	 * Run an action at a moment, whatever is pending or running.
	 *
	 * @since 0.1.0
	 *
	 * @param int                  $timestamp When, as a UTC timestamp.
	 * @param string               $hook      The action.
	 * @param array<string, mixed> $args      Its arguments.
	 */
	public function schedule( int $timestamp, string $hook, array $args = array() ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		as_schedule_single_action( $timestamp, $hook, $args, Options::QUEUE_GROUP, false );
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
	 * Cancel every pending action with this hook, whatever its arguments.
	 *
	 * No arguments and no group are passed: with a group, Action Scheduler cancels only the actions whose arguments
	 * equal the ones given, so `array()` would leave every part's job waiting (WooCommerce 11.2.2,
	 * packages/action-scheduler/functions.php:316 to :328). The hooks are the plugin's own, so its group is the only
	 * one that holds them.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook The action.
	 */
	public function cancelAll( string $hook ): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( $hook );
	}

	/**
	 * Queue an action unless one with the same hook and arguments is waiting.
	 *
	 * @since 0.3.0
	 *
	 * @param string               $hook The action.
	 * @param array<string, mixed> $args Its arguments.
	 */
	public function enqueueDistinct( string $hook, array $args ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return;
		}
		$waiting = as_get_scheduled_actions(
			array(
				'hook'     => $hook,
				'args'     => $args,
				'group'    => Options::QUEUE_GROUP,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
			),
			'ids'
		);
		if ( array() === $waiting ) {
			as_enqueue_async_action( $hook, $args, Options::QUEUE_GROUP, false );
		}
	}

	/**
	 * Schedule an action for a moment to come, unless one with the same hook and arguments waits for a moment to come.
	 * "To come" is after the Clock's now, the clock the caller's timestamp comes from, not the system's. It goes in as
	 * a timestamp: `as_get_scheduled_actions()` passes `date` to `as_get_datetime_object()` (WooCommerce 11.3.1,
	 * packages/action-scheduler/functions.php:447 to 449), which takes a `DateTime`, a timestamp or a string, and
	 * throws a TypeError for the Clock's `DateTimeImmutable` (functions.php:489 to 495).
	 *
	 * @since 1.2.0
	 *
	 * @param int                  $timestamp When, a UTC timestamp.
	 * @param string               $hook      The action.
	 * @param array<string, mixed> $args      Its arguments.
	 */
	public function scheduleDistinct( int $timestamp, string $hook, array $args ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return;
		}
		$waiting = as_get_scheduled_actions(
			array(
				'hook'         => $hook,
				'args'         => $args,
				'group'        => Options::QUEUE_GROUP,
				'status'       => \ActionScheduler_Store::STATUS_PENDING,
				'date'         => $this->clock->now()->getTimestamp(),
				'date_compare' => '>',
				'per_page'     => 1,
			),
			'ids'
		);
		if ( array() === $waiting ) {
			as_schedule_single_action( $timestamp, $hook, $args, Options::QUEUE_GROUP, false );
		}
	}

	/**
	 * How many of the plugin's actions have failed and are still on the queue's record. Action Scheduler keeps a
	 * failed action for three months by default (WooCommerce 11.2.2,
	 * packages/action-scheduler/classes/ActionScheduler_QueueCleaner.php:121), so the caller says from when.
	 *
	 * @since 0.3.0
	 *
	 * @param int $since Count only actions scheduled after this moment, a UTC timestamp; 0 for all of them.
	 */
	public function failed( int $since = 0 ): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}
		$query = array(
			'group'    => Options::QUEUE_GROUP,
			'status'   => \ActionScheduler_Store::STATUS_FAILED,
			'per_page' => self::MOST_FAILED,
		);
		if ( $since > 0 ) {
			// When it last ran, not when it was due: a job queued before the moment may fail after it
			// (Action Scheduler in WooCommerce 11.2.2, classes/data-stores/ActionScheduler_DBStore.php:627).
			$query['modified']         = $since;
			$query['modified_compare'] = '>';
		}
		return count( as_get_scheduled_actions( $query, 'ids' ) );
	}

	/**
	 * How many of one of the plugin's actions wait to run, counted up to a limit. The count is of IDs, one page of them.
	 *
	 * @since 0.7.0
	 *
	 * @param string $hook The action.
	 * @param int    $upTo The most to count.
	 */
	public function pending( string $hook, int $upTo ): int {
		if ( $upTo < 1 || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}
		return count(
			as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'group'    => Options::QUEUE_GROUP,
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => $upTo,
				),
				'ids'
			)
		);
	}

	/**
	 * Run an action again and again, a set time apart, unless it is already scheduled. Action Scheduler's unique flag
	 * finds one pending or running (WooCommerce 11.3.0, packages/action-scheduler/functions.php:122).
	 *
	 * @since 0.7.0
	 *
	 * @param int    $first    When it first runs, as a UTC timestamp.
	 * @param int    $interval The seconds between runs.
	 * @param string $hook     The action.
	 */
	public function recurringUnique( int $first, int $interval, string $hook ): void {
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action( $first, $interval, $hook, array(), Options::QUEUE_GROUP, true );
		}
	}
}
