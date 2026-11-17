<?php
/**
 * Composition: the layout placed on a canvas in pixels, and its name.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Canvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\CanvasError;
use CreativeBranding\SkateboardBuilder\Domain\Picture\InvalidCanvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerArt;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Placement;
use PHPUnit\Framework\TestCase;

/**
 * Spec, The picture: "A composed image of the same layout, made in the background, serves the cart, the emails and
 * share cards." Data model: composed pictures are "named by a hash of their layers, layout version and size, never by
 * anything a visitor sends" (D-196).
 */
final class CompositionTest extends TestCase {

	private static function layout(): Layout {
		return new Layout(
			2000000,
			8000000,
			array(
				new Layer( SlotKey::DECK, 100, 0, 0, 2000000, 8000000 ),
				new Layer( SlotKey::GRIP, 700, 0, 0, 254000, 8000000 ),
				new Layer( SlotKey::WHEELS, 300, -500000, 3000000, 300000, 500000 ),
			),
			array()
		);
	}

	private static function art( int $attachment, int $width, int $height, string $url = '' ): LayerArt {
		return new LayerArt( $attachment, '' === $url ? 'https://example.com/' . $attachment . '.png' : $url, $width, $height, array() );
	}

	public function test_the_board_fits_the_canvas_inside_its_margin_and_each_layer_fits_its_box_centred(): void {
		$composition = Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), array( 100 => self::art( 10, 400, 1600 ), 300 => self::art( 30, 60, 100 ) ) );
		self::assertEquals(
			array(
				new Placement( 10, 'https://example.com/10.png', 527, 25, 145, 580 ),
				new Placement( 30, 'https://example.com/30.png', 552, 79, 22, 36 ),
			),
			$composition->placements,
			'the grip has no art; the board is drawn 580 px tall, nose up, centred'
		);
	}

	public function test_an_image_of_another_shape_is_fitted_into_its_box_and_centred_in_it(): void {
		$wide = Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), array( 100 => self::art( 10, 400, 800 ) ) );
		self::assertEquals( array( new Placement( 10, 'https://example.com/10.png', 527, 170, 145, 290 ) ), $wide->placements, 'a short image, as wide as the box' );
		$tall = Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), array( 100 => self::art( 10, 100, 1600 ) ) );
		self::assertEquals( array( new Placement( 10, 'https://example.com/10.png', 581, 25, 36, 580 ) ), $tall->placements, 'a narrow one, as tall as the box' );
	}

	public function test_a_wide_canvas_is_filled_across(): void {
		$board = new Layout( 8000000, 2000000, array( new Layer( SlotKey::DECK, 100, 0, 0, 8000000, 2000000 ) ), array() );
		$wide  = Composition::of( new Canvas( 600, 600, 'image/jpeg' ), $board, array( 100 => self::art( 10, 1600, 400 ) ) );
		self::assertEquals( array( new Placement( 10, 'https://example.com/10.png', 24, 231, 552, 138 ) ), $wide->placements );
	}

	public function test_nothing_to_draw_is_an_empty_composition(): void {
		self::assertSame( array(), Composition::of( new Canvas( 600, 600, 'image/jpeg' ), new Layout( 0, 0, array(), array() ), array() )->placements );
		self::assertSame( array(), Composition::of( new Canvas( 600, 600, 'image/jpeg' ), self::layout(), array() )->placements );
		self::assertTrue( Composition::of( new Canvas( 600, 600, 'image/jpeg' ), self::layout(), array() )->isEmpty() );
		self::assertFalse( Composition::of( new Canvas( 600, 600, 'image/jpeg' ), self::layout(), array( 100 => self::art( 10, 400, 1600 ) ) )->isEmpty() );
	}

	public function test_the_name_is_a_hash_of_the_layers_the_version_and_the_size_with_the_types_extension(): void {
		$art   = array( 100 => self::art( 10, 400, 1600 ) );
		$webp  = Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), $art );
		$again = Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), $art );
		self::assertMatchesRegularExpression( '#^[0-9a-f]{32}\.webp$#', $webp->name() );
		self::assertSame( $webp->name(), $again->name(), 'the same build, the same file' );
		self::assertStringEndsWith( '.jpg', Composition::of( new Canvas( 1200, 630, 'image/jpeg' ), self::layout(), $art )->name() );
		$names = array(
			$webp->name(),
			Composition::of( new Canvas( 600, 630, 'image/webp' ), self::layout(), $art )->name(),
			Composition::of( new Canvas( 1200, 600, 'image/webp' ), self::layout(), $art )->name(),
			Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), array( 100 => self::art( 11, 400, 1600 ) ) )->name(),
			Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), array( 100 => self::art( 10, 400, 1600, 'https://example.com/10-e1.png' ) ) )->name(),
			Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), array( 100 => self::art( 10, 400, 800 ) ) )->name(),
		);
		self::assertSame( $names, array_values( array_unique( $names ) ), 'another size, another image or another file is another name' );
		self::assertSame( 1, Composition::VERSION );
	}

	public function test_a_canvas_is_a_jpeg_or_a_webp_with_pixels(): void {
		foreach ( array( array( 600, 600, 'image/png', CanvasError::NotJpegOrWebp ), array( 0, 600, 'image/webp', CanvasError::NoPixels ), array( 600, 0, 'image/jpeg', CanvasError::NoPixels ) ) as $canvas ) {
			try {
				new Canvas( $canvas[0], $canvas[1], $canvas[2] );
				self::fail( 'refused' );
			} catch ( InvalidCanvas $refused ) {
				self::assertSame( $canvas[3], $refused->reason );
				self::assertSame( $canvas[3]->value, $refused->getMessage(), 'its message is the reason\'s code' );
			}
		}
		self::assertSame( 1, ( new Canvas( 1, 1, 'image/jpeg' ) )->width, 'one pixel is a canvas' );
	}

	public function test_a_layout_with_no_width_or_no_height_places_nothing_and_one_of_a_single_unit_does(): void {
		$canvas = new Canvas( 480, 480, 'image/webp' );
		$art    = array( 100 => self::art( 10, 40, 40 ) );
		$layer  = new Layer( SlotKey::DECK, 100, 0, 0, 1, 1 );
		self::assertTrue( Composition::of( $canvas, new Layout( 0, 1, array( $layer ), array() ), $art )->isEmpty(), 'no width' );
		self::assertTrue( Composition::of( $canvas, new Layout( 1, 0, array( $layer ), array() ), $art )->isEmpty(), 'no height' );
		self::assertFalse( Composition::of( $canvas, new Layout( 1, 1, array( $layer ), array() ), $art )->isEmpty(), 'the smallest board is still drawn' );
	}

	public function test_the_margin_is_four_hundredths_of_the_shorter_side_rounded_to_the_nearest_pixel(): void {
		// A board four times as tall as it is wide, on canvases wider than that: the board fills the height inside the
		// margin, so its top is the margin. 87 px: 3.48, so 3; 113 px: 4.52, so 5.
		$layout = new Layout( 1, 4, array( new Layer( SlotKey::DECK, 100, 0, 0, 1, 4 ) ), array() );
		foreach ( array( 87 => 3, 113 => 5 ) as $side => $margin ) {
			$placed = Composition::of( new Canvas( 1000, $side, 'image/webp' ), $layout, array( 100 => self::art( 10, 100, 400 ) ) )->placements[0];
			self::assertSame( $margin, $placed->y, $side . ' px' );
		}
	}

	public function test_a_layer_under_half_a_pixel_is_drawn_a_pixel_wide_and_one_of_a_pixel_stays_one(): void {
		// The board 580 px tall on a 1200 by 630 canvas, 8,000,000 units: a unit is 0.0000725 px.
		$canvas = new Canvas( 1200, 630, 'image/webp' );
		$art    = array( 100 => self::art( 10, 400, 1600 ), 200 => self::art( 20, 1600, 400 ) );
		$thin   = new Layout( 2000000, 8000000, array( new Layer( SlotKey::DECK, 100, 0, 0, 2000000, 8000000 ), new Layer( SlotKey::GRIP, 200, 0, 0, 1, 1 ) ), array() );
		$tiny   = Composition::of( $canvas, $thin, $art )->placements[1];
		self::assertSame( array( 1, 1 ), array( $tiny->width, $tiny->height ), 'under half a pixel each way: one' );
		$pixel = new Layout( 2000000, 8000000, array( new Layer( SlotKey::DECK, 100, 0, 0, 2000000, 8000000 ), new Layer( SlotKey::GRIP, 200, 0, 0, 13793, 13793 ) ), array() );
		$one   = Composition::of( $canvas, $pixel, $art )->placements[1];
		self::assertSame( array( 1, 1 ), array( $one->width, $one->height ), 'a pixel each way stays a pixel' );
	}

	public function test_the_name_is_the_first_32_hex_digits_of_a_hash_of_the_version_the_canvas_and_each_placement(): void {
		$composition = Composition::of( new Canvas( 1200, 630, 'image/webp' ), self::layout(), array( 100 => self::art( 10, 400, 1600 ) ) );
		$placed      = $composition->placements[0];
		$drawn       = Composition::VERSION . "|1200|630|image/webp\n" . implode( '|', array( 10, $placed->x, $placed->y, $placed->width, $placed->height, 'https://example.com/10.png' ) );
		self::assertSame( substr( hash( 'sha256', $drawn ), 0, 32 ) . '.webp', $composition->name(), 'D-196: named by what is drawn, its version and its size' );
	}
}
