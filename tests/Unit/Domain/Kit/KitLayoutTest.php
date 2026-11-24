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
