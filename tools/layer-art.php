<?php
/**
 * Draws the test parts' layer images: clean art, not photographs, made to the shooting template in docs/PICTURE.md.
 * Each is seen from underneath, nose up, on a transparent ground, at two pixels to the millimetre of the box the
 * picture draws the part in, so the board shows how a shop's own cut-outs will sit.
 *
 * The deck's bolt holes are where the layout puts the trucks: the inner rows half the wheelbase from the middle (the
 * layout's usual wheelbase, 0.445 of the length), the outer rows 2 1/8 in further, the two columns 1 5/8 in apart. The
 * trucks' baseplate holes are at the same spacing, so a truck's holes land on the deck's. The browser fixture uploads
 * the files, and Claude in Cowork can upload the same ones to a demo's test parts.
 *
 * From 0.8.0 the board has a top (#46): the grip's image is a swatch the top repeats across the deck, and the
 * hardware's is one bolt's head, drawn at each hole. Both are seen from the top. From 1.1.0 a grip's image is its whole
 * sheet (F18), and the swatches stay as a 1.0.x shop's.
 *
 * Usage: php tools/layer-art.php [out dir]. Needs GD.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tools\LayerArt;

const PX_PER_MM = 2;
const SUPER     = 4;
const INCH      = 25.4;

/**
 * A colour as GD wants it, from 0xRRGGBB, with GD's alpha (0 opaque to 127 clear).
 *
 * @param \GdImage $image  The image.
 * @param int      $colour The colour.
 * @param int      $alpha  Its alpha.
 */
function colour( \GdImage $image, int $colour, int $alpha = 0 ): int {
	return (int) imagecolorallocatealpha( $image, ( $colour >> 16 ) & 0xff, ( $colour >> 8 ) & 0xff, $colour & 0xff, $alpha );
}

/**
 * A canvas at SUPER times the size, clear, which finish() shrinks.
 *
 * @param int $width  Pixels across at the final size.
 * @param int $height Pixels along at the final size.
 * @param int $ground The colour of the clear ground, so the edges' fringe is the part's own.
 */
function canvas( int $width, int $height, int $ground ): \GdImage {
	$image = imagecreatetruecolor( $width * SUPER, $height * SUPER );
	imagealphablending( $image, false );
	imagesavealpha( $image, true );
	imagefill( $image, 0, 0, colour( $image, $ground, 127 ) );
	imagealphablending( $image, true );
	return $image;
}

/**
 * Shrink a SUPER canvas to its size, which smooths every edge, and write it as a PNG.
 *
 * @param \GdImage $big    The drawn canvas.
 * @param int      $width  Pixels across.
 * @param int      $height Pixels along.
 * @param int      $ground The clear ground's colour.
 * @param string   $file   Where to write it.
 */
function finish( \GdImage $big, int $width, int $height, int $ground, string $file ): void {
	$small = imagecreatetruecolor( $width, $height );
	imagealphablending( $small, false );
	imagesavealpha( $small, true );
	imagefill( $small, 0, 0, colour( $small, $ground, 127 ) );
	imagecopyresampled( $small, $big, 0, 0, 0, 0, $width, $height, $width * SUPER, $height * SUPER );
	// The template asks for every corner clear; the resampling can leave a trace there.
	foreach ( array( array( 0, 0 ), array( $width - 1, 0 ), array( 0, $height - 1 ), array( $width - 1, $height - 1 ) ) as [ $x, $y ] ) {
		imagesetpixel( $small, $x, $y, colour( $small, $ground, 127 ) );
	}
	imagepng( $small, $file, 9 );
}

/**
 * A rectangle with rounded corners, in SUPER pixels.
 *
 * @param \GdImage $image  The canvas.
 * @param float    $left   Its left edge.
 * @param float    $top    Its top edge.
 * @param float    $right  Its right edge.
 * @param float    $bottom Its bottom edge.
 * @param float    $radius The corners' radius.
 * @param int      $fill   The colour.
 */
function rounded( \GdImage $image, float $left, float $top, float $right, float $bottom, float $radius, int $fill ): void {
	$radius = min( $radius, ( $right - $left ) / 2, ( $bottom - $top ) / 2 );
	$r      = (int) round( $radius );
	$l      = (int) round( $left );
	$t      = (int) round( $top );
	$rt     = (int) round( $right );
	$b      = (int) round( $bottom );
	imagefilledrectangle( $image, $l + $r, $t, $rt - $r, $b, $fill );
	imagefilledrectangle( $image, $l, $t + $r, $rt, $b - $r, $fill );
	foreach ( array( array( $l + $r, $t + $r ), array( $rt - $r, $t + $r ), array( $l + $r, $b - $r ), array( $rt - $r, $b - $r ) ) as [ $x, $y ] ) {
		imagefilledellipse( $image, $x, $y, 2 * $r, 2 * $r, $fill );
	}
}

