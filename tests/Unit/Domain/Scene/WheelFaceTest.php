<?php
/**
 * A wheel's outer face, read from its featured photo.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Scene;

use CreativeBranding\SkateboardBuilder\Domain\Scene\WheelFace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M15, the wheel faces (Zach, 27 November): a wheel's photo, square, face-on and on white or nothing, gives the 3D
 * wheel its outer face, trimmed to the wheel; any other photo is refused, and the wheel stays its colour. The shots
 * a shop has are single wheels on white (the demo's), so those pass, and an angled shot, a set or a box does not.
 */
final class WheelFaceTest extends TestCase {

	private const G = 64;

	private const WHITE = array( 255, 255, 255, 255 );

	/**
	 * A photo's grid of samples, each pixel from a function of its place.
	 *
	 * @param callable(int, int): array{int, int, int, int} $pixel The pixel at x, y.
	 * @param int                                           $side  Samples a side.
	 * @return list<list<array{int, int, int, int}>>
	 */
	private static function photo( callable $pixel, int $side = self::G ): array {
		$rows = array();
		for ( $y = 0; $y < $side; $y++ ) {
			$row = array();
			for ( $x = 0; $x < $side; $x++ ) {
				$row[] = $pixel( $x, $y );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * A wheel seen face-on: a disc with a tread ring of its own colour and a core, on a ground.
	 *
	 * @param int                          $cx     Its centre across, in samples.
	 * @param int                          $cy     Its centre down.
	 * @param int                          $rx     Its radius across.
	 * @param int                          $ry     Its radius down.
	 * @param array{int, int, int, int}    $ground What is around it.
	 * @return callable(int, int): array{int, int, int, int}
	 */
	private static function wheel( int $cx = 32, int $cy = 32, int $rx = 28, int $ry = 28, array $ground = self::WHITE ): callable {
		return static function ( int $x, int $y ) use ( $cx, $cy, $rx, $ry, $ground ): array {
			$d = ( ( $x - $cx ) ** 2 ) / ( $rx ** 2 ) + ( ( $y - $cy ) ** 2 ) / ( $ry ** 2 );
			if ( $d > 1 ) {
				return $ground;
			}
			// The urethane at the edge is red; the core and its print inside, dark grey.
			return $d > 0.6 ? array( 200, 30, 40, 255 ) : array( 60, 60, 60, 255 );
		};
	}

	public function test_a_single_wheel_face_on_on_white_gives_its_face_trimmed_to_it_and_its_treads_colour(): void {
		$face = WheelFace::read( 1200, 1200, self::photo( self::wheel() ) );

		self::assertNull( $face->refused );
		// Samples 4 to 60 of 64, as ten-thousandths of the photo.
		self::assertSame( 625, $face->x );
		self::assertSame( 625, $face->y );
		self::assertSame( 8906, $face->size );
		self::assertSame( '#c81e28', $face->tread, 'the ring just inside the edge' );
	}

	public function test_a_wheel_on_nothing_reads_as_on_white(): void {
		$face = WheelFace::read( 800, 800, self::photo( self::wheel( ground: array( 0, 0, 0, 0 ) ) ) );

		self::assertNull( $face->refused );
		self::assertSame( '#c81e28', $face->tread );
	}

	/**
	 * Photos that are refused, each with its reason.
	 *
	 * @return iterable<string, array{int, int, callable(int, int): array{int, int, int, int}, string}>
	 */
	public static function refused(): iterable {
		yield 'a photo that is not square' => array( 1600, 1200, self::wheel(), WheelFace::NOT_SQUARE );
		yield 'a wheel on grey' => array( 1200, 1200, self::wheel( ground: array( 180, 180, 180, 255 ) ), WheelFace::NO_PLAIN_BACKGROUND );
		yield 'a wheel seen at an angle' => array( 1200, 1200, self::wheel( rx: 14 ), WheelFace::NOT_FACE_ON );
		yield 'a wheel off to one side' => array( 1200, 1200, self::wheel( cx: 40, rx: 20, ry: 20 ), WheelFace::NOT_CENTRED );
		yield 'a small wheel in a wide frame' => array( 1200, 1200, self::wheel( rx: 12, ry: 12 ), WheelFace::TOO_SMALL );
		yield 'a set of four' => array(
			1200,
			1200,
			static function ( int $x, int $y ): array {
				$one = self::wheel( $x < 32 ? 17 : 47, $y < 32 ? 17 : 47, 13, 13 );
				return $one( $x, $y );
			},
			WheelFace::NOT_ONE_WHEEL,
		);
		yield 'a box: a square of colour' => array(
			1200,
			1200,
			static fn( int $x, int $y ): array => $x >= 6 && $x < 58 && $y >= 6 && $y < 58 ? array( 20, 90, 160, 255 ) : self::WHITE,
			WheelFace::NOT_ONE_WHEEL,
		);
		yield 'nothing but white' => array( 1200, 1200, static fn(): array => self::WHITE, WheelFace::TOO_SMALL );
	}

	/**
	 * A refused photo gives no face, and the wheel is drawn in its colour.
	 *
	 * @param int                                           $width  The photo's width.
	 * @param int                                           $height Its height.
	 * @param callable(int, int): array{int, int, int, int} $pixel  Its pixels.
	 * @param string                                        $why    The reason's code.
	 */
	#[DataProvider( 'refused' )]
	public function test_a_photo_that_is_no_single_wheel_face_on_is_refused( int $width, int $height, callable $pixel, string $why ): void {
		$face = WheelFace::read( $width, $height, self::photo( $pixel ) );

		self::assertSame( $why, $face->refused );
		self::assertNull( $face->tread );
	}

	public function test_a_grid_too_coarse_to_read_is_refused_as_too_small(): void {
		self::assertSame( WheelFace::TOO_SMALL, WheelFace::read( 1200, 1200, array( array( self::WHITE ) ) )->refused );
		self::assertSame( WheelFace::TOO_SMALL, WheelFace::read( 0, 0, self::photo( self::wheel() ) )->refused );
	}

	private const DARK = array( 60, 60, 60, 255 );

	/**
	 * A filled rectangle of dark samples on white: a box, or a stand-in for any shape of that extent.
	 *
	 * @return callable(int, int): array{int, int, int, int}
	 */
	private static function rect( int $left, int $top, int $across, int $down ): callable {
		return static fn( int $x, int $y ): array => $x >= $left && $x < $left + $across && $y >= $top && $y < $top + $down ? self::DARK : self::WHITE;
	}

	/**
	 * A box of a side, its samples painted by their band: a hundredth of the squared diameter, in half-samples from the
	 * box's centre, so the circle's edge is band 100, as the reading measures it. White outside the box.
	 *
	 * @param callable(int, int, int): array{int, int, int, int} $paint By the band, x and y.
	 * @return callable(int, int): array{int, int, int, int}
	 */
	private static function rings( int $size, int $left, int $top, callable $paint ): callable {
		return static function ( int $x, int $y ) use ( $size, $left, $top, $paint ): array {
			if ( $x < $left || $x >= $left + $size || $y < $top || $y >= $top + $size ) {
				return self::WHITE;
			}
			return $paint( self::band( $x - $left, $y - $top, $size ), $x, $y );
		};
	}

	/**
	 * A sample's band in a box.
	 */
	private static function band( int $x, int $y, int $size ): int {
		return intdiv( 100 * ( ( 2 * $x + 1 - $size ) ** 2 + ( 2 * $y + 1 - $size ) ** 2 ), $size * $size );
	}

	/**
	 * A plain disc filling a box.
	 *
	 * @return callable(int, int): array{int, int, int, int}
	 */
	private static function disc( int $size, int $left, int $top ): callable {
		return self::rings( $size, $left, $top, static fn( int $band ): array => $band >= 100 ? self::WHITE : array( 200, 30, 40, 255 ) );
	}

	public function test_the_grid_is_square_and_sixteen_samples_a_side_at_least(): void {
		self::assertNull( WheelFace::read( 1200, 1200, self::photo( self::disc( 14, 1, 1 ), 16 ) )->refused, 'sixteen is enough' );
		self::assertSame( WheelFace::TOO_SMALL, WheelFace::read( 1200, 1200, self::photo( self::disc( 14, 1, 1 ), 15 ) )->refused );
		$ragged      = self::photo( self::wheel() );
		$ragged[3][] = self::WHITE;
		self::assertSame( WheelFace::TOO_SMALL, WheelFace::read( 1200, 1200, $ragged )->refused );
		self::assertSame( WheelFace::TOO_SMALL, WheelFace::read( 0, 1200, self::photo( self::wheel() ) )->refused );
		self::assertSame( WheelFace::TOO_SMALL, WheelFace::read( 1200, 0, self::photo( self::wheel() ) )->refused );
	}

	public function test_a_refusal_has_no_box(): void {
		$face = WheelFace::read( 1600, 1200, self::photo( self::wheel() ) );
		self::assertSame( array( 0, 0, 0, null ), array( $face->x, $face->y, $face->size, $face->tread ) );
	}

	public function test_a_photo_is_square_within_three_hundredths(): void {
		$wheel = self::photo( self::wheel() );
		self::assertNull( WheelFace::read( 1029, 999, $wheel )->refused );
		self::assertSame( WheelFace::NOT_SQUARE, WheelFace::read( 1031, 1000, $wheel )->refused );
		self::assertSame( WheelFace::NOT_SQUARE, WheelFace::read( 1000, 1031, $wheel )->refused );
	}

	/**
	 * Grounds, and whether each is plain: white within a little, or clear.
	 *
	 * @return iterable<string, array{array{int, int, int, int}, bool}>
	 */
	public static function grounds(): iterable {
		yield 'white with a little blue' => array( array( 232, 232, 244, 255 ), true );
		yield 'almost clear black' => array( array( 0, 0, 0, 25 ), true );
		yield 'light grey' => array( array( 231, 231, 231, 255 ), false );
		yield 'white with too much blue' => array( array( 232, 232, 245, 255 ), false );
		yield 'faint black' => array( array( 0, 0, 0, 26 ), false );
	}

	/**
	 * A ground is plain where it is white within a little, or clear.
	 *
	 * @param array{int, int, int, int} $ground The ground.
	 * @param bool                      $plain  Whether it is plain.
	 */
	#[DataProvider( 'grounds' )]
	public function test_a_ground_is_plain_where_it_is_white_or_clear( array $ground, bool $plain ): void {
		$face = WheelFace::read( 1200, 1200, self::photo( self::wheel( ground: $ground ) ) );
		self::assertSame( $plain ? null : WheelFace::NO_PLAIN_BACKGROUND, $face->refused );
	}

	/**
	 * Corners, each with the first sample of its eighth-square and the way its samples are painted.
	 *
	 * @return iterable<string, array{int, int, int, int}>
	 */
	public static function corners(): iterable {
		// x, y of the block's first sample, and the step along x and y from it.
		yield 'top left' => array( 0, 0, 1, 1 );
		yield 'bottom right' => array( 63, 63, -1, -1 );
	}

	/**
	 * The corners are nine tenths ground: 25 of their 256 samples painted pass, 26 do not.
	 *
	 * @param int $x0 The corner's x.
	 * @param int $y0 Its y.
	 * @param int $dx Its step along x.
	 * @param int $dy Its step along y.
	 */
	#[DataProvider( 'corners' )]
	public function test_the_corners_are_nine_tenths_ground( int $x0, int $y0, int $dx, int $dy ): void {
		$painted = static function ( int $count ) use ( $x0, $y0, $dx, $dy ): callable {
			$marks = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$marks[ ( $x0 + $dx * ( $i % 8 ) ) . ',' . ( $y0 + $dy * intdiv( $i, 8 ) ) ] = true;
			}
			$wheel = self::wheel();
			return static fn( int $x, int $y ): array => isset( $marks[ $x . ',' . $y ] ) ? self::DARK : $wheel( $x, $y );
		};
		self::assertNotSame( WheelFace::NO_PLAIN_BACKGROUND, WheelFace::read( 1200, 1200, self::photo( $painted( 25 ) ) )->refused );
		self::assertSame( WheelFace::NO_PLAIN_BACKGROUND, WheelFace::read( 1200, 1200, self::photo( $painted( 26 ) ) )->refused );
	}

	public function test_the_wheel_fills_half_the_photo_at_least(): void {
		self::assertSame( WheelFace::NOT_ONE_WHEEL, WheelFace::read( 1200, 1200, self::photo( self::rect( 16, 16, 32, 32 ) ) )->refused );
		self::assertSame( WheelFace::TOO_SMALL, WheelFace::read( 1200, 1200, self::photo( self::rect( 16, 16, 31, 31 ) ) )->refused );
	}

	public function test_the_wheel_is_as_wide_as_tall_within_eight_hundredths(): void {
		self::assertSame( WheelFace::NOT_ONE_WHEEL, WheelFace::read( 1200, 1200, self::photo( self::rect( 7, 9, 50, 46 ) ) )->refused );
		self::assertSame( WheelFace::NOT_FACE_ON, WheelFace::read( 1200, 1200, self::photo( self::rect( 7, 9, 50, 45 ) ) )->refused );
		self::assertSame( WheelFace::NOT_ONE_WHEEL, WheelFace::read( 1200, 1200, self::photo( self::rect( 9, 7, 46, 50 ) ) )->refused );
		self::assertSame( WheelFace::NOT_FACE_ON, WheelFace::read( 1200, 1200, self::photo( self::rect( 9, 7, 45, 50 ) ) )->refused );
		// Five in sixty is over the line, where five in fifty is not under it: on a grid of a hundred.
		self::assertSame( WheelFace::NOT_FACE_ON, WheelFace::read( 1200, 1200, self::photo( self::rect( 20, 22, 60, 55 ), 100 ) )->refused );
	}

	/**
	 * Boxes off the photo's centre, and whether each is too far: its left, top and side.
	 *
	 * @return iterable<string, array{int, int, int, int, bool}>
	 */
	public static function offsets(): iterable {
		yield 'five samples right' => array( 17, 12, 40, 64, false );
		yield 'five and a half right' => array( 17, 11, 41, 64, true );
		yield 'five and a half left' => array( 6, 11, 41, 64, true );
		yield 'five samples down' => array( 12, 17, 40, 64, false );
		yield 'five and a half down' => array( 11, 17, 41, 64, true );
		yield 'five and a half up' => array( 11, 6, 41, 64, true );
		yield 'four and a half in fifty-six' => array( 18, 13, 29, 56, true );
	}

	/**
	 * The wheel's centre is within 8 % of the photo's side of its centre, across and down.
	 *
	 * @param int  $left  The box's left.
	 * @param int  $top   Its top.
	 * @param int  $size  Its side.
	 * @param int  $side  The grid's side.
	 * @param bool $off   Whether it is too far off.
	 */
	#[DataProvider( 'offsets' )]
	public function test_the_wheel_is_centred_within_eight_hundredths( int $left, int $top, int $size, int $side, bool $off ): void {
		$face = WheelFace::read( 1200, 1200, self::photo( self::rect( $left, $top, $size, $size ), $side ) );
		self::assertSame( $off ? WheelFace::NOT_CENTRED : WheelFace::NOT_ONE_WHEEL, $face->refused );
	}

	/**
	 * Wheel sizes in samples, odd and even, so every edge of a band falls on some sample.
	 *
	 * @return iterable<string, array{int}>
	 */
	public static function sizes(): iterable {
		foreach ( array( 40, 41, 44, 47 ) as $size ) {
			yield "$size samples" => array( $size );
		}
	}

	/**
	 * The tread's colour is the mean of the wheel's samples in bands 74 to 91, 86 % to 96 % of the radius, and no others.
	 *
	 * @param int $size The wheel's side in samples.
	 */
	#[DataProvider( 'sizes' )]
	public function test_the_treads_colour_is_the_mean_of_its_band( int $size ): void {
		$left  = intdiv( 64 - $size, 2 );
		$sum   = array( 0, 0, 0, 0 );
		$paint = static function ( int $band, int $x, int $y ) use ( &$sum ): array {
			if ( $band >= 100 ) {
				return self::WHITE;
			}
			if ( $band > 91 ) {
				return array( 10, 200, 10, 255 );
			}
			if ( $band < 74 ) {
				return self::DARK;
			}
			$pixel = array( 100 + ( $x * 7 + $y * 3 ) % 100, 20 + ( $x * 5 + $y * 11 ) % 60, 30, 255 );
			$sum   = array( $sum[0] + $pixel[0], $sum[1] + $pixel[1], $sum[2] + $pixel[2], $sum[3] + 1 );
			return $pixel;
		};
		$grid  = self::photo( self::rings( $size, $left, $left, $paint ) );
		$face  = WheelFace::read( 1200, 1200, $grid );

		self::assertGreaterThan( 0, $sum[3] );
		self::assertSame( sprintf( '#%02x%02x%02x', intdiv( $sum[0], $sum[3] ), intdiv( $sum[1], $sum[3] ), intdiv( $sum[2], $sum[3] ) ), $face->tread );
	}

	public function test_an_outline_with_no_tread_is_no_wheel(): void {
		$rim = self::rings( 40, 12, 12, static fn( int $band ): array => $band > 91 && $band < 100 ? self::DARK : self::WHITE );
		self::assertSame( WheelFace::NOT_ONE_WHEEL, WheelFace::read( 1200, 1200, self::photo( $rim ) )->refused );
	}

	/**
	 * The box's corners outside the circle are a quarter wheel at most: one more painted sample, and it is no wheel.
	 *
	 * @param int $size The wheel's side in samples.
	 */
	#[DataProvider( 'sizes' )]
	public function test_the_corners_of_the_box_are_a_quarter_wheel_at_most( int $size ): void {
		$left    = intdiv( 64 - $size, 2 );
		$outside = array();
		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				if ( self::band( $x, $y, $size ) >= 100 ) {
					$outside[] = ( $left + $x ) . ',' . ( $left + $y );
				}
			}
		}
		$most    = intdiv( count( $outside ), 4 );
		$painted = static function ( int $count ) use ( $outside, $size, $left ): callable {
			$marks = array_flip( array_slice( $outside, 0, $count ) );
			$disc  = self::disc( $size, $left, $left );
			return static fn( int $x, int $y ): array => isset( $marks[ $x . ',' . $y ] ) ? self::DARK : $disc( $x, $y );
		};
		self::assertSame( '#c81e28', WheelFace::read( 1200, 1200, self::photo( $painted( $most ) ) )->tread );
		self::assertSame( WheelFace::NOT_ONE_WHEEL, WheelFace::read( 1200, 1200, self::photo( $painted( $most + 1 ) ) )->refused );
	}

