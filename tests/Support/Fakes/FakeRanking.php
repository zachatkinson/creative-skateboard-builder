<?php
/**
 * A search engine's ranking, faked.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Ranking;

/**
 * Answers what the test sets, and records what it was asked.
 */
final class FakeRanking implements Ranking {

	/**
	 * The answer, or null for no search engine.
	 *
	 * @var array{ids: list<int>, corrected: string|null}|null
	 */
	public ?array $answer = null;

	/**
	 * Each query and its products, as asked.
	 *
	 * @var list<array{string, list<int>}>
	 */
	public array $asked = array();

	/**
	 * The ranking.
	 *
	 * @param string    $query    The query.
	 * @param list<int> $products The products.
	 * @return array{ids: list<int>, corrected: string|null}|null
	 */
	public function ranked( string $query, array $products ): ?array {
		$this->asked[] = array( $query, $products );
		return $this->answer;
	}
}
