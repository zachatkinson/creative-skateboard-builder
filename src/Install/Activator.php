<?php
/**
 * Activation.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RunResult;
use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Port\Reindex;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the migrations, which create the two tables and seed nothing (docs/ROADMAP.md, milestone 1). On a network
 * activation every site gets the same (D-025). Activation is an admin action, so the run is inline, under the lock.
 * Then every part is asked to be read again: a new install has an empty index, and while the plugin was switched off
 * its jobs were cancelled and no save was heard (D-082).
 */
final class Activator {

	/**
	 * Activation hook.
	 *
	 * @since 0.1.0
	 *
	 * @param bool|mixed $networkWide Whether the plugin is being network-activated.
	 */
	public static function activate( $networkWide = false ): void {
		if ( is_multisite() && true === $networkWide ) {
			Sites::each( array( self::class, 'site' ) );
			return;
		}
		self::site();
	}

	/**
	 * Activate the current site.
	 *
	 * @since 0.1.0
	 */
	public static function site(): void {
		$result = Plugin::container()->get( MigrationRunner::class )->run();
		// A run that stopped short asks when it finishes (Hooks\InstallHooks). A site with no queue yet has nowhere to
		// put the job: WooCommerce is missing, and a product cannot be saved without it.
		if ( in_array( $result, array( RunResult::Finished, RunResult::NotNeeded ), true ) && Sites::hasQueueTables() ) {
			Plugin::container()->get( Reindex::class )->requestReindex();
		}
	}
}
