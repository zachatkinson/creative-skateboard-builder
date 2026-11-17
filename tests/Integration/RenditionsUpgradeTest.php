<?php
/**
 * Missing layer renditions made with nobody saving a part: by the update to 0.8.0, and by "Read every part again".
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpLayerRenditions;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RunResult;
use CreativeBranding\SkateboardBuilder\Port\Reindex;
use CreativeBranding\SkateboardBuilder\Service\PartRenditions;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Cowork's recheck of 0.7.0, N1 (D-214): six layer images set under 0.7.0-rc.1 had no resized copies on the demo, and
 * nothing made them but saving each part. Run through the container, as on a site.
 */
final class RenditionsUpgradeTest extends CartTestCase {

	public function set_up(): void {
		parent::set_up();
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 420, 1626 ) );
		$deck->save();
		self::work();
		self::assertSame( 0, $this->service( PartRenditions::class )->unrendered(), 'the save made them' );
		// What 0.7.0-rc.1 left: the copies gone.
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		self::assertSame( array( $this->parts['deck'] ), $this->service( PartRenditions::class )->unrenderedParts() );
	}

	public function tear_down(): void {
		as_unschedule_all_actions( Options::JOB_INDEX_PART, array(), Options::QUEUE_GROUP );
		as_unschedule_all_actions( Options::JOB_REINDEX, array(), Options::QUEUE_GROUP );
		parent::tear_down();
	}

	public function test_the_update_from_0_7_0_makes_the_missing_copies_in_the_background(): void {
		$runner = $this->service( MigrationRunner::class );
		// A site at 0.7.0: every migration done but this release's.
		update_option( Options::DB_VERSION, '0', true );
		self::assertSame( RunResult::Finished, $runner->run() );
		self::work();
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		$states = get_option( Options::MIGRATIONS );
		self::assertIsArray( $states );
		unset( $states['layer_renditions_1'] );
		update_option( Options::MIGRATIONS, $states, false );
		update_option( Options::DB_VERSION, '5', true );

		self::assertTrue( $runner->needed() );
		self::assertSame( RunResult::Finished, $runner->run() );
		self::assertSame( array( array( 'part' => $this->parts['deck'] ) ), self::waiting( Options::JOB_INDEX_PART ), 'the deck\'s own job, queued by the update' );

		self::work();
		self::assertSame( 0, $this->service( PartRenditions::class )->unrendered(), 'made, with nobody saving the part' );
	}

	public function test_read_every_part_again_makes_the_missing_copies(): void {
		$this->service( Reindex::class )->requestReindex();
		self::work();
		self::assertSame( 0, $this->service( PartRenditions::class )->unrendered() );
	}
}
