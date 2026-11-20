<?php
/**
 * Search's folding and Creative Search's ranking.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Port\Ranking;
use CreativeBranding\SkateboardBuilder\Port\TextFolding;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * `Adapter\WpTextFolding` and `Adapter\CreativeSearchRanking` (D-248).
 */
final class SearchAdaptersTest extends IntegrationTestCase {

	public function test_text_is_folded_into_lower_case_without_its_accents(): void {
		$folding = $this->service( TextFolding::class );
		self::assertSame( 'blanc casse', $folding->fold( 'Blanc Cassé' ) );
		self::assertSame( 'eclair a creme', $folding->fold( 'ÉCLAIR À CRÈME' ) );
	}

	public function test_with_no_search_engine_the_ranking_is_none(): void {
		self::assertNull( $this->service( Ranking::class )->ranked( 'mint', array( 1, 2 ) ) );
	}

	public function test_creative_searchs_answer_is_read_as_its_filter_documents_it(): void {
		$asked = array();
		add_filter(
			'csearch_ranked_ids',
			static function ( mixed $answer, string $query, array $args ) use ( &$asked ): array {
				$asked = compact( 'answer', 'query', 'args' );
				return array(
					'ids'       => array( 12, '34', '-3', '1.5', 'abc', 0, -7, array() ),
					'corrected' => 'mint',
					'query'     => $query,
					'lang'      => 'en_US',
				);
			},
			10,
			3
		);
		$ranked = $this->service( Ranking::class )->ranked( 'mnit', range( 1, 2500 ) );
		self::assertSame(
			array(
				'ids'       => array( 12, 34 ),
				'corrected' => 'mint',
			),
			$ranked,
			'ids that are no positive whole number left out'
		);
		self::assertNull( $asked['answer'] );
		self::assertSame( 'mnit', $asked['query'] );
		self::assertSame( determine_locale(), $asked['args']['lang'] );
		self::assertSame( 500, $asked['args']['limit'] );
		self::assertSame( 'builder', $asked['args']['source'] );
		self::assertCount( 2000, $asked['args']['product_ids'], 'the most the filter ranks among' );
		self::assertNull( $this->service( Ranking::class )->ranked( 'mnit', array() ), 'no parts, nothing asked' );
	}

	public function test_an_answer_that_is_not_one_is_none_and_an_empty_correction_is_no_correction(): void {
		$answer = null;
		// An arrow function would keep the answer as it was when made.
		add_filter(
			'csearch_ranked_ids',
			static function () use ( &$answer ): mixed {
				return $answer;
			},
			10,
			0
		);
		foreach ( array( 'ids', array( 'ids' => 'x' ), array( 'corrected' => 'x' ) ) as $wrong ) {
			$answer = $wrong;
			self::assertNull( $this->service( Ranking::class )->ranked( 'q', array( 1 ) ) );
		}
		$answer = array(
			'ids'       => array(),
			'corrected' => '',
		);
		self::assertSame(
			array(
				'ids'       => array(),
				'corrected' => null,
			),
			$this->service( Ranking::class )->ranked( 'q', array( 1 ) )
		);
	}
}
