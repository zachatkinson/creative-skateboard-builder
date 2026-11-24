<?php
/**
 * KitLayout.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitLayout;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SizeGuide;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SizeGuideRow;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec (configurator): Settings (D-255): the kit's Layout, in `_cbuild_layout`: the stage's side and the board's size
 * on a desktop, and which search the steps use. A kit with none is laid out with the defaults.
 */
final class KitLayoutTest extends TestCase {

	public function test_a_kit_with_no_layout_has_the_defaults(): void {
		$layout = KitLayout::fromDocument( null );
		self::assertSame( array( 'left', 'large', 'creative_search', 'playful' ), array( $layout->stageSide, $layout->boardSize, $layout->search, $layout->motion ) );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly() );
		self::assertEquals( $layout, $kit->layout, 'and so does a kit saved before 1.0.0' );
	}

	public function test_a_layout_names_its_choices_and_leaves_the_rest_as_they_are(): void {
		$layout = KitLayout::fromDocument(
			array(
				'schema_version' => 1,
				'stage_side'     => 'right',
				'board_size'     => 'balanced',
			)
		);
		self::assertSame( array( 'right', 'balanced', 'creative_search' ), array( $layout->stageSide, $layout->boardSize, $layout->search ) );
		self::assertSame( 'own', KitLayout::fromDocument( array( 'schema_version' => 1, 'search' => 'own' ) )->search );
		// Milestone 11: Motion, playful unless the kit says subtle. A layout saved by 1.0.0 has none, so it is playful.
		self::assertSame( 'subtle', KitLayout::fromDocument( array( 'schema_version' => 1, 'motion' => 'subtle' ) )->motion );
		self::assertSame( 'playful', KitLayout::fromDocument( array( 'schema_version' => 1, 'stage_side' => 'right' ) )->motion );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'stage_side' => 'right' ) );
		self::assertSame( 'right', $kit->layout->stageSide );
	}

	// #104 (R1): Show ready builds, on unless the kit says off. A kit saved before 1.1.0 has no key, so its ready
	// builds show as they did.
	public function test_ready_builds_show_unless_the_kit_turns_them_off(): void {
		self::assertTrue( KitLayout::fromDocument( null )->showPresets, 'a kit with no layout' );
		self::assertTrue( KitLayout::fromDocument( array( 'schema_version' => 1, 'motion' => 'subtle' ) )->showPresets, 'a layout saved before 1.1.0' );
		self::assertTrue( KitLayout::fromDocument( array( 'schema_version' => 1, 'show_presets' => true ) )->showPresets );
		self::assertFalse( KitLayout::fromDocument( array( 'schema_version' => 1, 'show_presets' => false ) )->showPresets );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'show_presets' => false ) );
		self::assertFalse( $kit->layout->showPresets );
	}

	// #115: "Build a complete with this deck" on the kit's parts' pages, on unless the kit says off. A kit saved before
	// 1.2.0 has no key, so its parts' pages show the button as they did.
	public function test_the_start_button_shows_unless_the_kit_turns_it_off(): void {
		self::assertTrue( KitLayout::fromDocument( null )->startButton, 'a kit with no layout' );
		self::assertTrue( KitLayout::fromDocument( array( 'schema_version' => 1, 'show_presets' => false ) )->startButton, 'a layout saved before 1.2.0' );
		self::assertTrue( KitLayout::fromDocument( array( 'schema_version' => 1, 'start_button' => true ) )->startButton );
		self::assertFalse( KitLayout::fromDocument( array( 'schema_version' => 1, 'start_button' => false ) )->startButton );
		self::assertTrue( KitLayout::fromDocument( array( 'schema_version' => 1, 'start_button' => false ) )->showPresets, 'and nothing else changes' );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'start_button' => false ) );
		self::assertFalse( $kit->layout->startButton );
	}

	// #113: the Builder page, a page's ID, or none for the kit's own page. A kit saved before 1.2.0 has none.
	public function test_the_builder_page_is_the_page_named_or_none(): void {
		self::assertNull( KitLayout::fromDocument( null )->builderPage, 'a kit with no layout' );
		self::assertNull( KitLayout::fromDocument( array( 'schema_version' => 1, 'show_presets' => true ) )->builderPage, 'a layout saved before 1.2.0' );
		self::assertSame( 42, KitLayout::fromDocument( array( 'schema_version' => 1, 'builder_page' => 42 ) )->builderPage );
		self::assertSame( 1, KitLayout::fromDocument( array( 'schema_version' => 1, 'builder_page' => 1 ) )->builderPage );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'builder_page' => 42 ) );
		self::assertSame( 42, $kit->layout->builderPage );
	}

	// #114: the width guide, the plugin's rows unless the kit gives its own, shown unless the kit hides it. A kit saved
	// before 1.2.0 has no width_guide, and shows the plugin's rows as 1.1.0 did.
	public function test_the_width_guide_is_the_plugins_and_shown_unless_the_kit_says_otherwise(): void {
		foreach ( array(
			'a kit with no layout'           => null,
			'a layout saved before 1.2.0'    => array( 'schema_version' => 1, 'show_presets' => false ),
			'a guide that names nothing'     => array( 'schema_version' => 1, 'width_guide' => array() ),
			'a guide that is shown, no rows' => array( 'schema_version' => 1, 'width_guide' => array( 'shown' => true ) ),
		) as $case => $document ) {
			$guide = KitLayout::fromDocument( $document )->guide;
			self::assertTrue( $guide->shown, $case );
			self::assertNull( $guide->rows, $case . ': the plugin\'s rows' );
		}

		$hidden = KitLayout::fromDocument( array( 'schema_version' => 1, 'width_guide' => array( 'shown' => false ) ) )->guide;
		self::assertFalse( $hidden->shown );
		self::assertNull( $hidden->rows );

		$own = KitLayout::fromDocument(
			array(
				'schema_version' => 1,
				'width_guide'    => array(
					'shown' => false,
					'rows'  => array(
						array( 'shoe' => 'EU 38 to 40', 'from' => '7.75', 'to' => '8' ),
						array( 'shoe' => 'EU 41', 'from' => '8.125', 'to' => '' ),
						array( 'shoe' => 'EU 42 and up', 'from' => '8.25' ),
					),
				),
			)
		)->guide;
		self::assertFalse( $own->shown, 'hidden, with its rows kept for when it is shown again' );
		self::assertNotNull( $own->rows );
		self::assertSame( array( 'EU 38 to 40', 'EU 41', 'EU 42 and up' ), array_map( static fn( SizeGuideRow $row ): string => $row->shoe, $own->rows ) );
		self::assertTrue( Measure::inches( 77500 )->equals( $own->rows[0]->from ) );
		self::assertTrue( Measure::inches( 80000 )->equals( $own->rows[0]->to ?? Measure::inches( 0 ) ) );
		self::assertNull( $own->rows[1]->to, 'an empty widest is none' );
		self::assertNull( $own->rows[2]->to, 'and so is one left out' );

		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'width_guide' => array( 'shown' => false ) ) );
		self::assertFalse( $kit->layout->guide->shown );
	}

	public function test_a_guide_takes_twelve_rows_and_a_width_from_5_to_12_inches(): void {
		$rows = array_map( static fn( int $at ): array => array( 'shoe' => sprintf( '%02d', $at ) . str_repeat( 'é', SizeGuide::MAX_SHOE - 2 ), 'from' => '5', 'to' => '12' ), range( 1, SizeGuide::MAX_ROWS ) );
		$read = KitLayout::fromDocument( array( 'schema_version' => 1, 'width_guide' => array( 'rows' => $rows ) ) )->guide->rows;
		self::assertCount( 12, $read ?? array() );
		$same = KitLayout::fromDocument( array( 'schema_version' => 1, 'width_guide' => array( 'rows' => array( array( 'shoe' => '9', 'from' => '8.25', 'to' => '8.25' ) ) ) ) )->guide->rows;
		self::assertTrue( Measure::inches( 82500 )->equals( $same[0]->to ?? Measure::inches( 0 ) ), 'one width, said once' );
	}

	/**
	 * @return iterable<string, array{mixed, string, string}>
	 */
	public static function wrongGuides(): iterable {
		$row = static fn( array $row ): array => array( 'schema_version' => 1, 'width_guide' => array( 'rows' => array( array( 'shoe' => '9', 'from' => '8' ), $row ) ) );
		yield 'a guide that is no object' => array( array( 'schema_version' => 1, 'width_guide' => array( 'rows' ) ), 'document_not_an_object', 'width_guide' );
		yield 'shown as text' => array( array( 'schema_version' => 1, 'width_guide' => array( 'shown' => 'no' ) ), 'document_wrong_type', 'width_guide.shown' );
		yield 'rows that are no list' => array( array( 'schema_version' => 1, 'width_guide' => array( 'rows' => array( 'a' => array() ) ) ), 'document_wrong_type', 'width_guide.rows' );
		yield 'an empty list of rows' => array( array( 'schema_version' => 1, 'width_guide' => array( 'rows' => array() ) ), 'document_out_of_range', 'width_guide.rows' );
		yield 'thirteen rows' => array( array( 'schema_version' => 1, 'width_guide' => array( 'rows' => array_fill( 0, 13, array( 'shoe' => '9', 'from' => '8' ) ) ) ), 'document_out_of_range', 'width_guide.rows' );
		yield 'a row with no shoe size' => array( $row( array( 'from' => '8' ) ), 'document_field_missing', 'width_guide.rows[1].shoe' );
		yield 'an empty shoe size' => array( $row( array( 'shoe' => '', 'from' => '8' ) ), 'document_out_of_range', 'width_guide.rows[1].shoe' );
		yield 'a shoe size too long' => array( $row( array( 'shoe' => str_repeat( 'a', 41 ), 'from' => '8' ) ), 'document_out_of_range', 'width_guide.rows[1].shoe' );
		yield 'a row with no width' => array( $row( array( 'shoe' => '10' ) ), 'document_field_missing', 'width_guide.rows[1].from' );
		yield 'a width that is no number' => array( $row( array( 'shoe' => '10', 'from' => 'eight' ) ), 'document_bad_measure', 'width_guide.rows[1].from' );
		yield 'a width as a number' => array( $row( array( 'shoe' => '10', 'from' => 8 ) ), 'document_wrong_type', 'width_guide.rows[1].from' );
		yield 'a widest that is no number' => array( $row( array( 'shoe' => '10', 'from' => '8', 'to' => '8 1/4' ) ), 'document_bad_measure', 'width_guide.rows[1].to' );
		yield 'a width under 5 inches' => array( $row( array( 'shoe' => '10', 'from' => '4.9999' ) ), 'document_out_of_range', 'width_guide.rows[1].from' );
		yield 'a width over 12 inches' => array( $row( array( 'shoe' => '10', 'from' => '8', 'to' => '12.0001' ) ), 'document_out_of_range', 'width_guide.rows[1].to' );
		// The page keys each row by its shoe size, so a guide names each once.
		yield 'a shoe size twice' => array( $row( array( 'shoe' => '9', 'from' => '8.5' ) ), 'document_duplicate', 'width_guide.rows[1].shoe' );
		yield 'a widest narrower than the narrowest' => array( $row( array( 'shoe' => '10', 'from' => '8.25', 'to' => '8.125' ) ), 'rules_out_of_order', 'width_guide.rows[1].to' );
	}

	#[DataProvider( 'wrongGuides' )]
	public function test_a_guide_that_breaks_its_shape_is_refused_at_its_field( mixed $document, string $code, string $path ): void {
		try {
			KitLayout::fromDocument( $document );
			self::fail( 'refused' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( array( $code, $path ), array( $refused->reason->value, $refused->path ) );
		}
	}

	/**
	 * @return iterable<string, array{mixed, string}>
	 */
	public static function refused(): iterable {
		yield 'no version' => array( array( 'stage_side' => 'left' ), 'document_unknown_version' );
		yield 'another version' => array( array( 'schema_version' => 2 ), 'document_unknown_version' );
		yield 'a side that is none' => array( array( 'schema_version' => 1, 'stage_side' => 'top' ), 'document_out_of_range' );
		yield 'a size that is none' => array( array( 'schema_version' => 1, 'board_size' => 'huge' ), 'document_out_of_range' );
		yield 'a search that is none' => array( array( 'schema_version' => 1, 'search' => 'google' ), 'document_out_of_range' );
		yield 'a motion that is none' => array( array( 'schema_version' => 1, 'motion' => 'wild' ), 'document_out_of_range' );
		yield 'a side that is no text' => array( array( 'schema_version' => 1, 'stage_side' => 1 ), 'document_wrong_type' );
		yield 'ready builds shown as text' => array( array( 'schema_version' => 1, 'show_presets' => 'yes' ), 'document_wrong_type' );
		yield 'the start button shown as text' => array( array( 'schema_version' => 1, 'start_button' => 'yes' ), 'document_wrong_type' );
		yield 'a builder page of none' => array( array( 'schema_version' => 1, 'builder_page' => 0 ), 'document_out_of_range' );
		yield 'a builder page below none' => array( array( 'schema_version' => 1, 'builder_page' => -3 ), 'document_out_of_range' );
		yield 'a builder page as text' => array( array( 'schema_version' => 1, 'builder_page' => '42' ), 'document_wrong_type' );
		yield 'a builder page as an address' => array( array( 'schema_version' => 1, 'builder_page' => 'https://elsewhere.example/' ), 'document_wrong_type' );
		yield 'the start button shown as a number' => array( array( 'schema_version' => 1, 'start_button' => 1 ), 'document_wrong_type' );
	}

	#[DataProvider( 'refused' )]
	public function test_a_layout_with_a_choice_it_does_not_have_is_refused( mixed $document, string $code ): void {
		try {
			KitLayout::fromDocument( $document );
			self::fail( 'refused' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( $code, $refused->reason->value );
		}
	}
}
