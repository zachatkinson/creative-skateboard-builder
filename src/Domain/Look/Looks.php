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
}
