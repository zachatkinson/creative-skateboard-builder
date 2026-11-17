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
use CreativeBranding\SkateboardBuilder\Support\Options;
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
	public const FOLDER = Options::UPLOADS_FOLDER . '/pictures';

	/**
	 * The libraries, in the order tried.
	 */
	public const LIBRARIES = array( 'gd', 'imagick' );

	/**
	 * The quality of the JPEGs and WebPs written.
	 */
	public const QUALITY = 82;

	/**
	 * How long a shown picture goes untouched: its file's time is a day's measure of when it was last used.
	 */
	private const TOUCHED = 86400;

	/**
	 * The layer types a library must read to be counted able to compose at all, for Site Health: PNG and WebP. A
	 * composition with an AVIF layer is drawn by a library that reads AVIF too (D-210).
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
	public function draw( Composition $composition ): ?string {
		if ( $composition->isEmpty() ) {
			return self::EMPTY;
		}
		$folder = self::folder();
		if ( null === $folder || ! wp_mkdir_p( $folder['dir'] ) ) {
			return self::NO_FOLDER;
		}
		$path = $folder['dir'] . '/' . $composition->name();
		if ( file_exists( $path ) ) {
			return null;
		}
		// Each layer's file and its own type, then a library that reads every one of those types.
		$files = array();
		$types = array();
		foreach ( $composition->placements as $placement ) {
			$file = WpLayerFile::of( $placement->attachment, $this->libraries );
			if ( null === $file ) {
				return self::LAYER_UNREADABLE;
			}
			$files[]                = array( $placement, $file['path'], $file['type'] );
			$types[ $file['type'] ] = $file['type'];
		}
		$library = $this->library( $composition->canvas->type, ...array_values( $types ) );
		if ( null === $library ) {
			return self::NO_LIBRARY;
		}
		$temporary = $path . '.' . wp_generate_password( 8, false ) . '.tmp';
		$drawn     = 'gd' === $library ? self::gd( $composition, $files, $temporary ) : self::imagick( $composition, $files, $temporary );
		if ( ! $drawn || ! file_exists( $temporary ) ) {
			wp_delete_file( $temporary );
			return self::DRAW_FAILED;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_rename -- Both paths are in wp_get_upload_dir()'s folder: a move within the plugin's own folder, so a half-written file is never served.
		if ( ! rename( $temporary, $path ) ) {
			wp_delete_file( $temporary );
			return self::MOVE_FAILED;
		}
		return null;
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
	 * Note that a picture was shown, at most once a day.
	 *
	 * @since 0.7.0
	 *
	 * @param string $name A composition's name.
	 * @param int    $now  The moment, a UTC timestamp.
	 */
	public function used( string $name, int $now ): void {
		$folder = self::folder();
		$path   = null === $folder ? '' : $folder['dir'] . '/' . basename( $name );
		$then   = '' !== $path && file_exists( $path ) ? filemtime( $path ) : false;
		if ( false !== $then && $then < $now - self::TOUCHED ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_touch -- The path is in wp_get_upload_dir()'s folder, the plugin's own.
			touch( $path, $now );
		}
	}

	/**
	 * Delete the pictures last used before a moment, then the oldest of the rest until the folder holds no more than
	 * a size.
	 *
	 * @since 0.7.0
	 *
	 * @param int $before    The moment, a UTC timestamp.
	 * @param int $mostBytes The most the folder may hold.
	 */
	public function prune( int $before, int $mostBytes ): int {
		$folder = self::folder();
		if ( null === $folder || ! is_dir( $folder['dir'] ) ) {
			return 0;
		}
		$kept = array();
		$gone = 0;
		foreach ( (array) glob( $folder['dir'] . '/*' ) as $path ) {
			$path = (string) $path;
			$then = is_file( $path ) ? filemtime( $path ) : false;
			if ( false === $then ) {
				continue;
			}
			if ( $then < $before ) {
				wp_delete_file( $path );
				++$gone;
				continue;
			}
			$kept[] = array( $then, $path, (int) filesize( $path ) );
		}
		sort( $kept );
		$total = array_sum( array_column( $kept, 2 ) );
		foreach ( $kept as [ , $path, $bytes ] ) {
			if ( $total <= $mostBytes ) {
				break;
			}
			wp_delete_file( $path );
			$total -= $bytes;
			++$gone;
		}
		return $gone;
	}

	/**
	 * Whether pictures can be written.
	 *
	 * @since 0.7.0
	 */
	public function writable(): bool {
		$folder = self::folder();
		if ( null === $folder ) {
			return false;
		}
		$dir = $folder['dir'];
		while ( ! is_dir( $dir ) && dirname( $dir ) !== $dir ) {
			$dir = dirname( $dir );
		}
		return wp_is_writable( $dir );
	}

	/**
	 * The library a picture of this type is drawn with: the first that reads every layer type given, PNG and WebP
	 * when none is, and writes the picture's.
	 *
	 * @since 0.7.0
	 *
	 * @param string $type      The picture's MIME type.
	 * @param string ...$layers The layers' types.
	 */
	public function library( string $type, string ...$layers ): ?string {
		$layers = array() === $layers ? self::LAYERS : array_values( $layers );
		foreach ( $this->libraries as $library ) {
			if ( 'gd' === $library && self::gdCan( $type, $layers ) ) {
				return 'gd';
			}
			if ( 'imagick' === $library && self::imagickCan( $type, $layers ) ) {
				return 'imagick';
			}
		}
		return null;
	}

	/**
	 * Whether GD reads these layer types and writes this one.
	 *
	 * @param string       $type   The picture's MIME type.
	 * @param list<string> $layers The layers' types.
	 */
	private static function gdCan( string $type, array $layers ): bool {
		if ( ! function_exists( 'imagecreatetruecolor' ) || array() !== array_filter( $layers, static fn( string $layer ): bool => ! WpLayerFile::gdReads( $layer ) ) ) {
			return false;
		}
		return 'image/jpeg' === $type ? function_exists( 'imagejpeg' ) : function_exists( 'imagewebp' );
	}

	/**
	 * Whether Imagick reads these layer types and writes this one.
	 *
	 * @param string       $type   The picture's MIME type.
	 * @param list<string> $layers The layers' types.
	 */
	private static function imagickCan( string $type, array $layers ): bool {
		if ( ! class_exists( Imagick::class ) || array() !== array_filter( $layers, static fn( string $layer ): bool => ! WpLayerFile::imagickReads( $layer ) ) ) {
			return false;
		}
		return array() !== Imagick::queryFormats( 'image/jpeg' === $type ? 'JPEG' : 'WEBP' );
	}

	/**
	 * Draw with GD.
	 *
	 * @param Composition                            $composition The drawing.
	 * @param list<array{Placement, string, string}> $files       Each placement with its layer's file and type.
	 * @param string                                 $path        Where to write.
	 */
	private static function gd( Composition $composition, array $files, string $path ): bool {
		$canvas = imagecreatetruecolor( max( 1, $composition->canvas->width ), max( 1, $composition->canvas->height ) );
		$white  = imagecolorallocate( $canvas, 255, 255, 255 );
		if ( false === $white ) {
			return false;
		}
		imagefill( $canvas, 0, 0, $white );
		imagealphablending( $canvas, true );
		foreach ( $files as [ $placement, $file, $type ] ) {
			$layer = WpLayerFile::gd( $file, $type );
			if ( null === $layer ) {
				return false;
			}
			imagecopyresampled( $canvas, $layer, $placement->x, $placement->y, 0, 0, $placement->width, $placement->height, imagesx( $layer ), imagesy( $layer ) );
		}
		return 'image/jpeg' === $composition->canvas->type ? imagejpeg( $canvas, $path, self::QUALITY ) : imagewebp( $canvas, $path, self::QUALITY );
	}

	/**
	 * Draw with Imagick.
	 *
	 * @param Composition                            $composition The drawing.
	 * @param list<array{Placement, string, string}> $files       Each placement with its layer's file and type.
	 * @param string                                 $path        Where to write.
	 */
	private static function imagick( Composition $composition, array $files, string $path ): bool {
		try {
			$canvas = new Imagick();
			$canvas->newImage( $composition->canvas->width, $composition->canvas->height, new ImagickPixel( 'white' ) );
			foreach ( $files as [ $placement, $file, $type ] ) {
				// The type's own coder, never one Imagick would guess from the file (Cowork's M7 review, M7).
				$layer = WpLayerFile::imagick( $file, $type );
				if ( null === $layer ) {
					return false;
				}
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
