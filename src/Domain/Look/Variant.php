<?php
/**
 * One part as a look sees it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;

/**
 * A part with what decides its look: the product it belongs to, its photo and its layer image, each already fallen
 * back to the product's where it has none of its own, and its other attributes, the size's left out (D-240).
 */
final readonly class Variant {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Part                  $part    The part: a simple product, or a variation.
	 * @param int                   $product The product it belongs to: its parent, or itself.
	 * @param int                   $photo   Its photo's attachment ID, 0 for none.
	 * @param int                   $layer   Its layer image's attachment ID, 0 for none.
	 * @param array<string, string> $choices Its attributes other than the size, as slug to value slug.
	 */
	public function __construct( public Part $part, public int $product, public int $photo, public int $layer, public array $choices ) {}
}
