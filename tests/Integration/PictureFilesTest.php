<?php
/**
 * The composed pictures' files, drawn with GD and with Imagick.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Port\PictureFiles;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Canvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerArt;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Adapter\WpLayerFile;
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
	 * @param string $type   The deck image's type: `png`, `webp` or `avif`.
	 */
	private static function composition( Canvas $canvas, string $type = 'png' ): Composition {
		$deck   = LayerFiles::attachment( 400, 1600, true, $type );
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
		self::assertNull( ( new WpPictureFiles( array( $library ) ) )->draw( $composition ), $library );
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
				self::assertNull( $files->draw( $composition ), $library . ' ' . $type );
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

	public function test_an_avif_layer_is_drawn_by_a_library_that_reads_avif_and_a_png_an_output_format_made_avif_by_either(): void {
		// Cowork's M7 review, F1 (D-210): a layer image in AVIF, and the demo's case, a PNG its host's mapping turned
		// into an AVIF under an attachment WordPress still calls a PNG.
		$avif = self::composition( new Canvas( 1200, 630, 'image/webp' ), 'avif' );
		self::assertSame( 'image/avif', get_post_mime_type( $avif->placements[0]->attachment ) );
		$image = self::drawn( 'gd', $avif );
		self::assertEqualsWithDelta( array( 0x1f, 0x6f, 0xeb ), self::colour( $image, 600, 315 ), 12, 'GD reads the AVIF deck' );
		self::assertEqualsWithDelta( array( 255, 255, 255 ), self::colour( $image, 530, 30 ), 6, 'and its transparent edge' );
		self::assertSame( WpLayerFile::imagickReads( 'image/avif' ) ? null : PictureFiles::NO_LIBRARY, ( new WpPictureFiles( array( 'imagick' ) ) )->draw( $avif ), 'Imagick draws it only with an AVIF coder' );

		add_filter( 'image_editor_output_format', static fn( array $formats ): array => array( 'image/png' => 'image/avif' ) + $formats );
		try {
			$mapped = self::composition( new Canvas( 1200, 630, 'image/jpeg' ) );
		} finally {
			remove_all_filters( 'image_editor_output_format' );
		}
		$deck = $mapped->placements[0]->attachment;
		self::assertSame( array( 'image/png', 'image/avif' ), array( get_post_mime_type( $deck ), wp_get_image_mime( (string) get_attached_file( $deck ) ) ), 'the attachment says PNG, its file is AVIF' );
		foreach ( array( 'gd', 'imagick' ) as $library ) {
			$image = self::drawn( $library, $mapped );
			self::assertEqualsWithDelta( array( 0x1f, 0x6f, 0xeb ), self::colour( $image, 600, 315 ), 12, $library . ': the AVIF, or the PNG WordPress kept beside it' );
			self::assertEqualsWithDelta( array( 0xd1, 0x24, 0x2f ), self::colour( $image, 563, 97 ), 12, $library . ': the wheel' );
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
		self::assertNull( $files->draw( $composition ) );
		$path = wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . $composition->name();
		file_put_contents( $path, 'kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A marker another draw would overwrite.
		self::assertNull( $files->draw( $composition ) );
		self::assertSame( 'kept', file_get_contents( $path ), 'drawn once' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.

		self::assertSame( PictureFiles::EMPTY, $files->draw( Composition::of( new Canvas( 600, 600, 'image/webp' ), new Layout( 0, 0, array(), array() ), array() ) ) );
		$none = new WpPictureFiles( array() );
		self::assertNull( $none->library( 'image/webp' ) );
		self::assertSame( PictureFiles::NO_LIBRARY, $none->draw( self::composition( new Canvas( 300, 300, 'image/webp' ) ) ), 'a host with neither library makes no picture' );

		$gone = self::composition( new Canvas( 400, 400, 'image/jpeg' ) );
		wp_delete_file( (string) get_attached_file( $gone->placements[1]->attachment ) );
		self::assertSame( PictureFiles::LAYER_UNREADABLE, $files->draw( $gone ), 'a picture missing a part is not drawn, and says why' );
		self::assertNull( $files->url( $gone->name() ) );
		self::assertSame( array( $path ), (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ), 'no temporary file is left' );
	}

	/**
	 * A file in the pictures' folder, last changed at a moment.
	 *
	 * @param string $name  Its name.
	 * @param int    $bytes Its size.
	 * @param int    $then  The moment.
	 */
	private static function file( string $name, int $bytes, int $then ): string {
		$dir = wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER;
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/' . $name, str_repeat( 'x', $bytes ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A fixture of a known size.
		touch( $dir . '/' . $name, $then );
		return $dir . '/' . $name;
	}

	// Spec, Options and jobs: "`cbuild_prune_pictures` (daily: composed images unused for 30 days, and the folder kept
	// under its size cap)". Plan: "The folder's cap is pruned oldest first" (D-198).
	public function test_pruning_deletes_what_was_not_shown_for_thirty_days_then_the_oldest_down_to_the_cap(): void {
		$now   = 1_800_000_000;
		$day   = 86400;
		$month = $now - 30 * $day;
		self::file( 'a.webp', 100, $month - 1 );
		self::file( 'b.webp.k3j9x0q1.tmp', 100, $month - $day );
		self::file( 'c.webp', 100, $now - 10 * $day );
		self::file( 'd.jpg', 100, $now - 5 * $day );
		self::file( 'e.webp', 100, $now - $day );
		self::file( 'f.webp', 100, $month );
		clearstatcache();
		$files = new WpPictureFiles();
		self::assertSame( 4, $files->prune( $month, 250 ), 'a and the half-written file, unused for 30 days; then f and c, the oldest, to get under 250 bytes' );
		self::assertSame( array( 'd.jpg', 'e.webp' ), array_map( 'basename', array_map( 'strval', (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) ) ) );
		self::assertSame( 0, $files->prune( $month, 250 ), 'nothing more to do' );
	}

	public function test_a_picture_shown_is_touched_at_most_once_a_day_and_the_folder_is_writable(): void {
		$now   = 1_800_000_000;
		$old   = self::file( 'old.webp', 10, $now - 2 * 86400 );
		$fresh = self::file( 'fresh.webp', 10, $now - 3600 );
		$files = new WpPictureFiles();
		$files->used( 'old.webp', $now );
		$files->used( 'fresh.webp', $now );
		$files->used( '../../../wp-config.php', $now );
		$files->used( 'never-made.webp', $now );
		clearstatcache();
		self::assertSame( $now, filemtime( $old ) );
		self::assertSame( $now - 3600, filemtime( $fresh ), 'shown within the day: not written again' );
		self::assertFileDoesNotExist( dirname( $old ) . '/never-made.webp' );
		self::assertTrue( $files->writable() );
	}

	// Cowork's review of 1.0.0, F19 (#105; D-309): a part another plugin flags is drawn blurred, as Mature Content blurs
	// it on the page (22 px), so its artwork can't be made out and its shape stays.
	public function test_a_blurred_layer_is_drawn_so_its_artwork_cannot_be_made_out_and_its_shape_stays(): void {
		// A deck whose artwork is bands of black and white, 40 pixels each, on a transparent edge.
		$image = imagecreatetruecolor( 400, 1600 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefill( $image, 0, 0, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
		for ( $band = 0; $band < 36; $band++ ) {
			$grey = 0 === $band % 2 ? 0 : 255;
			imagefilledrectangle( $image, 40, 80 + 40 * $band, 359, 119 + 40 * $band, imagecolorallocate( $image, $grey, $grey, $grey ) );
		}
		$path = sys_get_temp_dir() . '/cbuild-bands-' . wp_generate_password( 8, false ) . '.png';
		imagepng( $image, $path );
		$deck = (int) ( new \WP_UnitTest_Factory_For_Attachment() )->create_upload_object( $path );
		unlink( $path );
		$layout = new Layout( 2000000, 8000000, array( new Layer( SlotKey::DECK, 100, 0, 0, 2000000, 8000000 ) ), array() );
		$art    = array( 100 => new LayerArt( $deck, (string) wp_get_attachment_url( $deck ), 400, 1600, array() ) );
		// Down the deck's middle, where the bands are: how far apart the darkest and the lightest are.
		$spread = static function ( \GdImage $drawn ): int {
			$greys = array();
			for ( $y = 120; $y <= 510; $y += 2 ) {
				$greys[] = (int) round( array_sum( self::colour( $drawn, 600, $y ) ) / 3 );
			}
			return max( $greys ) - min( $greys );
		};

		foreach ( array( 'gd', 'imagick' ) as $library ) {
			foreach ( array( 'image/webp', 'image/jpeg' ) as $type ) {
				$canvas = new Canvas( 1200, 630, $type );
				$where  = $library . ' ' . $type;
				$sharp  = self::drawn( $library, Composition::of( $canvas, $layout, $art ) );
				self::assertGreaterThan( 200, $spread( $sharp ), 'drawn as it is, the bands are there: ' . $where );

				$blurred = self::drawn( $library, Composition::of( $canvas, $layout, $art, array( 100 => true ) ) );
				self::assertLessThan( 60, $spread( $blurred ), 'blurred, they are not: ' . $where );
				$middle = (int) round( array_sum( self::colour( $blurred, 600, 315 ) ) / 3 );
				self::assertEqualsWithDelta( 128, $middle, 50, 'the deck is still there, its bands run together: ' . $where );
				self::assertEqualsWithDelta( array( 255, 255, 255 ), self::colour( $blurred, 10, 10 ), 6, 'the ground is white: ' . $where );
				self::assertEqualsWithDelta( array( 255, 255, 255 ), self::colour( $blurred, 450, 315 ), 6, 'and the deck keeps its shape, nothing drawn beside it: ' . $where );
			}
		}
	}
}
