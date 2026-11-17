<?php
/**
 * Layer images for the tests, drawn by GD.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

/**
 * A layer fixture is a block of one known colour on a transparent background, or on a white one, so a test can say
 * what the picture should show where. The files go in the system's temporary folder and an attachment copies them.
 */
final class LayerFiles {

	/**
	 * Draw an image and return its path.
	 *
	 * @param int    $width  Pixels across.
	 * @param int    $height Pixels along.
	 * @param bool   $clear  A transparent background (true) or a white one.
	 * @param string $type   `png`, `webp`, `avif` or `jpeg`.
	 * @param int    $colour The block's colour, as 0xRRGGBB.
	 */
	public static function draw( int $width, int $height, bool $clear = true, string $type = 'png', int $colour = 0x1f6feb ): string {
		$image = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefill( $image, 0, 0, $clear ? imagecolorallocatealpha( $image, 0, 0, 0, 127 ) : imagecolorallocate( $image, 255, 255, 255 ) );
		$inset = max( 1, intdiv( min( $width, $height ), 10 ) );
		imagefilledrectangle( $image, $inset, $inset, $width - 1 - $inset, $height - 1 - $inset, imagecolorallocate( $image, ( $colour >> 16 ) & 0xff, ( $colour >> 8 ) & 0xff, $colour & 0xff ) );
		$path = sys_get_temp_dir() . '/cbuild-layer-' . wp_generate_password( 8, false ) . '.' . $type;
		match ( $type ) {
			'webp'  => imagewebp( $image, $path, 101 ),
			'avif'  => imageavif( $image, $path, 100 ),
			'jpeg'  => imagejpeg( $image, $path ),
			default => imagepng( $image, $path ),
		};
		return $path;
	}

	/**
	 * Draw an image and put it in the Media Library.
	 *
	 * @param int    $width  Pixels across.
	 * @param int    $height Pixels along.
	 * @param bool   $clear  A transparent background.
	 * @param string $type   `png`, `webp`, `avif` or `jpeg`.
	 * @return int The attachment's ID.
	 */
	public static function attachment( int $width, int $height, bool $clear = true, string $type = 'png' ): int {
		$path = self::draw( $width, $height, $clear, $type );
		$id   = ( new \WP_UnitTest_Factory_For_Attachment() )->create_upload_object( $path );
		unlink( $path );
		return (int) $id;
	}
}
