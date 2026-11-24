<?php
/**
 * The deck width guide by shoe size, as a shop gives it.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use ArrayObject;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitLayout;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SizeGuide;
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

	/**
	 * A kit's width guide, as its layout document holds it.
	 *
	 * @param array<string, mixed> $guide The document's `width_guide`.
	 */
	private static function kits( array $guide ): SizeGuide {
		return KitLayout::fromDocument(
			array(
				'schema_version' => 1,
				'width_guide'    => $guide,
			)
		)->guide;
	}

	/**
	 * The plugin's rows, as 1.1.0 shows them.
	 *
	 * @return list<array{shoe: string, width: string}>
	 */
	private static function plugins(): array {
		return array(
			array( 'shoe' => '6.5 or smaller', 'width' => '7″ to 7.75″' ),
			array( 'shoe' => '7 to 8', 'width' => '7.75″ to 8″' ),
			array( 'shoe' => '8.5 to 9', 'width' => '8″ to 8.25″' ),
			array( 'shoe' => '9.5 and larger', 'width' => '8.25″ and wider' ),
		);
	}

	// #114: a kit saved before 1.2.0 has no width guide of its own, and shows the plugin's rows as 1.1.0 did.
	public function test_a_kit_with_no_guide_of_its_own_shows_the_plugins_rows(): void {
		self::assertSame( self::plugins(), WidthGuide::rows( self::KIT ) );
		self::assertSame( self::plugins(), WidthGuide::rows( self::KIT, SizeGuide::plugins() ) );
		self::assertSame( self::plugins(), WidthGuide::rows( self::KIT, self::kits( array( 'shown' => true ) ) ) );
	}

	// #114: the Build tab starts a kit's own rows from the plugin's, so it asks for them from outside the guide: each
	// shoe size in the shopper's words, and the widths as measures, the last row with no widest.
	public function test_the_build_tab_starts_a_kits_own_rows_from_the_plugins(): void {
		$defaults = WidthGuide::defaults();
		self::assertSame( array_column( self::plugins(), 'shoe' ), array_column( $defaults, 'shoe' ) );
		self::assertSame(
			array( '7.0000 7.7500', '7.7500 8.0000', '8.0000 8.2500', '8.2500 none' ),
			array_map( static fn( array $row ): string => $row['from']->decimal() . ' ' . ( $row['to']?->decimal() ?? 'none' ), $defaults )
		);
	}

	// #114: the shop's own rows, each shoe size as the owner typed it, through `cbuild_kit_text` as the kit's other
	// texts are, and each width in the shopper's units.
	public function test_a_kit_with_rows_of_its_own_shows_them_its_shoe_sizes_through_the_kits_text_filter(): void {
		$asked = array();
		$GLOBALS['cbuild_test_filters']['cbuild_kit_text'] = static function ( string $text, string $field, int $kit, string $of ) use ( &$asked ): string {
			$asked[] = array( $text, $field, $kit, $of );
			return 'EU 41' === $text ? 'Pointure 41' : $text;
		};
		$rows = WidthGuide::rows(
			self::KIT,
			self::kits(
				array(
					'rows' => array(
						array( 'shoe' => 'EU 38 to 40', 'from' => '7.75', 'to' => '8' ),
						array( 'shoe' => 'EU 41', 'from' => '8.125', 'to' => '8.125' ),
						array( 'shoe' => 'EU 42 and up', 'from' => '8.25' ),
					),
				)
			)
		);
		self::assertSame(
			array(
				array( 'shoe' => 'EU 38 to 40', 'width' => '7.75″ to 8″' ),
				array( 'shoe' => 'Pointure 41', 'width' => '8.125″' ),
				array( 'shoe' => 'EU 42 and up', 'width' => '8.25″ and wider' ),
			),
			$rows
		);
		self::assertSame(
			array(
				array( 'EU 38 to 40', 'width_guide_shoe', self::KIT, '0' ),
				array( 'EU 41', 'width_guide_shoe', self::KIT, '1' ),
				array( 'EU 42 and up', 'width_guide_shoe', self::KIT, '2' ),
			),
			$asked,
			'each shoe size asked of the filter with its row\'s place'
		);
	}

	// #114: a hidden guide has no rows, whether the kit keeps rows of its own or not.
	public function test_a_hidden_guide_has_no_rows(): void {
		self::assertSame( array(), WidthGuide::rows( self::KIT, self::kits( array( 'shown' => false ) ) ) );
		self::assertSame( array(), WidthGuide::rows( self::KIT, self::kits( array( 'shown' => false, 'rows' => array( array( 'shoe' => 'EU 40', 'from' => '8' ) ) ) ) ) );
	}

	// #114: `cbuild_width_guide` still answers last: it is given the kit's rows, or none for a hidden guide, and what
	// it answers is shown.
	public function test_the_filter_answers_after_the_kits_setting(): void {
		$given = array();
		$GLOBALS['cbuild_test_filters']['cbuild_width_guide'] = static function ( array $rows, int $kit ) use ( &$given ): array {
			$given[] = array( $rows, $kit );
			return array( array( 'shoe' => 'UK 7', 'width' => '8″' ) );
		};
		self::assertSame( array( array( 'shoe' => 'UK 7', 'width' => '8″' ) ), WidthGuide::rows( self::KIT, self::kits( array( 'rows' => array( array( 'shoe' => 'EU 40', 'from' => '8' ) ) ) ) ) );
		self::assertSame( array( array( 'shoe' => 'UK 7', 'width' => '8″' ) ), WidthGuide::rows( self::KIT, self::kits( array( 'shown' => false ) ) ), 'over a hidden guide too' );
		self::assertSame(
			array(
				array( array( array( 'shoe' => 'EU 40', 'width' => '8″ and wider' ) ), self::KIT ),
				array( array(), self::KIT ),
			),
			$given
		);
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
