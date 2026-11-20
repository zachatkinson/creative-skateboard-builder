<?php
/**
 * A step's filter chips.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use function array_keys;
use function array_intersect;
use function array_unique;
use function count;
use function in_array;
use function strnatcasecmp;
use function uksort;
use function usort;

/**
 * The chip groups a step offers, from its own looks, and the looks the chips chosen leave (SPEC-CONFIGURATOR,
 * Filters from the step's own data; D-247):
 *
 * - the facets always offered (brand, and the size where the shopper picks it), in the order given;
 * - then each other facet at least 80 % of the looks carry, the most carried first, then by name;
 * - each with two values or more, up to four groups.
 *
 * Chips in one group widen; groups narrow each other. Each chip counts the looks it leaves with the other groups'
 * choices applied and its own group's not.
 */
final class Chips {

	/**
	 * The most groups a step offers.
	 */
	public const int MOST = 4;

	/**
	 * The share of a step's looks, in percent, a facet must be carried by to be offered when it isn't always.
	 */
	public const int SHARE = 80;

	/**
	 * The step's chip groups.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Facets>                $looks  The step's offered looks, before any chip.
	 * @param list<string>                $always The facets offered whenever they have two values, in order.
	 * @param array<string, list<string>> $chosen The values chosen, by facet.
	 * @return list<ChipGroup>
	 */
	public static function groups( array $looks, array $always, array $chosen ): array {
		$groups = array();
		foreach ( self::offered( $looks, $always ) as $name ) {
			$values = self::values( $looks, $name );
			if ( count( $values ) < 2 ) {
				continue;
			}
			$others = $chosen;
			unset( $others[ $name ] );
			$left  = self::filter( $looks, $others );
			$chips = array();
			foreach ( $values as $value ) {
				$count = 0;
				foreach ( $left as $look ) {
					if ( in_array( $value, $look->facets[ $name ] ?? array(), true ) ) {
						++$count;
					}
				}
				$chips[] = new Chip( $value, $count, in_array( $value, $chosen[ $name ] ?? array(), true ) );
			}
			$groups[] = new ChipGroup( $name, $chips );
			if ( count( $groups ) === self::MOST ) {
				break;
			}
		}
		return $groups;
	}

	/**
	 * The looks the chips chosen leave: those with one of the values chosen in every facet with a choice.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Facets>                $looks  The looks.
	 * @param array<string, list<string>> $chosen The values chosen, by facet.
	 * @return list<Facets>
	 */
	public static function filter( array $looks, array $chosen ): array {
		$left = array();
		foreach ( $looks as $look ) {
			foreach ( $chosen as $name => $values ) {
				if ( array() !== $values && array() === array_intersect( $values, $look->facets[ $name ] ?? array() ) ) {
					continue 2;
				}
			}
			$left[] = $look;
		}
		return $left;
	}

	/**
	 * The facets that may be offered, in order: those always offered, then the rest carried by enough looks.
	 *
	 * @param list<Facets> $looks  The looks.
	 * @param list<string> $always The facets always offered.
	 * @return list<string>
	 */
	private static function offered( array $looks, array $always ): array {
		$carried = array();
		foreach ( $looks as $look ) {
			foreach ( array_keys( $look->facets ) as $name ) {
				if ( ! in_array( $name, $always, true ) && array() !== $look->facets[ $name ] ) {
					$carried[ $name ] = ( $carried[ $name ] ?? 0 ) + 1;
				}
			}
		}
		$enough = array();
		foreach ( $carried as $name => $count ) {
			if ( 100 * $count >= self::SHARE * count( $looks ) ) {
				$enough[ $name ] = $count;
			}
		}
		uksort( $enough, static fn( string $a, string $b ): int => array( $enough[ $b ], $a ) <=> array( $enough[ $a ], $b ) );
		return array( ...$always, ...array_keys( $enough ) );
	}

	/**
	 * A facet's values across the looks, each once, in natural order.
	 *
	 * @param list<Facets> $looks The looks.
	 * @param string       $name  The facet.
	 * @return list<string>
	 */
	private static function values( array $looks, string $name ): array {
		$values = array();
		foreach ( $looks as $look ) {
			foreach ( $look->facets[ $name ] ?? array() as $value ) {
				$values[] = $value;
			}
		}
		$values = array_unique( $values );
		usort( $values, strnatcasecmp( ... ) );
		return $values;
	}
}
