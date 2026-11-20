<?php
/**
 * The deck width guide by shoe size, as a shop gives it.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use ArrayObject;
use CreativeBranding\SkateboardBuilder\Front\WidthGuide;
use PHPUnit\Framework\TestCase;

/**
 * Spec (configurator): The deck's width first, "Not sure of your width?". A shop's rows through `cbuild_width_guide`
 * are shown as plain text, a row without both a shoe size and a width is left out, and twelve rows are shown at most.
 */
final class WidthGuideTest extends TestCase {

	private const int KIT = 77;

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'] );
	}

	/**
	 * The guide with a shop's rows.
	 *
	 * @param list<mixed> $rows What the filter answers.
	 * @return list<array{shoe: string, width: string}>
	 */
	private static function given( array $rows ): array {
		$GLOBALS['cbuild_test_filters']['cbuild_width_guide'] = static fn(): array => $rows;
		return WidthGuide::rows( self::KIT );
	}

	public function test_a_shops_rows_lose_the_space_round_them_and_a_row_of_markup_or_space_alone_is_left_out(): void {
		self::assertSame(
			array( array( 'shoe' => 'EU 40', 'width' => '8″' ) ),
			self::given(
				array(
					array( 'shoe' => '  EU 40 ', 'width' => "\t8″ " ),
					array( 'shoe' => '<b> </b>', 'width' => '8.5″' ),
					array( 'shoe' => 'EU 44', 'width' => '   ' ),
				)
			)
		);
	}

	public function test_a_row_that_is_not_an_array_is_left_out_even_one_that_reads_like_one(): void {
		self::assertSame( array(), self::given( array( new ArrayObject( array( 'shoe' => 'EU 40', 'width' => '8″' ) ) ) ) );
	}

	public function test_the_guide_shows_twelve_rows_at_most(): void {
		$rows = array();
		for ( $at = 1; $at <= 13; $at++ ) {
			$rows[] = array( 'shoe' => 'EU ' . $at, 'width' => '8″' );
		}
		$shown = self::given( $rows );
		self::assertCount( 12, $shown );
		self::assertSame( 'EU 12', $shown[11]['shoe'], 'the first twelve, in the shop\'s order' );
	}
}
