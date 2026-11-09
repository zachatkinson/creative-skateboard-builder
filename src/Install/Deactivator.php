<?php
/**
 * Deactivation.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cancels the pending jobs and releases the migration lock; everything else stays, so reactivation finds the shop as
 * it was (docs/reviews/M1.md). On a network deactivation every site gets the same. Action Scheduler is loaded here,
 * since `Requires Plugins` keeps WooCommerce active while the builder is (D-024).
 */
final class Deactivator {

	/**
	 * Deactivation hook.
	 *
	 * @param bool|mixed $networkWide Whether the plugin is being deactivated for the whole network.
	 */
	public static function deactivate( $networkWide = false ): void {
		if ( is_multisite() && true === $networkWide ) {
			/** @var int[] $siteIds */
			$siteIds = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $siteIds as $siteId ) {
				switch_to_blog( (int) $siteId ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- deactivation walks every site once; only the plugin's jobs and its lock are touched.
				self::site();
				restore_current_blog();
			}
			return;
		}
		self::site();
	}

	/**
	 * Deactivate the current site. Action Scheduler's tables follow switch_to_blog(), and a site of a network that
	 * was never loaded has none yet, so its queue is left alone: it holds nothing.
	 */
	public static function site(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Asked once per site on deactivation.
		if ( null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'actionscheduler_actions' ) ) ) ) {
			$queue = Plugin::container()->get( Queue::class );
			foreach ( Options::jobs() as $job ) {
				$queue->cancelAll( $job );
			}
		}
		Plugin::container()->get( MigrationStore::class )->clearLock();
	}
}
