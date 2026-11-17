<?php
/**
 * Renditions: the sizes a layer image is made at for the page.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, The builder, The picture, and the plan's renditions: "The plugin makes its AVIF and WebP renditions at the
 * sizes the picture shows, without changing how WordPress handles the shop's other uploads." The sizes are the part's
 * box at half a pixel, one and two to the millimetre, the image fitted into each and never enlarged (D-194).
 */
final class RenditionsTest extends TestCase {

	/**
	 * @return iterable<string, array{array{int, int}, int, int, list<array{int, int}>}>
	 */
	public static function sized(): iterable {
		$deck = array( 2095500, 8128000 );
		yield 'an 8.25 in deck shot at two pixels to the millimetre' => array( $deck, 420, 1626, array( array( 105, 407 ), array( 210, 813 ), array( 420, 1626 ) ) );
		yield 'an image too small for the largest is its own size, once' => array( $deck, 200, 800, array( array( 102, 407 ), array( 200, 800 ) ) );
		yield 'a wheel' => array( array( 324000, 540000 ), 130, 216, array( array( 16, 27 ), array( 33, 54 ), array( 65, 108 ) ) );
		yield 'the grip\'s strip' => array( array( 254000, 8128000 ), 51, 1626, array( array( 13, 407 ), array( 26, 813 ), array( 51, 1626 ) ) );
		yield 'a box of whole pixels' => array( array( 200000, 200000 ), 100, 100, array( array( 10, 10 ), array( 20, 20 ), array( 40, 40 ) ) );
		yield 'a sliver keeps a pixel across' => array( $deck, 1, 2000, array( array( 1, 407 ), array( 1, 813 ), array( 1, 1626 ) ) );
		yield 'a sliver keeps a pixel along' => array( $deck, 2000, 1, array( array( 105, 1 ), array( 210, 1 ), array( 420, 1 ) ) );
		yield 'one pixel' => array( $deck, 1, 1, array( array( 1, 1 ) ) );
		yield 'an image of no pixels across' => array( $deck, 0, 1626, array() );
		yield 'an image of no pixels along' => array( $deck, 420, 0, array() );
		yield 'a box of no width' => array( array( 0, 8128000 ), 420, 1626, array() );
		yield 'a box of no length' => array( array( 2095500, 0 ), 420, 1626, array() );
	}

	/**
	 * @param array{int, int}       $box    The part's box.
	 * @param int                   $width  The image's pixels across.
	 * @param int                   $height Its pixels along.
	 * @param list<array{int, int}> $sizes  What is made.
	 */
	#[DataProvider( 'sized' )]
	public function test_a_layer_is_made_at_half_one_and_two_pixels_to_the_millimetre_fitted_and_never_enlarged( array $box, int $width, int $height, array $sizes ): void {
		self::assertSame( $sizes, Renditions::of( $box, $width, $height ) );
	}

	public function test_a_part_is_made_at_the_size_its_slot_draws_it_and_not_where_the_picture_does_not_draw_it(): void {
		$deck = Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32' ), 1 )->specs;
		self::assertSame( array( array( 105, 407 ), array( 210, 813 ), array( 420, 1626 ) ), Renditions::drawn( SlotKey::DECK, $deck, 420, 1626 ) );
		self::assertSame( array(), Renditions::drawn( SlotKey::TRUCKS, $deck, 420, 1626 ), 'trucks with no axle are not drawn' );
		self::assertSame( array(), Renditions::drawn( SlotKey::RISERS, $deck, 420, 1626 ) );
	}

	public function test_each_size_is_made_as_avif_then_webp(): void {
		self::assertSame(
			array(
				'image/avif' => 'avif',
				'image/webp' => 'webp',
			),
			Renditions::TYPES
		);
	}

	public function test_a_box_of_the_smallest_length_still_has_sizes_and_a_length_just_over_two_millimetres_rounds_up(): void {
		self::assertNotSame( array(), Renditions::of( array( 1, 10000 ), 10, 10 ), 'a box one unit across' );
		self::assertNotSame( array(), Renditions::of( array( 10000, 1 ), 10, 10 ), 'a box one unit along' );
		// 2.0001 mm at half a pixel to the millimetre is 1.00005 px: two, rounded up.
		self::assertSame( array( 2, 2 ), Renditions::of( array( 20001, 20001 ), 100, 100 )[0] );
	}

	public function test_an_image_of_the_boxs_own_shape_fills_it_whichever_side_decides(): void {
		self::assertSame( array( array( 50, 100 ), array( 100, 200 ), array( 200, 400 ) ), Renditions::of( array( 1000000, 2000000 ), 400, 800 ) );
	}
}