/**
 * A deck's outline: straight sides and a rounded nose and tail, in SUPER pixels, inset from the box.
 *
 * @param \GdImage $image The canvas.
 * @param float    $w     The box across.
 * @param float    $h     The box along.
 * @param float    $inset How far in from the box.
 * @param int      $fill  The colour.
 */
function deck_shape( \GdImage $image, float $w, float $h, float $inset, int $fill ): void {
	$across = $w - 2 * $inset;
	// A nose and tail rounder than a semicircle's quarter, flatter than its half: most popsicle shapes.
	$cap = $across * 0.42;
	imagefilledrectangle( $image, (int) round( $inset ), (int) round( $inset + $cap ), (int) round( $w - $inset ), (int) round( $h - $inset - $cap ), $fill );
	foreach ( array( $inset + $cap, $h - $inset - $cap ) as $y ) {
		imagefilledellipse( $image, (int) round( $w / 2 ), (int) round( $y ), (int) round( $across ), (int) round( 2 * $cap ), $fill );
	}
}

/**
 * A deck from underneath: a ply edge, the graphic, and the eight bolt holes where the trucks go.
 *
 * @param float  $width_in  Its width in inches.
 * @param float  $length_in Its length in inches.
 * @param int    $graphic   The graphic's colour.
 * @param int    $accent    The stripe's colour.
 * @param string $file      Where to write it.
 */
function deck( float $width_in, float $length_in, int $graphic, int $accent, string $file ): void {
	$width  = (int) ceil( $width_in * INCH * PX_PER_MM );
	$length = (int) ceil( $length_in * INCH * PX_PER_MM );
	$ply    = 0xc8a06a;
	$image  = canvas( $width, $length, $ply );
	$s      = PX_PER_MM * SUPER;
	$w      = $width * SUPER;
	$h      = $length * SUPER;
	deck_shape( $image, $w, $h, 0.6 * $s, colour( $image, $ply ) );
	deck_shape( $image, $w, $h, 3.5 * $s, colour( $image, $graphic ) );

	// A stripe across the middle third, and a ring at the middle: enough to see the deck's direction and its centre.
	$clip = imagecreatetruecolor( $w, $h );
	imagealphablending( $clip, false );
	imagesavealpha( $clip, true );
	imagefill( $clip, 0, 0, colour( $clip, 0, 127 ) );
	imagealphablending( $clip, true );
	$band = 0.09 * $h;
	imagefilledpolygon( $clip, array( 0, (int) round( $h * 0.44 ), $w, (int) round( $h * 0.40 ), $w, (int) round( $h * 0.40 + $band ), 0, (int) round( $h * 0.44 + $band ) ), colour( $clip, $accent ) );
	imagefilledellipse( $clip, (int) round( $w / 2 ), (int) round( $h * 0.62 ), (int) round( $w * 0.46 ), (int) round( $w * 0.46 ), colour( $clip, $accent ) );
	imagefilledellipse( $clip, (int) round( $w / 2 ), (int) round( $h * 0.62 ), (int) round( $w * 0.30 ), (int) round( $w * 0.30 ), colour( $clip, $graphic ) );
	// Only where the graphic is: the ply edge and the ground are left as they are.
	for ( $y = 0; $y < $h; $y += 1 ) {
		for ( $x = 0; $x < $w; $x += 1 ) {
			$over = imagecolorat( $clip, $x, $y );
			if ( ( ( $over >> 24 ) & 0x7f ) < 127 && ( imagecolorat( $image, $x, $y ) & 0xffffff ) === $graphic ) {
				imagesetpixel( $image, $x, $y, $over );
			}
		}
	}

	// The bolt holes: the layout's usual wheelbase between the inner rows.
	$wheelbase = $length_in * 0.445 * INCH;
	$spacing   = 2.125 * INCH;
	$columns   = 1.625 * INCH / 2;
	$middle    = $h / 2;
	foreach ( array( $wheelbase / 2, $wheelbase / 2 + $spacing ) as $out ) {
		foreach ( array( -1, 1 ) as $end ) {
			foreach ( array( -1, 1 ) as $side ) {
				$x = (int) round( $w / 2 + $side * $columns * $s );
				$y = (int) round( $middle + $end * $out * $s );
				imagefilledellipse( $image, $x, $y, (int) round( 7 * $s ), (int) round( 7 * $s ), colour( $image, 0x1b1b1b, 40 ) );
				imagefilledellipse( $image, $x, $y, (int) round( 5 * $s ), (int) round( 5 * $s ), colour( $image, 0x0d0d0d ) );
			}
		}
	}
	finish( $image, $width, $length, $ply, $file );
}

