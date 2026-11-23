<?php
/**
 * LayerImage, and the size one part is drawn at.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerError;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerImage;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, Milestone 7: "A Layer image field on each part's Part specs panel takes a transparent PNG or WebP from
 * the Media Library, previews it on the template at the part's size, and checks its transparency and size." The part's
 * size is the one the layout draws it at; the image must hold two pixels to the millimetre of it (D-190).
 */
final class LayerImageTest extends TestCase {

	/**
	 * The specs of a part, read as the index reads them.
	 *
	 * @param array<string, string> $specs The fields.
	 */
	private static function specs( array $specs ): Specs {
		return Parts::of( $specs, 1 )->specs;
	}

	/**
	 * @return iterable<string, array{string, array<string, string>, array{int, int}|null}>
	 */
	public static function drawn(): iterable {
		yield 'a deck by its width and length' => array( SlotKey::DECK, array( 'deck_width' => '8.25', 'deck_length' => '31.5' ), array( 2095500, 8001000 ) );
		yield 'a deck with no length, the usual 32 in' => array( SlotKey::DECK, array( 'deck_width' => '8.5' ), array( 2159000, 8128000 ) );
		yield 'a deck with no width is not drawn' => array( SlotKey::DECK, array( 'deck_length' => '32' ), null );
		// Cowork's 1.0.0 review, F18 (#103): a grip's image is its whole sheet, 9 by 33 in where its specs give none.
		yield 'grip as its whole sheet, the usual 9 by 33 in' => array( SlotKey::GRIP, array(), array( 2286000, 8382000 ) );
		yield 'grip by its sheet\'s width and length' => array( SlotKey::GRIP, array( 'grip_width' => '10', 'grip_length' => '34' ), array( 2540000, 8636000 ) );
		yield 'trucks by the axle and a new school baseplate' => array( SlotKey::TRUCKS, array( 'axle_width' => '8.5' ), array( 2159000, 762000 ) );
		yield 'trucks with no axle are not drawn' => array( SlotKey::TRUCKS, array(), null );
		yield 'a wheel by its diameter' => array( SlotKey::WHEELS, array( 'wheel_diameter' => '54' ), array( 324000, 540000 ) );
		yield 'a wheel with none, the usual 54 mm' => array( SlotKey::WHEELS, array(), array( 324000, 540000 ) );
		yield 'risers sit under the trucks' => array( SlotKey::RISERS, array( 'riser_thickness' => '3' ), null );
		yield 'a slot of the kit\'s own' => array( 'stickers', array(), null );
	}

	/**
	 * @param array<string, string> $specs The part's fields.
	 * @param array{int, int}|null  $size  Across and along, in ten-thousandths of a millimetre.
	 */
	#[DataProvider( 'drawn' )]
	public function test_one_part_is_drawn_alone_at_the_size_the_board_draws_it( string $slot, array $specs, ?array $size ): void {
		self::assertSame( $size, Layout::drawn( $slot, self::specs( $specs ) ) );
	}

	public function test_the_size_alone_is_the_size_in_the_board(): void {
		$deck   = Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32', 'wheelbase' => '14.25' ), 100 );
		$trucks = Parts::of( array( 'axle_width' => '8.25' ), 200 );
		$wheels = Parts::of( array( 'wheel_diameter' => '53' ), 300 );
		$grip   = Parts::of( array(), 700 );
		$board  = Layout::of( Parts::picks( array( SlotKey::DECK => $deck, SlotKey::TRUCKS => $trucks, SlotKey::WHEELS => $wheels, SlotKey::GRIP => $grip ) ) );
		foreach ( $board->layers as $layer ) {
			$part = array( 100 => $deck, 200 => $trucks, 300 => $wheels, 700 => $grip )[ $layer->part ];
			self::assertSame( array( $layer->width, $layer->height ), Layout::drawn( $layer->slot, $part->specs ), $layer->slot );
		}
	}

	public function test_an_image_needs_two_pixels_to_the_millimetre_rounded_up(): void {
		self::assertSame( array( 420, 1626 ), LayerImage::needs( array( 2095500, 8128000 ) ) );
		self::assertSame( array( 65, 108 ), LayerImage::needs( array( 324000, 540000 ) ) );
		self::assertSame( array( 1, 1 ), LayerImage::needs( array( 1, 1 ) ) );
	}

