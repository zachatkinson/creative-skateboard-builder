<?php
/**
 * Typing values that arrive as mixed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hook arguments, stored options and filter results arrive untyped. These turn them into the type the code needs,
 * with a safe value for anything else, so a plugin passing the wrong thing to a hook cannot break a request.
 */
final class Cast {

	/**
	 * An integer; anything not numeric is the fallback.
	 *
	 * @param mixed $value    Value.
	 * @param int   $fallback Fallback.
	 */
	public static function int( mixed $value, int $fallback = 0 ): int {
		return is_numeric( $value ) ? (int) $value : $fallback;
	}

	/**
	 * A string; arrays, objects and null are the fallback.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 */
	public static function str( mixed $value, string $fallback = '' ): string {
		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * A float from a number or numeric string, else the fallback.
	 *
	 * @param mixed $value    Value.
	 * @param float $fallback When the value is not numeric.
	 */
	public static function float( mixed $value, float $fallback = 0.0 ): float {
		return is_numeric( $value ) ? (float) $value : $fallback;
	}

	/**
	 * A boolean: true, 1, '1', 'yes', 'true' and 'on' are true; everything else false.
	 *
	 * @param mixed $value Value.
	 */
	public static function bool( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}
		return is_string( $value ) && in_array( strtolower( trim( $value ) ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * A list of integers from an array (non-numeric members dropped); anything else is empty.
	 *
	 * @param mixed $value Value.
	 * @return int[]
	 */
	public static function ints( mixed $value ): array {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			if ( is_numeric( $item ) ) {
				$out[] = (int) $item;
			}
		}
		return $out;
	}

	/**
	 * A list of strings from an array (non-scalar members dropped); anything else is empty.
	 *
	 * @param mixed $value Value.
	 * @return string[]
	 */
	public static function strings( mixed $value ): array {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			if ( is_scalar( $item ) ) {
				$out[] = (string) $item;
			}
		}
		return $out;
	}

	/**
	 * An array keyed by strings (other keys dropped); anything else is empty.
	 *
	 * @param mixed $value Value.
	 * @return array<string, mixed>
	 */
	public static function map( mixed $value ): array {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $key => $item ) {
			if ( is_string( $key ) ) {
				$out[ $key ] = $item;
			}
		}
		return $out;
	}
}
