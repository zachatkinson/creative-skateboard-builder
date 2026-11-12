<?php
/**
 * A build ordered again.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

/**
 * What WooCommerce's Order again needs of a build on an order (D-137): what the order's snapshot asks for, to be
 * judged again as the shop is now, and whether the lines WooCommerce put back make a whole build. WooCommerce leaves
 * a line out by itself, for a product that is gone or sold out, and a build with a line missing is no build.
 *
 * @since 0.5.0
 */
final class BuildAgain {

	/**
	 * What an order's snapshot of a build asks for: each slot's part, and whether it was assembled. Null when what
	 * is stored is no snapshot.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $snapshot The order item's snapshot.
	 * @return array{picks: array<int|string, int>, assembly: bool}|null
	 */
	public static function asked( mixed $snapshot ): ?array {
		$stored = is_array( $snapshot ) ? ( $snapshot['picks'] ?? null ) : null;
		if ( ! is_array( $snapshot ) || ! is_array( $stored ) || array() === $stored ) {
			return null;
		}
		$picks = array();
		foreach ( $stored as $slot => $pick ) {
			$part = is_array( $pick ) ? ( $pick['part'] ?? null ) : null;
			if ( ! is_int( $part ) || $part < 1 ) {
				return null;
			}
			$picks[ $slot ] = $part;
		}
		return array(
			'picks'    => $picks,
			'assembly' => true === ( $snapshot['assembly'] ?? null ),
		);
	}

	/**
	 * Which of the lines put back make whole builds. A kit's line is whole with one line for each of its build's
	 * slots and no other; a part's line belongs with the kit's line it names.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, array{build: Build|null, parent: string, slot: string}> $lines By cart key: a kit's line
	 *                                      has its build; a part's has its kit's line's key and its slot.
	 * @return array{children: array<string, list<string>>, dropped: list<string>} Each whole kit's line's parts,
	 *                                      and the keys of every line that is of no whole build.
	 */
	public static function whole( array $lines ): array {
		$parts = array();
		foreach ( $lines as $key => $line ) {
			if ( null === $line['build'] ) {
				$parts[ $line['parent'] ][ $line['slot'] ][] = $key;
			}
		}
		$children = array();
		$dropped  = array();
		foreach ( $lines as $key => $line ) {
			$build = $line['build'];
			if ( null === $build ) {
				continue;
			}
			$found = $parts[ $key ] ?? array();
			unset( $parts[ $key ] );
			$keys  = array_merge( ...array_values( $found ) );
			$slots = array_keys( $found );
			sort( $slots );
			$asked = array_keys( $build->picks );
			sort( $asked );
			if ( $slots === $asked && count( $keys ) === count( $asked ) ) {
				$children[ $key ] = $keys;
			} else {
				$dropped = array( ...$dropped, $key, ...$keys );
			}
		}
		// What is left names a kit's line that is not there.
		foreach ( $parts as $orphans ) {
			$dropped = array( ...$dropped, ...array_merge( ...array_values( $orphans ) ) );
		}
		return array(
			'children' => $children,
			'dropped'  => $dropped,
		);
	}
}
