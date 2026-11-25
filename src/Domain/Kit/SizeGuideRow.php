<?php
/**
 * One row of a kit's width guide.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

/**
 * A shoe size as the shop writes it, and the deck widths it rides: from one width to another, or from one width and
 * wider (#114).
 *
 * @since 1.2.0
 */
final readonly class SizeGuideRow {

	/**
	 * Constructor.
	 *
	 * @param string       $shoe The shoe size, the shop's own words.
	 * @param Measure      $from The narrowest deck it rides, in inches.
	 * @param Measure|null $to   The widest, at least the narrowest, or null for "and wider".
	 */
	public function __construct( public string $shoe, public Measure $from, public ?Measure $to ) {}
}
