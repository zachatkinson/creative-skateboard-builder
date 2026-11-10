<?php
/**
 * One size of a maker's chart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use function is_array;
use function is_string;
use function preg_match;
use function strlen;
use function trim;

/**
 * A row of a spec table: a size as the maker names it (Independent's 149, Venture's 5.2), a value for each spec the
 * chart gives, where the row was read and on which day (spec: Admin, Spec tables). The values are text as the index
 * stores it; `Reading::from()` checks each when the row is used, so a row a newer version wrote still reads.
 *
 * @since 0.3.0
 */
final readonly class TableRow {

	/**
	 * The longest size kept.
	 */
	public const int MAX_SIZE = 40;

	/**
	 * The longest source kept.
	 */
	public const int MAX_SOURCE = 200;

	/**
	 * Constructor.
	 *
	 * @param string                $size   The size's name.
	 * @param array<string, string> $values Each spec's value, by the spec's key.
	 * @param string                $source Where the row was read, '' when not said.
	 * @param string                $readOn The day it was read, as `2026-11-10`, '' when not said.
	 */
	private function __construct( public string $size, public array $values, public string $source, public string $readOn ) {}

	/**
	 * A row as it was saved, or null when it is not one: no size, or a size too long. A value that is not text, or
	 * is blank, is left out; a source too long or a day that is not one reads as not said.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $row What was stored: `size`, `specs`, `source` and `read_on`.
	 */
	public static function read( mixed $row ): ?self {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$size = $row['size'] ?? null;
		$size = is_string( $size ) ? trim( $size ) : '';
		if ( '' === $size || strlen( $size ) > self::MAX_SIZE ) {
			return null;
		}
		$specs  = $row['specs'] ?? null;
		$values = array();
		foreach ( is_array( $specs ) ? $specs : array() as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) && '' !== trim( $value ) ) {
				$values[ $key ] = trim( $value );
			}
		}
		$source = $row['source'] ?? null;
		$source = is_string( $source ) && strlen( trim( $source ) ) <= self::MAX_SOURCE ? trim( $source ) : '';
		$readOn = $row['read_on'] ?? null;
		$readOn = is_string( $readOn ) && 1 === preg_match( '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $readOn ) ? $readOn : '';
		return new self( $size, $values, $source, $readOn );
	}

	/**
	 * The row for the store.
	 *
	 * @since 0.3.0
	 *
	 * @return array{size: string, specs: array<string, string>, source: string, read_on: string}
	 */
	public function toArray(): array {
		return array(
			'size'    => $this->size,
			'specs'   => $this->values,
			'source'  => $this->source,
			'read_on' => $this->readOn,
		);
	}
}
