<?php
/**
 * A slot's parts as a cache keeps them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidPart;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes a slot's parts as scalars and arrays of them, which is all a cache is given (docs/STANDARDS.md, Object
 * cache), and reads them back (D-116). A row is the part's ID, its brand, its price in minor units, whether it is
 * in stock, how many the shop holds or null, and its specs as the index stores them.
 *
 * What a cache hands back is not trusted to be what was written: a list with anything else in it is no list at
 * all, and the parts are then read from the shop again.
 *
 * @since 0.4.0
 */
final class PartRows {

	/**
	 * The parts as rows.
	 *
	 * @since 0.4.0
	 *
	 * @param list<Part> $parts The parts.
	 * @return list<array{int, string, int, bool, int|null, array<string, string>}>
	 */
	public static function of( array $parts ): array {
		$rows = array();
		foreach ( $parts as $part ) {
			$rows[] = array( $part->id, $part->brand, $part->price, $part->inStock, $part->stock, $part->specs->toIndex() );
		}
		return $rows;
	}

	/**
	 * The rows as parts, in their order, or null when they are not what of() writes.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $kept What was kept.
	 * @return list<Part>|null
	 */
	public static function read( mixed $kept ): ?array {
		if ( ! is_array( $kept ) || ! array_is_list( $kept ) ) {
			return null;
		}
		$parts = array();
		foreach ( $kept as $row ) {
			if ( ! is_array( $row ) || 6 !== count( $row ) || ! array_is_list( $row ) ) {
				return null;
			}
			[ $id, $brand, $price, $in, $stock, $specs ] = $row;
			if ( ! is_int( $id ) || ! is_string( $brand ) || ! is_int( $price ) || ! is_bool( $in ) || ! ( null === $stock || is_int( $stock ) ) || ! is_array( $specs ) ) {
				return null;
			}
			$texts = array();
			foreach ( $specs as $key => $text ) {
				if ( ! is_string( $key ) || ! is_string( $text ) ) {
					return null;
				}
				$texts[ $key ] = $text;
			}
			try {
				$parts[] = new Part( $id, $brand, Specs::fromIndex( $texts ), $price, $in, $stock );
			} catch ( InvalidPart ) {
				return null;
			}
		}
		return $parts;
	}
}
