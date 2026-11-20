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
		self::assertSame( array( 'left', 'large', 'creative_search' ), array( $layout->stageSide, $layout->boardSize, $layout->search ) );
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
		$kit = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'stage_side' => 'right' ) );
		self::assertSame( 'right', $kit->layout->stageSide );
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
		yield 'a side that is no text' => array( array( 'schema_version' => 1, 'stage_side' => 1 ), 'document_wrong_type' );
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
