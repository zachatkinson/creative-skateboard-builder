<?php
/**
 * The composer's time, memory and files with layer images at a photographer's scale.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Benchmark;

use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\RunnerSpeed;

/**
 * Plan, milestone 7, Performance: "the composer's time and memory in the benchmark suite", and the risk of "GD's
 * memory on a large layer". D-198: the 200 MB cap on the pictures' folder "is an estimate", to be measured here
 * "with photographic layers". The layers are drawn at 6.5 pixels to the millimetre, PICTURE.md's suggested scale,
 * so a deck's shot is 1,403 by 5,283 pixels, which WordPress keeps as 680 by 2,560. Each is a gradient with random grain, which no photograph
 * outdoes for detail, so the files measured are the most a build's pictures take. Run by `composer test:benchmark:integration`.
 */
final class ComposerBenchmarkTest extends CartTestCase {

	/**
	 * The budgets: a build's three pictures in seconds, and the job's peak memory above what it started with, in MB.
	 * The memory budget is a quarter of WordPress's 256 MB for a background request (`WP_MAX_MEMORY_LIMIT`).
	 */
	private const SECONDS = 4.0;
	private const MEGABYTES = 64.0;

	/**
	 * Pixels to the millimetre at the table, as PICTURE.md suggests.
	 */
	private const SCALE = 6.5;

	private static function say( string $line ): void {
		fwrite( STDERR, "\n[benchmark] " . $line ); // phpcs:ignore
	}

	public function tear_down(): void {
		as_unschedule_all_actions( Options::JOB_COMPOSE_PICTURE, array(), Options::QUEUE_GROUP );
		as_unschedule_all_actions( Options::JOB_PRUNE_PICTURES, array(), Options::QUEUE_GROUP );
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		parent::tear_down();
	}

	/**
	 * A photograph's stand-in, a PNG cut-out of a part's box in millimetres, in the Media Library.
	 *
	 * @param float $across The box across, in millimetres.
	 * @param float $along  The box along.
	 * @param int   $seed   Its grain.
	 * @param int   $grain  How many of the layer's pixels each grain spans.
	 */
	private static function photo( float $across, float $along, int $seed, int $grain ): int {
		$width  = (int) round( $across * self::SCALE );
		$height = (int) round( $along * self::SCALE );
		// The grain at a fraction of the size, then smoothed up.
		$small = imagecreatetruecolor( max( 1, intdiv( $width, $grain ) ), max( 1, intdiv( $height, $grain ) ) );
		mt_srand( $seed );
		for ( $y = 0, $h = imagesy( $small ); $y < $h; $y++ ) {
			for ( $x = 0, $w = imagesx( $small ); $x < $w; $x++ ) {
				$tone = (int) ( 60 + 120 * $y / $h + 40 * $x / $w );
				imagesetpixel( $small, $x, $y, imagecolorallocate( $small, min( 255, $tone + mt_rand( -24, 24 ) ), min( 255, (int) ( $tone * 0.8 ) + mt_rand( -24, 24 ) ), min( 255, (int) ( $tone * 0.6 ) + mt_rand( -24, 24 ) ) ) );
			}
		}
		$image = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefill( $image, 0, 0, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
		// The part inside a transparent edge, as a cut-out is.
		$inset = max( 1, intdiv( min( $width, $height ), 20 ) );
		imagecopyresampled( $image, $small, $inset, $inset, 0, 0, $width - 2 * $inset, $height - 2 * $inset, imagesx( $small ), imagesy( $small ) );
		$path = get_temp_dir() . 'cbuild-photo-' . $seed . '.png';
		imagepng( $image, $path, 9 );
		$id = ( new \WP_UnitTest_Factory_For_Attachment() )->create_upload_object( $path );
		unlink( $path );
		return (int) $id;
	}

	/**
	 * The layers' grain. A deck kept 2,560 pixels long is drawn about 580 long on the share card, so grain four pixels
	 * across is grain in every pixel of the picture: the most detail, so the largest files, it can hold. Finer grain
	 * averages out as the layer is scaled down. A photograph has less detail than either, so these bound its files.
	 *
	 * @return array<string, array{int}>
	 */
	public function grains(): array {
		return array(
			'grain the size of the picture\'s pixels' => array( 4 ),
			'grain finer than the picture\'s pixels'  => array( 1 ),
		);
	}

	/**
	 * @dataProvider grains
	 *
	 * @param int $grain How many pixels each grain spans.
	 */
	public function test_a_builds_three_pictures_are_drawn_within_the_budgets_and_their_files_measured( int $grain ): void {
		$layers = array(
			'deck'   => self::photo( 215.9, 812.8, 1, $grain ),
			'grip'   => self::photo( 25.4, 812.8, 2, $grain ),
			'trucks' => self::photo( 215.9, 76.2, 3, $grain ),
			'wheels' => self::photo( 32.4, 54, 4, $grain ),
		);
		foreach ( $layers as $slot => $image ) {
			$part = wc_get_product( $this->parts[ $slot ] );
			$part->update_meta_data( Options::META_LAYER, (string) $image );
			$part->save();
		}
		$deck = wp_getimagesize( (string) get_attached_file( $layers['deck'] ) );
		self::assertIsArray( $deck );
		// WordPress scales an upload over 2,560 pixels down and attaches that copy (`big_image_size_threshold`), so
		// the composer draws what a shop's upload of the shot leaves, not the shot itself.
		self::assertSame( array( 680, 2560 ), array( $deck[0], $deck[1] ), 'a deck shot at the suggested scale, as WordPress keeps it' );

		$pictures = $this->service( ComposedPictures::class );
		$before   = memory_get_usage();
		memory_reset_peak_usage();
		$start = hrtime( true );
		self::assertSame( 3, $pictures->compose( $this->kit, $this->picks() ), 'the cart\'s, the share card\'s and the email\'s' );
		$took = ( hrtime( true ) - $start ) / 1e9;
		$peak = ( memory_get_peak_usage() - $before ) / 1048576;

		$sizes = array();
		foreach ( array( ComposedPictures::CART, ComposedPictures::SHARE, ComposedPictures::EMAIL ) as $purpose ) {
			$url = $pictures->url( $this->kit, $this->picks(), $purpose );
			self::assertNotNull( $url, $purpose );
			$sizes[ $purpose ] = (int) filesize( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . basename( $url ) );
		}
		$build = array_sum( $sizes );
		self::say(
			sprintf(
				'composer, GD, %s, layers shot at %.1f px/mm (the deck kept at %d by %d): %.2f s, %.1f MB above the start; files %s; %d kB a build, so 200 MB holds %d builds',
				1 === $grain ? 'grain finer than the picture\'s pixels' : 'grain the size of the picture\'s pixels',
				self::SCALE,
				$deck[0],
				$deck[1],
				$took,
				$peak,
				implode( ', ', array_map( static fn( string $purpose, int $bytes ): string => $purpose . ' ' . round( $bytes / 1024, 1 ) . ' kB', array_keys( $sizes ), $sizes ) ),
				(int) round( $build / 1024 ),
				intdiv( 200 * 1048576, max( 1, $build ) )
			)
		);
		self::assertLessThan( RunnerSpeed::limit( self::SECONDS * 1000 ) / 1000, $took, 'a build\'s three pictures' );
		self::assertLessThan( self::MEGABYTES, $peak, 'the job\'s memory' );
	}
}
