<?php
/**
 * A screen's script and the admin kit's styles, loaded as the build wrote them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin\Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each screen's script is its own entry (build/admin/<screen>.js), with the dependencies and version the build wrote
 * beside it (<screen>.asset.php). The kit's styles are one stylesheet every screen shares (build/admin/kit.css), after
 * `wp-components`' and with its right-to-left copy (docs/research/admin-handbooks-2026.md). A screen calls this from
 * its own `admin_enqueue_scripts`, so nothing loads on a page that is not one of its own.
 *
 * @since 1.5.0
 */
final class Assets {

	/**
	 * The handle of the kit's stylesheet.
	 */
	public const STYLE = 'cbuild-admin-kit';

	/**
	 * The kit's stylesheet, which every screen shares.
	 */
	private const KIT_STYLE = 'build/admin/kit.css';

	/**
	 * The screens' builds, each named in full, so the zip's check finds every one (tools/build-zip.sh, D-086).
	 */
	private const SCREENS = array(
		'settings'    => 'build/admin/settings',
		'import'      => 'build/admin/import',
		'check'       => 'build/admin/check',
		'spec-tables' => 'build/admin/spec-tables',
	);

	/**
	 * Loads a screen's script and the kit's styles.
	 *
	 * @since 1.5.0
	 *
	 * @param string $screen The screen's entry, as build/admin/<screen>.js: one of SCREENS' keys.
	 * @param string $dir    The plugin's folder, with a trailing slash.
	 * @param string $url    The plugin's URL, with a trailing slash.
	 * @return bool Whether the screen's build was found.
	 */
	public static function enqueue( string $screen, string $dir, string $url ): bool {
		$built = self::SCREENS[ $screen ] ?? null;
		if ( null === $built ) {
			return false;
		}
		$asset = $dir . $built . '.asset.php';
		if ( ! is_readable( $asset ) ) {
			return false;
		}
		$asset_file   = require $asset; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- the build's own file: the plugin's folder and a screen named in SCREENS.
		$dependencies = is_array( $asset_file ) && isset( $asset_file['dependencies'] ) && is_array( $asset_file['dependencies'] ) ? array_values( array_filter( $asset_file['dependencies'], static fn( mixed $handle ): bool => is_string( $handle ) && '' !== $handle ) ) : array();
		$version      = is_array( $asset_file ) && isset( $asset_file['version'] ) && is_string( $asset_file['version'] ) ? $asset_file['version'] : false;
		wp_enqueue_style( self::STYLE, $url . self::KIT_STYLE, array( 'wp-components' ), $version );
		wp_style_add_data( self::STYLE, 'rtl', 'replace' );
		wp_enqueue_script( 'cbuild-admin-' . $screen, $url . $built . '.js', $dependencies, $version, true );
		return true;
	}
}
