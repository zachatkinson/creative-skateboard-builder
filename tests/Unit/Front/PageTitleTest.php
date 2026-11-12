<?php
/**
 * The page's title on a builder's page.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Front\PageTitle;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Keyboard and screen readers: the step goes before the page's own title through one pattern, and the Builder
 * block's kit is found at any depth (D-149).
 */
final class PageTitleTest extends TestCase {

	public function test_the_step_goes_before_the_pages_own_title(): void {
		self::assertSame(
			array(
				'before' => '',
				'after'  => ' - Build a complete - Shop',
			),
			PageTitle::around( 'Build a complete - Shop' )
		);
	}

	public function test_a_page_title_that_holds_a_placeholder_is_kept_as_it_is(): void {
		self::assertSame( ' - 100%1$s off %s', PageTitle::around( '100%1$s off %s' )['after'] );
		self::assertSame( " - A \u{E000} B", PageTitle::around( "A \u{E000} B" )['after'], 'a title holding the mark itself, from a private-use font' );
	}

	public function test_the_kit_is_the_first_builder_blocks_at_any_depth(): void {
		$builder = static fn ( mixed $kit ): array => array(
			'blockName'   => 'creative-skateboard-builder/builder',
			'attrs'       => array( 'kit' => $kit ),
			'innerBlocks' => array(),
		);
		$group   = static fn ( array $inner ): array => array(
			'blockName'   => 'core/group',
			'attrs'       => array(),
			'innerBlocks' => $inner,
		);
		self::assertSame( 7, PageTitle::kit( array( $group( array() ), $group( array( $group( array( $builder( 7 ) ) ) ) ), $builder( 9 ) ) ) );
		self::assertSame( 9, PageTitle::kit( array( $builder( 9 ), $builder( 7 ) ) ) );
		self::assertSame( 0, PageTitle::kit( array( $group( array( $group( array() ) ) ), 'not a block', array( 'blockName' => null ) ) ) );
		self::assertSame( 0, PageTitle::kit( array( $builder( 'x' ), $builder( 7 ) ) ), 'the first builder\'s kit, even one that names none' );
		self::assertSame( 0, PageTitle::kit( array() ) );
	}
}
