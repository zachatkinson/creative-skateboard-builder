<?php
/**
 * The composed pictures' files, drawn with GD or Imagick.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Placement;
use CreativeBranding\SkateboardBuilder\Port\PictureFiles;
use GdImage;
use Imagick;
use ImagickException;
use ImagickPixel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draws a composition on a white ground, each layer image from its own file in the Media Library, resampled into its
 * rectangle with its transparency kept, and saves it as a JPEG or a WebP at quality 82 in
 * `uploads/creative-skateboard-builder/pictures/` (D-196). WordPress's image editors resize and convert, but cannot
 * stack one image on another, so the libraries are called here. GD is tried first, as WordPress's default editor
 * for the types the plugin writes; Imagick where GD cannot read a layer's type or write the picture's. The file is
 * written under a temporary name and moved into place, so a half-written picture is never served. A layer whose file
 * cannot be read leaves the picture undrawn: a picture missing a part would show a build the shopper did not choose.
 *
 * @since 0.7.0
 */
final class WpPictureFiles implements PictureFiles {

	/**
	 * The folder in the uploads folder.
	 */
	public const FOLDER = 'creative-skateboard-builder/pictures';

	/**
	 * The libraries, in the order tried.
	 */
	public const LIBRARIES = array( 'gd', 'imagick' );

	/**
	 * The quality of the JPEGs and WebPs written.
	 */
	public const QUALITY = 82;

	/**
	 * The types a layer image may be, which a library must read.
	 */
	private const LAYERS = array( 'image/png', 'image/webp' );

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param list<string> $libraries The libraries to try, from LIBRARIES; tests name one, or none.
	 */
	public function __construct( private array $libraries = self::LIBRARIES ) {}

	/**
	 * Draw a composition.
	 *
	 * @since 0.7.0
	 *
	 * @param Composition $composition The drawing.
	 */
	public function draw( Composition $composition ): bool {
		$folder  = self::folder();
		$library = $this->library( $composition->canvas->type );
		if ( $composition->isEmpty() || null === $folder || null === $library || ! wp_mkdir_p( $folder['dir'] ) ) {
			return false;
		}
		$path = $folder['dir'] . '/' . $composition->name();
		if ( file_exists( $path ) ) {
			return true;
		}
		$files = array();
		foreach ( $composition->placements as $placement ) {
			$file = get_attached_file( $placement->attachment );
			if ( ! is_string( $file ) || ! is_readable( $file ) ) {
				return false;
			}
			$files[] = array( $placement, $file );
		}
		$temporary = $path . '.' . wp_generate_password( 8, false ) . '.tmp';
		$drawn     = 'gd' === $library ? self::gd( $composition, $files, $temporary ) : self::imagick( $composition, $files, $temporary );
		if ( ! $drawn || ! file_exists( $temporary ) ) {
			wp_delete_file( $temporary );
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_rename -- Both paths are in wp_get_upload_dir()'s folder: a move within the plugin's own folder, so a half-written file is never served.
		if ( ! rename( $temporary, $path ) ) {
			wp_delete_file( $temporary );
			return false;
		}
		return true;
	}

	/**
	 * The file's address.
	 *
	 * @since 0.7.0
	 *
	 * @param string $name A composition's name.
	 */
	public function url( string $name ): ?string {
		$folder = self::folder();
		return null !== $folder && file_exists( $folder['dir'] . '/' . $name ) ? $folder['url'] . '/' . $name : null;
	}

	/**
	 * The library a picture of this type is drawn with.
	 *
	 * @since 0.7.0
	 *
	 * @param string $type The picture's MIME type.
	 */
	public function library( string $type ): ?string {
		foreach ( $this->libraries as $library ) {
			if ( 'gd' === $library && self::gdCan( $type ) ) {
				return 'gd';
			}
			if ( 'imagick' === $library && self::imagickCan( $type ) ) {
				return 'imagick';
			}
		}
		return null;
	}

	/**
	 * Whether GD reads both layer types and writes this one.
	 *
	 * @param string $type The picture's MIME type.
	 */
	private static function gdCan( string $type ): bool {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagecreatefrompng' ) || ! function_exists( 'imagecreatefromwebp' ) ) {
			return false;
		}
		return 'image/jpeg' === $type ? function_exists( 'imagejpeg' ) : function_exists( 'imagewebp' );
	}

