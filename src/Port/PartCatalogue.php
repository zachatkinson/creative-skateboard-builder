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
	 * Whether a product's attribute, asked for by this name, is read from this taxonomy.
	 *
	 * @since 0.3.0
	 *
	 * @param string $attribute The name a mapping line holds: `pa_width`, `width` or `Width`.
	 * @param string $taxonomy  A taxonomy.
	 */
	public function reads( string $attribute, string $taxonomy ): bool;
}
