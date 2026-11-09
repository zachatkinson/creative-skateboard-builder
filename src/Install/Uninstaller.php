<?php
/**
 * Uninstall.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes everything the plugin made: the two tables (saved builds with them), every option and transient, the
 * scheduled actions, the object-cache group and the network's cached update manifest. Per site on a network. Never
 * touches products or orders: order item meta is part of the shop's records (spec: Uninstall).
 */
final class Uninstaller {

	/**
	 * Uninstall every site.
	 */
	public static function run(): void {
		foreach ( Options::siteTransients() as $transient ) {
			delete_site_transient( $transient );
		}
		if ( is_multisite() ) {
			/** @var int[] $siteIds */
			$siteIds = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $siteIds as $siteId ) {
				switch_to_blog( (int) $siteId ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- uninstall walks every site once; only options and the plugin's tables are touched.
				self::site();
				restore_current_blog();
			}
			return;
		}
		self::site();
	}

	/**
	 * Uninstall the current site.
	 */
	public static function site(): void {
		global $wpdb;

		foreach ( array_reverse( Tables::all() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', Tables::name( $table ) ) );
		}

		foreach ( Options::names() as $option ) {
			delete_option( $option );
		}
		// Every transient, by prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				$wpdb->esc_like( '_transient_' . Options::TRANSIENT_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . Options::TRANSIENT_PREFIX ) . '%'
			)
		);

		foreach ( Options::metaKeys() as $key ) {
			delete_post_meta_by_key( $key );
		}

		self::deleteJobs();

		wp_cache_flush_group( Options::CACHE_GROUP );
	}

	/**
	 * Delete every Action Scheduler action in the plugin's group, whatever its status, through Action Scheduler when
	 * it is loaded, then straight from its tables for what the store leaves or when it is not loaded (D-024): a shop removing WooCommerce too deactivates the
	 * builder first, then WooCommerce, then deletes both, so this often runs with no Action Scheduler loaded.
	 * Unscheduling alone would only mark pending actions cancelled and leave their rows and logs.
	 */
	public static function deleteJobs(): void {
		if ( class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			$store = \ActionScheduler::store();
			do {
				$ids = $store->query_actions(
					array(
						'group'    => Options::QUEUE_GROUP,
						'status'   => '',
						'per_page' => 500,
					)
				);
				$ids = is_array( $ids ) ? $ids : array();
				foreach ( $ids as $id ) {
					$store->delete_action( is_scalar( $id ) ? (string) $id : '' );
				}
				$more = count( $ids ) === 500;
			} while ( $more );
		}
		// The store deletes actions and their logs, never a group's own row, so the SQL always finishes the job; with
		// no Action Scheduler loaded it does all of it.
		self::deleteJobsBySql();
	}

	/**
	 * Delete the group's actions, their logs and the group row from Action Scheduler's tables, when the tables exist.
	 * The one place the plugin writes another plugin's tables (D-024).
	 */
	public static function deleteJobsBySql(): void {
		global $wpdb;
		$actions = $wpdb->prefix . 'actionscheduler_actions';
		$groups  = $wpdb->prefix . 'actionscheduler_groups';
		$logs    = $wpdb->prefix . 'actionscheduler_logs';
		foreach ( array( $actions, $groups, $logs ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( null === $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) ) ) {
				return;
			}
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall; nothing to cache.
		$groupId = $wpdb->get_var( $wpdb->prepare( 'SELECT group_id FROM %i WHERE slug = %s', $groups, Options::QUEUE_GROUP ) );
		if ( ! is_numeric( $groupId ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'DELETE l FROM %i l INNER JOIN %i a ON a.action_id = l.action_id WHERE a.group_id = %d', $logs, $actions, (int) $groupId ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE group_id = %d', $actions, (int) $groupId ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE group_id = %d', $groups, (int) $groupId ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * The plugin's tables, for `wpmu_drop_tables` when a network site is deleted (D-025).
	 *
	 * @param mixed $tables Tables WordPress will drop.
	 * @return mixed
	 */
	public static function siteTables( $tables ) {
		if ( ! is_array( $tables ) ) {
			return $tables;
		}
		foreach ( Tables::all() as $table ) {
			$tables[] = Tables::name( $table );
		}
		return $tables;
	}
}
