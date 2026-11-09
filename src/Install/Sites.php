<?php
/**
 * The sites of a network, and what each has.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What activation, deactivation and uninstall share: the walk over every site of a network, and whether a site has
 * Action Scheduler's tables. One of the files uninstall.php loads itself below the floors, so it must parse on
 * PHP 7.4 and use nothing but WordPress (D-023, D-043).
 */
final class Sites {

	/**
	 * Run the work on every site of a network, each as the current site.
	 *
	 * @since 0.1.0
	 *
	 * @param callable $work The work for one site.
	 */
	public static function each( callable $work ): void {
		/** @var int[] $siteIds */
		$siteIds = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		foreach ( $siteIds as $siteId ) {
			switch_to_blog( (int) $siteId ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- activation, deactivation and uninstall walk every site once; only the plugin's own tables, options and jobs are touched.
			try {
				$work();
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * Whether the current site has Action Scheduler's three tables. They follow switch_to_blog(), and a site of a
	 * network that was never loaded has none yet.
	 *
	 * @since 0.1.0
	 */
	public static function hasQueueTables(): bool {
		global $wpdb;
		foreach ( array( 'actionscheduler_actions', 'actionscheduler_groups', 'actionscheduler_logs' ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Asked once per site, on deactivation and uninstall.
			if ( null === $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->prefix . $table ) ) ) {
				return false;
			}
		}
		return true;
	}
}
