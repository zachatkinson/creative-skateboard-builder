<?php
/**
 * The parts table, in the database.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Repository;

use CreativeBranding\SkateboardBuilder\Port\PartFact;
use CreativeBranding\SkateboardBuilder\Port\PartFacts;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table; the table is what is kept, and a cache over it would be a second thing to keep current.

/**
 * `{prefix}cbuild_parts`: a row to a part and a price context (D-116). Every read goes by the primary key (part,
 * context) or by the key on a variation's parent, and every statement is prepared.
 *
 * @since 0.4.0
 */
final class PartFactsRepository implements PartFacts {

	/**
	 * How many rows one INSERT writes.
	 */
	private const int CHUNK = 500;

	/**
	 * The rows of these products and of their variations, by ID.
	 *
	 * @since 0.4.0
	 *
	 * @param string $context The price context.
	 * @param int    ...$ids  Products' and variations' IDs.
	 * @return array<int, PartFact>
	 */
	public function of( string $context, int ...$ids ): array {
		global $wpdb;
		if ( array() === $ids ) {
			return array();
		}
		$read = 'SELECT part_id, parent_id, sellable, brand, price, in_stock, stock, attributes, specs, read_at FROM %i WHERE context = %s AND ';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$facts = self::facts( $wpdb->get_results( $wpdb->prepare( $read . 'part_id IN (' . self::marks( count( $ids ) ) . ')', array( Tables::parts(), $context, ...array_values( $ids ) ) ) ) );
		// Only a product that is no part itself can stand for variations: a second read for those, by the parent's key.
		$parents = array();
		foreach ( $facts as $fact ) {
			if ( ! $fact->sellable && 0 === $fact->of ) {
				$parents[] = $fact->id;
			}
		}
		if ( array() !== $parents ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
			$facts += self::facts( $wpdb->get_results( $wpdb->prepare( $read . 'parent_id IN (' . self::marks( count( $parents ) ) . ')', array( Tables::parts(), $context, ...$parents ) ) ) );
		}
		return $facts;
	}

	/**
	 * Make the table hold exactly these rows for these products and their variations, in one price context.
	 *
	 * @since 0.4.0
	 *
	 * @param string         $context The price context.
	 * @param list<int>      $ids     The products and variations read.
	 * @param list<PartFact> $facts   What was read of them and of their variations.
	 */
	public function replace( string $context, array $ids, array $facts ): void {
		global $wpdb;
		if ( array() === $ids ) {
			return;
		}
		$marks = self::marks( count( $ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE context = %s AND ( part_id IN (' . $marks . ') OR parent_id IN (' . $marks . ') )', array( Tables::parts(), $context, ...$ids, ...$ids ) ) );
		foreach ( array_chunk( $facts, self::CHUNK ) as $chunk ) {
			$values = array();
			$args   = array( Tables::parts() );
			foreach ( $chunk as $fact ) {
				$values[] = null === $fact->stock ? '(%d, %s, %d, %d, %s, %d, %d, NULL, %s, %s, %d)' : '(%d, %s, %d, %d, %s, %d, %d, %d, %s, %s, %d)';
				array_push( $args, $fact->id, $context, $fact->of, $fact->sellable ? 1 : 0, $fact->brand, $fact->price, $fact->inStock ? 1 : 0 );
				if ( null !== $fact->stock ) {
					$args[] = $fact->stock;
				}
				array_push( $args, (string) wp_json_encode( (object) $fact->attributes ), (string) wp_json_encode( (object) $fact->specs ), $fact->readAt );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one group per row, each of the two literals above.
			$wpdb->query( $wpdb->prepare( 'REPLACE INTO %i (part_id, context, parent_id, sellable, brand, price, in_stock, stock, attributes, specs, read_at) VALUES ' . implode( ', ', $values ), $args ) );
		}
	}

	/**
	 * Forget these products in every price context, with their variations and the rows of their parents.
	 *
	 * @since 0.4.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function forget( int ...$ids ): void {
		global $wpdb;
		if ( array() === $ids ) {
			return;
		}
		$ids   = array_values( $ids );
		$marks = self::marks( count( $ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$parents = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT parent_id FROM %i WHERE parent_id > 0 AND part_id IN (' . $marks . ')', array( Tables::parts(), ...$ids ) ) );
		$own     = array_values( array_unique( array_merge( $ids, array_map( static fn( mixed $id ): int => Cast::int( $id ), $parents ) ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE part_id IN (' . self::marks( count( $own ) ) . ') OR parent_id IN (' . $marks . ')', array( Tables::parts(), ...$own, ...$ids ) ) );
	}

	/**
	 * Forget every row.
	 *
	 * @since 0.4.0
	 */
	public function forgetAll(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::parts() ) );
	}

	/**
	 * Rows as facts, by part.
	 *
	 * @param mixed $found What the database returned: rows as objects.
	 * @return array<int, PartFact>
	 */
	private static function facts( mixed $found ): array {
		$facts = array();
		foreach ( is_array( $found ) ? $found : array() as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$row          = get_object_vars( $row );
			$id           = Cast::int( $row['part_id'] ?? null );
			$facts[ $id ] = new PartFact(
				$id,
				Cast::int( $row['parent_id'] ?? null ),
				1 === Cast::int( $row['sellable'] ?? null ),
				Cast::str( $row['brand'] ?? null ),
				max( 0, Cast::int( $row['price'] ?? null ) ),
				1 === Cast::int( $row['in_stock'] ?? null ),
				null === ( $row['stock'] ?? null ) ? null : Cast::int( $row['stock'] ),
				self::texts( $row['attributes'] ?? null ),
				self::texts( $row['specs'] ?? null ),
				Cast::int( $row['read_at'] ?? null )
			);
		}
		return $facts;
	}

	/**
	 * A column of JSON as text by name: what is not that is left out.
	 *
	 * @param mixed $column What the database returned.
	 * @return array<string, string>
	 */
	private static function texts( mixed $column ): array {
		$read  = json_decode( Cast::str( $column ), true );
		$texts = array();
		foreach ( is_array( $read ) ? $read : array() as $name => $text ) {
			if ( is_string( $text ) ) {
				$texts[ (string) $name ] = $text;
			}
		}
		return $texts;
	}

	/**
	 * A list of placeholders for whole numbers.
	 *
	 * @param int $count How many.
	 */
	private static function marks( int $count ): string {
		return implode( ', ', array_fill( 0, $count, '%d' ) );
	}
}
