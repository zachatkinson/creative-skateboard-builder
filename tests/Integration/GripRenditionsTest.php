<?php
/**
 * A grip's layer image, made at the sizes the board's top draws it.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpLayerRenditions;
use CreativeBranding\SkateboardBuilder\Port\Reindex;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\PartRenditions;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Cowork's review of 1.1.0, F29 (issue #129, D-371): three grips on the demo had layer images and not one rendition,
 * and neither their index jobs, "Read every part again" nor Site Health saw it. A grip that gives no size is drawn
 * as the usual 9 by 33 in sheet, and so is made at that sheet's sizes. Run through the container and the queue, as
 * on a site.
 */
final class GripRenditionsTest extends CartTestCase {

	/**
	 * The demo's grip sheets: 572 by 2096 pixels.
	 */
	private const PIXELS = array( 572, 2096 );

	/**
	 * Its sizes on a 9 by 33 in sheet, at 0.5, 1 and 2 px/mm.
	 */
	private const SIZES = array( '115x420', '229x839', '458x1677' );

	public function tear_down(): void {
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		as_unschedule_all_actions( Options::JOB_INDEX_PART, array(), Options::QUEUE_GROUP );
		as_unschedule_all_actions( Options::JOB_REINDEX, array(), Options::QUEUE_GROUP );
		parent::tear_down();
	}

	/**
	 * The sizes of an image's renditions on disk, by type, smallest first.
	 *
	 * @param int $image The attachment.
	 * @return array<string, list<string>>
	 */
	private static function made( int $image ): array {
		$made = array();
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER . '/' . $image . '-*' ) as $file ) {
			if ( 1 === preg_match( '/-(\d+)x(\d+)\.(avif|webp)$/', (string) $file, $match ) ) {
				$made[ $match[3] ][ (int) $match[1] ] = $match[1] . 'x' . $match[2];
			}
		}
		ksort( $made );
		return array_map(
			static function ( array $sizes ): array {
				ksort( $sizes );
				return array_values( $sizes );
			},
			$made
		);
	}

	/**
	 * The grip given a layer image, and saved as wp-admin saves it.
	 *
	 * @param bool $sized Whether it keeps its width and length.
	 */
	private function layered( bool $sized ): int {
		$image = LayerFiles::attachment( self::PIXELS[0], self::PIXELS[1], true, 'webp' );
		$grip  = wc_get_product( $this->parts['grip'] );
		if ( ! $sized ) {
			$grip->delete_meta_data( Options::META_SPECS );
		}
		$grip->update_meta_data( Options::META_LAYER, (string) $image );
		$grip->save();
		return $image;
	}

	public function test_a_grip_that_gives_no_size_has_its_layer_image_made_at_the_usual_sheets_sizes(): void {
		$image = $this->layered( false );
		self::work();
		self::assertSame(
			array(
				'avif' => self::SIZES,
				'webp' => self::SIZES,
			),
			self::made( $image ),
			'its index job made them'
		);
		self::assertSame( 0, $this->service( PartRenditions::class )->unrendered() );
	}

	public function test_a_grip_with_its_size_has_its_layer_image_made_at_that_sheets_sizes(): void {
		$image = $this->layered( true );
		self::work();
		self::assertSame(
			array(
				'avif' => self::SIZES,
				'webp' => self::SIZES,
			),
			self::made( $image )
		);
	}

	public function test_read_every_part_again_makes_a_grips_missing_copies(): void {
		$image = $this->layered( false );
		self::work();
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		self::assertSame( array( $this->parts['grip'] ), $this->service( PartRenditions::class )->unrenderedParts(), 'Site Health counts it' );

		$this->service( Reindex::class )->requestReindex();
		self::work();
		self::assertSame( self::SIZES, self::made( $image )['webp'] ?? array() );
	}

	public function test_an_index_job_that_ran_before_the_layer_image_was_set_leaves_none_missing(): void {
		// Cowork saw the grip's index job run in the second the import set its layer image. That is not the cause: the
		// save queues a job of its own, as a running job is not a waiting one, so the job that ran first leaves nothing
		// behind. A grip with its size, so this holds apart from the fix.
		$this->service( IndexJobs::class )->indexPart( $this->parts['grip'] );
		$image = $this->layered( true );
		self::assertSame( array(), self::made( $image ), 'the job before found no layer image' );
		self::assertContains( array( 'part' => $this->parts['grip'] ), self::waiting( Options::JOB_INDEX_PART ), 'the save queued its own job' );
		self::work();
		self::assertSame( self::SIZES, self::made( $image )['webp'] ?? array() );
	}
}
