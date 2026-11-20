<?php
/**
 * What a shopper asks of a step's shelf.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

/**
 * The width asked, the chips chosen, the query, the order and the place in the list, each already checked against
 * what the route allows.
 */
final readonly class ShelfAsk {

	/**
	 * The orders a shopper can choose (D-243).
	 */
	public const array SORTS = array( 'recommended', 'price-asc', 'price-desc', 'newest' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Measure|null                $width     The deck width asked, if any.
	 * @param array<string, list<string>> $chosen    The chips chosen, by group.
	 * @param string                      $query     The folded query, or empty.
	 * @param list<int>|null              $ranked    Creative Search's parents for the query, best first; null without it.
	 * @param string|null                 $corrected Creative Search's folded corrected query, if any.
	 * @param string                      $sort      One of SORTS; anything else is the default.
	 * @param int                         $from      How many looks in stock come before the page.
	 * @param int                         $shown     How many to show from there.
	 */
	public function __construct(
		public ?Measure $width = null,
		public array $chosen = array(),
		public string $query = '',
		public ?array $ranked = null,
		public ?string $corrected = null,
		public string $sort = 'recommended',
		public int $from = 0,
		public int $shown = Shelf::SHOWN
	) {}
}
