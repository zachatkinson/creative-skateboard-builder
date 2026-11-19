<?php
/**
 * A step's shelf, as asked.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

/**
 * What a step lists: a page of the looks in stock, the sold-out fold, the chips, and the counts the tools and the
 * "Showing 30 of 90" line read.
 */
final readonly class Listing {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param list<ShelfLook> $looks    The page of looks in stock.
	 * @param list<ShelfLook> $soldOut  The looks sold out that match, for the fold.
	 * @param list<ChipGroup> $groups   The chip groups.
	 * @param list<Width>     $widths   On the deck step, the widths the question offers; elsewhere none.
	 * @param int             $offered  How many looks the step offers before any search or chip, for its tools.
	 * @param int             $matching How many looks in stock the search and chips leave.
	 * @param int|null        $more     Where the next page starts, or null when none is left.
	 */
	public function __construct(
		public array $looks,
		public array $soldOut,
		public array $groups,
		public array $widths,
		public int $offered,
		public int $matching,
		public ?int $more
	) {}
}
