<?php
/**
 * PictureSettings, and the layout drawn with them.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\PictureSettings;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Data model: `_cbuild_picture`, "the slots' layer order, and any anchor the kit moves, such as where the
 * trucks sit on a shape with no wheelbase". A kit saved before 0.7.0 has none, and is drawn as it was (D-192).
 */
final class PictureSettingsTest extends TestCase {

	/**
	 * A whole build: deck, grip, trucks and wheels.
	 *
	 * @param array<string, string> $deck The deck's specs.
	 */
	private static function board( array $deck = array( 'deck_width' => '8.25', 'deck_length' => '32', 'wheelbase' => '14.25' ) ): \CreativeBranding\SkateboardBuilder\Domain\Kit\Picks {
		return Parts::picks(
			array(
				SlotKey::DECK   => Parts::of( $deck, 100 ),
				SlotKey::GRIP   => Parts::of( array(), 700 ),
				SlotKey::TRUCKS => Parts::of( array( 'axle_width' => '8.25' ), 200 ),
				SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ), 300 ),
			)
		);
	}

	/**
	 * The slots of a layout's layers, in stack order.
	 *
	 * @return list<string>
	 */
	private static function stack( Layout $layout ): array {
		return array_values( array_unique( array_map( static fn( Layer $layer ): string => $layer->slot, $layout->layers ) ) );
	}

	public function test_no_document_is_the_order_the_layout_always_drew_and_no_anchor(): void {
		$none = PictureSettings::fromDocument( null );
		self::assertSame( array(), $none->order );
		self::assertNull( $none->wheelbase );
		self::assertEquals( Layout::of( self::board() ), Layout::of( self::board(), $none ) );
		self::assertSame( array( 'deck', 'grip', 'trucks', 'wheels' ), self::stack( Layout::of( self::board() ) ) );
	}

	public function test_the_order_names_the_slots_from_the_bottom_and_those_it_leaves_out_follow_in_the_usual_order(): void {
		$settings = PictureSettings::fromDocument(
			array(
				'schema_version' => 1,
				'order'          => array( 'grip', 'deck', 'wheels' ),
			)
		);
		self::assertSame( array( 'grip', 'deck', 'wheels' ), $settings->order );
		$layout = Layout::of( self::board(), $settings );
		self::assertSame( array( 'grip', 'deck', 'wheels', 'trucks' ), self::stack( $layout ) );
		self::assertCount( 8, $layout->layers, 'every layer is kept: a deck, a grip, two trucks and four wheels' );
		self::assertSame( array( 300, 300, 300, 300 ), array_map( static fn( Layer $layer ): int => $layer->part, array_slice( $layout->layers, 2, 4 ) ), 'a slot\'s own layers keep their order' );
		self::assertSame( Layout::of( self::board() )->width, $layout->width, 'the order moves nothing' );
	}

	public function test_the_wheelbase_anchor_places_the_trucks_on_a_deck_that_gives_none(): void {
		$settings = PictureSettings::fromDocument(
			array(
				'schema_version' => 1,
				'wheelbase'      => '15',
			)
		);
		self::assertSame( 150000, $settings->wheelbase?->tenThousandths );
		$given  = Layout::of( self::board( array( 'deck_width' => '8.25', 'deck_length' => '32', 'wheelbase' => '15' ) ) );
		$anchor = Layout::of( self::board( array( 'deck_width' => '8.25', 'deck_length' => '32' ) ), $settings );
		self::assertEquals( $given->layers, $anchor->layers, 'drawn as though the deck gave 15 in' );
		self::assertSame( array( SpecKey::Wheelbase ), $anchor->assumed, 'the deck still gives none, which the description may say' );

		$own = Layout::of( self::board(), $settings );
		self::assertEquals( Layout::of( self::board() )->layers, $own->layers, 'a deck that gives its wheelbase keeps it' );
	}

	/**
	 * @return iterable<string, array{array<string, mixed>, DocumentError, string}>
	 */
	public static function refused(): iterable {
		yield 'a version this code does not read' => array( array( 'schema_version' => 2 ), DocumentError::UnknownVersion, 'schema_version' );
		yield 'an order that is not a list' => array( array( 'schema_version' => 1, 'order' => 'deck' ), DocumentError::WrongType, 'order' );
		yield 'a key that is not a key' => array( array( 'schema_version' => 1, 'order' => array( 'deck', 'Grip tape' ) ), DocumentError::BadKey, 'order[1]' );
		yield 'a slot named twice' => array( array( 'schema_version' => 1, 'order' => array( 'deck', 'grip', 'deck' ) ), DocumentError::Duplicate, 'order[2]' );
		yield 'more slots than a kit holds' => array( array( 'schema_version' => 1, 'order' => array_map( static fn( int $n ): string => 'slot_' . $n, range( 1, 21 ) ) ), DocumentError::OutOfRange, 'order' );
		yield 'a wheelbase that is no decimal' => array( array( 'schema_version' => 1, 'wheelbase' => '14 1/4' ), DocumentError::BadMeasure, 'wheelbase' );
		yield 'a wheelbase under 10 in' => array( array( 'schema_version' => 1, 'wheelbase' => '9.9999' ), DocumentError::OutOfRange, 'wheelbase' );
		yield 'a wheelbase over 20 in' => array( array( 'schema_version' => 1, 'wheelbase' => '20.0001' ), DocumentError::OutOfRange, 'wheelbase' );
	}

	/**
	 * @param array<string, mixed> $document The document.
	 */
	#[DataProvider( 'refused' )]
	public function test_a_document_that_breaks_its_shape_is_refused_with_where( array $document, DocumentError $reason, string $path ): void {
		try {
			PictureSettings::fromDocument( $document );
			self::fail( 'refused' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( array( $reason, $path ), array( $refused->reason, $refused->path ) );
		}
	}

	public function test_the_ends_of_the_wheelbase_and_an_empty_one_are_taken(): void {
		self::assertSame( 100000, PictureSettings::fromDocument( array( 'schema_version' => 1, 'wheelbase' => '10' ) )->wheelbase?->tenThousandths );
		self::assertSame( 200000, PictureSettings::fromDocument( array( 'schema_version' => 1, 'wheelbase' => '20' ) )->wheelbase?->tenThousandths );
		self::assertNull( PictureSettings::fromDocument( array( 'schema_version' => 1, 'wheelbase' => '' ) )->wheelbase, 'empty is the usual by shape' );
		self::assertCount( 20, PictureSettings::fromDocument( array( 'schema_version' => 1, 'order' => array_map( static fn( int $n ): string => 'slot_' . $n, range( 1, 20 ) ) ) )->order );
	}

	public function test_a_kit_holds_its_picture_and_passes_over_a_slot_it_no_longer_has(): void {
		$picture = array(
			'schema_version' => 1,
			'order'          => array( 'grip', 'stickers', 'deck' ),
			'wheelbase'      => '14',
		);
		$kit     = \CreativeBranding\SkateboardBuilder\Domain\Kit\Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), $picture );
		self::assertSame( array( 'grip', 'deck' ), $kit->picture->order, 'a slot removed since does not keep the kit from opening' );
		self::assertSame( 140000, $kit->picture->wheelbase?->tenThousandths );
		self::assertSame( array(), KitDocuments::kit()->picture->order, 'a kit with no picture document' );
		self::assertNull( KitDocuments::kit()->picture->wheelbase );
	}
}
