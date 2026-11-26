<?php
/**
 * What the shop says of one part, for the prices one visitor is shown.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A row of the parts table (D-116): everything a build's state needs of a product or a variation beside its specs,
 * read from WooCommerce once and kept until the product moves.
 *
 * @since 0.4.0
 */
final readonly class PartFact {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 * @since 1.3.0 The rank.
	 *
	 * @param int                   $id         The product's or the variation's ID.
	 * @param int                   $of         A variation's parent, 0 for a product.
	 * @param bool                  $sellable   Whether a shopper could be shown it and could buy it, and no plugin
	 *                                          rules it out. False for a variable product, which stands for its variations.
	 * @param string                $brand      Its brand's slug, or empty.
	 * @param int                   $price      The price of one as the shop shows it, in minor units.
	 * @param bool                  $inStock    Whether it can be bought now.
	 * @param int|null              $stock      How many the shop holds, where it counts them and sells no more.
	 * @param array<string, string> $attributes A variation's own attribute values, by taxonomy.
	 * @param array<string, string> $specs      Its specs as the index held them when it was read, by key.
	 * @param int                   $readAt     When it was read from the shop, a UTC timestamp.
	 * @param int|null              $rank       Its rank in its slot from its Part specs, its product's for a variation
	 *                                          with none, or null; before `cbuild_part_rank` answers (#135, D-434).
	 */
	public function __construct(
		public int $id,
		public int $of,
		public bool $sellable,
		public string $brand,
		public int $price,
		public bool $inStock,
		public ?int $stock,
		public array $attributes,
		public array $specs,
		public int $readAt,
		public ?int $rank = null
	) {}
}