/**
 * A truck from underneath: the baseplate with its four holes, the hanger across it and the axle to its ends. The
 * box is the axle's width across and 3 in along: the holes' spacing and the baseplate past them.
 *
 * @param float  $axle_in Its axle's width in inches.
 * @param int    $metal   The hanger's colour.
 * @param int    $plate   The baseplate's colour.
 * @param string $file    Where to write it.
 */
function truck( float $axle_in, int $metal, int $plate, string $file ): void {
	$width  = (int) ceil( $axle_in * INCH * PX_PER_MM );
	$length = (int) ceil( 3 * INCH * PX_PER_MM );
	$image  = canvas( $width, $length, $metal );
	$s      = PX_PER_MM * SUPER;
	$w      = $width * SUPER;
	$h      = $length * SUPER;
	$mid    = $w / 2;
	// The axle, end to end: 8 mm.
	rounded( $image, 0, $h / 2 - 4 * $s, $w, $h / 2 + 4 * $s, 4 * $s, colour( $image, 0x8a8f96 ) );
	// The baseplate: 64 mm across, the box's length.
	rounded( $image, $mid - 32 * $s, 0.5 * $s, $mid + 32 * $s, $h - 0.5 * $s, 9 * $s, colour( $image, $plate ) );
	rounded( $image, $mid - 29 * $s, 3 * $s, $mid + 29 * $s, $h - 3 * $s, 7 * $s, colour( $image, $plate & 0xf0f0f0 ) );
	foreach ( array( -1, 1 ) as $end ) {
		foreach ( array( -1, 1 ) as $side ) {
			$x = (int) round( $mid + $side * 1.625 * INCH / 2 * $s );
			$y = (int) round( $h / 2 + $end * 2.125 * INCH / 2 * $s );
			imagefilledellipse( $image, $x, $y, (int) round( 8 * $s ), (int) round( 8 * $s ), colour( $image, 0x5b6068 ) );
			imagefilledellipse( $image, $x, $y, (int) round( 5 * $s ), (int) round( 5 * $s ), colour( $image, 0x2b2e33 ) );
		}
	}
	// The hanger: to within 34 mm of each axle end, where the wheel and its washers sit.
	rounded( $image, 34 * $s, $h / 2 - 12 * $s, $w - 34 * $s, $h / 2 + 12 * $s, 10 * $s, colour( $image, $metal ) );
	rounded( $image, 36 * $s, $h / 2 - 9 * $s, $w - 36 * $s, $h / 2 - 5 * $s, 2 * $s, colour( $image, 0xffffff, 95 ) );
	// The kingpin's nut over the bushing.
	imagefilledellipse( $image, (int) round( $mid ), (int) round( $h / 2 ), (int) round( 20 * $s ), (int) round( 20 * $s ), colour( $image, 0xd9a400 ) );
	imagefilledellipse( $image, (int) round( $mid ), (int) round( $h / 2 ), (int) round( 11 * $s ), (int) round( 11 * $s ), colour( $image, 0x4a4d52 ) );
	finish( $image, $width, $length, $metal, $file );
}

/**
 * A wheel from underneath: its riding surface, three fifths as wide as it is tall, rounding away at both ends.
 *
 * @param float  $diameter_mm Its diameter in millimetres.
 * @param int    $urethane    Its colour.
 * @param string $file        Where to write it.
 */
function wheel( float $diameter_mm, int $urethane, string $file ): void {
	$width  = (int) ceil( $diameter_mm * 3 / 5 * PX_PER_MM );
	$length = (int) ceil( $diameter_mm * PX_PER_MM );
	$image  = canvas( $width, $length, $urethane );
	$w      = $width * SUPER;
	$h      = $length * SUPER;
	// Shaded across, darker at the lips, as a cylinder is.
	$steps = 24;
	for ( $i = 0; $i < $steps; $i++ ) {
		$inset = $w * 0.5 * $i / $steps;
		$shade = 0.78 + 0.22 * sin( M_PI / 2 * $i / $steps );
		$tone  = ( (int) ( ( ( $urethane >> 16 ) & 0xff ) * $shade ) << 16 ) | ( (int) ( ( ( $urethane >> 8 ) & 0xff ) * $shade ) << 8 ) | (int) ( ( $urethane & 0xff ) * $shade );
		rounded( $image, $inset, $inset * 0.25, $w - $inset, $h - $inset * 0.25, $w * 0.42 - $inset * 0.6, colour( $image, $tone ) );
	}
	// The contact patch: a lighter band down the middle.
	rounded( $image, $w * 0.33, $h * 0.08, $w * 0.67, $h * 0.92, $w * 0.15, colour( $image, 0xffffff, 100 ) );
	finish( $image, $width, $length, $urethane, $file );
}

