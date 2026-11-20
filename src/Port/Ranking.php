<?php
/**
 * A search engine's ranking of a step's products.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Another plugin's ranking of a query among the step's own products, where one answers: Creative Search's
 * `csearch_ranked_ids` (D-248).
 *
 * @since 1.0.0
 */
interface Ranking {

	/**
	 * The products ranked for the query, best first, and the corrected query; null where no search engine answers,
	 * and the builder searches on its own.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $query    The query as the shopper typed it.
	 * @param list<int> $products The step's products and variations, 2,000 at most.
	 * @return array{ids: list<int>, corrected: string|null}|null The ids are parents: a variation counts as its product.
	 */
	public function ranked( string $query, array $products ): ?array;
}
