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
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\PictureFiles;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Service\Health;
use CreativeBranding\SkateboardBuilder\Port\ComposedLayers;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeComposedLayers;
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

	private InMemoryKits $kits;

	private ComposedPictures $pictures;

	private FakeLogger $logger;

	private FakeComposedLayers $answers;

	protected function setUp(): void {
		$kits       = new InMemoryKits();
		$this->kits = $kits;
		$kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$this->queue               = new FakeQueue();
		$this->layers              = new InMemoryLayerImages();
		$this->layers->attachments = array( 101 => 10, 301 => 30 );
		$this->layers->images      = array(
			10 => new ImageFacts( 'image/png', 420, 1626, true ),
			30 => new ImageFacts( 'image/png', 130, 216, true ),
		);
		$this->files               = new InMemoryPictureFiles();
		$this->logger              = new FakeLogger();
		$this->answers             = new FakeComposedLayers();
		$this->pictures            = new ComposedPictures(
			$kits,
			new BuildState( $kits, new InMemoryShopSettings(), new FakeLogger() ),
			new LayerArtwork( $this->layers, new InMemoryLayerRenditions() ),
			$this->files,
			$this->queue,
			new FakeClock( self::NOW ),
			$this->logger,
			$this->answers
		);
	}

	public function test_a_build_is_asked_for_once_with_its_ids_and_nothing_while_the_queue_is_full(): void {
		self::assertSame( Options::JOB_COMPOSE_PICTURE, ComposedPictures::JOB, 'the hooks run the action the service queues' );
		self::assertTrue( $this->pictures->ask( self::KIT, self::STREET ) );
		self::assertTrue( $this->pictures->ask( self::KIT, self::STREET ), 'asked again while it waits' );
		self::assertSame( array( array( Options::JOB_COMPOSE_PICTURE, array( 'kit' => self::KIT, 'picks' => self::STREET ) ) ), $this->queue->distinct, 'one job, with the IDs alone' );
		self::assertFalse( $this->pictures->ask( self::KIT, array() ), 'a build of nothing' );
		self::assertFalse( $this->pictures->ask( 0, self::STREET ), 'no kit' );
		self::assertTrue( $this->pictures->ask( 1, self::STREET ), 'the first ID a kit can have' );
		$this->queue->distinct = array_slice( $this->queue->distinct, 0, 1 );
		unset( $this->queue->pending[ Options::JOB_COMPOSE_PICTURE ] );

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

		$offered  = $this->kits->offered;
		$readings = $this->kits->readings;
		$this->pictures->url( self::KIT, self::STREET, ComposedPictures::CART );
		self::assertSame( array( $offered, $readings + 1 ), array( $this->kits->offered, $this->kits->readings ), 'the cart\'s question reads the build\'s parts once and runs no fit' );

		$names = array_keys( $this->files->drawn );
		self::assertSame( 3, $this->pictures->compose( self::KIT, self::STREET ) );
		self::assertSame( $names, array_keys( $this->files->drawn ), 'the same build is the same three names' );
	}

	// The auditor's 1.1.0 minor 7: a cart's line asks on every view until its picture is made. wanted() reads the build's
	// parts once a call, and asks the queue once a picture for the life of the service, a request.
	public function test_a_picture_wanted_is_read_once_a_call_and_asked_for_once_a_request(): void {
		$readings = $this->kits->readings;
		self::assertNull( $this->pictures->wanted( self::KIT, self::STREET, ComposedPictures::CART ) );
		self::assertSame( $readings + 1, $this->kits->readings, 'one read of the parts' );
		self::assertSame( array( array( Options::JOB_COMPOSE_PICTURE, array( 'kit' => self::KIT, 'picks' => self::STREET ) ) ), $this->queue->distinct, 'asked for, as a page asks' );

		$this->queue->distinct = array();
		self::assertNull( $this->pictures->wanted( self::KIT, self::STREET, ComposedPictures::CART ) );
		self::assertSame( array(), $this->queue->distinct, 'not asked again' );

		$this->answers->answers = array( 101 => array( ComposedPictures::CART => ComposedLayers::BLURRED ) );
		self::assertNull( $this->pictures->wanted( self::KIT, self::STREET, ComposedPictures::CART ) );
		self::assertCount( 1, $this->queue->distinct, 'a picture of another name is asked for' );
		$this->answers->answers = array();

		$this->pictures->compose( self::KIT, self::STREET );
		$url = $this->pictures->wanted( self::KIT, self::STREET, ComposedPictures::CART );
		self::assertSame( $this->pictures->url( self::KIT, self::STREET, ComposedPictures::CART ), $url );
		self::assertNotNull( $url );
		self::assertSame( array( basename( $url ) => self::NOW ), array_intersect_key( $this->files->used, array( basename( $url ) => true ) ), 'marked as shown' );

		self::assertNull( $this->pictures->wanted( self::KIT, array( SlotKey::DECK => 999 ), ComposedPictures::CART ), 'a part gone' );
		$this->files->library  = null;
		$this->queue->distinct = array();
		self::assertNull( $this->pictures->wanted( self::KIT, array( SlotKey::DECK => 101 ), ComposedPictures::CART ) );
		self::assertSame( array(), $this->queue->distinct, 'a host that can\'t draw it asks nothing' );
	}

	// D-252: a rider build's tile shows the build's composed picture, asked for as a page asks until it is made.
	public function test_a_presets_tile_has_its_picture_once_made_and_asks_for_it_until_then(): void {
		self::assertNull( $this->pictures->tile( self::KIT, self::STREET ) );
		self::assertSame( array( array( Options::JOB_COMPOSE_PICTURE, array( 'kit' => self::KIT, 'picks' => self::STREET ) ) ), $this->queue->distinct, 'asked for' );
		$this->pictures->compose( self::KIT, self::STREET );
		self::assertSame( $this->pictures->url( self::KIT, self::STREET, ComposedPictures::CART ), $this->pictures->tile( self::KIT, self::STREET ), 'the cart\'s, a square' );
		self::assertCount( 1, $this->queue->distinct, 'and not asked again' );
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
		$cases['a required slot left unpicked'] = array( self::KIT, $short );
		foreach ( $cases as $case => [ $kit, $picks ] ) {
			self::assertSame( 0, $this->pictures->compose( $kit, $picks ), $case );
			self::assertNull( $this->pictures->url( $kit, $picks, ComposedPictures::CART ), $case );
		}
		self::assertSame( 0, $this->files->asked, 'nothing is drawn' );
	}

	/**
	 * A review that cannot go to the cart, with nothing refused: the kit's trucks are all gone, so no recommendation
	 * fills the slot, and what is counted is still the shopper's own picks.
	 *
	 * @param BuildState $states The states.
	 */
	private function unready( BuildState $states ): State {
		$parts                     = Shop::parts();
		$parts[ SlotKey::TRUCKS ]  = array();
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$short = self::STREET;
		unset( $short[ SlotKey::TRUCKS ] );
		$unready = $states->of( self::KIT, $short, null, State::REVIEW, null );
		self::assertNotNull( $unready );
		self::assertFalse( $unready->ready() );
		self::assertSame( array(), $unready->refused );
		self::assertSame( array_keys( $short ), array_keys( $unready->counted() ), 'the picks alone' );
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
		return $unready;
	}

	public function test_no_picture_is_drawn_for_a_review_that_cannot_go_to_the_cart(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array();
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$short = self::STREET;
		unset( $short[ SlotKey::TRUCKS ] );
		self::assertSame( 0, $this->pictures->compose( self::KIT, $short ) );
		self::assertSame( 0, $this->files->asked, 'nothing is drawn of a build with no trucks to be had' );
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
		self::assertNull( $this->pictures->url( self::KIT, self::STREET, ComposedPictures::CART ) );
		self::assertSame( array(), $this->files->used, 'a picture not made yet is not marked' );
		$this->pictures->compose( self::KIT, self::STREET );
		self::assertSame( array(), $this->files->used, 'drawing is not showing' );
		$url = (string) $this->pictures->url( self::KIT, self::STREET, ComposedPictures::CART );
		self::assertSame( array( basename( $url ) => self::NOW ), $this->files->used );

		$this->files->pruned = array();
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

	// The audit of milestone 7, should-fix: an anonymous page's ask could fill the queue, leaving a shopper's add to
	// the cart with no picture. A page takes PAGE_MOST of the places; the rest are kept for the cart.
	public function test_a_pages_asks_take_at_most_their_share_of_the_queue_and_the_cart_keeps_the_rest(): void {
		for ( $more = 0; $more < ComposedPictures::PAGE_MOST; $more++ ) {
			self::assertTrue( $this->pictures->ask( self::KIT, array( SlotKey::DECK => 1000 + $more ), ComposedPictures::FROM_PAGE ), 'a page\'s ask ' . $more );
		}
		self::assertFalse( $this->pictures->ask( self::KIT, array( SlotKey::DECK => 2000 ), ComposedPictures::FROM_PAGE ), 'the page\'s share is taken' );
		self::assertTrue( $this->pictures->ask( self::KIT, self::STREET ), 'a cart\'s add still has a place' );
		self::assertLessThan( ComposedPictures::QUEUED_MOST, ComposedPictures::PAGE_MOST );
	}

	// The audit of milestone 7, should-fix: the 200 MB cap held only once a day, so a day of asks could fill a disk.
	public function test_a_job_with_something_to_draw_holds_the_folder_to_its_cap_before_drawing(): void {
		$this->layers->attachments = array();
		$this->pictures->compose( self::KIT, self::STREET );
		self::assertSame( array(), $this->files->pruned, 'nothing to draw, nothing pruned' );

		$this->layers->attachments = array( 101 => 10 );
		$this->pictures->compose( self::KIT, self::STREET );
		self::assertSame( array( array( self::NOW - 30 * 86400, 200 * 1024 * 1024 ) ), $this->files->pruned );
		self::assertSame( array( 'prune', 'draw', 'draw', 'draw' ), $this->files->order, 'the cap first, then the drawing' );
	}

	// The audit of milestone 7, should-fix: a picture that could not be drawn was written nowhere.
	public function test_a_picture_that_cannot_be_drawn_is_logged_hourly_with_the_kit_and_the_reason(): void {
		$this->files->refuse = PictureFiles::LAYER_UNREADABLE;
		self::assertSame( 0, $this->pictures->compose( self::KIT, self::STREET ) );
		self::assertSame( array( 'picture_failed_' . self::KIT ), array_keys( $this->logger->keys ) );
		self::assertCount( 1, $this->logger->lines, 'three refused, one line in the hour' );
		self::assertSame( array( 'kit' => self::KIT, 'reason' => PictureFiles::LAYER_UNREADABLE ), $this->logger->lines[0]['context'], 'the kit and why, nothing of the shopper' );
		self::assertSame( Logger::WARNING, $this->logger->lines[0]['level'] );

		$this->logger->lines = array();
		$this->files->refuse = null;
		self::assertSame( 3, $this->pictures->compose( self::KIT, self::STREET ) );
		self::assertSame( array(), $this->logger->lines, 'a picture drawn is not logged' );
	}

	// The audit of milestone 7, should-fix: a shared build's first unfurl was never its picture, since only the
	// crawler's fetch asked for it. The review's answer, where the link is offered, asks; a page's share.
	public function test_a_ready_review_offering_its_link_asks_for_its_picture_once_and_nothing_else_does(): void {
		$states = new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() );
		$review = $states->of( self::KIT, self::STREET, null, State::REVIEW, null );
		self::assertNotNull( $review );
		// Before its picture is made, so that only the state keeps them from asking.
		$trucks = $states->of( self::KIT, self::STREET, null, SlotKey::TRUCKS, null );
		self::assertNotNull( $trucks );
		self::assertTrue( $trucks->ready() );
		$this->pictures->offered( $trucks );
		$unready = $this->unready( $states );
		$this->pictures->offered( $unready );
		self::assertSame( array(), $this->queue->distinct, 'another step, or a build not ready, asks nothing' );

		$this->pictures->offered( $review );
		self::assertSame( array( array( Options::JOB_COMPOSE_PICTURE, array( 'kit' => self::KIT, 'picks' => self::STREET ) ) ), $this->queue->distinct );

		$this->pictures->compose( self::KIT, self::STREET );
		$this->queue->distinct = array();
		$this->pictures->offered( $review );
		self::assertSame( array(), $this->queue->distinct, 'its picture is made' );

		$this->layers->attachments = array();
		$this->files->drawn        = array();
		$this->pictures->offered( $review );
		self::assertSame( array(), $this->queue->distinct, 'a build with nothing to draw asks nothing' );
	}

	// Cowork's review of 1.0.0, F19 (#105; D-309): another plugin answers for each part's layer in each use's picture.
	// Mature Content blurs a flagged deck on the share card alone and leaves the cart and the emails as they are.
	public function test_each_parts_layer_is_drawn_as_the_filter_answers_for_each_use(): void {
		$this->answers->answers = array(
			101 => array(
				ComposedPictures::SHARE => ComposedLayers::BLURRED,
				ComposedPictures::EMAIL => ComposedLayers::LEFT_OUT,
			),
		);
		self::assertSame( 3, $this->pictures->compose( self::KIT, self::STREET ) );
		$drawn = array();
		foreach ( $this->files->drawn as $composition ) {
			$drawn[ $composition->canvas->width ] = array_map( static fn( $placement ): array => array( $placement->attachment, $placement->blurred ), $composition->placements );
		}
		self::assertSame( array( array( 10, false ), array( 30, false ), array( 30, false ), array( 30, false ), array( 30, false ) ), $drawn[480], 'the cart\'s: as it is' );
		self::assertSame( array( array( 10, true ), array( 30, false ), array( 30, false ), array( 30, false ), array( 30, false ) ), $drawn[1200], 'the share card\'s: the deck blurred' );
		self::assertSame( array( array( 30, false ), array( 30, false ), array( 30, false ), array( 30, false ) ), $drawn[600], 'the emails\': the deck left out' );
		self::assertContains( array( 101, SlotKey::DECK, self::KIT, ComposedPictures::SHARE ), $this->answers->asked, 'asked with the part, its slot, the kit and the use' );
		self::assertContains( array( 301, SlotKey::WHEELS, self::KIT, ComposedPictures::CART ), $this->answers->asked );
		foreach ( array_keys( ComposedPictures::CANVASES ) as $use ) {
			self::assertNotNull( $this->pictures->url( self::KIT, self::STREET, $use ), 'found by the name it was drawn with: ' . $use );
		}
		self::assertTrue( $this->pictures->shown( self::KIT, SlotKey::DECK, 101, ComposedPictures::CART ) );
		self::assertFalse( $this->pictures->shown( self::KIT, SlotKey::DECK, 101, ComposedPictures::SHARE ), 'the deck\'s own photo is no stand-in for a share card that blurs it (#106)' );
		self::assertFalse( $this->pictures->shown( self::KIT, SlotKey::DECK, 101, ComposedPictures::EMAIL ) );
	}

	public function test_a_flag_set_after_the_pictures_were_drawn_gives_new_pictures_and_a_flag_lifted_finds_the_old(): void {
		$this->pictures->compose( self::KIT, self::STREET );
		$sharp = $this->pictures->url( self::KIT, self::STREET, ComposedPictures::SHARE );
		self::assertNotNull( $sharp );

		$this->answers->answers = array( 101 => array( ComposedPictures::SHARE => ComposedLayers::BLURRED ) );
		self::assertNull( $this->pictures->url( self::KIT, self::STREET, ComposedPictures::SHARE ), 'the sharp picture is never shown once the deck is flagged' );
		self::assertNotNull( $this->pictures->url( self::KIT, self::STREET, ComposedPictures::CART ), 'the cart\'s, which the filter leaves, is kept' );
		self::assertTrue( $this->pictures->drawable( self::KIT, self::STREET, ComposedPictures::SHARE ), 'and a new one is there to draw' );
		$this->pictures->compose( self::KIT, self::STREET );
		$blurred = $this->pictures->url( self::KIT, self::STREET, ComposedPictures::SHARE );
		self::assertNotNull( $blurred );
		self::assertNotSame( $sharp, $blurred );

		$this->answers->answers = array();
		self::assertSame( $sharp, $this->pictures->url( self::KIT, self::STREET, ComposedPictures::SHARE ), 'the flag lifted, the sharp one is found again' );
	}

	// The auditor's 1.1.0 minor 5: the service alone reads the filter's answer, so an answer it doesn't know, whatever
	// the port hands it, is drawn as it is, under the name the pictures as they are have.
	public function test_an_answer_that_is_none_of_the_three_draws_the_layer_as_it_is(): void {
		$this->answers->answers = array( 101 => array_fill_keys( array_keys( ComposedPictures::CANVASES ), 'junk' ) );
		self::assertSame( 3, $this->pictures->compose( self::KIT, self::STREET ) );
		self::assertCount( 3, $this->files->drawn );
		foreach ( $this->files->drawn as $composition ) {
			self::assertSame( array( 10, false ), array( $composition->placements[0]->attachment, $composition->placements[0]->blurred ), 'the deck drawn sharp' );
		}
		foreach ( array_keys( ComposedPictures::CANVASES ) as $use ) {
			self::assertTrue( $this->pictures->shown( self::KIT, SlotKey::DECK, 101, $use ), $use );
		}
		$junk = $this->pictures->url( self::KIT, self::STREET, ComposedPictures::SHARE );
		self::assertNotNull( $junk );
		$this->answers->answers = array();
		self::assertSame( $junk, $this->pictures->url( self::KIT, self::STREET, ComposedPictures::SHARE ), 'the name the pictures as they are have' );
	}

	public function test_a_build_whose_every_layer_is_left_out_has_no_picture_to_draw(): void {
		$this->answers->answers = array(
			101 => array( ComposedPictures::CART => ComposedLayers::LEFT_OUT ),
			301 => array( ComposedPictures::CART => ComposedLayers::LEFT_OUT ),
		);
		self::assertFalse( $this->pictures->drawable( self::KIT, self::STREET, ComposedPictures::CART ) );
		self::assertSame( 2, $this->pictures->compose( self::KIT, self::STREET ), 'the share card and the emails\'' );
	}
}
