<?php
/**
 * The wheels' outer faces, read from their featured photos on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpWheelFaces;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use Imagick;
use WC_Product_Simple;

/**
 * M15, the wheel faces: a wheel part's featured photo, single and face-on on white or nothing, gives its face, in
 * JPEG as the shops' photos are, or PNG; any other photo, or none, gives none. The answer is kept for the file.
 */
final class WheelFacesTest extends IntegrationTestCase {

	/**
	 * A wheel product whose featured photo is drawn: a red tread, a dark core and a bearing's hole, on white or clear.
	 *
	 * @param int    $across The wheel's width in the photo, of 800.
	 * @param int    $down   Its height.
	 * @param string $type   `jpeg` or `png`.
	 * @param bool   $clear  A clear ground, for PNG.
	 * @return int The product's ID.
	 */
	private static function wheel( int $across = 700, int $down = 700, string $type = 'jpeg', bool $clear = false ): int {
		$image = imagecreatetruecolor( 800, 800 );
		self::assertNotFalse( $image );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefill( $image, 0, 0, (int) ( $clear ? imagecolorallocatealpha( $image, 0, 0, 0, 127 ) : imagecolorallocate( $image, 255, 255, 255 ) ) );
		imagealphablending( $image, true );
		imagefilledellipse( $image, 400, 400, $across, $down, (int) imagecolorallocate( $image, 200, 30, 40 ) );
		imagefilledellipse( $image, 400, 400, intdiv( $across * 6, 10 ), intdiv( $down * 6, 10 ), (int) imagecolorallocate( $image, 60, 60, 60 ) );
		imagefilledellipse( $image, 400, 400, intdiv( $across, 5 ), intdiv( $down, 5 ), (int) imagecolorallocate( $image, 255, 255, 255 ) );
		$path = sys_get_temp_dir() . '/cbuild-wheel-' . wp_generate_password( 8, false ) . '.' . $type;
		'png' === $type ? imagepng( $image, $path ) : imagejpeg( $image, $path, 90 );
		$photo = (int) self::factory()->attachment->create_upload_object( $path );
		unlink( $path );
		$product = new WC_Product_Simple();
		$product->set_name( 'Wheel ' . wp_generate_password( 4, false ) );
		$product->set_image_id( $photo );
		return $product->save();
	}

	/**
	 * Asserts a face: its box about the drawn wheel's, its tread red.
	 *
	 * @param array{url: string, x: int, y: int, size: int, tread: string}|null $face The face.
	 */
	private static function assertRedWheel( ?array $face ): void {
		self::assertNotNull( $face );
		// 700 of 800 is 8750 ten-thousandths, from 625; the 64-sample grid reads it within a sample or two.
		self::assertEqualsWithDelta( 625, $face['x'], 320 );
		self::assertEqualsWithDelta( 625, $face['y'], 320 );
		self::assertEqualsWithDelta( 8750, $face['size'], 320 );
		self::assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $face['tread'] );
		[ $red, $green, $blue ] = sscanf( $face['tread'], '#%2x%2x%2x' );
		self::assertGreaterThan( 160, $red, 'the tread is the urethane\'s red' );
		self::assertLessThan( 80, $green );
		self::assertLessThan( 90, $blue );
	}

	public function test_a_jpeg_of_one_wheel_face_on_on_white_gives_its_face_and_the_photos_address(): void {
		$part = self::wheel();
		$face = ( new WpWheelFaces() )->of( $part );

		self::assertRedWheel( $face );
		self::assertSame( wp_get_attachment_image_url( (int) get_post_thumbnail_id( $part ), 'medium_large' ), $face['url'] ?? null );
	}

	public function test_a_png_on_nothing_gives_its_face(): void {
		self::assertRedWheel( ( new WpWheelFaces() )->of( self::wheel( type: 'png', clear: true ) ) );
	}

	public function test_a_wheel_seen_at_an_angle_or_no_photo_gives_none(): void {
		self::assertNull( ( new WpWheelFaces() )->of( self::wheel( 700, 350 ) ) );
		$bare = new WC_Product_Simple();
		$bare->set_name( 'Wheel with no photo' );
		self::assertNull( ( new WpWheelFaces() )->of( $bare->save() ) );
	}

	public function test_the_answer_is_kept_for_the_file_and_read_once(): void {
		$part = self::wheel();
		self::assertRedWheel( ( new WpWheelFaces() )->of( $part ) );
		// With nothing to read it with, the kept answer stands.
		self::assertRedWheel( ( new WpWheelFaces( array() ) )->of( $part ) );

		$unread = self::wheel();
		self::assertNull( ( new WpWheelFaces( array() ) )->of( $unread ), 'a photo nothing can read gives none' );
		self::assertNull( ( new WpWheelFaces() )->of( $unread ), 'and that answer is kept for the file too' );
	}

	public function test_imagick_reads_it_where_gd_does_not(): void {
		if ( ! class_exists( Imagick::class ) || array() === Imagick::queryFormats( 'JPEG' ) ) {
			self::markTestSkipped( 'This site\'s Imagick has no JPEG coder (D-191).' );
		}
		self::assertRedWheel( ( new WpWheelFaces( array( 'imagick' ) ) )->of( self::wheel() ) );
	}
}
