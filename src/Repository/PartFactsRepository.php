<?php
/**
 * The parts table, in the database.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Repository;

use CreativeBranding\SkateboardBuilder\Port\Clock;
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
	 * The looks or cards kept in one statement: each is a few kilobytes.
	 */
	private const int KEPT = 100;

	/**
	 * How long a kept look or card is believed, in seconds: as long as the object cache keeps them, so that what
	 * no save tells of, a term renamed, shows within the hour (D-289).
	 */
	private const int KEPT_FOR = HOUR_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Clock $clock When a look or a card was kept, and whether it still is.
	 */
	public function __construct( private Clock $clock ) {}

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
		// A part's look and card outlast a rewrite of its facts, a reindex's included (D-289): the look holds nothing
		// a fact does, and the card is kept only where its price and stock are the same. A save changes the stamp
		// each is kept against, so it is read again then.
		$fact_ids = array_map( static fn( PartFact $fact ): int => $fact->id, $facts );
		$before   = array();
		if ( array() !== $fact_ids ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT part_id, price, in_stock, stock, look, card FROM %i WHERE context = %s AND ( look IS NOT NULL OR card IS NOT NULL ) AND part_id IN (' . self::marks( count( $fact_ids ) ) . ')', array( Tables::parts(), $context, ...$fact_ids ) ) );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( is_object( $row ) ) {
					$before[ Cast::int( $row->part_id ?? null ) ] = $row;
				}
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE context = %s AND ( part_id IN (' . $marks . ') OR parent_id IN (' . $marks . ') )', array( Tables::parts(), $context, ...$ids, ...$ids ) ) );
		foreach ( array_chunk( $facts, self::CHUNK ) as $chunk ) {
			$values = array();
			$args   = array( Tables::parts() );
			foreach ( $chunk as $fact ) {
				$old      = $before[ $fact->id ] ?? null;
				$look     = is_object( $old ) && is_string( $old->look ?? null ) ? $old->look : null;
				$same     = is_object( $old ) && Cast::int( $old->price ?? null ) === $fact->price && ( 1 === Cast::int( $old->in_stock ?? null ) ) === $fact->inStock && ( null === ( $old->stock ?? null ) ? null : Cast::int( $old->stock ) ) === $fact->stock;
				$card     = $same && is_string( $old->card ?? null ) ? $old->card : null;
				$values[] = '(%d, %s, %d, %d, %s, %d, %d, ' . ( null === $fact->stock ? 'NULL' : '%d' ) . ', %s, %s, %d, ' . ( null === $look ? 'NULL' : '%s' ) . ', ' . ( null === $card ? 'NULL' : '%s' ) . ')';
				array_push( $args, $fact->id, $context, $fact->of, $fact->sellable ? 1 : 0, $fact->brand, $fact->price, $fact->inStock ? 1 : 0 );
				if ( null !== $fact->stock ) {
					$args[] = $fact->stock;
				}
				array_push( $args, (string) wp_json_encode( (object) $fact->attributes ), (string) wp_json_encode( (object) $fact->specs ), $fact->readAt );
				foreach ( array( $look, $card ) as $kept ) {
					if ( null !== $kept ) {
						$args[] = $kept;
					}
				}
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one group per row, its NULLs written in.
			$wpdb->query( $wpdb->prepare( 'REPLACE INTO %i (part_id, context, parent_id, sellable, brand, price, in_stock, stock, attributes, specs, read_at, look, card) VALUES ' . implode( ', ', $values ), $args ) );
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
		// Read again from the shop by the next request that needs them, as a row an hour old is (`Adapter\WooKits`),
		// and written again with their looks and cards kept (D-289): a reindex forgets every part's facts and none
		// of its pictures.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET read_at = 0 WHERE part_id IN (' . self::marks( count( $own ) ) . ') OR parent_id IN (' . $marks . ')', array( Tables::parts(), ...$own, ...$ids ) ) );
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
	 * Each part's look and card as kept beside its facts in a price context (D-289).
	 *
	 * @since 1.0.0
	 *
	 * @param string $context The price context.
	 * @param int    ...$ids  Products' and variations' IDs.
	 * @return array<int, array{look: array<mixed>|null, card: array<mixed>|null}>
	 */
	public function kept( string $context, int ...$ids ): array {
		global $wpdb;
		if ( array() === $ids ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT part_id, look, card FROM %i WHERE context = %s AND part_id IN (' . self::marks( count( $ids ) ) . ')', array( Tables::parts(), $context, ...array_values( $ids ) ) ) );
		$kept = array();
		$now  = $this->clock->now()->getTimestamp();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$kept[ Cast::int( $row->part_id ?? null ) ] = array(
				'look' => self::decoded( $row->look ?? null, $now ),
				'card' => self::decoded( $row->card ?? null, $now ),
			);
		}
		return $kept;
	}

	/**
	 * Keep these parts' looks, or their cards, beside their facts in a price context, a chunk to a statement.
	 *
	 * @since 1.0.0
	 *
	 * @param string                   $context The price context.
	 * @param 'look'|'card'            $column  Which is kept.
	 * @param array<int, array<mixed>> $values  By part.
	 */
	public function keep( string $context, string $column, array $values ): void {
		global $wpdb;
		foreach ( array_chunk( $values, self::KEPT, true ) as $chunk ) {
			$cases = array();
			$args  = array( Tables::parts(), $column );
			$until = $this->clock->now()->getTimestamp() + self::KEPT_FOR;
			foreach ( $chunk as $id => $value ) {
				$cases[] = 'WHEN %d THEN %s';
				array_push(
					$args,
					$id,
					(string) wp_json_encode(
						array(
							'until' => $until,
							'kept'  => $value,
						)
					)
				);
			}
			array_push( $args, $context, ...array_keys( $chunk ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one WHEN and one %d per product.
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET %i = CASE part_id ' . implode( ' ', $cases ) . ' END WHERE context = %s AND part_id IN (' . self::marks( count( $chunk ) ) . ')', $args ) );
		}
	}

	/**
	 * Forget these parts' looks and cards in every price context: something a card shows moved. Their facts are
	 * forgotten by the same hooks (`Hooks\CardHooks`), and a row written since is kept, so a request that settles a
	 * move after it has read the part again keeps what it read.
	 *
	 * @since 1.0.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function clear( int ...$ids ): void {
		global $wpdb;
		if ( array() === $ids ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET look = NULL, card = NULL WHERE part_id IN (' . self::marks( count( $ids ) ) . ')', array( Tables::parts(), ...array_values( $ids ) ) ) );
	}

	/**
	 * A kept look or card as it was kept, or null where none is, its hour is over or it does not read.
	 *
	 * @param mixed $column The column's value.
	 * @param int   $now    The time, a UTC timestamp.
	 * @return array<mixed>|null
	 */
	private static function decoded( mixed $column, int $now ): ?array {
		if ( ! is_string( $column ) || '' === $column ) {
			return null;
		}
		$value = json_decode( $column, true );
		if ( ! is_array( $value ) || Cast::int( $value['until'] ?? null ) <= $now || ! is_array( $value['kept'] ?? null ) ) {
			return null;
		}
		return $value['kept'];
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
