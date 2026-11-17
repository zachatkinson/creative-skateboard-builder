<?php
/**
 * The saved builds' table.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Repository;

use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Port\SavedBuildStore;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A repository: SQL lives here (docs/STANDARDS.md). The table is the plugin's own, and a customer's builds are read on their own pages alone, which no cache may share.

/**
 * `{prefix}cbuild_builds` (spec: Data model, Saved builds; `Install\Schema`). Every statement names the customer in its
 * WHERE, so no read or change reaches another customer's row, whatever UUID is asked for. The picks are JSON, slot key
 * to part ID; times are UTC.
 *
 * @since 0.8.0
 */
final class SavedBuildRepository implements SavedBuildStore {

	/**
	 * A customer's builds, the last saved first.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 * @return list<SavedBuild>
	 */
	public function of( int $user ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT uuid, user_id, kit_id, picks, assembly, name, created_at, updated_at FROM %i WHERE user_id = %d ORDER BY updated_at DESC, id DESC', Tables::builds(), $user ) );
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$build = self::build( $row );
			if ( null !== $build ) {
				$out[] = $build;
			}
		}
		return $out;
	}

	/**
	 * One of a customer's builds, or null.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 */
	public function find( int $user, string $uuid ): ?SavedBuild {
		global $wpdb;
		return self::build( $wpdb->get_row( $wpdb->prepare( 'SELECT uuid, user_id, kit_id, picks, assembly, name, created_at, updated_at FROM %i WHERE user_id = %d AND uuid = %s', Tables::builds(), $user, $uuid ) ) );
	}

	/**
	 * How many builds a customer has.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 */
	public function count( int $user ): int {
		global $wpdb;
		return Cast::int( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Tables::builds(), $user ) ) );
	}

	/**
	 * Keep a build: a new row, or the customer's own row written again. A UUID another customer holds is left alone.
	 *
	 * @since 0.8.0
	 *
	 * @param SavedBuild $build The build.
	 * @return bool Whether the table holds it now.
	 */
	public function put( SavedBuild $build ): bool {
		global $wpdb;
		$picks   = (string) wp_json_encode( $build->picks );
		$created = self::utc( $build->created );
		$updated = self::utc( $build->updated );
		$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET kit_id = %d, picks = %s, assembly = %d, name = %s, updated_at = %s WHERE user_id = %d AND uuid = %s', Tables::builds(), $build->kit, $picks, $build->assembly ? 1 : 0, $build->name, $updated, $build->user, $build->uuid ) );
		if ( false === $changed ) {
			return false;
		}
		if ( 0 !== $changed || null !== $this->find( $build->user, $build->uuid ) ) {
			return true;
		}
		// INSERT IGNORE: the UUID's unique key keeps another customer's row as it is, and nothing is inserted.
		return 1 === $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (uuid, user_id, kit_id, picks, assembly, name, created_at, updated_at) VALUES (%s, %d, %d, %s, %d, %s, %s, %s)', Tables::builds(), $build->uuid, $build->user, $build->kit, $picks, $build->assembly ? 1 : 0, $build->name, $created, $updated ) );
	}

	/**
	 * Delete one of a customer's builds.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 */
	public function delete( int $user, string $uuid ): bool {
		global $wpdb;
		return 1 === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE user_id = %d AND uuid = %s', Tables::builds(), $user, $uuid ) );
	}

	/**
	 * Delete every build of a customer's.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 */
	public function forget( int $user ): int {
		global $wpdb;
		return Cast::int( $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE user_id = %d', Tables::builds(), $user ) ) );
	}

	/**
	 * A row as a build, or null where it is none: picks that are not JSON of slot keys to IDs, or a time unread.
	 *
	 * @param mixed $row The row.
	 */
	private static function build( mixed $row ): ?SavedBuild {
		if ( ! is_object( $row ) ) {
			return null;
		}
		$row   = get_object_vars( $row );
		$picks = array();
		foreach ( Cast::map( json_decode( Cast::str( $row['picks'] ?? null ), true ) ) as $slot => $part ) {
			if ( is_string( $slot ) && is_int( $part ) && $part > 0 ) {
				$picks[ $slot ] = $part;
			}
		}
		$utc     = new DateTimeZone( 'UTC' );
		$created = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', Cast::str( $row['created_at'] ?? null ), $utc );
		$updated = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', Cast::str( $row['updated_at'] ?? null ), $utc );
		if ( false === $created || false === $updated ) {
			return null;
		}
		$assembly = Cast::str( $row['assembly'] ?? null );
		return new SavedBuild( Cast::str( $row['uuid'] ?? null ), Cast::int( $row['user_id'] ?? null ), Cast::int( $row['kit_id'] ?? null ), $picks, '1' === $assembly, Cast::str( $row['name'] ?? null ), $created, $updated );
	}

	/**
	 * A moment as the table keeps it.
	 *
	 * @param DateTimeImmutable $at The moment.
	 */
	private static function utc( DateTimeImmutable $at ): string {
		return $at->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
