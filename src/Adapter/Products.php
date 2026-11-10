<?php
/**
 * Products for a batch of parts.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the products and variations a batch names, and the parents of its variations, through WooCommerce's CRUD:
 * one query for the batch and one for the parents, so a source reading 100 parts does not ask for each in turn.
 *
 * @since 0.3.0
 */
final class Products {

	/**
	 * The products with these IDs, by ID. An ID that is not a product, or no longer one, is not in the answer.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 * @return array<int, WC_Product>
	 */
	public function load( int ...$ids ): array {
		$ids = array_values( array_unique( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) ) );
		if ( array() === $ids ) {
			return array();
		}
		$found    = wc_get_products(
			array(
				'include' => $ids,
				'type'    => array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) ),
				'status'  => array_keys( get_post_stati() ),
				'limit'   => count( $ids ),
				'orderby' => 'none',
			)
		);
		$products = array();
		foreach ( is_array( $found ) ? $found : array() as $product ) {
			if ( $product instanceof WC_Product ) {
				$products[ $product->get_id() ] = $product;
			}
		}
		return $products;
	}

	/**
	 * The parents of the variations among these products, by the parent's ID.
	 *
	 * @since 0.3.0
	 *
	 * @param array<int, WC_Product> $products From load().
	 * @return array<int, WC_Product>
	 */
	public function parentsOf( array $products ): array {
		$parents = array();
		foreach ( $products as $product ) {
			if ( $product->is_type( 'variation' ) ) {
				$parents[] = $product->get_parent_id();
			}
		}
		return $this->load( ...$parents );
	}
}
