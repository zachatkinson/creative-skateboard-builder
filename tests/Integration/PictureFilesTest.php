<?php
/**
 * The composed pictures' files, drawn with GD and with Imagick.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Canvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerArt;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Plan, milestone 7: "GD by default, and Imagick when the host has it. JPEG for email and WebP everywhere else."
 * "GD and Imagick agree within a tolerance, measured per channel on the same layout. The figure is set from the first
 * comparison and recorded." Spec, Testing: "the composed picture with GD and with Imagick" (D-196).
 */
final class PictureFilesTest extends IntegrationTestCase {

	/**
	 * The most a channel may differ between the two libraries on average over the picture, out of 255, and at any
	 * pixel. Set from the first comparison, a mean of 0.0492 and 40 at most, with room for another build of either library (D-196).
	 */
	private const MEAN = 0.25;

	private const MOST = 48;

	public function tear_down(): void {
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		parent::tear_down();
	}

	/**
	 * A deck of one colour and a wheel of another on it, as the board draws them.
	 *
	 * @param Canvas $canvas The canvas.
	 */
	private static function composition( Canvas $canvas ): Composition {
		$deck   = LayerFiles::attachment( 400, 1600 );
		$wheel  = (int) ( new \WP_UnitTest_Factory_For_Attachment() )->create_upload_object( LayerFiles::draw( 60, 100, true, 'png', 0xd1242f ) );
		$layout = new Layout(
			2000000,
			8000000,
			array(
				new Layer( SlotKey::DECK, 100, 0, 0, 2000000, 8000000 ),
				new Layer( SlotKey::WHEELS, 300, -500000, 3000000, 300000, 500000 ),
			),
			array()
		);
		return Composition::of(
			$canvas,
			$layout,
			array(
				100 => new LayerArt( $deck, (string) wp_get_attachment_url( $deck ), 400, 1600, array() ),
				300 => new LayerArt( $wheel, (string) wp_get_attachment_url( $wheel ), 60, 100, array() ),
			)
		);
	}

	/**
	 * A drawn picture's file, read back with GD.
	 *
	 * @param string $name Its name.
	 */
	private static function read( string $name ): \GdImage {
		$file  = wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . $name;
		$image = str_ends_with( $name, '.jpg' ) ? imagecreatefromjpeg( $file ) : imagecreatefromwebp( $file );
		self::assertInstanceOf( \GdImage::class, $image, $file );
		return $image;
	}

	/**
	 * The colour at a pixel, as red, green and blue.
	 *
	 * @return array{int, int, int}
	 */
	private static function colour( \GdImage $image, int $x, int $y ): array {
		$colour = imagecolorat( $image, $x, $y );
		return array( ( $colour >> 16 ) & 0xff, ( $colour >> 8 ) & 0xff, $colour & 0xff );
	}

	/**
	 * Draw with one library and move its file aside, so the other can draw the same name.
	 */
	private static function drawn( string $library, Composition $composition ): \GdImage {
		self::assertTrue( ( new WpPictureFiles( array( $library ) ) )->draw( $composition ), $library );
		$image = self::read( $composition->name() );
		wp_delete_file( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . $composition->name() );
		return $image;
	}

	public function test_each_library_draws_the_board_on_white_with_each_layer_in_its_place_as_a_webp_and_a_jpeg(): void {
		foreach ( array( 'gd', 'imagick' ) as $library ) {
			foreach ( array( 'image/webp', 'image/jpeg' ) as $type ) {
				$composition = self::composition( new Canvas( 1200, 630, $type ) );
				$files       = new WpPictureFiles( array( $library ) );
				self::assertSame( $library, $files->library( $type ) );
				self::assertNull( $files->url( $composition->name() ), 'not made yet' );
				self::assertTrue( $files->draw( $composition ), $library . ' ' . $type );
				self::assertStringEndsWith( '/creative-skateboard-builder/pictures/' . $composition->name(), (string) $files->url( $composition->name() ) );
				$size = wp_getimagesize( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . $composition->name() );
				self::assertIsArray( $size );
				self::assertSame( array( 1200, 630, $type ), array( $size[0], $size[1], $size['mime'] ) );

				$image = self::read( $composition->name() );
				$where = $library . ' ' . $type;
				self::assertEqualsWithDelta( array( 255, 255, 255 ), self::colour( $image, 10, 10 ), 6, 'the ground is white: ' . $where );
				self::assertEqualsWithDelta( array( 255, 255, 255 ), self::colour( $image, 530, 30 ), 6, 'the deck image\'s transparent edge shows the ground: ' . $where );
				self::assertEqualsWithDelta( array( 0x1f, 0x6f, 0xeb ), self::colour( $image, 600, 315 ), 12, 'the deck in the middle: ' . $where );
				self::assertEqualsWithDelta( array( 0xd1, 0x24, 0x2f ), self::colour( $image, 563, 97 ), 12, 'the wheel on the deck, above it in the stack: ' . $where );
				wp_delete_file( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . $composition->name() );
			}
		}
	}

	public function test_gd_and_imagick_agree_within_the_tolerance_on_the_same_layout(): void {
		$composition = self::composition( new Canvas( 1200, 630, 'image/webp' ) );
		$gd          = self::drawn( 'gd', $composition );
		$imagick     = self::drawn( 'imagick', $composition );
		$sum         = 0;
		$most        = 0;
		for ( $y = 0; $y < 630; $y++ ) {
			for ( $x = 0; $x < 1200; $x++ ) {
				$one = self::colour( $gd, $x, $y );
				$two = self::colour( $imagick, $x, $y );
				for ( $channel = 0; $channel < 3; $channel++ ) {
					$apart = abs( $one[ $channel ] - $two[ $channel ] );
					$sum  += $apart;
					$most  = max( $most, $apart );
				}
			}
		}
		$mean = $sum / ( 1200 * 630 * 3 );
		fwrite( STDERR, sprintf( "GD and Imagick: mean %.4f, most %d\n", $mean, $most ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- The measurement, for the record.
		self::assertLessThanOrEqual( self::MEAN, $mean );
		self::assertLessThanOrEqual( self::MOST, $most, 'only along the edges, where each resamples its own way' );
	}

	public function test_what_is_made_is_kept_and_nothing_is_drawn_for_an_empty_composition_a_missing_layer_or_no_library(): void {
		$composition = self::composition( new Canvas( 600, 600, 'image/webp' ) );
		$files       = new WpPictureFiles();
		self::assertTrue( $files->draw( $composition ) );
		$path = wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . $composition->name();
		file_put_contents( $path, 'kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A marker another draw would overwrite.
		self::assertTrue( $files->draw( $composition ) );
		self::assertSame( 'kept', file_get_contents( $path ), 'drawn once' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.

		self::assertFalse( $files->draw( Composition::of( new Canvas( 600, 600, 'image/webp' ), new Layout( 0, 0, array(), array() ), array() ) ) );
		$none = new WpPictureFiles( array() );
		self::assertNull( $none->library( 'image/webp' ) );
		self::assertFalse( $none->draw( self::composition( new Canvas( 300, 300, 'image/webp' ) ) ), 'a host with neither library makes no picture' );

		$gone = self::composition( new Canvas( 400, 400, 'image/jpeg' ) );
		wp_delete_file( (string) get_attached_file( $gone->placements[1]->attachment ) );
		self::assertFalse( $files->draw( $gone ), 'a picture missing a part is not drawn' );
		self::assertNull( $files->url( $gone->name() ) );
		self::assertSame( array( $path ), (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ), 'no temporary file is left' );
	}
}
