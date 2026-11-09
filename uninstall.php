<?php
/**
 * Uninstall: removes the two tables, every option and transient, the scheduled actions and the object-cache group, on
 * every site of a network, and the network's cached update manifest. Deactivation leaves all of it in place. Order
 * item meta is kept: it is part of the shop's records (spec: Uninstall).
 *
 * The work is in Install\Uninstaller so the integration suite can prove it leaves nothing; this file only reaches it,
 * and repeats the list inline when the autoloader is missing. It must parse on PHP 7.4 (D-023). It often runs with
 * WooCommerce already deactivated, so Action Scheduler's rows are removed by SQL when Action Scheduler is not loaded
 * (D-024).
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( class_exists( \CreativeBranding\SkateboardBuilder\Install\Uninstaller::class ) ) {
	\CreativeBranding\SkateboardBuilder\Install\Uninstaller::run();
	return;
}

/**
 * Fallback without the autoloader: the same list, by hand. Kept in step with Install\Uninstaller, Support\Options and
 * Support\Tables by tests/Integration/UninstallTest.php.
 */
function cbuild_uninstall_site(): void {
	global $wpdb;
	foreach ( array( 'builds', 'specs' ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'cbuild_' . $table ) );
	}
	foreach ( array( 'cbuild_db_version', 'cbuild_migrations', 'cbuild_migration_lock' ) as $option ) {
		delete_option( $option );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_cbuild_' ) . '%', $wpdb->esc_like( '_transient_timeout_cbuild_' ) . '%' ) );
	$cbuild_actions = $wpdb->prefix . 'actionscheduler_actions';
	$cbuild_groups  = $wpdb->prefix . 'actionscheduler_groups';
	$cbuild_logs    = $wpdb->prefix . 'actionscheduler_logs';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall; nothing to cache.
	$cbuild_present = 0;
	foreach ( array( $cbuild_actions, $cbuild_groups, $cbuild_logs ) as $cbuild_table ) {
		if ( null !== $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $cbuild_table ) ) ) {
			++$cbuild_present;
		}
	}
	if ( 3 === $cbuild_present ) {
		$cbuild_group_id = $wpdb->get_var( $wpdb->prepare( 'SELECT group_id FROM %i WHERE slug = %s', $cbuild_groups, 'creative-skateboard-builder' ) );
		if ( is_numeric( $cbuild_group_id ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE l FROM %i l INNER JOIN %i a ON a.action_id = l.action_id WHERE a.group_id = %d', $cbuild_logs, $cbuild_actions, (int) $cbuild_group_id ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE group_id = %d', $cbuild_actions, (int) $cbuild_group_id ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE group_id = %d', $cbuild_groups, (int) $cbuild_group_id ) );
		}
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	wp_cache_flush_group( 'cbuild' );
}

delete_site_transient( 'cbuild_update_manifest' );

if ( is_multisite() ) {
	$cbuild_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $cbuild_site_ids as $cbuild_site_id ) {
		switch_to_blog( (int) $cbuild_site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- uninstall walks every site once; only options and the plugin's tables are touched.
		cbuild_uninstall_site();
		restore_current_blog();
	}
} else {
	cbuild_uninstall_site();
}
