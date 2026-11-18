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
 * Removes everything the plugin made: the three tables (saved builds with them), the spec tables, every option and transient, the
 * scheduled actions, the object-cache group and the network's cached update manifest. Per site on a network. Never
 * touches products or orders: order item meta is part of the shop's records (spec: Uninstall).
 */
final class Uninstaller {

	/**
	 * Uninstall every site.
	 *
	 * @since 0.1.0
	 */
	public static function run(): void {
		NetworkFile::remove();
		foreach ( Options::siteTransients() as $transient ) {
			delete_site_transient( $transient );
		}
		if ( is_multisite() ) {
			Sites::each( array( self::class, 'site' ) );
			return;
		}
		self::site();
	}

	/**
	 * Uninstall the current site.
	 *
	 * @since 0.1.0
	 */
	public static function site(): void {
		global $wpdb;

		// The spec tables first, every one, whatever its state: deleting a post fires hooks, and nothing they write
		// may outlive the options and actions removed below. The type is not registered during uninstall, so the
		// statuses are named.
		do {
			$query  = new \WP_Query(
				array(
					'post_type'      => Options::postTypes(),
					'post_status'    => array_keys( get_post_stati() ),
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'orderby'        => 'none',
				)
			);
			$tables = is_array( $query->posts ) ? $query->posts : array();
			foreach ( $tables as $table ) {
				if ( is_int( $table ) ) {
					wp_delete_post( $table, true );
				}
			}
		} while ( array() !== $tables );

		foreach ( array_reverse( Tables::all() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', Tables::name( $table ) ) );
		}

		foreach ( Options::names() as $option ) {
			delete_option( $option );
		}
		// Every transient the options table holds, by prefix, each through delete_transient() so the cached copy of
		// its option goes with the row. With a persistent object cache transients are not rows at all, and the cache
		// group's flush below is what clears the plugin's own entries.
		$prefix = '_transient_' . Options::TRANSIENT_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$names = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( $prefix ) . '%' ) );
		foreach ( $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
		// A timeout whose value row is already gone.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_timeout_' . Options::TRANSIENT_PREFIX ) . '%' ) );

		foreach ( Options::metaKeys() as $key ) {
			delete_post_meta_by_key( $key );
		}

		self::deleteJobs();
		self::deleteUploads();

		// Not every object cache can flush one group; where it cannot, the plugin's entries expire on their own.
		if ( wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( Options::CACHE_GROUP );
		}
	}

	/**
	 * Delete the plugin's folder in this site's uploads folder: the layers' renditions and the composed pictures
	 * (spec: Uninstall). Nothing in it is in the Media Library, and nothing else is in it. WordPress's direct
	 * filesystem is used whatever the site's filesystem method: the folder is the web server's own uploads.
	 *
	 * @since 0.7.0
	 */
	public static function deleteUploads(): void {
		$uploads = wp_get_upload_dir();
		$base    = $uploads['basedir'] ?? '';
		if ( ! is_string( $base ) || '' === $base || ! is_dir( $base . '/' . Options::UPLOADS_FOLDER ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		( new \WP_Filesystem_Direct( null ) )->rmdir( $base . '/' . Options::UPLOADS_FOLDER, true );
	}

	/**
	 * Delete every Action Scheduler action in the plugin's group, whatever its status, through Action Scheduler when
	 * it is loaded, then straight from its tables for what the store leaves or when it is not loaded (D-024): a shop removing WooCommerce too deactivates the
	 * builder first, then WooCommerce, then deletes both, so this often runs with no Action Scheduler loaded.
	 * Unscheduling alone would only mark pending actions cancelled and leave their rows and logs.
	 *
	 * @since 0.1.0
	 */
	public static function deleteJobs(): void {
		if ( ! Sites::hasQueueTables() ) {
			// A site of a network that was never loaded has no Action Scheduler tables, and so no action of the plugin's.
			return;
		}
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
	 *
	 * @since 0.1.0
	 */
	public static function deleteJobsBySql(): void {
		global $wpdb;
		$actions = $wpdb->prefix . 'actionscheduler_actions';
		$groups  = $wpdb->prefix . 'actionscheduler_groups';
		$logs    = $wpdb->prefix . 'actionscheduler_logs';
		if ( ! Sites::hasQueueTables() ) {
			return;
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
		// Action Scheduler keeps a group's ID in the object cache for ten minutes (4.1.0 in WooCommerce 11.2.2,
		// classes/data-stores/ActionScheduler_DBStore.php:15, :344). On a persistent cache a reinstall inside that
		// time would file its actions under the row just deleted, where nothing finds them by group.
		wp_cache_delete( Options::QUEUE_GROUP, 'action_scheduler_groups' );
	}

	/**
	 * The plugin's tables, for `wpmu_drop_tables` when a network site is deleted (D-025).
	 *
	 * @since 0.1.0
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
