<?php
/**
 * Query.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Look\Query;
use PHPUnit\Framework\TestCase;

/**
 * A step's search (SPEC-CONFIGURATOR, Search; D-248). Text comes folded, as the adapter folds it.
 */
final class QueryTest extends TestCase {

	public function test_words_are_runs_of_letters_and_digits(): void {
		self::assertSame( array( 'rascals', '8', '25', 'creme', 'brulee' ), Query::words( 'rascals - 8.25″, creme/brulee' ) );
		self::assertSame( array( 'été', '99a' ), Query::words( ' été  99a ' ), 'letters beyond ASCII are letters' );
		self::assertSame( array(), Query::words( ' -, ' ) );
		self::assertSame( array(), Query::words( "rascals \xff" ), 'text that is not UTF-8 has no words' );
	}

	public function test_every_word_of_the_query_must_start_a_word_of_the_look(): void {
		$look = array( 'rascals', 'retna cruiser deck', 'mint', '8.25″' );
		self::assertTrue( Query::matches( 'retna', $look ) );
		self::assertTrue( Query::matches( 'ret mi', $look ), 'the start of a word is enough' );
		self::assertTrue( Query::matches( 'mint rascals', $look ), 'in any order, across brand, name and look' );
		self::assertTrue( Query::matches( '8', $look ), 'the size is a word' );
		self::assertFalse( Query::matches( 'etna', $look ), 'a word must start, not sit inside' );
		self::assertFalse( Query::matches( 'retna blue', $look ), 'every word, not any' );
		self::assertFalse( Query::matches( 'retnа', $look ), 'no typos: the last letter is Cyrillic' );
	}

	public function test_a_query_with_no_words_matches_nothing(): void {
		self::assertFalse( Query::matches( ' - ', array( 'rascals' ) ) );
		self::assertFalse( Query::matches( 'rascals', array() ) );
	}

	public function test_creative_searchs_parents_keep_the_looks_whose_own_words_match(): void {
		$looks = array(
			10 => array(
				'10:1:0' => array( 'mint' ),
				'10:2:0' => array( 'black' ),
				'10:3:0' => array( 'mint', 'green' ),
			),
			20 => array(
				'20:4:0' => array( 'red' ),
				'20:5:0' => array( 'blue' ),
			),
		);
		self::assertSame( array( '20:4:0', '20:5:0', '10:1:0', '10:3:0' ), Query::narrow( array( 20, 10 ), $looks, 'cruiser mint', null ), 'Creative Search\'s order; the name matched the second, so all its looks' );
		self::assertSame( array( '10:2:0' ), Query::narrow( array( 10 ), $looks, 'blak', 'black' ), 'the corrected query counts' );
		self::assertSame( array( '10:3:0' ), Query::narrow( array( 10 ), $looks, 'gre', null ), 'the start of a word' );
		self::assertSame( array( '10:1:0', '10:3:0' ), Query::narrow( array( 10 ), $looks, 'mint green', null ), 'a look two words match is kept once' );
		self::assertSame( array( '10:1:0', '10:2:0', '10:3:0' ), Query::narrow( array( 10 ), $looks, 'een', null ), 'inside a word is no match, so all of them' );
		self::assertSame( array( '20:4:0', '20:5:0' ), Query::narrow( array( 30, 20 ), $looks, 'retna', null ), 'a parent the step lacks adds nothing' );
		self::assertSame( array(), Query::narrow( array(), $looks, 'mint', null ) );
	}
}
