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
	 * @since 0.1.0
	 *
	 * @param bool|mixed $networkWide Whether the plugin is being deactivated for the whole network.
	 */
	public static function deactivate( $networkWide = false ): void {
		if ( is_multisite() && true === $networkWide ) {
			Sites::each( array( self::class, 'site' ) );
			return;
		}
		self::site();
	}

	/**
	 * Deactivate the current site. Action Scheduler's tables follow switch_to_blog(), and a site of a network that
	 * was never loaded has none yet, so its queue is left alone: it holds nothing.
	 *
	 * @since 0.1.0
	 */
	public static function site(): void {
		if ( Sites::hasQueueTables() ) {
			$queue = Plugin::container()->get( Queue::class );
			foreach ( Options::jobs() as $job ) {
				$queue->cancelAll( $job );
			}
		}
		Plugin::container()->get( MigrationStore::class )->clearLock();
		// What rewrite rules were flushed for: anything may rebuild them without My builds while the plugin is off.
		delete_option( Options::ENDPOINTS );
	}
}
