<?php
/**
 * KitSettings.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SizeGuide;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SizeGuideRow;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * D-354: the kit's settings from 1.2.0 on, in `_cbuild_settings`, a document the release before never reads: the
 * Builder page (#113), "Build a complete with this deck" on the kit's parts' pages (#115) and the width guide (#114).
 * A kit with none has the defaults, and a setting this release does not know is passed over.
 */
final class KitSettingsTest extends TestCase {

	public function test_a_kit_with_no_settings_has_the_defaults_and_a_setting_not_known_is_passed_over(): void {
		$defaults = KitSettings::fromDocument( null );
		self::assertTrue( $defaults->startButton );
		self::assertNull( $defaults->builderPage );
		self::assertEquals( SizeGuide::plugins(), $defaults->guide );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'stage_side' => 'right' ) );
		self::assertEquals( $defaults, $kit->settings, 'and so does a kit saved before 1.2.0' );
		// A later release adds its settings to this document, and a rollback to this one still reads the kit.
		self::assertEquals( $defaults, KitSettings::fromDocument( array( 'schema_version' => 1, 'a_later_setting' => array( 'on' => true ) ) ) );
		self::assertFalse( KitSettings::fromDocument( array( 'schema_version' => 1, 'start_button' => false, 'a_later_setting' => 'on' ) )->startButton );
	}

	// #115: "Build a complete with this deck" on the kit's parts' pages, on unless the kit says off. A kit saved before
	// 1.2.0 has no key, so its parts' pages show the button as they did.
	public function test_the_start_button_shows_unless_the_kit_turns_it_off(): void {
		self::assertTrue( KitSettings::fromDocument( null )->startButton, 'a kit with no settings' );
		self::assertTrue( KitSettings::fromDocument( array( 'schema_version' => 1 ) )->startButton, 'settings that name nothing' );
		self::assertTrue( KitSettings::fromDocument( array( 'schema_version' => 1, 'start_button' => true ) )->startButton );
		self::assertFalse( KitSettings::fromDocument( array( 'schema_version' => 1, 'start_button' => false ) )->startButton );
		self::assertNull( KitSettings::fromDocument( array( 'schema_version' => 1, 'start_button' => false ) )->builderPage, 'and nothing else changes' );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, null, array( 'schema_version' => 1, 'start_button' => false ) );
		self::assertFalse( $kit->settings->startButton );
	}

	// D-409: Help me choose, the cards' ready-build picks and their ratings, each on unless the kit turns it off. A kit
	// saved before 1.3.0 has no key, and shows each as 1.3.0 does by default.
	public function test_help_me_choose_the_cards_picks_and_their_ratings_show_unless_the_kit_turns_each_off(): void {
		foreach ( array(
			'a kit with no settings'       => null,
			'settings saved before 1.3.0'  => array( 'schema_version' => 1, 'start_button' => false ),
			'each said on'                 => array( 'schema_version' => 1, 'guide' => true, 'picks' => true, 'ratings' => true ),
		) as $case => $document ) {
			$settings = KitSettings::fromDocument( $document );
			self::assertTrue( $settings->guided, $case . ': Help me choose' );
			self::assertTrue( $settings->cardPicks, $case . ': the picks' );
			self::assertTrue( $settings->cardRatings, $case . ': the ratings' );
		}
		foreach ( array(
			'guide'   => array( false, true, true ),
			'picks'   => array( true, false, true ),
			'ratings' => array( true, true, false ),
		) as $key => $expected ) {
			$off = KitSettings::fromDocument( array( 'schema_version' => 1, $key => false ) );
			self::assertSame( $expected, array( $off->guided, $off->cardPicks, $off->cardRatings ), $key . ' off, and the others not' );
			self::assertTrue( $off->startButton, $key . ' off, and nothing else changes' );
		}
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, null, array( 'schema_version' => 1, 'guide' => false ) );
		self::assertFalse( $kit->settings->guided );
	}

	// #113: the Builder page, a page's ID, or none for the kit's own page. A kit saved before 1.2.0 has none.
	public function test_smart_swaps_are_offered_unless_the_kit_turns_them_off(): void {
		self::assertTrue( KitSettings::fromDocument( null )->swaps, 'a kit with no settings, as before 1.2.0' );
		self::assertTrue( KitSettings::fromDocument( array( 'schema_version' => 1, 'start_button' => false ) )->swaps, 'settings saved before 1.3.0' );
		self::assertTrue( KitSettings::fromDocument( array( 'schema_version' => 1, 'swaps' => true ) )->swaps );
		$off = KitSettings::fromDocument( array( 'schema_version' => 1, 'swaps' => false ) );
		self::assertFalse( $off->swaps );
		self::assertTrue( $off->startButton, 'and nothing else changes' );
	}

	public function test_the_builder_page_is_the_page_named_or_none(): void {
		self::assertNull( KitSettings::fromDocument( null )->builderPage, 'a kit with no settings' );
		self::assertNull( KitSettings::fromDocument( array( 'schema_version' => 1 ) )->builderPage, 'settings that name nothing' );
		self::assertSame( 42, KitSettings::fromDocument( array( 'schema_version' => 1, 'builder_page' => 42 ) )->builderPage );
		self::assertSame( 1, KitSettings::fromDocument( array( 'schema_version' => 1, 'builder_page' => 1 ) )->builderPage );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, null, array( 'schema_version' => 1, 'builder_page' => 42 ) );
		self::assertSame( 42, $kit->settings->builderPage );
	}

	// #114: the width guide, the plugin's rows unless the kit gives its own, shown unless the kit hides it. A kit saved
	// before 1.2.0 has no width_guide, and shows the plugin's rows as 1.1.0 did.
	public function test_the_width_guide_is_the_plugins_and_shown_unless_the_kit_says_otherwise(): void {
		foreach ( array(
			'a kit with no settings'           => null,
			'settings that name nothing'    => array( 'schema_version' => 1 ),
			'a guide that names nothing'     => array( 'schema_version' => 1, 'width_guide' => array() ),
			'a guide that is shown, no rows' => array( 'schema_version' => 1, 'width_guide' => array( 'shown' => true ) ),
		) as $case => $document ) {
			$guide = KitSettings::fromDocument( $document )->guide;
			self::assertTrue( $guide->shown, $case );
			self::assertNull( $guide->rows, $case . ': the plugin\'s rows' );
		}

		$hidden = KitSettings::fromDocument( array( 'schema_version' => 1, 'width_guide' => array( 'shown' => false ) ) )->guide;
		self::assertFalse( $hidden->shown );
		self::assertNull( $hidden->rows );

		$own = KitSettings::fromDocument(
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

		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, null, array( 'schema_version' => 1, 'width_guide' => array( 'shown' => false ) ) );
		self::assertFalse( $kit->settings->guide->shown );
	}

	public function test_a_guide_takes_twelve_rows_and_a_width_from_5_to_12_inches(): void {
		$rows = array_map( static fn( int $at ): array => array( 'shoe' => sprintf( '%02d', $at ) . str_repeat( 'é', SizeGuide::MAX_SHOE - 2 ), 'from' => '5', 'to' => '12' ), range( 1, SizeGuide::MAX_ROWS ) );
		$read = KitSettings::fromDocument( array( 'schema_version' => 1, 'width_guide' => array( 'rows' => $rows ) ) )->guide->rows;
		self::assertCount( 12, $read ?? array() );
		$same = KitSettings::fromDocument( array( 'schema_version' => 1, 'width_guide' => array( 'rows' => array( array( 'shoe' => '9', 'from' => '8.25', 'to' => '8.25' ) ) ) ) )->guide->rows;
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
			KitSettings::fromDocument( $document );
			self::fail( 'refused' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( array( $code, $path ), array( $refused->reason->value, $refused->path ) );
		}
	}

	// #142: the assembly time in business days, "2" or "2-4", or none. A kit saved before 1.2.0 has none.
	public function test_the_assembly_time_is_the_business_days_named_or_none(): void {
		self::assertNull( KitSettings::fromDocument( null )->leadDays, 'a kit with no settings' );
		self::assertNull( KitSettings::fromDocument( array( 'schema_version' => 1 ) )->leadDays, 'settings that name nothing' );
		$range = KitSettings::fromDocument( array( 'schema_version' => 1, 'lead_days' => '2-4' ) )->leadDays;
		self::assertSame( array( 2, 4, '2-4' ), array( $range?->from, $range?->to, $range?->stored() ) );
		self::assertSame( '0', KitSettings::fromDocument( array( 'schema_version' => 1, 'lead_days' => '0' ) )->leadDays?->stored(), 'none to wait is a time' );
		self::assertSame( '3', KitSettings::fromDocument( array( 'schema_version' => 1, 'lead_days' => '3', 'start_button' => false ) )->leadDays?->stored() );
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, null, array( 'schema_version' => 1, 'lead_days' => '60' ) );
		self::assertSame( '60', $kit->settings->leadDays?->stored() );
		self::assertTrue( $kit->settings->startButton, 'and nothing else changes' );
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function wrongDays(): iterable {
		yield 'past 60' => array( '61', 'document_out_of_range' );
		yield 'the most first' => array( '4-2', 'rules_out_of_order' );
		yield 'words' => array( 'two days', 'document_bad_measure' );
		yield 'a range in words, which nothing reads (D-389)' => array( '2 to 4', 'document_bad_measure' );
	}

	#[DataProvider( 'wrongDays' )]
	public function test_an_assembly_time_that_is_not_business_days_from_0_to_60_is_refused_at_its_field( string $days, string $code ): void {
		try {
			KitSettings::fromDocument( array( 'schema_version' => 1, 'lead_days' => $days ) );
			self::fail( 'refused' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( array( $code, 'lead_days' ), array( $refused->reason->value, $refused->path ) );
		}
	}

	/**
	 * @return iterable<string, array{mixed, string}>
	 */
	public static function refused(): iterable {
		yield 'no version' => array( array( 'start_button' => true ), 'document_unknown_version' );
		yield 'another version' => array( array( 'schema_version' => 2 ), 'document_unknown_version' );
		yield 'the start button shown as text' => array( array( 'schema_version' => 1, 'start_button' => 'yes' ), 'document_wrong_type' );
		yield 'the start button shown as a number' => array( array( 'schema_version' => 1, 'start_button' => 1 ), 'document_wrong_type' );
		yield 'smart swaps as text' => array( array( 'schema_version' => 1, 'swaps' => 'on' ), 'document_wrong_type' );
		yield 'Help me choose as text' => array( array( 'schema_version' => 1, 'guide' => 'on' ), 'document_wrong_type' );
		yield 'the cards\' picks as a number' => array( array( 'schema_version' => 1, 'picks' => 0 ), 'document_wrong_type' );
		yield 'the cards\' ratings as text' => array( array( 'schema_version' => 1, 'ratings' => '' ), 'document_wrong_type' );
		yield 'a builder page of none' => array( array( 'schema_version' => 1, 'builder_page' => 0 ), 'document_out_of_range' );
		yield 'a builder page below none' => array( array( 'schema_version' => 1, 'builder_page' => -3 ), 'document_out_of_range' );
		yield 'a builder page as text' => array( array( 'schema_version' => 1, 'builder_page' => '42' ), 'document_wrong_type' );
		yield 'a builder page as an address' => array( array( 'schema_version' => 1, 'builder_page' => 'https://elsewhere.example/' ), 'document_wrong_type' );
		yield 'an assembly time as a number' => array( array( 'schema_version' => 1, 'lead_days' => 2 ), 'document_wrong_type' );
		yield 'an assembly time that is empty' => array( array( 'schema_version' => 1, 'lead_days' => '' ), 'document_out_of_range' );
	}

	#[DataProvider( 'refused' )]
	public function test_settings_that_break_their_shape_are_refused( mixed $document, string $code ): void {
		try {
			KitSettings::fromDocument( $document );
			self::fail( 'refused' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( $code, $refused->reason->value );
		}
	}
}
