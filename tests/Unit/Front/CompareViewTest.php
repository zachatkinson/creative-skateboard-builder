<?php
/**
 * The compare's decision, table or a word.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Front\CompareView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The auditor's 1.3.0 minor: whether My builds shows the compare's table was decided in `SavedBuildHooks::page()`. It is
 * the view's: two builds or more are compared, and fewer of the customer's own ask for two or three.
 */
#[CoversClass( CompareView::class )]
final class CompareViewTest extends TestCase {

	/**
	 * @return iterable<string, array{int, string|null}>
	 */
	public static function counts(): iterable {
		yield 'none of the customer\'s own' => array( 0, 'Choose two or three of your saved builds to compare.' );
		yield 'one' => array( 1, 'Choose two or three of your saved builds to compare.' );
		yield 'two, compared' => array( 2, null );
		yield 'three, compared' => array( 3, null );
	}

	#[DataProvider( 'counts' )]
	public function test_two_builds_or_more_are_compared_and_fewer_ask_for_two_or_three( int $count, ?string $said ): void {
		$builds = array_fill( 0, $count, array( 'uuid' => '', 'name' => '', 'open' => null ) );
		self::assertSame( $said, CompareView::refused( array( 'builds' => $builds ) ) );
	}
}
