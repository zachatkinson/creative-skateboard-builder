<?php
/**
 * Where a kit's builder is: the page its Build tab chose, re-checked on use.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Front\BuilderAddress;
use CreativeBranding\SkateboardBuilder\Front\BuilderPlace;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeBuilderPosts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use PHPUnit\Framework\TestCase;

/**
 * #113 (D-348): a kit's Build tab chooses the page its builder is on, among published pages that hold the kit's Builder
 * block. A page that is unpublished, trashed, deleted or has lost the block is not used, and the kit's own page is,
 * as 1.1.0 had it; so is a kit saved before 1.2.0.
 */
final class BuilderAddressTest extends TestCase {

	private const KIT = 7;

	private static function builder( int $kit ): array {
		return array(
			'blockName'   => 'creative-skateboard-builder/builder',
			'attrs'       => array( 'kit' => $kit ),
			'innerBlocks' => array(),
		);
	}

	/**
	 * A kit whose layout names this page, or none.
	 */
	private static function kit( ?int $page ): Kit {
		$layout = null === $page ? null : array(
			'schema_version' => 1,
			'builder_page'   => $page,
		);
		return Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, $layout );
	}

	/**
	 * The address's reader over these posts: 20 holds the kit's block, 21 another kit's, 22 none, 23 the kit's in a
	 * synced pattern (30), 24 the kit's but is no page a visitor may see.
	 */
	private static function address( InMemoryKits $kits ): BuilderAddress {
		$posts = new FakeBuilderPosts(
			array(
				20 => array( self::builder( self::KIT ) ),
				21 => array( self::builder( 9 ) ),
				22 => array(),
				23 => array(
					array(
						'blockName'   => 'core/block',
						'attrs'       => array( 'ref' => 30 ),
						'innerBlocks' => array(),
					),
				),
				24 => array( self::builder( self::KIT ) ),
				30 => array( self::builder( self::KIT ) ),
			),
			array( self::KIT ),
			array( 20, 21, 22, 23 )
		);
		return new BuilderAddress( static fn(): InMemoryKits => $kits, new BuilderPlace( $posts ) );
	}

	public function test_a_kit_saved_before_1_2_0_is_built_on_its_own_page(): void {
		$kits = new InMemoryKits();
		$kits->add( self::KIT, self::kit( null ), array() );
		self::assertSame( 0, self::address( $kits )->page( self::KIT ) );
		self::assertSame( 0, self::address( new InMemoryKits() )->page( self::KIT ), 'nor does a kit the shop no longer has' );
	}

	public function test_the_chosen_page_is_used_while_it_is_published_and_holds_the_kits_block(): void {
		$kits = new InMemoryKits();
		$kits->add( self::KIT, self::kit( 20 ), array() );
		self::assertSame( 20, self::address( $kits )->page( self::KIT ) );
		self::assertSame( 23, self::address( $kits )->page( self::KIT, self::kit( 23 ) ), 'the block in a synced pattern; the kit as the caller read it' );
	}

	public function test_a_page_that_cannot_be_used_now_sends_no_one_there(): void {
		$kits = new InMemoryKits();
		foreach ( array(
			21          => 'another kit\'s builder',
			22          => 'a page that has lost the block',
			24          => 'unpublished, trashed or not a page a visitor may see',
			99          => 'deleted',
			self::KIT   => 'the kit itself',
		) as $page => $why ) {
			self::assertSame( 0, self::address( $kits )->page( self::KIT, self::kit( $page ) ), $why );
		}
	}
}
