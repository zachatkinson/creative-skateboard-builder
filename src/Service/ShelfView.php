<?php
/**
 * A step's shelf and the names its chips need.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Look\Listing;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The listing, with what the edge needs to name its values: the shop's names for the brands and the attributes'
 * values, and each size as a measure, for the edge to say in the shop's units.
 *
 * @since 1.0.0
 */
final readonly class ShelfView {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Listing                              $listing The listing.
	 * @param array<string, array<string, string>> $labels  Names by group and value: `brand`, and each `pa_…` attribute.
	 * @param array<string, Measure>               $sizes   Each size as a measure, by its name (`54mm`).
	 * @param string                               $query   The search as it was read: trimmed, 100 characters at most.
	 * @param string                               $sort    The order as it was read: one of `ShelfAsk::SORTS`.
	 * @param string                               $width   The deck width asked, as a size is named, or empty.
	 * @param array<string, string>                $groups  The shop's names of the attributes the values are of, by taxonomy.
	 * @param array<int, string>                   $names   Each product's name, in plain text, by its ID (D-273).
	 * @param int                                  $hidden  How many of the step's hidden parts match the search: ones that
	 *                                                      don't fit the build (spec: Search, D-277).
	 */
	public function __construct(
		public Listing $listing,
		public array $labels,
		public array $sizes,
		public string $query,
		public string $sort,
		public string $width,
		public array $groups = array(),
		public array $names = array(),
		public int $hidden = 0
	) {}
}
