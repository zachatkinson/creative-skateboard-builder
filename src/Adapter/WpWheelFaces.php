<?php
/**
 * The wheels' outer faces, read from their featured photos with GD or Imagick.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Scene\WheelFace;
use CreativeBranding\SkateboardBuilder\Port\WheelFaces;
use CreativeBranding\SkateboardBuilder\Support\Options;
use GdImage;
use Imagick;
use ImagickException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A wheel's photo is its product's featured image. It is read at WordPress's `medium` size where that was made, else
 * whole, into a grid of 64 by 64 samples: by GD, from the file's own bytes, or by Imagick where GD cannot read it. The
 * domain decides from the grid (WheelFace). The answer, a face or a refusal, is kept in a transient named by the file
 * and its time, so a new photo is read again and the old answer is let lapse.
 *
 * The face is drawn from the photo at `medium_large`, 768 pixels across, where it was made: the box is in
 * ten-thousandths of the photo, so it holds at every size WordPress makes of it.
 *
 * @since 1.5.0
 */
final class WpWheelFaces implements WheelFaces {

	/**
	 * The grid's samples a side.
	 */
	public const SIDE = 64;

	/**
	 * How long an answer is kept: a month, past which it is read again.
	 */
	private const KEEP = 30 * 86400;

	/**
	 * The libraries, in the order tried.
	 *
	 * @param list<string> $libraries `gd`, `imagick`: the tests pass one to read with it alone.
	 */
	public function __construct( private array $libraries = array( 'gd', 'imagick' ) ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.5.0
	 *
	 * @param int $part The wheel part's product ID.
	 * @return array{url: string, x: int, y: int, size: int, tread: string}|null
	 */
	public function of( int $part ): ?array {
		$attachment = (int) get_post_thumbnail_id( $part );
		$file       = $attachment > 0 ? self::path( $attachment ) : null;
		$url        = $attachment > 0 ? wp_get_attachment_image_url( $attachment, 'medium_large' ) : false;
		if ( null === $file || false === $url ) {
			return null;
		}
		$key  = Options::TRANSIENT_PREFIX . 'wheel_face_' . md5( $attachment . '|' . $file . '|' . (string) filemtime( $file ) );
		$kept = get_transient( $key );
		if ( ! is_array( $kept ) ) {
			$kept = $this->read( $attachment, $file );
			set_transient( $key, $kept, self::KEEP );
		}
		if ( ! isset( $kept['x'], $kept['y'], $kept['size'], $kept['tread'] ) || ! is_int( $kept['x'] ) || ! is_int( $kept['y'] ) || ! is_int( $kept['size'] ) || ! is_string( $kept['tread'] ) ) {
			return null;
		}
		return array(
			'url'   => $url,
			'x'     => $kept['x'],
			'y'     => $kept['y'],
			'size'  => $kept['size'],
			'tread' => $kept['tread'],
		);
	}

	/**
	 * The photo's answer: its face, or why there is none.
	 *
	 * @param int    $attachment The photo's attachment ID.
	 * @param string $file       The file read.
	 * @return array{x: int, y: int, size: int, tread: string}|array{refused: string}
	 */
	private function read( int $attachment, string $file ): array {
		$meta   = wp_get_attachment_metadata( $attachment );
		$width  = is_array( $meta ) && isset( $meta['width'] ) && is_int( $meta['width'] ) ? $meta['width'] : 0;
		$height = is_array( $meta ) && isset( $meta['height'] ) && is_int( $meta['height'] ) ? $meta['height'] : 0;
		$grid   = null;
		foreach ( $this->libraries as $library ) {
			$grid ??= 'gd' === $library ? self::gd( $file ) : self::imagick( $file );
		}
		if ( null === $grid ) {
			return array( 'refused' => 'unreadable' );
		}
		$face = WheelFace::read( $width, $height, $grid );
		if ( null !== $face->refused || null === $face->tread ) {
			return array( 'refused' => $face->refused ?? WheelFace::NOT_ONE_WHEEL );
		}
		return array(
			'x'     => $face->x,
			'y'     => $face->y,
			'size'  => $face->size,
			'tread' => $face->tread,
		);
	}

	/**
	 * The file to read: the photo's `medium` size where WordPress made one, else the photo.
	 *
	 * @param int $attachment The attachment's ID.
	 */
	private static function path( int $attachment ): ?string {
		$whole = get_attached_file( $attachment );
		if ( ! is_string( $whole ) || '' === $whole ) {
			return null;
		}
		$meta  = wp_get_attachment_metadata( $attachment );
		$small = is_array( $meta ) && isset( $meta['sizes']['medium']['file'] ) && is_string( $meta['sizes']['medium']['file'] ) ? dirname( $whole ) . '/' . $meta['sizes']['medium']['file'] : null;
		foreach ( array( $small, $whole ) as $path ) {
			if ( null !== $path && is_readable( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	/**
	 * The grid, by GD, or null where GD cannot read the file. Its type is the file's own, from its first bytes; JPEG is
	 * read here, and PNG, WebP and AVIF as the layer images are (WpLayerFile). GD's alpha runs from 0, opaque, to 127,
	 * clear; the grid's from 0, clear, to 255, opaque.
	 *
	 * @param string $file The file.
	 * @return list<list<array{int, int, int, int}>>|null
	 */
	private static function gd( string $file ): ?array {
		$type = wp_get_image_mime( $file );
		if ( 'image/jpeg' === $type ) {
			$image = null;
			if ( function_exists( 'imagecreatefromjpeg' ) ) {
				set_error_handler( static fn(): bool => true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Held for one decode, and put back below.
				try {
					$image = imagecreatefromjpeg( $file );
				} finally {
					restore_error_handler();
				}
			}
		} else {
			$image = is_string( $type ) ? WpLayerFile::gd( $file, $type ) : null;
		}
		if ( ! $image instanceof GdImage ) {
			return null;
		}
		$small = imagecreatetruecolor( self::SIDE, self::SIDE );
		if ( ! $small instanceof GdImage ) {
			return null;
		}
		imagealphablending( $small, false );
		imagesavealpha( $small, true );
		imagefill( $small, 0, 0, (int) imagecolorallocatealpha( $small, 0, 0, 0, 127 ) );
		imagecopyresampled( $small, $image, 0, 0, 0, 0, self::SIDE, self::SIDE, imagesx( $image ), imagesy( $image ) );
		$grid = array();
		for ( $y = 0; $y < self::SIDE; $y++ ) {
			$row = array();
			for ( $x = 0; $x < self::SIDE; $x++ ) {
				$colour = (int) imagecolorat( $small, $x, $y );
				$row[]  = array( ( $colour >> 16 ) & 0xFF, ( $colour >> 8 ) & 0xFF, $colour & 0xFF, intdiv( ( 127 - ( ( $colour >> 24 ) & 0x7F ) ) * 255, 127 ) );
			}
			$grid[] = $row;
		}
		return $grid;
	}

	/**
	 * The grid, by Imagick with the coder of the file's own type, or null where it cannot read it.
	 *
	 * @param string $file The file.
	 * @return list<list<array{int, int, int, int}>>|null
	 */
	private static function imagick( string $file ): ?array {
		$coder = array(
			'image/jpeg' => 'JPEG',
			'image/png'  => 'PNG',
			'image/webp' => 'WEBP',
			'image/avif' => 'AVIF',
		)[ (string) wp_get_image_mime( $file ) ] ?? null;
		if ( null === $coder || ! class_exists( Imagick::class ) ) {
			return null;
		}
		try {
			$image = new Imagick();
			$image->readImage( $coder . ':' . $file );
			$image->resizeImage( self::SIDE, self::SIDE, Imagick::FILTER_TRIANGLE, 1 );
			$pixels = $image->exportImagePixels( 0, 0, self::SIDE, self::SIDE, 'RGBA', Imagick::PIXEL_CHAR );
		} catch ( ImagickException ) {
			return null;
		}
		$grid = array();
		foreach ( array_chunk( array_map( 'intval', $pixels ), 4 * self::SIDE ) as $row ) {
			$grid[] = array_map( static fn( array $pixel ): array => array( $pixel[0] ?? 0, $pixel[1] ?? 0, $pixel[2] ?? 0, $pixel[3] ?? 0 ), array_chunk( $row, 4 ) );
		}
		return $grid;
	}
}
