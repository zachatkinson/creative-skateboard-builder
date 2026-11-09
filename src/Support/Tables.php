<?php
/**
 * Table name helper.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place table names are built: `{$wpdb->prefix}cbuild_{table}` (spec: Data model).
 */
final class Tables {

	public const SPECS  = 'specs';
	public const BUILDS = 'builds';

	public const PREFIX = 'cbuild_';

	/**
	 * Every plugin table, in creation order.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::SPECS, self::BUILDS );
	}

	/**
	 * Fully prefixed table name.
	 *
	 * @param string $table One of the class constants.
	 */
	public static function name( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . self::PREFIX . $table;
	}

	/**
	 * The specs index.
	 */
	public static function specs(): string {
		return self::name( self::SPECS );
	}

	/**
	 * The saved builds.
	 */
	public static function builds(): string {
		return self::name( self::BUILDS );
	}
}
