<?php
/**
 * Creative Search's ranking.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Ranking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asks Creative Search's `csearch_ranked_ids` filter (its docs/HOOKS.md, since 1.7.0) for the query's ranking
 * among the step's products, in the visitor's language, logged under its `builder` source. With Creative Search
 * inactive, or with no index in that language yet, the filter answers null and the builder searches on its own. An
 * answer that is not what the filter documents counts as none.
 *
 * @since 1.0.0
 */
final class CreativeSearchRanking implements Ranking {

	/**
	 * The most products the filter ranks among.
	 */
	public const int MOST = 2000;

	/**
	 * The ranking.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $query    The query.
	 * @param list<int> $products The step's products and variations.
	 * @return array{ids: list<int>, corrected: string|null}|null
	 */
	public function ranked( string $query, array $products ): ?array {
		if ( array() === $products ) {
			return null;
		}
		$answer = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Creative Search's own filter.
			'csearch_ranked_ids',
			null,
			$query,
			array(
				'lang'        => determine_locale(),
				'limit'       => 500,
				'source'      => 'builder',
				'product_ids' => array_slice( $products, 0, self::MOST ),
			)
		);
		if ( ! is_array( $answer ) || ! is_array( $answer['ids'] ?? null ) ) {
			return null;
		}
		$ids = array();
		foreach ( $answer['ids'] as $id ) {
			// From 1.7.1 an id may come as a string of digits.
			if ( ( is_int( $id ) && $id > 0 ) || ( is_string( $id ) && 1 === preg_match( '/\A[1-9][0-9]{0,18}\z/', $id ) ) ) {
				$ids[] = (int) $id;
			}
		}
		$corrected = $answer['corrected'] ?? null;
		return array(
			'ids'       => $ids,
			'corrected' => is_string( $corrected ) && '' !== $corrected ? $corrected : null,
		);
	}
}
