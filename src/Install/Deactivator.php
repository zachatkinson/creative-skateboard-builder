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
 * it was (docs/reviews/M1.md). Action Scheduler is loaded here, since `Requires Plugins` keeps WooCommerce active
 * while the builder is (D-024).
 */
final class Deactivator {

	/**
	 * Deactivation hook.
	 */
	public static function deactivate(): void {
		$queue = Plugin::container()->get( Queue::class );
		foreach ( Options::jobs() as $job ) {
			$queue->cancelAll( $job );
		}
		Plugin::container()->get( MigrationStore::class )->releaseLock();
	}
}
