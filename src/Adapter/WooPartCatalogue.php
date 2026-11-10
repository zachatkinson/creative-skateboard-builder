<?php
/**
 * The shop's parts, from WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parts through WooCommerce's CRUD: every product type but the variable one, and variations.
 *
 * @since 0.3.0
 */
final class WooPartCatalogue implements PartCatalogue {

	/**
	 * One page of every part, by ID.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page The page, from 1.
	 * @param int $size How many a page holds.
	 * @return list<int>
	 */
	public function page( int $page, int $size ): array {
		$types = array_diff( array_keys( wc_get_product_types() ), array( 'variable' ) );
		$found = wc_get_products(
			array(
				'type'    => array_merge( array_values( $types ), array( 'variation' ) ),
				'status'  => array_keys( get_post_stati() ),
				'limit'   => $size,
				'page'    => $page,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);
		return array_values( Cast::ints( $found ) );
	}

	/**
	 * The parts a product stands for.
	 *
	 * @since 0.3.0
	 *
	 * @param int $id A product's or a variation's ID.
	 * @return list<int>
	 */
	public function partsOf( int $id ): array {
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product ) {
			return array();
		}
		return $product->is_type( 'variable' ) ? array_values( Cast::ints( $product->get_children() ) ) : array( $id );
	}

	/**
	 * Whether posts of this type are products or variations.
	 *
	 * @since 0.3.0
	 *
	 * @param string $postType A post type.
	 */
	public function holds( string $postType ): bool {
		return 'product' === $postType || 'product_variation' === $postType;
	}

	/**
	 * Whether a mapping line's attribute is this taxonomy: by the name's slug alone, as the attribute source reads
	 * one (D-089). A product's own attribute has no taxonomy, and no term to change.
	 *
	 * @since 0.3.0
	 *
	 * @param string $attribute The name a mapping line holds.
	 * @param string $taxonomy  A taxonomy.
	 */
	public function reads( string $attribute, string $taxonomy ): bool {
		return sanitize_title( $attribute ) === $taxonomy;
	}
}
