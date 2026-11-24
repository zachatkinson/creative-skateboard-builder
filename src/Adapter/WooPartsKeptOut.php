<?php
/**
 * The parts the shop keeps out of the builder, from WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\PartsKeptOut;
use CreativeBranding\SkateboardBuilder\Port\ProductMeta;
use CreativeBranding\SkateboardBuilder\Support\NotOffered;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads each part's box through WooCommerce's CRUD: the parts' posts, meta and terms read together, and their products'
 * together, however many parts there are (D-202, D-308, D-351).
 *
 * @since 1.2.0
 */
final class WooPartsKeptOut implements PartsKeptOut {

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param ProductMeta $meta Reads several products' meta in one query.
	 */
	public function __construct( private ProductMeta $meta ) {}

	/**
	 * Which of these parts the shop keeps out, and by whose box.
	 *
	 * @since 1.2.0
	 *
	 * @param int ...$parts Product or variation IDs.
	 * @return array<int, string>
	 */
	public function keptOut( int ...$parts ): array {
		$this->prime( $parts );
		$kept    = array();
		$parents = array();
		foreach ( $parts as $part ) {
			$product = wc_get_product( $part );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( NotOffered::own( $product ) ) {
				$kept[ $part ] = self::OWN;
			} elseif ( $product->is_type( 'variation' ) ) {
				$parents[ $part ] = $product->get_parent_id();
			}
		}
		$this->prime( array_values( $parents ) );
		foreach ( $parents as $part => $parent ) {
			$product = wc_get_product( $parent );
			if ( $product instanceof WC_Product && NotOffered::own( $product ) ) {
				$kept[ $part ] = self::PRODUCT;
			}
		}
		ksort( $kept );
		return $kept;
	}

	/**
	 * Read products' meta into WooCommerce's cache, and their posts, meta and terms into WordPress's, where they are
	 * not there yet: a few queries for them all, not two for each, as the layer images are read (D-308).
	 *
	 * @param array<int> $ids Product or variation IDs.
	 */
	private function prime( array $ids ): void {
		$this->meta->prime( ...$ids );
		$cold = array_values( array_filter( array_unique( $ids ), static fn( int $id ): bool => false === wp_cache_get( $id, 'post_meta' ) ) );
		if ( count( $cold ) > 1 ) {
			_prime_post_caches( $cold, true, true );
		}
	}
}