	/**
	 * @return iterable<string, array{array{0: string, 1: int, 2: int, 3: bool|null, 4?: bool}, array{int, int}|null, list<LayerError>}>
	 */
	public static function checked(): iterable {
		$deck = array( 2095500, 8128000 );
		yield 'a transparent PNG large enough' => array( array( 'image/png', 420, 1626, true ), $deck, array() );
		yield 'a transparent WebP' => array( array( 'image/webp', 500, 2000, true ), $deck, array() );
		// Cowork's M7 review, F1 (D-210): an AVIF where the host reads it, and a refusal of its own where it does not.
		yield 'a transparent AVIF the host reads' => array( array( 'image/avif', 500, 2000, true, true ), $deck, array() );
		yield 'an AVIF the host cannot read, whatever else is true of it' => array( array( 'image/avif', 10, 10, null, false ), $deck, array( LayerError::Unreadable ) );
		yield 'a JPEG the host cannot read is the wrong type first' => array( array( 'image/jpeg', 800, 3000, null, false ), $deck, array( LayerError::WrongType ) );
		yield 'wide enough though too short: drawn to fit, it is never enlarged' => array( array( 'image/png', 420, 900, true ), $deck, array() );
		yield 'long enough though too narrow' => array( array( 'image/png', 100, 1626, true ), $deck, array() );
		yield 'short of both' => array( array( 'image/png', 419, 1625, true ), $deck, array( LayerError::TooSmall ) );
		yield 'a JPEG, which has no transparency' => array( array( 'image/jpeg', 800, 3000, null ), $deck, array( LayerError::WrongType ) );
		yield 'an SVG' => array( array( 'image/svg+xml', 0, 0, null ), $deck, array( LayerError::WrongType ) );
		yield 'a PNG with its background left in' => array( array( 'image/png', 800, 3000, false ), $deck, array( LayerError::Opaque ) );
		yield 'opaque and small' => array( array( 'image/png', 10, 10, false ), $deck, array( LayerError::Opaque, LayerError::TooSmall ) );
		yield 'no size to check, from a part the picture does not draw yet' => array( array( 'image/png', 10, 10, true ), null, array() );
		yield 'transparency not known, with no image library on the host' => array( array( 'image/webp', 800, 3000, null ), $deck, array() );
	}

	/**
	 * @param array{0: string, 1: int, 2: int, 3: bool|null, 4?: bool} $image    The image's type, pixels, corners and whether the host reads it.
	 * @param array{int, int}|null                                $size     The part's size.
	 * @param list<LayerError>                                    $problems What is wrong.
	 */
	#[DataProvider( 'checked' )]
	public function test_a_layer_image_is_a_transparent_png_webp_or_avif_the_host_reads_holding_the_parts_size( array $image, ?array $size, array $problems ): void {
		self::assertSame( $problems, LayerImage::problems( new ImageFacts( ...$image ), $size ) );
	}

	// F18 (#103): "Change the grip's save check to 2 px/mm of the sheet with no transparent-corner rule". A sheet is
	// shot flat, its grit to every edge, so its corners are the sheet's own.
	public function test_a_grip_sheet_needs_two_pixels_to_the_millimetre_of_the_sheet_and_no_clear_corners(): void {
		$sheet = Layout::drawn( SlotKey::GRIP, self::specs( array() ) );
		self::assertNotNull( $sheet );
		self::assertSame( array( 458, 1677 ), LayerImage::needs( $sheet ) );
		self::assertSame( array(), LayerImage::problems( new ImageFacts( 'image/webp', 460, 1687, false ), $sheet, false ), 'a sheet shot flat, corners and all' );
		self::assertSame( array( LayerError::TooSmall ), LayerImage::problems( new ImageFacts( 'image/webp', 457, 1676, false ), $sheet, false ) );
		self::assertSame( array( LayerError::Opaque ), LayerImage::problems( new ImageFacts( 'image/png', 800, 3000, false ), array( 2095500, 8128000 ) ), 'a cut-out still needs them' );
		self::assertFalse( Layout::cutOut( SlotKey::GRIP ) );
		foreach ( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::HARDWARE ) as $slot ) {
			self::assertTrue( Layout::cutOut( $slot ), $slot );
		}
	}

	public function test_a_length_just_over_a_whole_number_of_half_millimetres_needs_the_next_pixel(): void {
		// 1.0001 mm at two pixels to the millimetre is 2.0002 px: three, rounded up.
		self::assertSame( array( 3, 2 ), LayerImage::needs( array( 10001, 10000 ) ) );
	}
}
