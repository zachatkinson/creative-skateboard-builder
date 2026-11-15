<?php
/**
 * The sizes a layer image is made at for the page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;

use function intdiv;
use function max;
use function min;

/**
 * A layer image is shown in the page at the size the layout draws its part, fitted to that box and never enlarged
 * (spec: The picture; D-194). The page offers it at half a pixel, one pixel and two pixels to the millimetre, which a
 * phone's picture, a desktop's and a sharp screen's each come near; the browser picks from them. A size the image is
 * too small for is the image's own size, and two sizes that come out the same are one.
 */
final class Renditions {

	/**
	 * The densities, in half pixels to the millimetre: 0.5, 1 and 2.
	 */
	public const array HALVES = array( 1, 2, 4 );

	/**
	 * The types each size is made in, with their files' extensions, the smallest first.
	 */
	public const array TYPES = array(
		'image/avif' => 'avif',
		'image/webp' => 'webp',
	);

	/**
	 * The sizes, smallest first, to make of an image of these pixels for a part drawn in this box.
	 *
	 * @since 0.7.0
	 *
	 * @param array{int, int} $box    Across and along, in ten-thousandths of a millimetre.
	 * @param int             $width  The image's pixels across.
	 * @param int             $height Its pixels along.
	 * @return list<array{int, int}> Pixels across and along.
	 */
	public static function of( array $box, int $width, int $height ): array {
		if ( $width < 1 || $height < 1 || $box[0] < 1 || $box[1] < 1 ) {
			return array();
		}
		$sizes = array();
		foreach ( self::HALVES as $halves ) {
			$size = self::fitted( self::pixels( $box[0], $halves ), self::pixels( $box[1], $halves ), $width, $height );
			if ( array() === $sizes || $sizes[ count( $sizes ) - 1 ] !== $size ) {
				$sizes[] = $size;
			}
		}
		return $sizes;
	}

	/**
	 * The sizes to make of an image of these pixels for a part of these specs in this slot: none where the picture
	 * does not draw it.
	 *
	 * @since 0.7.0
	 *
	 * @param string $slot   The slot's key.
	 * @param Specs  $specs  The part's specs.
	 * @param int    $width  The image's pixels across.
	 * @param int    $height Its pixels along.
	 * @return list<array{int, int}>
	 */
	public static function drawn( string $slot, Specs $specs, int $width, int $height ): array {
		$box = Layout::drawn( $slot, $specs );
		return null === $box ? array() : self::of( $box, $width, $height );
	}

	/**
	 * The image fitted into a box of pixels, keeping its shape, never larger than it is.
	 *
	 * @param int $across The box's pixels across.
	 * @param int $along  Its pixels along.
	 * @param int $width  The image's pixels across.
	 * @param int $height Its pixels along.
	 * @return array{int, int}
	 */
	private static function fitted( int $across, int $along, int $width, int $height ): array {
		// The image is taller for its width than the box when its height decides the fit.
		if ( $width * $along <= $height * $across ) {
			$tall = min( $height, $along );
			return array( max( 1, self::rounded( $width * $tall, $height ) ), $tall );
		}
		$wide = min( $width, $across );
		return array( $wide, max( 1, self::rounded( $height * $wide, $width ) ) );
	}

	/**
	 * A length's pixels at a density, rounded up.
	 *
	 * @param int $length Ten-thousandths of a millimetre.
	 * @param int $halves Half pixels to the millimetre.
	 */
	private static function pixels( int $length, int $halves ): int {
		return intdiv( $length * $halves + 19999, 20000 );
	}

	/**
	 * A quotient rounded to the nearest whole number, a half up.
	 *
	 * @param int $dividend The dividend.
	 * @param int $divisor  The divisor, above 0.
	 */
	private static function rounded( int $dividend, int $divisor ): int {
		return intdiv( 2 * $dividend + $divisor, 2 * $divisor );
	}
}
