<?php
/**
 * A part the shop keeps out of the builder.
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
 * The one rule every reader of "Not offered in the builder" takes it by (#116, D-351): a part is kept out where its own
 * box is ticked, or, for a variation, its product's. It reads what WooCommerce has already loaded of the product, so it
 * asks no query of its own where the caller primed the products' meta (`ProductMeta`), as WooKits and the check do.
 */
final class NotOffered {

	/**
	 * Whether a product's or a variation's own box is ticked.
	 *
	 * @since 1.2.0
	 *
	 * @param WC_Product $product The product or the variation.
	 */
	public static function own( WC_Product $product ): bool {
		return 'yes' === $product->get_meta( Options::META_NOT_OFFERED, true );
	}

	/**
	 * Whether the shop keeps the part out: its own box, or its product's.
	 *
	 * @since 1.2.0
	 *
	 * @param WC_Product      $part  The part.
	 * @param WC_Product|null $owner A variation's product, or null for a product or a parent not read.
	 */
	public static function keptOut( WC_Product $part, ?WC_Product $owner ): bool {
		return self::own( $part ) || ( null !== $owner && $owner !== $part && self::own( $owner ) );
	}
}
