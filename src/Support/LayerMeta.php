<?php
/**
 * A part's Layer image, as every reader of it takes it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Support;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one rule the picture, the cards, the looks and the Layer image field read a layer image by: a part's own, else
 * a variation's parent's (D-282; the auditor's 1.0.0 minor 8). Each reader finds the parent its own way, one at a
 * time or many at once, and passes it in.
 */
final class LayerMeta {

	/**
	 * A product's or variation's own layer image, or 0.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Product $product The part.
	 */
	public static function own( WC_Product $product ): int {
		return max( 0, Cast::int( $product->get_meta( Options::META_LAYER, true ) ) );
	}

	/**
	 * The layer image a part is drawn with: its own, else its parent's, or 0.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Product      $product The part.
	 * @param WC_Product|null $owner   A variation's parent, or null for a product or a parent not read.
	 */
	public static function inUse( WC_Product $product, ?WC_Product $owner ): int {
		$own = self::own( $product );
		return $own > 0 || null === $owner ? $own : self::own( $owner );
	}
}