/**
 * Grip tape from the top: a swatch the board's top repeats across the deck, nose to tail (#46). Its box is 1 in across
 * and the deck's length along, the strip the layout sizes a grip's image by; the whole box is grit, but for the
 * corners the template asks to be clear. A faint band every 4 in shows the swatch running nose to tail.
 *
 * @param int    $base The grit's colour.
 * @param int    $fleck The flecks' colour.
 * @param int    $seed  The flecks' places, so the same art is drawn every time.
 * @param string $file  Where to write it.
 */
function grip( int $base, int $fleck, int $seed, string $file ): void {
	$width  = (int) ceil( INCH * PX_PER_MM );
	$length = (int) ceil( 32 * INCH * PX_PER_MM );
	$image  = canvas( $width, $length, $base );
	$s      = PX_PER_MM * SUPER;
	$w      = $width * SUPER;
	$h      = $length * SUPER;
	imagefilledrectangle( $image, 0, 0, $w - 1, $h - 1, colour( $image, $base ) );
	for ( $band = 2; $band < 32; $band += 4 ) {
		$y = (int) round( $band * INCH * $s );
		imagefilledrectangle( $image, 0, $y, $w - 1, $y + (int) round( 6 * $s ), colour( $image, $fleck, 112 ) );
	}
	mt_srand( $seed );
	for ( $i = 0; $i < 2400; $i++ ) {
		$size = mt_rand( 1, 3 ) * SUPER;
		imagefilledellipse( $image, mt_rand( 0, $w - 1 ), mt_rand( 0, $h - 1 ), $size, $size, colour( $image, $fleck, mt_rand( 40, 100 ) ) );
	}
	finish( $image, $width, $length, $base, $file );
}

/**
 * A whole sheet of grip from the top, 9 by 33 in, as 1.1.0 asks for (F18, #103): grit to every edge, shot flat, with
 * no clear corners. A printed sheet carries a figure a third of the way up from the tail, off to one side, as a
 * maker's graphic sits, so the test can tell it is drawn once at its place and not repeated or squeezed.
 *
 * @param int    $base    The grit's colour.
 * @param int    $fleck   The flecks' colour.
 * @param int    $seed    The flecks' places.
 * @param bool   $printed Whether the sheet carries a figure.
 * @param string $file    Where to write it.
 */
function sheet( int $base, int $fleck, int $seed, bool $printed, string $file ): void {
	$width  = (int) ceil( 9 * INCH * PX_PER_MM );
	$length = (int) ceil( 33 * INCH * PX_PER_MM );
	$image  = canvas( $width, $length, $base );
	$w      = $width * SUPER;
	$h      = $length * SUPER;
	imagefilledrectangle( $image, 0, 0, $w - 1, $h - 1, colour( $image, $base ) );
	mt_srand( $seed );
	for ( $i = 0; $i < 9000; $i++ ) {
		$size = mt_rand( 1, 3 ) * SUPER;
		imagefilledellipse( $image, mt_rand( 0, $w - 1 ), mt_rand( 0, $h - 1 ), $size, $size, colour( $image, $fleck, mt_rand( 40, 100 ) ) );
	}
	if ( $printed ) {
		// A white star with a ring round it, 3 in across, its middle 2.5 in right of the sheet's and 22 in from the nose.
		$s     = PX_PER_MM * SUPER;
		$cx    = (int) round( $w / 2 + 2.5 * INCH * $s );
		$cy    = (int) round( 22 * INCH * $s );
		$outer = 1.5 * INCH * $s;
		imagesetthickness( $image, (int) round( 0.12 * INCH * $s ) );
		imageellipse( $image, $cx, $cy, (int) round( 2 * $outer ), (int) round( 2 * $outer ), colour( $image, 0xf4f1ea ) );
		imagesetthickness( $image, 1 );
		$points = array();
		for ( $point = 0; $point < 10; $point++ ) {
			$radius   = 0 === $point % 2 ? $outer * 0.82 : $outer * 0.34;
			$angle    = -M_PI / 2 + M_PI / 5 * $point;
			$points[] = (int) round( $cx + $radius * cos( $angle ) );
			$points[] = (int) round( $cy + $radius * sin( $angle ) );
		}
		imagefilledpolygon( $image, $points, colour( $image, 0xf4f1ea ) );
	}
	$small = imagecreatetruecolor( $width, $length );
	imagecopyresampled( $small, $image, 0, 0, 0, 0, $width, $length, $w, $h );
	imagepng( $small, $file, 9 );
}

