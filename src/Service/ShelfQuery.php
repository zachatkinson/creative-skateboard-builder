<?php
/**
 * What a request asks of a step's shelf.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shelf's arguments as the route or the build link read them, before they are checked against the step.
 *
 * @since 1.0.0
 */
final readonly class ShelfQuery {

	/**
	 * The longest query (SPEC-CONFIGURATOR, Search).
	 *
	 * @since 1.0.0
	 */
	public const int QUERY = 100;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string                      $width  The deck width asked, as a size is named (`8.25in`), or empty.
	 * @param array<string, list<string>> $chips  The chips chosen, by group.
	 * @param string                      $query  The search, as typed, or empty.
	 * @param string                      $sort   The order asked.
	 * @param int                         $from   How many looks in stock come before the page.
	 * @param int                         $shown  How many looks the page shows.
	 * @param bool                        $ranked Whether a search engine may rank the query: the kit's Search setting.
	 */
	public function __construct(
		public string $width = '',
		public array $chips = array(),
		public string $query = '',
		public string $sort = 'recommended',
		public int $from = 0,
		public int $shown = Shelf::SHOWN,
		public bool $ranked = true
	) {}
}
