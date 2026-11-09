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
	 * @param bool|mixed $networkWide Whether the plugin is being network-activated.
	 */
	public static function activate( $networkWide = false ): void {
		if ( is_multisite() && true === $networkWide ) {
			/** @var int[] $siteIds */
			$siteIds = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $siteIds as $siteId ) {
				switch_to_blog( (int) $siteId ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- activation walks every site once; only the plugin's tables and options are written.
				self::site();
				restore_current_blog();
			}
			return;
		}
		self::site();
	}

	/**
	 * Activate the current site.
	 */
	public static function site(): void {
		Plugin::container()->get( MigrationRunner::class )->run();
	}
}
