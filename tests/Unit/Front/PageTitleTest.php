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
 * Spec, Keyboard and screen readers: the step goes before the page's own title through one pattern (D-149).
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
}