/**
 * A bolt's head from the top, as the board's top draws one at each of the deck's eight holes (#46): 3/8 in across,
 * round, with a cross for a Phillips head or a hexagon for an Allen key.
 *
 * @param int    $metal The head's colour.
 * @param bool   $allen An Allen head, not a Phillips.
 * @param string $file  Where to write it.
 */
function bolt( int $metal, bool $allen, string $file ): void {
	$side  = (int) ceil( 0.375 * INCH * PX_PER_MM );
	$image = canvas( $side, $side, $metal );
	$w     = $side * SUPER;
	$mid   = (int) round( $w / 2 );
	imagefilledellipse( $image, $mid, $mid, $w - SUPER, $w - SUPER, colour( $image, $metal ) );
	imagefilledellipse( $image, $mid - (int) round( $w * 0.08 ), $mid - (int) round( $w * 0.08 ), (int) round( $w * 0.6 ), (int) round( $w * 0.6 ), colour( $image, 0xffffff, 100 ) );
	$recess = colour( $image, ( $metal >> 1 ) & 0x7f7f7f );
	if ( $allen ) {
		$points = array();
		for ( $corner = 0; $corner < 6; $corner++ ) {
			$points[] = (int) round( $mid + $w * 0.2 * cos( M_PI / 3 * $corner ) );
			$points[] = (int) round( $mid + $w * 0.2 * sin( M_PI / 3 * $corner ) );
		}
		imagefilledpolygon( $image, $points, $recess );
	} else {
		$arm  = (int) round( $w * 0.26 );
		$half = (int) round( $w * 0.05 );
		imagefilledrectangle( $image, $mid - $arm, $mid - $half, $mid + $arm, $mid + $half, $recess );
		imagefilledrectangle( $image, $mid - $half, $mid - $arm, $mid + $half, $mid + $arm, $recess );
	}
	finish( $image, $side, $side, $metal, $file );
}

$out = $argv[1] ?? dirname( __DIR__ ) . '/tests/e2e/fixtures/layers';
if ( ! is_dir( $out ) ) {
	mkdir( $out, 0755, true );
}
deck( 8.0, 31.5, 0x1d3557, 0xe9c46a, $out . '/deck-8.0-31.5.png' );
deck( 8.25, 32.0, 0x2a6f4e, 0xf1faee, $out . '/deck-8.25-32.png' );
deck( 8.5, 32.0, 0x7a1f2b, 0xf4a261, $out . '/deck-8.5-32.png' );
// Each part its own colours, so a change of trucks or wheels shows on the board.
foreach ( array(
	'8.0'  => array( 0x8d939b, 0xc3c8ce ),
	'8.25' => array( 0xb07d2a, 0xd9b36a ),
	'8.5'  => array( 0x2b2d31, 0x55595f ),
) as $axle => [ $metal, $plate ] ) {
	truck( (float) $axle, $metal, $plate, $out . '/trucks-' . $axle . '.png' );
}
foreach ( array(
	52 => 0xf3eee2,
	54 => 0xd94b3d,
	56 => 0x3b82c4,
	58 => 0xf2c14e,
) as $diameter => $urethane ) {
	wheel( $diameter, $urethane, $out . '/wheels-' . $diameter . '.png' );
}
// The top (#46): black grip and a blue one, and the bolts' heads, black Phillips and silver Allen.
grip( 0x232326, 0x8a8f96, 46, $out . '/grip-black.png' );
// From 1.1.0 (F18, #103): whole sheets, a plain one and a printed one. The swatches above stay, as 1.0.x's.
sheet( 0x1f1f22, 0x6d7178, 103, false, $out . '/grip-sheet-plain.png' );
sheet( 0x1f1f22, 0x6d7178, 104, true, $out . '/grip-sheet-printed.png' );
grip( 0x1d4f8c, 0xcfe3f7, 47, $out . '/grip-blue.png' );
bolt( 0x26272b, false, $out . '/bolts-1.png' );
bolt( 0xc3c8ce, true, $out . '/bolts-1.25.png' );
echo 'Drawn into ' . $out . PHP_EOL;
