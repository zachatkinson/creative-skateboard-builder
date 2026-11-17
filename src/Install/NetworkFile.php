<?php
/**
 * The must-use file that keeps a network's deletions whole where the plugin is not loaded.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

use CreativeBranding\SkateboardBuilder\Support\Tables;
use WP_Filesystem_Direct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issue #8 (D-220). WordPress drops a deleted network site's tables only where a plugin loaded on the request that
 * deletes it names them for `wpmu_drop_tables` (wp-includes/ms-site.php:832). With the plugin active on that one site
 * alone, none of its code is loaded when the network's admin deletes it, and its tables, saved builds among them,
 * would stay. In the same way the network's admin deletes an account where only network-active plugins are loaded, and
 * the builds it saved on a site with the plugin active there alone would stay. So on a network the plugin writes a
 * small must-use file, which loads on every site and in the network's admin, that does those two things and nothing
 * else: it names the plugin's tables for `wpmu_drop_tables`, and on `wpmu_delete_user` (wp-admin/includes/ms.php:174)
 * deletes the account's rows from each site's builds table. Uninstall removes it. One of the files uninstall.php loads itself below the floors, so it
 * must parse on PHP 7.4 and use nothing but WordPress (D-023, D-043).
 *
 * @since 0.8.0
 */
final class NetworkFile {

	/**
	 * The file's name in the must-use folder.
	 *
	 * @since 0.8.0
	 */
	public const NAME = 'creative-skateboard-builder-network.php';

	/**
	 * The file's path.
	 *
	 * @since 0.8.0
	 */
	public static function path(): string {
		return WPMU_PLUGIN_DIR . '/' . self::NAME;
	}

	/**
	 * What the file holds: the plugin's tables, without the site's prefix, which the file adds for the site deleted.
	 *
	 * @since 0.8.0
	 */
	public static function content(): string {
		$names = array();
		foreach ( Tables::all() as $table ) {
			$names[] = "'" . Tables::PREFIX . $table . "'";
		}
		return '<?php' . "\n"
			. '/**' . "\n"
			. ' * Plugin Name: Creative Skateboard Builder, network deletions' . "\n"
			. ' * Description: Written by Creative Skateboard Builder on a network, so that deleting a site drops the plugin\'s tables, and deleting an account deletes its saved builds on every site, even where the plugin is not loaded. Removed when the plugin is deleted.' . "\n"
			. ' */' . "\n\n"
			. "defined( 'ABSPATH' ) || exit;\n\n"
			. "add_filter(\n"
			. "\t'wpmu_drop_tables',\n"
			. "\tstatic function ( \$tables, \$site_id = 0 ) {\n"
			. "\t\tglobal \$wpdb;\n"
			. "\t\tif ( ! is_array( \$tables ) ) {\n"
			. "\t\t\treturn \$tables;\n"
			. "\t\t}\n"
			. "\t\tforeach ( array( " . implode( ', ', $names ) . " ) as \$table ) {\n"
			. "\t\t\t\$tables[] = \$wpdb->get_blog_prefix( (int) \$site_id ) . \$table;\n"
			. "\t\t}\n"
			. "\t\treturn array_values( array_unique( \$tables ) );\n"
			. "\t},\n"
			. "\t10,\n"
			. "\t2\n"
			. ");\n\n"
			. "add_action(\n"
			. "\t'wpmu_delete_user',\n"
			. "\tstatic function ( \$user_id ) {\n"
			. "\t\tglobal \$wpdb;\n"
			. "\t\t// One query finds every site's builds table, however many sites there are; then one delete each.\n"
			. "\t\t\$tables = \$wpdb->get_col( \$wpdb->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s', \$wpdb->esc_like( \$wpdb->base_prefix ) . '%' . \$wpdb->esc_like( '" . Tables::PREFIX . Tables::BUILDS . "' ) ) );\n"
			. "\t\tforeach ( \$tables as \$table ) {\n"
			. "\t\t\tif ( 1 === preg_match( '/\\A' . preg_quote( \$wpdb->base_prefix, '/' ) . '(\\d+_)?" . Tables::PREFIX . Tables::BUILDS . "\\z/', (string) \$table ) ) {\n"
			. "\t\t\t\t\$wpdb->query( \$wpdb->prepare( 'DELETE FROM %i WHERE user_id = %d', \$table, (int) \$user_id ) );\n"
			. "\t\t\t}\n"
			. "\t\t}\n"
			. "\t}\n"
			. ");\n";
	}

	/**
	 * Whether the file is in place, as this release writes it. Always true on a single site, which needs none.
	 *
	 * @since 0.8.0
	 */
	public static function inPlace(): bool {
		if ( ! is_multisite() ) {
			return true;
		}
		return is_readable( self::path() ) && self::content() === self::disk()->get_contents( self::path() );
	}

	/**
	 * Write the file on a network, where it is missing or out of date. False where it could not be written, which Site
	 * Health says.
	 *
	 * @since 0.8.0
	 */
	public static function ensure(): bool {
		if ( self::inPlace() ) {
			return true;
		}
		if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) || ! wp_is_writable( WPMU_PLUGIN_DIR ) ) {
			return false;
		}
		return self::disk()->put_contents( self::path(), self::content(), 0644 );
	}

	/**
	 * Remove the file, if it is the plugin's.
	 *
	 * @since 0.8.0
	 */
	public static function remove(): void {
		$path = self::path();
		if ( is_file( $path ) && false !== strpos( (string) self::disk()->get_contents( $path ), 'Written by Creative Skateboard Builder' ) ) {
			self::disk()->delete( $path );
		}
	}

	/**
	 * WordPress's direct file access, loaded where it is not yet.
	 */
	private static function disk(): WP_Filesystem_Direct {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new WP_Filesystem_Direct( null );
	}
}
