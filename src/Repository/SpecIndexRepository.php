<?php
/**
 * The specs index, in its table.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Repository;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKind;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A repository: SQL lives here (docs/STANDARDS.md). The table is the plugin's own; what is cached of it is cached by the services that read it, under keys that change when a part does (D-066).

/**
 * `{prefix}cbuild_specs`: a measure, a flag and a count in `number`, a choice in `text` (spec: Data model). Every read
 * goes by the primary key (part, spec) or by one of the two value indexes (spec and number, spec and text), which the
 * benchmark's EXPLAIN cases hold it to.
 *
 * @since 0.3.0
 */
final class SpecIndexRepository implements SpecIndex {

	/**
	 * What `specsOf()` read in this request, by the parts asked for. The cart asks for a build's parts once for its
	 * prices and again for each line's picture (D-202), so an answer is kept until this index is written.
	 *
	 * @var array<string, array<int, Specs>>
	 */
	private array $read = array();

	/**
	 * Make the index hold exactly these specs for a part. Two statements, not one transaction: between them a reader
	 * may see a spec the part has just lost beside its new values, for as long as one statement takes. The index is
	 * derived, and the cart checks a build again when it is added.
	 *
	 * @since 0.3.0
	 *
	 * @param int               $partId The part.
	 * @param list<IndexedSpec> $rows   Its specs, one per key.
	 * @param DateTimeImmutable $at     When they were read.
	 */
	public function replace( int $partId, array $rows, DateTimeImmutable $at ): void {
		global $wpdb;
		$this->read = array();
		if ( array() === $rows ) {
			$this->forget( $partId );
			return;
		}
		$when   = $at->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$values = array();
		$args   = array( Tables::specs() );
		$kept   = array( Tables::specs(), $partId );
		foreach ( $rows as $row ) {
			$values[] = SpecKind::Choice === $row->key->kind() ? '(%d, %s, NULL, %s, %s, %s, %s)' : '(%d, %s, %s, NULL, %s, %s, %s)';
			array_push( $args, $partId, $row->key->value, $row->text, $row->origin->value, $row->ref, $when );
			$kept[] = $row->key->value;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one group per row, each of the two literals above.
		$wpdb->query( $wpdb->prepare( 'REPLACE INTO %i (part_id, spec, number, text, source, source_ref, indexed_at) VALUES ' . implode( ', ', $values ), $args ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %s per spec kept.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE part_id = %d AND spec NOT IN (' . self::marks( '%s', count( $rows ) ) . ')', $kept ) );
	}

	/**
	 * Remove everything the index holds for these parts.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds The parts.
	 */
	public function forget( int ...$partIds ): void {
		global $wpdb;
		$this->read = array();
		if ( array() === $partIds ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per part.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE part_id IN (' . self::marks( '%d', count( $partIds ) ) . ')', array( Tables::specs(), ...array_values( $partIds ) ) ) );
	}

	/**
	 * The specs of each of these parts, in one query, or none where the same parts were read in this request and the
	 * index has not been written since.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds The parts.
	 * @return array<int, Specs> By part.
	 */
	public function specsOf( int ...$partIds ): array {
		global $wpdb;
		if ( array() === $partIds ) {
			return array();
		}
		$asked = $partIds;
		sort( $asked );
		$key = implode( ',', $asked );
		if ( isset( $this->read[ $key ] ) ) {
			return $this->read[ $key ];
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per part.
		$found = $wpdb->get_results( $wpdb->prepare( 'SELECT part_id, spec, number, text FROM %i WHERE part_id IN (' . self::marks( '%d', count( $partIds ) ) . ')', array( Tables::specs(), ...array_values( $partIds ) ) ), ARRAY_A );
		$texts = array();
		foreach ( self::rows( $found ) as $row ) {
			$text = self::text( $row );
			if ( null !== $text ) {
				$texts[ Cast::int( $row['part_id'] ?? null ) ][ Cast::str( $row['spec'] ?? null ) ] = $text;
			}
		}
		$this->read[ $key ] = array_map( static fn( array $rows ): Specs => Specs::fromIndex( $rows ), $texts );
		return $this->read[ $key ];
	}

	/**
	 * One part's rows, with their sources, in the order of the spec keys. A row this version cannot read (a key or a
	 * source a newer version wrote) is passed over, as `Specs::fromIndex()` passes over it.
	 *
	 * @since 0.3.0
	 *
	 * @param int $partId The part.
	 * @return list<IndexedSpec>
	 */
	public function rowsOf( int $partId ): array {
		global $wpdb;
		$found = $wpdb->get_results( $wpdb->prepare( 'SELECT spec, number, text, source, source_ref FROM %i WHERE part_id = %d', Tables::specs(), $partId ), ARRAY_A );
		$rows  = array();
		foreach ( self::rows( $found ) as $row ) {
			$key    = SpecKey::tryFrom( Cast::str( $row['spec'] ?? null ) );
			$origin = Origin::tryFrom( Cast::str( $row['source'] ?? null ) );
			$text   = self::text( $row );
			if ( null !== $key && null !== $origin && null !== $text ) {
				$rows[ $key->value ] = new IndexedSpec( $key, $text, $origin, Cast::str( $row['source_ref'] ?? null ) );
			}
		}
		$ordered = array();
		foreach ( SpecKey::cases() as $key ) {
			if ( isset( $rows[ $key->value ] ) ) {
				$ordered[] = $rows[ $key->value ];
			}
		}
		return $ordered;
	}

	/**
	 * The parts whose measure for a spec is from one value to another, smallest first.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key   A measure's key.
	 * @param Measure $from  The least.
	 * @param Measure $to    The most.
	 * @param int     $limit How many at most.
	 * @return list<int>
	 */
	public function partsBetween( SpecKey $key, Measure $from, Measure $to, int $limit ): array {
		global $wpdb;
		$unit = $key->unit();
		if ( null === $unit ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The statement is the constant below, a literal with placeholders.
		return self::ids( $wpdb->get_col( $wpdb->prepare( self::BETWEEN, Tables::specs(), $key->value, $from->to( $unit )->decimal(), $to->to( $unit )->decimal(), $limit ) ) );
	}

	/**
	 * The parts whose choice for a spec is this one, by ID.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key    A choice's key.
	 * @param string  $choice The value.
	 * @param int     $limit  How many at most.
	 * @return list<int>
	 */
	public function partsWith( SpecKey $key, string $choice, int $limit ): array {
		global $wpdb;
		if ( SpecKind::Choice !== $key->kind() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The statement is the constant below, a literal with placeholders.
		return self::ids( $wpdb->get_col( $wpdb->prepare( self::WITH, Tables::specs(), $key->value, $choice, $limit ) ) );
	}

	/**
	 * The parts the index holds anything for, after a part ID, by ID.
	 *
	 * @since 0.3.0
	 *
	 * @param int $after The last part ID seen.
	 * @param int $limit How many at most.
	 * @return list<int>
	 */
	public function partsAfter( int $after, int $limit ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The statement is the constant below, a literal with placeholders.
		return self::ids( $wpdb->get_col( $wpdb->prepare( self::AFTER, Tables::specs(), $after, $limit ) ) );
	}

	/**
	 * The candidate queries and the reindex's walk, named so the benchmark can ask the database for the plan of the very statements
	 * that run.
	 */
	public const BETWEEN = 'SELECT part_id FROM %i WHERE spec = %s AND number BETWEEN %s AND %s ORDER BY number, part_id LIMIT %d';
	public const WITH    = 'SELECT part_id FROM %i WHERE spec = %s AND text = %s ORDER BY part_id LIMIT %d';
	public const AFTER   = 'SELECT DISTINCT part_id FROM %i WHERE part_id > %d ORDER BY part_id LIMIT %d';

	/**
	 * What a query returned, as rows.
	 *
	 * @param mixed $found From `$wpdb->get_results()` with ARRAY_A.
	 * @return list<array<mixed>>
	 */
	private static function rows( mixed $found ): array {
		return array_values( array_filter( is_array( $found ) ? $found : array(), 'is_array' ) );
	}

	/**
	 * A row's value as `Specs::fromIndex()` reads it: a measure as the decimal the column holds, a flag and a count
	 * as whole digits, a choice as its text. Null for a key this version does not know, and for a row with its value
	 * in the wrong column.
	 *
	 * @param array<mixed> $row A row with `spec`, `number` and `text`.
	 */
	private static function text( array $row ): ?string {
		$key = SpecKey::tryFrom( Cast::str( $row['spec'] ?? null ) );
		if ( null === $key ) {
			return null;
		}
		$kind  = $key->kind();
		$value = $row[ SpecKind::Choice === $kind ? 'text' : 'number' ] ?? null;
		if ( ! is_string( $value ) ) {
			return null;
		}
		return SpecKind::Flag === $kind || SpecKind::Count === $kind ? (string) (int) $value : $value;
	}

	/**
	 * A column of IDs as whole numbers.
	 *
	 * @param array<mixed> $column What the database returned.
	 * @return list<int>
	 */
	private static function ids( array $column ): array {
		return array_map( static fn( mixed $id ): int => is_numeric( $id ) ? (int) $id : 0, array_values( $column ) );
	}

	/**
	 * A list of placeholders.
	 *
	 * @param string $mark  The placeholder.
	 * @param int    $count How many.
	 */
	private static function marks( string $mark, int $count ): string {
		return implode( ', ', array_fill( 0, $count, $mark ) );
	}
}
