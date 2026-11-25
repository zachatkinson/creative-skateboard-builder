<?php
/**
 * A part's own rank.
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
 * A part's own rank in its slot, its `_cbuild_rank` meta, as its Part specs panel writes it and the ranks read it
 * (#135, D-395).
 *
 * @since 1.3.0
 */
final class PartRank {

	/**
	 * The most a rank can be: nine digits.
	 *
	 * @since 1.3.0
	 */
	public const string PATTERN = '/\A(?:0|[1-9][0-9]{0,8})\z/';

	/**
	 * A product's or a variation's own rank, not its parent's, or null where it has none.
	 *
	 * @since 1.3.0
	 *
	 * @param WC_Product $product The product or variation.
	 */
	public static function own( WC_Product $product ): ?int {
		$rank = $product->get_meta( Options::META_RANK, true );
		return is_string( $rank ) && 1 === preg_match( self::PATTERN, $rank ) ? (int) $rank : null;
	}
}
