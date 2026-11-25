<?php
/**
 * The shop's ranks of its parts, from their meta.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\PartRanks;
use CreativeBranding\SkateboardBuilder\Port\ProductMeta;
use CreativeBranding\SkateboardBuilder\Support\PartRank;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A part's rank is its `_cbuild_rank` meta, as its Part specs panel writes it, read through the product's CRUD; a
 * variation with none takes its product's, as its other Part specs do. Each is then the answer to `cbuild_part_rank`.
 * The parts' meta and their products' are read in one query (D-202), and the products in one more.
 *
 * @since 1.3.0
 */
final class WooPartRanks implements PartRanks {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param Products    $products Loads the products.
	 * @param ProductMeta $meta     Reads their meta in one go.
	 */
	public function __construct( private Products $products, private ProductMeta $meta ) {}

	/**
	 * The rank of each of these parts that has one, by ID.
	 *
	 * @since 1.3.0
	 *
	 * @param int ...$parts Products' and variations' IDs.
	 * @return array<int, int>
	 */
	public function ranks( int ...$parts ): array {
		$parts = array_values( array_unique( array_filter( $parts, static fn( int $id ): bool => $id > 0 ) ) );
		if ( array() === $parts ) {
			return array();
		}
		$this->meta->primeWithParents( ...$parts );
		$loaded  = $this->products->load( ...$parts );
		$parents = $this->products->parentsOf( $loaded );
		$ranks   = array();
		foreach ( $loaded as $id => $product ) {
			$rank = PartRank::own( $product );
			if ( null === $rank && $product->is_type( 'variation' ) ) {
				$parent = $parents[ $product->get_parent_id() ] ?? null;
				$rank   = null === $parent ? null : PartRank::own( $parent );
			}
			/**
			 * Filters a part's rank in its slot, for smart swaps' "better" (#135): a whole number, higher being better,
			 * or null for none. A slot where no part has one ranks its parts by price.
			 *
			 * @since 1.3.0
			 *
			 * @param int|null $rank The part's rank from its Part specs, its product's for a variation with none, or
			 *                       null.
			 * @param int      $id   The product's or variation's ID.
			 */
			$rank = apply_filters( 'cbuild_part_rank', $rank, $id );
			if ( is_int( $rank ) ) {
				$ranks[ $id ] = $rank;
			}
		}
		return $ranks;
	}
}
