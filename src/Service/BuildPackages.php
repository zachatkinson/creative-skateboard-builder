<?php
/**
 * A build's parts in one shipping package.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puts a build's parts back in one shipping package where a shop's own code has split the cart into several
 * (spec: Pricing, stock, assembly and returns, Shipping). It moves lines between the packages it is given and
 * makes none: a cart in one package comes back as it went in.
 *
 * A package is WooCommerce's: `contents`, cart key to line, and `contents_cost`, the sum of its lines'
 * `line_total`. Everything else in one is left as it is.
 *
 * @since 0.5.0
 */
final class BuildPackages {

	/**
	 * Move each build's parts into the package that holds the first of them, in step order.
	 *
	 * @since 0.5.0
	 *
	 * @param array<array-key, mixed> $packages The packages.
	 * @param list<list<string>>      $builds   For each build to keep together, its parts' cart keys in step order.
	 * @return array<array-key, mixed> The packages, with none left empty by a move.
	 */
	public static function together( array $packages, array $builds ): array {
		$contents = array();
		foreach ( $packages as $at => $package ) {
			if ( is_array( $package ) && is_array( $package['contents'] ?? null ) ) {
				$contents[ $at ] = $package['contents'];
			}
		}
		$touched = array();
		foreach ( $builds as $keys ) {
			$home = null;
			foreach ( $keys as $key ) {
				$at = self::holder( $contents, $key );
				if ( null === $at ) {
					continue;
				}
				if ( null === $home ) {
					$home = $at;
					continue;
				}
				if ( $at !== $home ) {
					$contents[ $home ][ $key ] = $contents[ $at ][ $key ];
					unset( $contents[ $at ][ $key ] );
					$touched[] = $home;
					$touched[] = $at;
				}
			}
		}
		if ( array() === $touched ) {
			return $packages;
		}
		$kept = array();
		foreach ( $packages as $at => $package ) {
			if ( ! in_array( $at, $touched, true ) || ! is_array( $package ) ) {
				$kept[] = $package;
				continue;
			}
			if ( array() === $contents[ $at ] ) {
				continue;
			}
			$package['contents']      = $contents[ $at ];
			$package['contents_cost'] = self::cost( $contents[ $at ] );
			$kept[]                   = $package;
		}
		return $kept;
	}

	/**
	 * Which package holds a line, or null where none does.
	 *
	 * @param array<array-key, array<array-key, mixed>> $contents Each package's lines.
	 * @param string                                    $key      The line's cart key.
	 */
	private static function holder( array $contents, string $key ): int|string|null {
		foreach ( $contents as $at => $lines ) {
			if ( array_key_exists( $key, $lines ) ) {
				return $at;
			}
		}
		return null;
	}

	/**
	 * What a package's lines come to, as WooCommerce adds them: each line's `line_total`.
	 *
	 * @param array<array-key, mixed> $contents The lines.
	 */
	private static function cost( array $contents ): float {
		$cost = 0.0;
		foreach ( $contents as $line ) {
			$total = is_array( $line ) ? ( $line['line_total'] ?? 0 ) : 0;
			$cost += is_numeric( $total ) ? floatval( $total ) : 0.0;
		}
		return $cost;
	}
}
