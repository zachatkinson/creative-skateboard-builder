<?php
/**
 * The checks on a layer image.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

use function in_array;
use function intdiv;

/**
 * A layer image is a cut-out on a transparent background, a PNG or a WebP, holding at least two pixels to each
 * millimetre of the size the layout draws its part at (spec: Admin; D-190). The picture fits an image to its part's
 * box and never enlarges it, so one side reaching its share is enough.
 */
final class LayerImage {

	/**
	 * The types a layer image may be.
	 */
	public const array TYPES = array( 'image/png', 'image/webp' );

	/**
	 * Pixels to the millimetre.
	 */
	public const int DENSITY = 2;

	/**
	 * The pixels across and along an image needs for a part drawn at this size.
	 *
	 * @since 0.7.0
	 *
	 * @param array{int, int} $size Across and along, in ten-thousandths of a millimetre.
	 * @return array{int, int}
	 */
	public static function needs( array $size ): array {
		return array( self::pixels( $size[0] ), self::pixels( $size[1] ) );
	}

	/**
	 * What is wrong with an image as a layer, in the order the edge says it.
	 *
	 * @since 0.7.0
	 *
	 * @param ImageFacts           $image The image.
	 * @param array{int, int}|null $size  The part's size, where the layout draws it.
	 * @return list<LayerError>
	 */
	public static function problems( ImageFacts $image, ?array $size ): array {
		if ( ! in_array( $image->type, self::TYPES, true ) ) {
			return array( LayerError::NotPngOrWebp );
		}
		$problems = array();
		if ( false === $image->clear ) {
			$problems[] = LayerError::Opaque;
		}
		if ( null !== $size ) {
			$needs = self::needs( $size );
			if ( $image->width < $needs[0] && $image->height < $needs[1] ) {
				$problems[] = LayerError::TooSmall;
			}
		}
		return $problems;
	}

	/**
	 * The pixels for a length, rounded up.
	 *
	 * @param int $length Ten-thousandths of a millimetre.
	 */
	private static function pixels( int $length ): int {
		return intdiv( $length * self::DENSITY + 9999, 10000 );
	}
}
