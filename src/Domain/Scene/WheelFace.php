<?php
/**
 * A wheel's outer face, read from its photo.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Scene;

use function abs;
use function array_column;
use function array_key_last;
use function array_map;
use function array_slice;
use function count;
use function intdiv;
use function max;
use function min;
use function sprintf;

/**
 * The 3D view draws a wheel's outer face from the wheel's own photo, trimmed to the wheel (M15, Zach, 27 November). A
 * photo gives a face only where it is a single wheel seen face-on: square, on white or on nothing, the wheel centred
 * and filling most of it. An angled shot, a set, a box or a wheel on a coloured ground is refused with its reason, and
 * the wheel is drawn in its colour, as before. The face's box is in ten-thousandths of the photo's sides; the tread's
 * colour is the urethane's, from a ring just inside the wheel's edge.
 *
 * The photo is read from a square grid of samples over the whole of it (the adapter makes it), each a list of red,
 * green, blue and opacity from 0 to 255. Every test here is on whole numbers: distances are compared squared, and in
 * half-samples, so a sample's centre is exact.
 *
 * @since 1.5.0
 */
final class WheelFace {

	/** The photo's sides differ by more than 3 %. */
	public const NOT_SQUARE = 'not_square';

	/** A corner of the photo is not white or clear. */
	public const NO_PLAIN_BACKGROUND = 'no_plain_background';

	/** The wheel fills less than half the photo, or there is none. */
	public const TOO_SMALL = 'too_small';

	/** The wheel is wider than it is tall, or taller than wide: seen at an angle. */
	public const NOT_FACE_ON = 'not_face_on';

	/** The wheel's centre is off the photo's by more than 8 % of its side. */
	public const NOT_CENTRED = 'not_centred';

	/** What fills the photo is not one round wheel: a set, a box, a pack. */
	public const NOT_ONE_WHEEL = 'not_one_wheel';

	/** The fewest samples a side the grid needs. */
	private const LEAST = 16;

	/**
	 * A reading: a face, or a refusal.
	 *
	 * @param string|null $refused Why the photo gives no face, or null.
	 * @param int         $x       The face's left edge, in ten-thousandths of the photo's width.
	 * @param int         $y       Its top edge, in ten-thousandths of the height.
	 * @param int         $size    Its side, in ten-thousandths of the photo's side.
	 * @param string|null $tread   The tread's colour, as `#rrggbb`, or null where refused.
	 */
	private function __construct(
		public readonly ?string $refused,
		public readonly int $x,
		public readonly int $y,
		public readonly int $size,
		public readonly ?string $tread,
	) {}

	/**
	 * A refusal: no face, and no box.
	 *
	 * @param string $why The reason's code.
	 */
	private static function refuse( string $why ): self {
		return new self( $why, 0, 0, 0, null );
	}

