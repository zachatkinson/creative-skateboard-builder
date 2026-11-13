<?php
/**
 * Where a builder is: on a kit's page, or in a post's blocks.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Front\BuilderPlace;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeBuilderPosts;
use PHPUnit\Framework\TestCase;

/**
 * One answer to which kit a page shows, for its title, its robots and canonical, and the state route's links (audit
 * 14 and 16, milestone 6): a kit's own page, or the first Builder block in a post, at any depth and inside a synced
 * pattern.
 */
final class BuilderPlaceTest extends TestCase {

	private static function builder( mixed $kit ): array {
		return array(
			'blockName'   => 'creative-skateboard-builder/builder',
			'attrs'       => array( 'kit' => $kit ),
			'innerBlocks' => array(),
		);
	}

	private static function group( array $inner ): array {
		return array(
			'blockName'   => 'core/group',
			'attrs'       => array(),
			'innerBlocks' => $inner,
		);
	}

	private static function synced( mixed $ref ): array {
		return array(
			'blockName'   => 'core/block',
			'attrs'       => array( 'ref' => $ref ),
			'innerBlocks' => array(),
		);
	}

	public function test_a_kits_own_page_shows_the_kit_and_another_product_shows_none(): void {
		$place = new BuilderPlace( new FakeBuilderPosts( array( 8 => array( self::builder( 7 ) ) ), array( 7 ) ) );
		self::assertSame( 7, $place->kit( 7, 'product' ) );
		self::assertSame( 0, $place->kit( 8, 'product' ), 'a product that is no kit, whatever its description holds' );
		self::assertSame( 0, $place->kit( 0, 'page' ) );
	}

	public function test_a_post_shows_the_kit_of_its_first_builder_block_at_any_depth(): void {
		$place = new BuilderPlace(
			new FakeBuilderPosts(
				array(
					1 => array( self::group( array() ), self::group( array( self::group( array( self::builder( 7 ) ) ) ) ), self::builder( 9 ) ),
					2 => array( self::builder( 9 ), self::builder( 7 ) ),
					3 => array( self::group( array( self::group( array() ) ) ), 'not a block', array( 'blockName' => null ) ),
					4 => array( self::builder( 'x' ), self::builder( 7 ) ),
				)
			)
		);
		self::assertSame( 7, $place->kit( 1, 'page' ) );
		self::assertSame( 9, $place->kit( 2, 'page' ) );
		self::assertSame( 0, $place->kit( 3, 'page' ) );
		self::assertSame( 0, $place->kit( 4, 'page' ), 'the first builder\'s kit, even one that names none' );
		self::assertSame( 0, $place->kit( 5, 'post' ), 'no blocks' );
	}

	public function test_no_post_is_looked_up_for_no_post_or_no_kit(): void {
		$posts = new FakeBuilderPosts(
			array(
				0 => array( self::builder( 7 ) ),
				1 => array( self::builder( 0 ), self::builder( 7 ) ),
			),
			array( 7 )
		);
		$place = new BuilderPlace( $posts );
		self::assertSame( 0, $place->kit( 0, 'page' ) );
		self::assertFalse( $place->holds( 0, 7 ) );
		self::assertFalse( $place->holds( 1, 0 ), 'a builder that names no kit holds none' );
		self::assertFalse( $place->holds( 1, -1 ) );
		self::assertSame( array(), $posts->asked );
	}

	public function test_a_page_that_holds_itself_as_a_pattern_is_read_once(): void {
		$posts = new FakeBuilderPosts(
			array(
				40 => array( self::synced( 40 ), self::builder( 9 ) ),
				41 => array( self::synced( 41 ), self::builder( 1 ) ),
			)
		);
		$place = new BuilderPlace( $posts );
		self::assertSame( 9, $place->kit( 40, 'page' ) );
		self::assertTrue( $place->holds( 41, 1 ), 'a kit whose ID is 1' );
		$cycle = new FakeBuilderPosts(
			array(
				50 => array( self::synced( 51 ), self::builder( 3 ) ),
				51 => array( self::synced( 52 ) ),
				52 => array( self::synced( 51 ) ),
			)
		);
		self::assertSame( 3, ( new BuilderPlace( $cycle ) )->kit( 50, 'page' ), 'two patterns that hold each other end' );
		self::assertSame(
			array(
				50 => 1,
				51 => 1,
				52 => 1,
			),
			$cycle->asked
		);
		self::assertSame(
			array(
				40 => 1,
				41 => 1,
			),
			$posts->asked
		);
	}

	public function test_a_builder_inside_a_synced_pattern_is_found_and_a_pattern_that_holds_itself_ends(): void {
		$posts = new FakeBuilderPosts(
			array(
				1  => array( self::group( array( self::synced( 20 ) ) ) ),
				20 => array( self::synced( 21 ) ),
				21 => array( self::builder( 7 ) ),
				2  => array( self::synced( 30 ), self::builder( 9 ) ),
				30 => array( self::synced( 30 ) ),
				3  => array( self::synced( 'x' ), self::synced( 0 ) ),
			)
		);
		$place = new BuilderPlace( $posts );
		self::assertSame( 7, $place->kit( 1, 'page' ) );
		self::assertSame( 9, $place->kit( 2, 'page' ), 'a pattern that holds itself is read once' );
		self::assertSame( 1, $posts->asked[30] );
		self::assertSame( 0, $place->kit( 3, 'page' ) );
		self::assertArrayNotHasKey( 0, $posts->asked, 'a ref that names no post is not looked up' );
	}

	public function test_a_post_holds_a_kit_wherever_its_builder_is_among_others(): void {
		$place = new BuilderPlace(
			new FakeBuilderPosts(
				array(
					1  => array( self::builder( 9 ), self::group( array( self::synced( 20 ) ) ) ),
					20 => array( self::builder( 7 ) ),
					2  => array( self::builder( 9 ) ),
				),
				array( 7 )
			)
		);
		self::assertTrue( $place->holds( 1, 7 ), 'the second builder, inside a pattern' );
		self::assertTrue( $place->holds( 1, 9 ) );
		self::assertFalse( $place->holds( 2, 7 ) );
		self::assertFalse( $place->holds( 1, 0 ), 'no kit is held by any page' );
		self::assertTrue( $place->holds( 7, 7 ), 'a kit\'s own page holds it' );
		self::assertFalse( $place->holds( 7, 9 ) );
	}
}
