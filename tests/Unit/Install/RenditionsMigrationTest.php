<?php
/**
 * The update that queues the layer images' missing renditions.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RenditionsMigration;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\PartRenditions;
use CreativeBranding\SkateboardBuilder\Service\SavedSources;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCatalogue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryReindexStore;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecSource;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use PHPUnit\Framework\TestCase;

/**
 * Cowork's recheck of 0.7.0, N1: six layer images set under 0.7.0-rc.1 had no resized copies, and nothing made them but
 * saving each part. The update to 0.8.0 queues each such part's index job, with no step for the shop (D-214).
 */
final class RenditionsMigrationTest extends TestCase {

	public function test_the_update_queues_the_job_of_each_drawn_part_whose_layer_image_has_no_copies_and_nothing_twice(): void {
		$kits = new InMemoryKits();
		$deck = Builds::deck( '8.25', 101 );
		$bare = Builds::deck( '8.5', 102 );
		$made = Builds::deck( '8', 103 );
		$kits->add( 7, KitDocuments::kit(), array( 'deck' => array( $deck, $bare, $made ) ) );
		$kits->slotsOf = array(
			101 => array( 'deck' ),
			102 => array( 'deck' ),
			103 => array( 'deck' ),
		);
		$index = new InMemorySpecIndex();
		foreach ( array( 101 => '8.2500', 102 => '8.5000', 103 => '8.0000' ) as $id => $width ) {
			$index->replace( $id, array( new IndexedSpec( SpecKey::DeckWidth, $width, Origin::Field ) ), new \DateTimeImmutable( '2026-11-17' ) );
		}
		$layers              = new InMemoryLayerImages();
		$layers->attachments = array(
			101 => 11,
			103 => 13,
		);
		$layers->images      = array(
			11 => new ImageFacts( 'image/png', 420, 1626, true ),
			13 => new ImageFacts( 'image/png', 420, 1626, true ),
		);
		$renditions = new InMemoryLayerRenditions();
		$parts      = new PartRenditions( $kits, $index, $layers, $renditions );
		$parts->make( 103 );
		$queue  = new FakeQueue();
		$logger = new FakeLogger();

		$migration = new RenditionsMigration( static fn(): IndexJobs => self::jobs( $kits, $index, $queue )->withRenditions( $parts ), $logger );
		self::assertSame( 'layer_renditions_1', $migration->key() );
		self::assertTrue( $migration->run( Cursor::start() )->done );
		self::assertSame( array( array( IndexJobs::JOB_PART, array( 'part' => 101 ) ) ), $queue->distinct, 'the 101 alone: the 102 has no layer image and the 103 its copies' );
		self::assertSame( Logger::INFO, $logger->lines[0]['level'] );
		self::assertSame( array( 'parts' => 1 ), $logger->lines[0]['context'] );

		$migration->run( Cursor::start() );
		self::assertCount( 1, $queue->distinct, 'run again while the job waits, nothing is queued twice' );

		$bare  = new InMemoryKits();
		$quiet = new RenditionsMigration( static fn(): IndexJobs => self::jobs( $bare, $index, new FakeQueue() )->withRenditions( new PartRenditions( $bare, $index, $layers, $renditions ) ), $silent = new FakeLogger() );
		$quiet->run( Cursor::start() );
		self::assertSame( array(), $silent->lines, 'a site from before 0.7.0, with no layer images, queues nothing and says nothing' );
		self::assertSame( 0, self::jobs( $bare, $index, new FakeQueue() )->requestRenditions(), 'jobs built without renditions (D-208) queue nothing' );
	}

	/**
	 * The index's jobs, over these fakes.
	 *
	 * @param InMemoryKits      $kits  The kits.
	 * @param InMemorySpecIndex $index The index.
	 * @param FakeQueue         $queue The queue.
	 */
	private static function jobs( InMemoryKits $kits, InMemorySpecIndex $index, FakeQueue $queue ): IndexJobs {
		$settings = new InMemoryShopSettings();
		$clock    = new FakeClock();
		return new IndexJobs( new SpecIndexer( $index, $settings, $clock, new InMemorySpecSource() ), $index, new InMemoryPartCatalogue(), $queue, new InMemoryReindexStore(), $settings, $clock, new FakeLogger(), $kits, new SavedSources( array( '_cbuild_specs' ) ) );
	}
}