	/**
	 * Whether Imagick reads both layer types and writes this one.
	 *
	 * @param string $type The picture's MIME type.
	 */
	private static function imagickCan( string $type ): bool {
		if ( ! class_exists( Imagick::class ) ) {
			return false;
		}
		foreach ( array( 'PNG', 'WEBP', 'image/jpeg' === $type ? 'JPEG' : 'WEBP' ) as $format ) {
			if ( array() === Imagick::queryFormats( $format ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Draw with GD.
	 *
	 * @param Composition                    $composition The drawing.
	 * @param list<array{Placement, string}> $files       Each placement with its layer's file.
	 * @param string                         $path        Where to write.
	 */
	private static function gd( Composition $composition, array $files, string $path ): bool {
		$canvas = imagecreatetruecolor( max( 1, $composition->canvas->width ), max( 1, $composition->canvas->height ) );
		$white  = imagecolorallocate( $canvas, 255, 255, 255 );
		if ( false === $white ) {
			return false;
		}
		imagefill( $canvas, 0, 0, $white );
		imagealphablending( $canvas, true );
		foreach ( $files as [ $placement, $file ] ) {
			$layer = self::gdLayer( $file );
			if ( null === $layer ) {
				return false;
			}
			imagecopyresampled( $canvas, $layer, $placement->x, $placement->y, 0, 0, $placement->width, $placement->height, imagesx( $layer ), imagesy( $layer ) );
		}
		return 'image/jpeg' === $composition->canvas->type ? imagejpeg( $canvas, $path, self::QUALITY ) : imagewebp( $canvas, $path, self::QUALITY );
	}

	/**
	 * A layer's file read with GD, or null. Its warnings on a file cut short are caught: the picture is not drawn.
	 *
	 * @param string $file The file.
	 */
	private static function gdLayer( string $file ): ?GdImage {
		$type = wp_check_filetype( $file )['type'];
		if ( ! in_array( $type, self::LAYERS, true ) ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- GD warns on a damaged file; the picture is not drawn instead.
		set_error_handler( static fn(): bool => true );
		try {
			$image = 'image/png' === $type ? imagecreatefrompng( $file ) : imagecreatefromwebp( $file );
		} finally {
			restore_error_handler();
		}
		return $image instanceof GdImage ? $image : null;
	}

	/**
	 * Draw with Imagick.
	 *
	 * @param Composition                    $composition The drawing.
	 * @param list<array{Placement, string}> $files       Each placement with its layer's file.
	 * @param string                         $path        Where to write.
	 */
	private static function imagick( Composition $composition, array $files, string $path ): bool {
		try {
			$canvas = new Imagick();
			$canvas->newImage( $composition->canvas->width, $composition->canvas->height, new ImagickPixel( 'white' ) );
			foreach ( $files as [ $placement, $file ] ) {
				$layer = new Imagick( $file );
				$layer->resizeImage( $placement->width, $placement->height, Imagick::FILTER_LANCZOS, 1 );
				$canvas->compositeImage( $layer, Imagick::COMPOSITE_OVER, $placement->x, $placement->y );
			}
			$canvas->setImageFormat( 'image/jpeg' === $composition->canvas->type ? 'jpeg' : 'webp' );
			$canvas->setImageCompressionQuality( self::QUALITY );
			return $canvas->writeImage( $path );
		} catch ( ImagickException ) {
			return false;
		}
	}

	/**
	 * The folder, on disk and as a URL, or null when the uploads folder is not known.
	 *
	 * @return array{dir: string, url: string}|null
	 */
	private static function folder(): ?array {
		$uploads = wp_get_upload_dir();
		if ( ! is_string( $uploads['basedir'] ?? null ) || '' === $uploads['basedir'] || ! is_string( $uploads['baseurl'] ?? null ) ) {
			return null;
		}
		return array(
			'dir' => $uploads['basedir'] . '/' . self::FOLDER,
			'url' => $uploads['baseurl'] . '/' . self::FOLDER,
		);
	}
}
