<?php
/**
 * Activation.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the migrations, which create the two tables and seed nothing (docs/ROADMAP.md, milestone 1). On a network
 * activation every site gets the same (D-025). Activation is an admin action, so the run is inline, under the lock.
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
		Plugin::container()->get( MigrationRunner::class )->run();
	}
}
