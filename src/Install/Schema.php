<?php
/**
 * Database schema.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

use CreativeBranding\SkateboardBuilder\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades the two tables with dbDelta() (spec: Data model), from `Install\Migrations\SchemaMigration`.
 *
 * Keys are written exactly as dbDelta() parses them (two spaces after PRIMARY KEY, `UNIQUE KEY name (cols)`,
 * `KEY name (cols)`). Integer types carry the display width SHOW COLUMNS reports (bigint(20) unsigned, tinyint(1))
 * so a second dbDelta() reports no changes. Column types are lower case, as core writes its own schema. No comments
 * in the SQL: dbDelta() splits the string on semicolons. No JSON or ENUM columns but the picks, which are read whole.
 */
final class Schema {

	/**
	 * Create or upgrade every table. Idempotent.
	 *
	 * @return string[] dbDelta's report lines; empty when nothing changed.
	 */
	public static function install(): array {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		return array_values( dbDelta( self::sql() ) );
	}

	/**
	 * The plugin's tables the database does not have. dbDelta() reports a failed CREATE TABLE as if it had worked,
	 * so the migration asks.
	 *
	 * @return string[] Full table names.
	 */
	public static function missing(): array {
		global $wpdb;
		$missing = array();
		foreach ( Tables::all() as $table ) {
			$name = Tables::name( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Asked once, after dbDelta().
			if ( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) ) ) {
				$missing[] = $name;
			}
		}
		return $missing;
	}

	/**
	 * The full CREATE TABLE set as one string, dbDelta style. The specs index: one row per part and spec, the value
	 * as a number or a short text, where it came from and when. Saved builds: a customer's picks under a UUID.
	 */
	public static function sql(): string {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$specs   = Tables::specs();
		$builds  = Tables::builds();

		return "
CREATE TABLE {$specs} (
  part_id bigint(20) unsigned NOT NULL,
  spec varchar(32) NOT NULL,
  number decimal(10,4) NULL,
  text varchar(64) NULL,
  source varchar(16) NOT NULL,
  source_ref varchar(191) NOT NULL DEFAULT '',
  indexed_at datetime NOT NULL,
  PRIMARY KEY  (part_id,spec),
  KEY spec_number (spec,number),
  KEY spec_text (spec,text)
) {$charset};
CREATE TABLE {$builds} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  kit_id bigint(20) unsigned NOT NULL,
  picks longtext NOT NULL,
  assembly tinyint(1) NOT NULL DEFAULT 1,
  name varchar(100) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY user_id (user_id)
) {$charset};
";
	}
}