	public function test_the_ties_fall_on_the_side_the_rules_say(): void {
		// Exactly three hundredths apart: square.
		self::assertNull( WheelFace::read( 1000, 970, self::photo( self::wheel() ) )->refused );
		// Exactly 8 % of the side off centre, on a grid of 25: centred.
		self::assertSame( WheelFace::NOT_ONE_WHEEL, WheelFace::read( 1200, 1200, self::photo( self::rect( 8, 6, 13, 13 ), 25 ) )->refused );
		// Five in sixty-two is over 8 %, by less than a hundredth: on a grid of a hundred.
		self::assertSame( WheelFace::NOT_FACE_ON, WheelFace::read( 1200, 1200, self::photo( self::rect( 19, 21, 62, 57 ), 100 ) )->refused );
	}

	public function test_the_corners_are_nine_tenths_ground_exactly(): void {
		// A grid of forty: four corners of five by five, a hundred samples; ninety plain pass, eighty-nine do not.
		$painted = static function ( int $count ): callable {
			$disc = self::disc( 34, 3, 3 );
			return static fn( int $x, int $y ): array => $y * 5 + $x < $count && $x < 5 && $y < 5 ? self::DARK : $disc( $x, $y );
		};
		self::assertNotSame( WheelFace::NO_PLAIN_BACKGROUND, WheelFace::read( 1200, 1200, self::photo( $painted( 10 ), 40 ) )->refused );
		self::assertSame( WheelFace::NO_PLAIN_BACKGROUND, WheelFace::read( 1200, 1200, self::photo( $painted( 11 ), 40 ) )->refused );
	}

