<?php
/**
 * Groups a step's parts into looks.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

/**
 * Groups parts into looks by product, photo and layer image, so no two cards show the same photo. A product's looks
 * sit side by side, products in the order first given (SPEC-CONFIGURATOR, One card per look; D-240).
 */
final class Looks {

	/**
	 * The looks the parts make.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Variant> $variants The step's parts with their looks' facts.
	 * @return list<Look>
	 */
	public static function of( array $variants ): array {
		$grouped = array();
		foreach ( $variants as $variant ) {
			$key                                    = $variant->product . ':' . $variant->photo . ':' . $variant->layer;
			$grouped[ $variant->product ][ $key ][] = $variant;
		}
		$looks = array();
		foreach ( $grouped as $product => $keys ) {
			foreach ( $keys as $key => $list ) {
				$looks[] = new Look( $key, $product, $list );
			}
		}
		return $looks;
	}

	/**
	 * Each look's name, by its key: the attributes every part of it shares that tell it from its product's other
	 * looks, as slug to value slug, in the order its first part gives them. A product with one look needs no name, and
	 * a value its parts differ in is chosen after the pick, not a name (D-273).
	 *
	 * @since 1.0.0
	 *
	 * @param list<Look> $looks The looks, as `of()` makes them.
	 * @return array<string, array<string, string>>
	 */
	public static function names( array $looks ): array {
		$shared = array();
		$kin    = array();
		foreach ( $looks as $look ) {
			$shared[ $look->key ]    = self::shared( $look );
			$kin[ $look->product ][] = $look->key;
		}
		$names = array();
		foreach ( $looks as $look ) {
			$own = array();
			foreach ( $shared[ $look->key ] as $name => $value ) {
				foreach ( $kin[ $look->product ] as $other ) {
					if ( $other !== $look->key && ( $shared[ $other ][ $name ] ?? null ) !== $value ) {
						$own[ $name ] = $value;
						break;
					}
				}
			}
			$names[ $look->key ] = $own;
		}
		return $names;
	}

	/**
	 * The attributes every part of a look has with the same value.
	 *
	 * @param Look $look The look.
	 * @return array<string, string>
	 */
	private static function shared( Look $look ): array {
		$shared = $look->variants[0]->choices;
		foreach ( $look->variants as $variant ) {
			foreach ( $shared as $name => $value ) {
				if ( ( $variant->choices[ $name ] ?? null ) !== $value ) {
					unset( $shared[ $name ] );
				}
			}
		}
		return $shared;
	}
}
