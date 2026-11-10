<?php
/**
 * The shop's products, as parts.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A part is something a shopper can pick: a product that is sold as it is, or a variation. A variable product is not
 * one; its variations are (spec: Vocabulary, Part).
 *
 * @since 0.3.0
 */
interface PartCatalogue {

	/**
	 * One page of every part in the shop, by ID, whatever its status.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page The page, from 1.
	 * @param int $size How many a page holds.
	 * @return list<int> Part IDs. Fewer than a page's worth means it is the last.
	 */
	public function page( int $page, int $size ): array;

	/**
	 * The parts a product stands for: itself when it is sold as it is or is a variation, its variations when it is
	 * variable, and none when it is not a product.
	 *
	 * @since 0.3.0
	 *
	 * @param int $id A product's or a variation's ID.
	 * @return list<int> Part IDs.
	 */
	public function partsOf( int $id ): array;

	/**
	 * Whether posts of this type are products or variations.
	 *
	 * @since 0.3.0
	 *
	 * @param string $postType A post type.
	 */
	public function holds( string $postType ): bool;

	/**
	 * Whether a mapping line's attribute is this taxonomy. A line names a taxonomy in full (`pa_width`); a name
	 * without `pa_` is a product's own attribute, which has no taxonomy (D-089).
	 *
	 * @since 0.3.0
	 *
	 * @param string $attribute The name a mapping line holds.
	 * @param string $taxonomy  A taxonomy.
	 */
	public function reads( string $attribute, string $taxonomy ): bool;
}