	/**
	 * Reads a photo.
	 *
	 * @since 1.5.0
	 *
	 * @param int                                   $width  The photo's width in pixels.
	 * @param int                                   $height Its height.
	 * @param list<list<array{int, int, int, int}>> $grid   Its samples, row by row, a square grid over the whole photo.
	 */
	public static function read( int $width, int $height, array $grid ): self {
		$side = count( $grid );
		foreach ( $grid as $row ) {
			if ( count( $row ) !== $side ) {
				return self::refuse( self::TOO_SMALL );
			}
		}
		if ( $side < self::LEAST || $width <= 0 || $height <= 0 ) {
			return self::refuse( self::TOO_SMALL );
		}
		if ( abs( $width - $height ) * 100 > 3 * max( $width, $height ) ) {
			return self::refuse( self::NOT_SQUARE );
		}

		$block  = intdiv( $side, 8 );
		$corner = 0;
		$plain  = 0;
		$at     = array();
		foreach ( $grid as $y => $row ) {
			foreach ( $row as $x => $pixel ) {
				$ground = self::ground( $pixel );
				if ( ( $x < $block || $x >= $side - $block ) && ( $y < $block || $y >= $side - $block ) ) {
					++$corner;
					$plain += $ground ? 1 : 0;
				}
				if ( ! $ground ) {
					$at[] = array( $x, $y );
				}
			}
		}
		if ( $plain * 10 < 9 * $corner ) {
			return self::refuse( self::NO_PLAIN_BACKGROUND );
		}
		if ( array() === $at ) {
			return self::refuse( self::TOO_SMALL );
		}
		// The rows come in order, so the first wheel sample's row is the top and the last's the bottom.
		$columns = array_column( $at, 0 );
		$left    = min( $columns );
		$right   = max( $columns );
		$top     = $at[0][1];
		$bottom  = $at[ array_key_last( $at ) ][1];
		$across  = $right - $left + 1;
		$down    = $bottom - $top + 1;
		$size    = max( $across, $down );
		if ( $size * 2 < $side ) {
			return self::refuse( self::TOO_SMALL );
		}
		if ( abs( $across - $down ) * 100 > 8 * $size ) {
			return self::refuse( self::NOT_FACE_ON );
		}
		// Twice the offset of the wheel's centre from the photo's, against 8 % of the side.
		if ( max( abs( $left + $right + 1 - $side ), abs( $top + $bottom + 1 - $side ) ) * 100 > 16 * $side ) {
			return self::refuse( self::NOT_CENTRED );
		}

		$box   = array_map( static fn( array $row ): array => array_slice( $row, $left, $size ), array_slice( $grid, $top, $size ) );
		$tread = self::wheel( $box, $size );
		if ( null === $tread ) {
			return self::refuse( self::NOT_ONE_WHEEL );
		}
		return new self( null, intdiv( $left * 10000, $side ), intdiv( $top * 10000, $side ), intdiv( $size * 10000, $side ), $tread );
	}

	/**
	 * Whether a sample is the ground: clear, or white.
	 *
	 * @param array{int, int, int, int} $pixel Red, green, blue and opacity.
	 */
	private static function ground( array $pixel ): bool {
		[ $red, $green, $blue, $alpha ] = $pixel;
		return $alpha < 26 || ( min( $red, $green, $blue ) >= 232 && max( $red, $green, $blue ) - min( $red, $green, $blue ) <= 12 );
	}

	/**
	 * Whether the box holds one round wheel, and if so its tread's colour. Each sample is in a band: a hundredth of the
	 * squared diameter, measured in half-samples from the box's centre, so the circle's edge is band 100. Outside it,
	 * in the box's corners, a quarter of the samples are wheel at most, which a set or a box is not; and the wheel
	 * shows in bands 74 to 91, 86 % to 96 % of the radius, which an outline does not. The tread's colour is the mean of
	 * the wheel's samples there: inside the edge, where a photo's pixels blend with the ground, and outside the core
	 * and its print.
	 *
	 * @param list<list<array{int, int, int, int}>> $box  The box's samples, row by row from its top left.
	 * @param int                                   $size The box's side.
	 * @return string|null The tread's colour, or null where the box holds no single wheel.
	 */
	private static function wheel( array $box, int $size ): ?string {
		$outside = 0;
		$covered = 0;
		$tread   = array( 0, 0, 0, 0 );
		foreach ( $box as $y => $row ) {
			foreach ( $row as $x => $pixel ) {
				$wheel = ! self::ground( $pixel );
				$band  = intdiv( 100 * ( ( 2 * $x + 1 - $size ) ** 2 + ( 2 * $y + 1 - $size ) ** 2 ), $size * $size );
				if ( $band >= 100 ) {
					++$outside;
					$covered += $wheel ? 1 : 0;
				} elseif ( $wheel && $band >= 74 && $band <= 91 ) {
					$tread = array( $tread[0] + $pixel[0], $tread[1] + $pixel[1], $tread[2] + $pixel[2], $tread[3] + 1 );
				}
			}
		}
		if ( $covered * 4 > $outside || 0 === $tread[3] ) {
			return null;
		}
		return sprintf( '#%02x%02x%02x', intdiv( $tread[0], $tread[3] ), intdiv( $tread[1], $tread[3] ), intdiv( $tread[2], $tread[3] ) );
	}
}
