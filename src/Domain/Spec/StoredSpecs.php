<?php
/**
 * What a part stores of its own specs, read.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use function is_array;
use function is_int;
use function is_string;
use function max;
use function trim;

/**
 * A part keeps two things: its own value per spec key, and the spec table and size it names. Both are read here
 * from whatever is stored, so the index's sources and the Part specs panel read them the same way.
 *
 * @since 0.3.0
 */
final class StoredSpecs {

	/**
	 * Why the table and size a part names cannot be read: the table is not there, or it has no such size. Every
	 * class that says so says it with these.
	 *
	 * @since 0.4.0
	 */
	public const TABLE_MISSING = 'table_missing';
	public const SIZE_MISSING  = 'size_missing';

	/**
	 * The part's own values: those that are text and not blank, trimmed, by key. A key this version does not know is
	 * kept, since a newer version may have written it.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $stored What is stored.
	 * @return array<string, string>
	 */
	public static function fields( mixed $stored ): array {
		$fields = array();
		foreach ( is_array( $stored ) ? $stored : array() as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) && '' !== trim( $value ) ) {
				$fields[ $key ] = trim( $value );
			}
		}
		return $fields;
	}

	/**
	 * The spec table and the size the part names: 0 and '' where it names none.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $stored What is stored.
	 * @return array{table: int, size: string}
	 */
	public static function named( mixed $stored ): array {
		$stored = is_array( $stored ) ? $stored : array();
		$table  = $stored['table'] ?? null;
		$size   = $stored['size'] ?? '';
		return array(
			'table' => is_int( $table ) ? max( 0, $table ) : 0,
			'size'  => is_string( $size ) ? trim( $size ) : '',
		);
	}

	/**
	 * What a part is to keep, as `fields()` and `named()` read it back. Null is nothing to keep: no value of its own,
	 * or neither a table nor a size. Every writer of a part stores this.
	 *
	 * @since 0.4.0
	 *
	 * @param array<string, string> $fields Its own values, by spec key.
	 * @param int                   $table  The spec table it names, 0 for none.
	 * @param string                $size   The size it names, '' for none.
	 * @return array{fields: array<string, string>|null, named: array{table: int, size: string}|null}
	 */
	public static function kept( array $fields, int $table, string $size ): array {
		return array(
			'fields' => array() === $fields ? null : $fields,
			'named'  => 0 === $table && '' === $size ? null : array(
				'table' => $table,
				'size'  => $size,
			),
		);
	}
}
