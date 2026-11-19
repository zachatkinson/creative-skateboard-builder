<?php
/**
 * One group of filter chips.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

/**
 * A facet's chips: brand, a size, or a global attribute (D-247).
 */
final readonly class ChipGroup {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $name  The facet.
	 * @param list<Chip> $chips Its chips, in natural order.
	 */
	public function __construct( public string $name, public array $chips ) {}
}
