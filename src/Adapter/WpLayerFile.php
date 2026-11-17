<?php
/**
 * A layer image's file, and which of the host's image libraries read it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerImage;
use GdImage;
use Imagick;
use ImagickException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The checks on upload, the renditions and the composed pictures read a layer image the same way (Cowork's M7 review,
 * F1; D-210):
 * - its type is the file's own, read from its first bytes, not the attachment's: a shop whose
 *   `image_editor_output_format` turns PNG into AVIF keeps `image/png` on the attachment and an AVIF file under it;
 * - the file is the attachment's, or the original WordPress kept beside it when the host reads that one's type and not
 *   the attachment's;
 * - GD reads PNG, WebP and, where PHP was built with it, AVIF; Imagick reads a type it has a coder for, named
 *   explicitly, so a file is never decoded as a type its name or first bytes merely suggest.
 *
 * @since 0.7.0
 */
final class WpLayerFile {

	/**
	 * The libraries, in the order tried.
	 */
	public const LIBRARIES = array( 'gd', 'imagick' );

	/**
	 * The coder Imagick names each type by.
	 */
	private const CODERS = array(
		'image/png'  => 'PNG',
		'image/webp' => 'WEBP',
		'image/avif' => 'AVIF',
	);

	/**
	 * An attachment's layer file and its type, or null when it has no readable image file of a layer's type.
	 *
	 * @since 0.7.0
	 *
	 * @param int          $attachment The attachment's ID.
	 * @param list<string> $libraries  The libraries that may read it.
	 * @return array{path: string, type: string}|null
	 */
	public static function of( int $attachment, array $libraries = self::LIBRARIES ): ?array {
		$attached = self::found( get_attached_file( $attachment ) );
		if ( null !== $attached && self::reads( $attached['type'], $libraries ) ) {
			return $attached;
		}
		$original = function_exists( 'wp_get_original_image_path' ) ? self::found( wp_get_original_image_path( $attachment ) ) : null;
		if ( null !== $original && self::reads( $original['type'], $libraries ) ) {
			return $original;
		}
		return $attached;
	}

	/**
	 * Whether one of these libraries reads this type.
	 *
	 * @since 0.7.0
	 *
	 * @param string       $type      A media type.
	 * @param list<string> $libraries The libraries.
	 */
	public static function reads( string $type, array $libraries = self::LIBRARIES ): bool {
		foreach ( $libraries as $library ) {
			if ( 'gd' === $library ? self::gdReads( $type ) : self::imagickReads( $type ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether GD reads this type.
	 *
	 * @since 0.7.0
	 *
	 * @param string $type A media type.
	 */
	public static function gdReads( string $type ): bool {
		return match ( $type ) {
			'image/png'  => function_exists( 'imagecreatefrompng' ),
			'image/webp' => function_exists( 'imagecreatefromwebp' ),
			'image/avif' => function_exists( 'imagecreatefromavif' ) && function_exists( 'imagetypes' ) && 0 !== ( imagetypes() & IMG_AVIF ),
			default      => false,
		};
	}

	/**
	 * Whether Imagick reads this type: the extension can load with no coder for a format, as in wp-env's cli image
	 * (D-191).
	 *
	 * @since 0.7.0
	 *
	 * @param string $type A media type.
	 */
	public static function imagickReads( string $type ): bool {
		return isset( self::CODERS[ $type ] ) && class_exists( Imagick::class ) && array() !== Imagick::queryFormats( self::CODERS[ $type ] );
	}

	/**
	 * A file read with GD, or null. A damaged file makes GD warn as well as fail; the failure is the answer, so the
	 * warning is not shown.
	 *
	 * @since 0.7.0
	 *
	 * @param string $path The file.
	 * @param string $type Its type.
	 */
	public static function gd( string $path, string $type ): ?GdImage {
		if ( ! self::gdReads( $type ) ) {
			return null;
		}
		set_error_handler( static fn(): bool => true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Held for one decode, and put back below.
		try {
			$image = match ( $type ) {
				'image/png'  => imagecreatefrompng( $path ),
				'image/webp' => imagecreatefromwebp( $path ),
				default      => imagecreatefromavif( $path ),
			};
		} finally {
			restore_error_handler();
		}
		return $image instanceof GdImage ? $image : null;
	}

	/**
	 * A file read with Imagick by its type's own coder, or null.
	 *
	 * @since 0.7.0
	 *
	 * @param string $path The file.
	 * @param string $type Its type.
	 */
	public static function imagick( string $path, string $type ): ?Imagick {
		if ( ! self::imagickReads( $type ) ) {
			return null;
		}
		try {
			$image = new Imagick();
			$image->readImage( self::CODERS[ $type ] . ':' . $path );
			return $image;
		} catch ( ImagickException ) {
			return null;
		}
	}

	/**
	 * A path and the type its first bytes say, when it is a readable file of a layer's type.
	 *
	 * @param mixed $path A path, or what WordPress gave in its place.
	 * @return array{path: string, type: string}|null
	 */
	private static function found( mixed $path ): ?array {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return null;
		}
		$type = wp_get_image_mime( $path );
		return is_string( $type ) && in_array( $type, LayerImage::TYPES, true )
			? array(
				'path' => $path,
				'type' => $type,
			)
			: null;
	}
}