	public function test_the_box_is_in_ten_thousandths_rounded_down(): void {
		// 19 of 64 is 2968.75 ten-thousandths; 33 of 64, 5156.25.
		$face = WheelFace::read( 1200, 1200, self::photo( self::disc( 33, 19, 19 ) ) );
		self::assertSame( array( 2968, 2968, 5156 ), array( $face->x, $face->y, $face->size ) );
	}

	public function test_the_treads_mean_is_rounded_down_and_a_colour_with_no_blue_is_one(): void {
		$first = null;
		$paint = static function ( int $band, int $x, int $y ) use ( &$first ): array {
			if ( $band >= 100 ) {
				return self::WHITE;
			}
			if ( $band >= 74 && $band <= 91 && null === $first ) {
				$first = "$x,$y";
				return array( 199, 29, 9, 255 );
			}
			return array( 200, 30, 10, 255 );
		};
		self::assertSame( '#c71d09', WheelFace::read( 1200, 1200, self::photo( self::rings( 40, 12, 12, $paint ) ) )->tread, 'one sample a shade darker' );
		$blueless = self::rings( 40, 12, 12, static fn( int $band ): array => $band >= 100 ? self::WHITE : array( 200, 30, 0, 255 ) );
		self::assertSame( '#c81e00', WheelFace::read( 1200, 1200, self::photo( $blueless ) )->tread, 'a tread with no blue' );
	}
}
