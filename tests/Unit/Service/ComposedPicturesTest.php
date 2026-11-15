<?php
/**
 * ComposedPictures: a build's composed pictures asked for, drawn by the job and found by name.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Service\Health;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPictureFiles;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Options and jobs: "`cbuild_compose_picture` (one build's composed image)". Spec, Security: "Composed pictures
 * are made only for builds that pass validation, from a capped queue, and named by a hash of their layers and size,
 * never by anything a visitor sends." Plan, milestone 7: "A composed picture is asked for, never waited on" (D-197).
 */
final class ComposedPicturesTest extends TestCase {

	private const int KIT = 77;

	private const int NOW = 1_800_000_000;

	private const array STREET = array(
		SlotKey::DECK     => 101,
		SlotKey::TRUCKS   => 139,
		SlotKey::WHEELS   => 301,
		SlotKey::BEARINGS => 501,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

	private FakeQueue $queue;

	private InMemoryLayerImages $layers;

	private InMemoryPictureFiles $files;

	private ComposedPictures $pictures;

	protected function setUp(): void {
		$kits = new InMemoryKits();
		$kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$this->queue               = new FakeQueue();
		$this->layers              = new InMemoryLayerImages();
		$this->layers->attachments = array( 101 => 10, 301 => 30 );
		$this->layers->images      = array(
			10 => new ImageFacts( 'image/png', 420, 1626, true ),
			30 => new ImageFacts( 'image/png', 130, 216, true ),
		);
		$this->files               = new InMemoryPictureFiles();
		$this->pictures            = new ComposedPictures(
			$kits,
			new BuildState( $kits, new InMemoryShopSettings(), new FakeLogger() ),
			new LayerArtwork( $this->layers, new InMemoryLayerRenditions() ),
			$this->files,
			$this->queue,
			new FakeClock( self::NOW )
		);
	}

	public function test_a_build_is_asked_for_once_with_its_ids_and_nothing_while_the_queue_is_full(): void {
		self::assertSame( Options::JOB_COMPOSE_PICTURE, ComposedPictures::JOB, 'the hooks run the action the service queues' );
		self::assertTrue( $this->pictures->ask( self::KIT, self::STREET ) );
		self::assertTrue( $this->pictures->ask( self::KIT, self::STREET ), 'asked again while it waits' );
		self::assertSame( array( array( Options::JOB_COMPOSE_PICTURE, array( 'kit' => self::KIT, 'picks' => self::STREET ) ) ), $this->queue->distinct, 'one job, with the IDs alone' );
		self::assertFalse( $this->pictures->ask( self::KIT, array() ), 'a build of nothing' );
		self::assertFalse( $this->pictures->ask( 0, self::STREET ), 'no kit' );

		for ( $more = 1; $more < ComposedPictures::QUEUED_MOST; $more++ ) {
			self::assertTrue( $this->pictures->ask( self::KIT, array( SlotKey::DECK => 1000 + $more ) ), 'room for ' . $more );
		}
		self::assertSame( ComposedPictures::QUEUED_MOST, $this->queue->pending( Options::JOB_COMPOSE_PICTURE, 1000 ) );
		self::assertFalse( $this->pictures->ask( self::KIT, array( SlotKey::DECK => 103 ) ), 'the queue is full' );
		self::assertCount( ComposedPictures::QUEUED_MOST, $this->queue->distinct );
	}

	public function test_the_job_draws_one_picture_for_each_use_and_each_is_found_by_its_name_once_made(): void {
		foreach ( array_keys( ComposedPictures::CANVASES ) as $use ) {
			self::assertNull( $this->pictures->url( self::KIT, self::STREET, $use ), 'not made yet: ' . $use );
		}
		self::assertSame( 3, $this->pictures->compose( self::KIT, self::STREET ) );
		$canvases = array();
		foreach ( $this->files->drawn as $drawn ) {
			$canvases[] = array( $drawn->canvas->width, $drawn->canvas->height, $drawn->canvas->type );
			self::assertSame( array( 10, 30 ), array_values( array_unique( array_map( static fn( $placement ): int => $placement->attachment, $drawn->placements ) ) ), 'the deck and the wheels, the parts with layer images' );
		}
		self::assertSame( array( array( 480, 480, 'image/webp' ), array( 1200, 630, 'image/webp' ), array( 600, 600, 'image/jpeg' ) ), $canvases );
		foreach ( array_keys( ComposedPictures::CANVASES ) as $use ) {
			self::assertStringStartsWith( 'https://example.com/pictures/', (string) $this->pictures->url( self::KIT, self::STREET, $use ), $use );
		}
		self::assertNull( $this->pictures->url( self::KIT, self::STREET, 'poster' ), 'a use there is none of' );

		$names = array_keys( $this->files->drawn );
		self::assertSame( 3, $this->pictures->compose( self::KIT, self::STREET ) );
		self::assertSame( $names, array_keys( $this->files->drawn ), 'the same build is the same three names' );
	}

	public function test_no_picture_is_drawn_for_a_build_that_does_not_validate_or_holds_what_the_shopper_did_not_pick(): void {
		$cases = array(
			'a kit the shop does not have'          => array( 78, self::STREET ),
			'a deck that is not one of the slot\'s' => array( self::KIT, array( SlotKey::DECK => 301 ) + self::STREET ),
			'a slot the kit lacks'                  => array( self::KIT, self::STREET + array( 'tail' => 101 ) ),
			'a part the shop does not have'         => array( self::KIT, array( SlotKey::WHEELS => 999 ) + self::STREET ),
		);
		$short = self::STREET;
		unset( $short[ SlotKey::TRUCKS ] );
		$cases['a required slot left, which a recommendation would fill'] = array( self::KIT, $short );
		foreach ( $cases as $case => [ $kit, $picks ] ) {
			self::assertSame( 0, $this->pictures->compose( $kit, $picks ), $case );
			self::assertNull( $this->pictures->url( $kit, $picks, ComposedPictures::CART ), $case );
		}
		self::assertSame( 0, $this->files->asked, 'nothing is drawn' );
	}

	public function test_a_build_with_no_layer_image_or_a_host_with_no_library_has_no_picture(): void {
		$this->layers->attachments = array();
		self::assertSame( 0, $this->pictures->compose( self::KIT, self::STREET ) );
		self::assertSame( 0, $this->files->asked, 'nothing to draw' );

		$this->layers->attachments = array( 101 => 10 );
		$this->files->library      = null;
		self::assertSame( 0, $this->pictures->compose( self::KIT, self::STREET ) );
		self::assertNull( $this->pictures->url( self::KIT, self::STREET, ComposedPictures::SHARE ) );
	}

	public function test_the_first_picture_starts_the_daily_pruning_and_a_build_with_none_does_not(): void {
		$this->layers->attachments = array();
		$this->pictures->compose( self::KIT, self::STREET );
		self::assertSame( array(), $this->queue->recurring, 'nothing drawn, nothing to prune' );

		$this->layers->attachments = array( 101 => 10 );
		$this->pictures->compose( self::KIT, self::STREET );
		self::assertSame( array( Options::JOB_PRUNE_PICTURES => array( self::NOW + 86400, 86400 ) ), $this->queue->recurring, 'a day from now, then daily' );
		self::assertSame( Options::JOB_PRUNE_PICTURES, ComposedPictures::PRUNE );
	}

	public function test_a_picture_handed_out_is_marked_used_and_the_pruning_keeps_thirty_days_under_200_mb(): void {
		$this->pictures->compose( self::KIT, self::STREET );
		self::assertSame( array(), $this->files->used, 'drawing is not showing' );
		$url = (string) $this->pictures->url( self::KIT, self::STREET, ComposedPictures::CART );
		self::assertSame( array( basename( $url ) => self::NOW ), $this->files->used );

		self::assertSame( 2, $this->pictures->prune() );
		self::assertSame( array( array( self::NOW - 30 * 86400, 200 * 1024 * 1024 ) ), $this->files->pruned );
	}

	public function test_site_health_hears_of_a_host_with_no_library_or_a_folder_it_cannot_write(): void {
		self::assertSame( array( 'status' => Health::GOOD, 'code' => ComposedPictures::HEALTH_FINE ), $this->pictures->health() );
		$this->files->writable = false;
		self::assertSame( array( 'status' => Health::RECOMMENDED, 'code' => ComposedPictures::HEALTH_UNWRITABLE ), $this->pictures->health() );
		$this->files->library = null;
		self::assertSame( array( 'status' => Health::RECOMMENDED, 'code' => ComposedPictures::HEALTH_NO_LIBRARY ), $this->pictures->health(), 'no library says so first: nothing could be drawn anyway' );
	}
}
