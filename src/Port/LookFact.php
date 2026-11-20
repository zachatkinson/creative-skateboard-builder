<?php
/**
 * What a part's look and order are read from.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A part's product, photo and layer image, which make its look (D-240); its attributes, for the chips and search
 * (D-247, D-248); and what the default order reads (D-243).
 *
 * @since 1.0.0
 */
final readonly class LookFact {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int                                  $product    The product: a variation's parent, or the part itself.
	 * @param int                                  $photo      Its photo, or its parent's where it has none; 0 for none.
	 * @param int                                  $layer      Its layer image, or its parent's where it has none; 0 for none.
	 * @param string                               $name       The product's name, in plain text.
	 * @param array<string, list<string>>          $attributes Its global attributes' values (`pa_…`) by taxonomy, as slugs:
	 *                                                         a variation's own, then those its parent holds and it does not vary.
	 * @param array<string, array<string, string>> $labels     Each value's name, by taxonomy and slug, in plain text.
	 * @param bool                                 $featured   Whether the shop stars the product.
	 * @param int                                  $sales      The product's sales count.
	 * @param int                                  $created    When the product was made, a UTC timestamp.
	 */
	public function __construct(
		public int $product,
		public int $photo,
		public int $layer,
		public string $name,
		public array $attributes,
		public array $labels,
		public bool $featured,
		public int $sales,
		public int $created
	) {}
}
