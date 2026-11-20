<?php
/**
 * Site Health, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\CheckScreen;
use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\Health;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Health\SiteHealth;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Service\PartRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use WP_REST_Request;

/**
 * Spec, Resilience: Site Health checks the specs index and failed jobs, and its Info tab lists the plugin's versions
 * and settings; a failure shows in Site Health.
 */
final class SiteHealthTest extends IntegrationTestCase {

	public function tear_down(): void {
		delete_option( Options::REINDEX );
		parent::tear_down();
	}

	/**
	 * The tests as Site Health gathers them, with the plugin's filter attached.
	 *
	 * @return array<string, mixed>
	 */
	private function tests(): array {
		$this->service( SiteHealth::class )->register();
		return (array) apply_filters( 'site_status_tests', array( 'direct' => array( 'core' => array( 'label' => 'Core' ) ), 'async' => array() ) );
	}

	public function test_site_health_gains_three_direct_tests_and_keeps_its_own(): void {
		$tests = $this->tests();
		$ids = array_keys( $tests['direct'] );
		self::assertSame( 'core', $ids[0] );
		// WooCommerce adds its own between.
		// On a network, a fourth: the must-use file that keeps deletions whole (#8, D-220).
		$ours = is_multisite() ? array( 'cbuild_index', 'cbuild_jobs', 'cbuild_pictures', 'cbuild_network' ) : array( 'cbuild_index', 'cbuild_jobs', 'cbuild_pictures' );
		self::assertSame( $ours, array_slice( $ids, -count( $ours ) ) );
		// WooCommerce 11.3.0 adds an async test of its own before it (D-139).
		self::assertSame( array( 'cbuild_catalogue' ), array_slice( array_keys( $tests['async'] ), -1 ) );
		self::assertSame( array(), array_values( array_filter( array_keys( $tests['async'] ), static fn ( string $id ): bool => str_starts_with( $id, 'cbuild_' ) && 'cbuild_catalogue' !== $id ) ) );
		self::assertSame( 'The skateboard builder\'s specs index', $tests['direct']['cbuild_index']['label'] );
		self::assertIsCallable( $tests['direct']['cbuild_index']['test'] );
		self::assertIsCallable( $tests['direct']['cbuild_jobs']['test'] );
		self::assertIsCallable( $tests['direct']['cbuild_pictures']['test'] );
		// What another plugin broke is handed on as it came.
		self::assertSame( 'broken', $this->service( SiteHealth::class )->tests( 'broken' ) );
		self::assertSame( 'broken', $this->service( SiteHealth::class )->info( 'broken' ) );
	}

	public function test_the_index_is_good_until_a_reindex_stalls_or_fails(): void {
		$tests = $this->tests();
		$index = $tests['direct']['cbuild_index']['test'];

		$result = $index();
		self::assertSame( 'good', $result['status'] );
		self::assertSame( 'cbuild_index', $result['test'] );
		self::assertSame( 'The skateboard builder\'s specs index is up to date', $result['label'] );
		self::assertSame( array( 'label' => 'Skateboard builder', 'color' => 'blue' ), $result['badge'] );
		self::assertSame( '', $result['actions'] );

		// Asked for, with its job waiting: under way.
		$this->service( IndexJobs::class )->requestReindex();
		$result = $index();
		self::assertSame( 'good', $result['status'] );
		self::assertSame( 'The skateboard builder\'s specs index is being rebuilt', $result['label'] );

		// Its job lost: stalled, with the way to start it again.
		as_unschedule_all_actions( Options::JOB_REINDEX );
		$result = $index();
		self::assertSame( 'recommended', $result['status'] );
		self::assertSame( 'orange', $result['badge']['color'] );
		self::assertSame( 'The skateboard builder\'s specs index stopped part way', $result['label'] );
		self::assertStringContainsString( 'Start it again with &quot;Read every part again&quot; on the Catalogue check.', $result['description'] );
		self::assertStringContainsString( 'page=cbuild&#038;tab=check">Open the Catalogue check</a>', $result['actions'] );
		// The M9 audit's minor 3: a count is said through _n().
		$this->service( ReindexStore::class )->save( ( new ReindexState( ReindexState::CATALOGUE, 1, 1, 1 ) )->toArray() );
		self::assertStringContainsString( 'got as far as 1 part and has no job', $index()['description'] );

		$this->service( ReindexStore::class )->save( ( new ReindexState( ReindexState::FAILED, 0, 0, 1, null, false, ReindexState::MAX_FAILURES, '<b>gone</b>' ) )->toArray() );
		$result = $index();
		self::assertSame( 'critical', $result['status'] );
		self::assertSame( 'red', $result['badge']['color'] );
		self::assertSame( 'The skateboard builder\'s specs index was not rebuilt', $result['label'] );
		self::assertStringStartsWith( '<p>Reading every part again stopped after five failures', $result['description'] );
		self::assertStringContainsString( 'Start it again with &quot;Read every part again&quot; on the Catalogue check.', $result['description'] );
		self::assertStringContainsString( 'page=cbuild&#038;tab=check">Open the Catalogue check</a>', $result['actions'] );
		self::assertStringNotContainsString( 'gone', $result['description'] . $result['actions'], 'the error stays in the log' );
	}

	public function test_a_failed_job_reaches_site_health_with_where_to_look(): void {
		$tests = $this->tests();
		$jobs  = $tests['direct']['cbuild_jobs']['test'];
		self::assertSame( 'good', $jobs()['status'] );
		self::assertSame( 'No background job of the skateboard builder has failed', $jobs()['label'] );
		self::assertSame( '', $jobs()['actions'] );

		\ActionScheduler::store()->mark_failure( (string) as_enqueue_async_action( Options::JOB_INDEX_PART, array( 'part' => 1 ), Options::QUEUE_GROUP ) );
		$result = $jobs();
		self::assertSame( 'recommended', $result['status'] );
		self::assertSame( 'cbuild_jobs', $result['test'] );
		self::assertSame( '1 background job of the skateboard builder has failed', $result['label'] );
		self::assertStringContainsString( 'page=wc-status&#038;tab=action-scheduler&#038;status=failed">See the failed jobs</a>', $result['actions'] );
		self::assertStringContainsString( 'page=cbuild&#038;tab=check">Open the Catalogue check</a>', $result['actions'] );
	}

	// Plan, milestone 7: "A host with neither image library makes no composed picture. It shows the deck's photo and
	// says so in Site Health" (D-198).
	public function test_a_host_that_cannot_draw_or_save_pictures_hears_of_it(): void {
		$pictures = $this->tests()['direct']['cbuild_pictures']['test'];
		$result   = $pictures();
		self::assertSame( array( 'good', 'cbuild_pictures', 'The skateboard builder can compose pictures' ), array( $result['status'], $result['test'], $result['label'] ), 'wp-env has GD and Imagick' );

		$with = fn ( WpPictureFiles $files ): SiteHealth => new SiteHealth(
			fn (): Health => $this->service( Health::class ),
			fn (): ShopSettings => $this->service( ShopSettings::class ),
			fn (): MigrationStore => $this->service( MigrationStore::class ),
			fn (): CatalogueCheck => $this->service( CatalogueCheck::class ),
			fn (): ComposedPictures => new ComposedPictures( $this->service( Kits::class ), $this->service( BuildState::class ), $this->service( LayerArtwork::class ), $files, $this->service( Queue::class ), $this->service( Clock::class ), $this->service( Logger::class ) )
		);
		$none = $with( new WpPictureFiles( array() ) )->pictures();
		self::assertSame( array( 'recommended', 'The skateboard builder cannot compose pictures on this server' ), array( $none['status'], $none['label'] ) );
		self::assertStringContainsString( 'neither GD nor Imagick', $none['description'] );

		$uploads = static fn ( array $dirs ): array => array( 'basedir' => '/proc/cbuild-not-writable', 'baseurl' => 'https://example.com/none' ) + $dirs;
		add_filter( 'upload_dir', $uploads );
		try {
			$locked = $with( new WpPictureFiles() )->pictures();
		} finally {
			remove_filter( 'upload_dir', $uploads );
		}
		self::assertSame( array( 'recommended', 'The skateboard builder cannot save composed pictures' ), array( $locked['status'], $locked['label'] ) );
		// Cowork's M7 review, M4: emails come in milestone 8, so Site Health does not speak of them yet.
		foreach ( array( $result, $none, $locked ) as $said ) {
			self::assertStringNotContainsStringIgnoringCase( 'email', $said['description'] );
		}
	}

	public function test_parts_whose_layer_images_have_no_resized_copies_are_counted_in_the_pictures_test(): void {
		// Cowork's M7 review, F2 (D-209). Two decks with layer images; one has its renditions, one has none.
		$kits   = new InMemoryKits();
		$layers = new InMemoryLayerImages();
		$made   = new InMemoryLayerRenditions();
		$index  = new InMemorySpecIndex();
		$kits->add( 3, Shop::kit(), Shop::parts() );
		foreach ( array( 101 => 11, 102 => 12 ) as $part => $image ) {
			$index->replace( $part, array( new IndexedSpec( SpecKey::DeckWidth, '8.0000', Origin::Field ) ), ( new FakeClock() )->now() );
			$layers->attachments[ $part ] = $image;
			$layers->images[ $image ]     = new ImageFacts( 'image/png', 420, 1626, true );
			$kits->slotsOf[ $part ]       = array( 'deck' );
		}
		$layers->layered = array( 101, 102 );
		$renditions      = new PartRenditions( $kits, $index, $layers, $made );
		$renditions->make( 101 );
		$health = new SiteHealth(
			fn (): Health => $this->service( Health::class ),
			fn (): ShopSettings => $this->service( ShopSettings::class ),
			fn (): MigrationStore => $this->service( MigrationStore::class ),
			fn (): CatalogueCheck => $this->service( CatalogueCheck::class ),
			fn (): ComposedPictures => $this->service( ComposedPictures::class ),
			fn (): PartRenditions => $renditions
		);
		// Cowork's recheck of 0.7.0, N1 (D-214): each wording only in its own case. Copies not made yet, with nothing
		// failed: the logs hold no reason, so they are not named.
		delete_transient( Options::RENDITIONS_FAILED );
		$one = $health->pictures();
		self::assertSame( array( 'recommended', 'cbuild_pictures', '1 part\'s layer image has no resized copies' ), array( $one['status'], $one['test'], $one['label'] ) );
		self::assertStringContainsString( '&quot;Read every part again&quot; on the Catalogue check makes the missing copies in the background', $one['description'] );
		self::assertStringNotContainsString( 'Logs', $one['description'] );
		self::assertStringNotContainsString( 'failed', $one['description'] );
		self::assertStringContainsString( CheckScreen::url(), html_entity_decode( $one['actions'] ) );
		self::assertStringNotContainsString( 'neither AVIF nor WebP', $one['description'] );

		// A copy failed: the logs have the reason.
		set_transient( Options::RENDITIONS_FAILED, 1, DAY_IN_SECONDS );
		$failed = $health->pictures()['description'];
		self::assertStringContainsString( 'Making a copy failed in the last 30 days. WooCommerce &gt; Status &gt; Logs has the reason', $failed );
		self::assertStringNotContainsString( 'neither AVIF nor WebP', $failed );

		// The audit of the fixes: where the host's editors write neither type, reading the parts again cannot help.
		add_filter( 'wp_image_editors', '__return_empty_array' );
		$none = $health->pictures()['description'];
		remove_filter( 'wp_image_editors', '__return_empty_array' );
		self::assertStringContainsString( 'This server&#039;s image editors write neither AVIF nor WebP. Your host can turn on WebP in GD or Imagick', $none );
		self::assertStringNotContainsString( 'Logs', $none, 'even with a failure recorded: the host is the cause' );
		delete_transient( Options::RENDITIONS_FAILED );

		$renditions->make( 102 );
		self::assertSame( 'The skateboard builder can compose pictures', $health->pictures()['label'] );
	}

	public function test_an_import_that_stopped_or_lost_its_job_reaches_site_health(): void {
		$tests = $this->tests();
		$jobs  = $tests['direct']['cbuild_jobs']['test'];
		$store = $this->service( ImportStore::class );

		$store->save( ( new ImportState( ImportState::FAILED, 't', 'id', '.', 1, array( 'id' => 0 ), array(), 300, 100, 90, 10 ) )->toArray() );
		$result = $jobs();
		self::assertSame( 'recommended', $result['status'] );
		self::assertSame( 'The skateboard builder\'s last specs import stopped before its end', $result['label'] );
		self::assertStringContainsString( 'Import the file again', $result['description'] );
		self::assertStringContainsString( 'page=cbuild&#038;tab=import">Open Import and export</a>', $result['actions'] );

		// Running with its job waiting is fine; with none, it has stalled.
		$store->save( ( new ImportState( ImportState::RUNNING, 't', 'id', '.', 1, array( 'id' => 0 ), array(), 300, 100, 90, 10 ) )->toArray() );
		as_enqueue_async_action( Options::JOB_IMPORT, array(), Options::QUEUE_GROUP, true );
		self::assertSame( 'good', $jobs()['status'] );
		as_unschedule_all_actions( Options::JOB_IMPORT );
		$result = $jobs();
		self::assertSame( 'recommended', $result['status'] );
		self::assertSame( 'The skateboard builder\'s specs import has no job left to run it', $result['label'] );
		self::assertStringContainsString( 'Opening Import and export starts it again', $result['description'] );
		self::assertStringContainsString( 'page=cbuild&#038;tab=import">Open Import and export</a>', $result['actions'] );
		delete_option( Options::IMPORT );
	}

	/**
	 * A truck whose own field and maker's chart disagree about its axle.
	 */
	private function truckThatDisagrees(): int {
		$chart = $this->service( SpecTables::class )->create( 'Independent', SpecTable::read( array( array( 'size' => '149', 'specs' => array( 'axle_width' => '8.5000' ) ) ) ) );
		$truck = new \WC_Product_Simple();
		$truck->set_name( 'Stage 11 149' );
		$truck->update_meta_data( Options::META_SPECS, array( 'axle_width' => '8.0000' ) );
		$truck->update_meta_data( Options::META_SPEC_TABLE, array( 'table' => $chart, 'size' => '149' ) );
		return $truck->save();
	}

	// Spec, Resilience: "Site Health checks the specs index, failed jobs and the Catalogue check" (D-094).
	public function test_the_catalogue_check_is_an_async_test_wordpress_asks_for_over_rest(): void {
		$tests = $this->tests();
		$test  = $tests['async']['cbuild_catalogue'];
		self::assertSame( 'The skateboard builder\'s Catalogue check', $test['label'] );
		self::assertTrue( $test['has_rest'] );
		self::assertSame( rest_url( 'cbuild/v1/site-health/catalogue-check' ), $test['test'] );
		self::assertSame( array( $this->service( SiteHealth::class ), 'catalogue' ), $test['async_direct_test'], 'the weekly check cannot sign in, and calls it' );
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		self::assertArrayHasKey( 'cbuild_catalogue', \WP_Site_Health::get_tests()['async'] );
		self::assertArrayNotHasKey( 'cbuild_catalogue', \WP_Site_Health::get_tests()['direct'], 'it reads every part, so the page does not wait for it' );
	}

	// The auditor's 1.0.0 minor 11: the test counts no shared cards (D-281), so it doesn't work them out.
	public function test_the_catalogue_test_does_not_work_out_which_variations_share_a_card(): void {
		$this->truckThatDisagrees();
		\CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory::kit();
		$looks = new \CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLooks();
		$check = $this->service( CatalogueCheck::class )->withLooks( $looks );
		$check->run();
		self::assertNotSame( array(), $looks->asked, 'the check\'s own screen does' );
		$looks->asked = array();
		$health       = new SiteHealth(
			fn (): Health => $this->service( Health::class ),
			fn (): ShopSettings => $this->service( ShopSettings::class ),
			fn (): MigrationStore => $this->service( MigrationStore::class ),
			fn (): CatalogueCheck => $check,
			fn (): ComposedPictures => $this->service( ComposedPictures::class )
		);
		self::assertSame( 'recommended', $health->catalogue()['status'] );
		self::assertSame( array(), $looks->asked );
	}

	public function test_the_catalogue_check_is_good_until_a_part_has_something_to_fix(): void {
		$health = $this->service( SiteHealth::class );
		$result = $health->catalogue();
		self::assertSame( array( 'label', 'status', 'badge', 'description', 'actions', 'test' ), array_keys( $result ) );
		self::assertSame( 'good', $result['status'] );
		self::assertSame( 'cbuild_catalogue', $result['test'] );
		self::assertSame( 'The skateboard builder\'s Catalogue check found nothing to fix', $result['label'] );
		self::assertSame( '', $result['actions'] );

		$this->truckThatDisagrees();
		$result = $health->catalogue();
		self::assertSame( 'recommended', $result['status'] );
		self::assertSame( 'orange', $result['badge']['color'] );
		self::assertSame( 'The skateboard builder\'s Catalogue check found 1 thing to fix', $result['label'] );
		self::assertStringContainsString( 'in 1 part', $result['description'] );
		self::assertStringContainsString( 'href="' . esc_url( CheckScreen::url() ) . '"', $result['actions'] );
		self::assertStringContainsString( 'Open the Catalogue check', $result['actions'] );
	}

	/**
	 * Site Health over one kit whose decks are these, and whose other parts have no layer image either.
	 *
	 * @param list<\CreativeBranding\SkateboardBuilder\Domain\Kit\Part> $decks The decks.
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	private function catalogueOver( array $decks ): array {
		$kits = new InMemoryKits();
		$kits->add(
			5,
			KitDocuments::kit(),
			array(
				'deck'     => $decks,
				'trucks'   => array( Builds::truck( '149' ) ),
				'wheels'   => array( Builds::wheel( '54' ) ),
				'bearings' => array( Builds::bearings() ),
				'hardware' => array( Builds::bolts() ),
				'grip'     => array( Builds::grip() ),
				'risers'   => array( Builds::riser( '0.125' ) ),
			),
			'Complete'
		);
		$check = ( new CatalogueCheck( $kits, $this->service( PartCatalogue::class ), $this->service( SpecIndexer::class ), $this->service( ReindexStore::class ), $this->service( ImportStore::class ), $this->service( Queue::class ) ) )->withLayers( $this->service( LayerImages::class ) );
		return ( new SiteHealth(
			fn (): Health => $this->service( Health::class ),
			fn (): ShopSettings => $this->service( ShopSettings::class ),
			fn (): MigrationStore => $this->service( MigrationStore::class ),
			static fn (): CatalogueCheck => $check,
			fn (): ComposedPictures => $this->service( ComposedPictures::class )
		) )->catalogue();
	}

	public function test_parts_with_no_layer_image_have_their_own_wording_and_are_not_things_to_fix(): void {
		// Cowork's M7 review, F4 (D-211): a missing layer image does not keep a part out of the builder.
		$alone = $this->catalogueOver( array( Builds::deck( '8.25', 900001 ), Builds::deck( '8.5', 900002 ) ) );
		self::assertSame( array( 'recommended', 'The skateboard builder\'s Catalogue check found 5 parts with no layer image' ), array( $alone['status'], $alone['label'] ), 'two decks, a truck, a wheel and a grip' );
		self::assertSame( '<p>5 parts the picture draws have no layer image. Each is still offered, and the picture shows its labelled photo beside the board until one is chosen on its product page. Nothing it found keeps a part out of the builder.</p>', $alone['description'] );
		self::assertStringContainsString( 'Open the Catalogue check', $alone['actions'] );

		// Decks missing two specs each: four things to fix, and the five parts' sentence after them.
		$both = $this->catalogueOver( array( Parts::of( array( 'deck_width' => '8.25' ), 900001 ), Parts::of( array( 'deck_width' => '8.5' ), 900002 ) ) );
		self::assertSame( 'The skateboard builder\'s Catalogue check found 4 things to fix', $both['label'], 'the layer images are not among them' );
		self::assertStringEndsWith( 'The check lists each finding with a link to where it is fixed. 5 parts the picture draws have no layer image. Each is still offered, and the picture shows its labelled photo beside the board until one is chosen on its product page.</p>', $both['description'] );
	}

	public function test_the_route_answers_who_may_see_site_health_and_nobody_else(): void {
		$this->service( SiteHealth::class )->register();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );
		$route = '/cbuild/v1/site-health/catalogue-check';
		self::assertArrayHasKey( $route, rest_get_server()->get_routes( 'cbuild/v1' ) );
		$this->truckThatDisagrees();

		wp_set_current_user( 0 );
		self::assertSame( 401, rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) )->get_status() );
		// A shop manager runs the check on its own screen; Site Health is an administrator's.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		self::assertSame( 403, rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) )->get_status() );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		if ( is_multisite() ) {
			// On a network Site Health is the network's: a site's administrator is refused, as WordPress's own tests are.
			self::assertSame( 403, rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) )->get_status() );
			grant_super_admin( $admin );
		}
		$answer = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) );
		self::assertSame( 200, $answer->get_status() );
		self::assertSame( 'recommended', $answer->get_data()['status'] );
		self::assertSame( 'cbuild_catalogue', $answer->get_data()['test'] );
		self::assertSame( 404, rest_get_server()->dispatch( new WP_REST_Request( 'POST', $route ) )->get_status(), 'it changes nothing, and takes no post' );
	}

	public function test_wordpress_itself_runs_the_tests_and_takes_their_results(): void {
		$this->service( SiteHealth::class )->register();
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		$all = \WP_Site_Health::get_tests();
		self::assertArrayHasKey( 'cbuild_index', $all['direct'] );
		self::assertArrayHasKey( 'cbuild_jobs', $all['direct'] );
		foreach ( array( 'cbuild_index', 'cbuild_jobs' ) as $test ) {
			$result = call_user_func( $all['direct'][ $test ]['test'] );
			self::assertSame( array( 'label', 'status', 'badge', 'description', 'actions', 'test' ), array_keys( $result ), $test );
			self::assertContains( $result['status'], array( 'good', 'recommended', 'critical' ), $test );
		}
	}

	public function test_the_info_tab_lists_the_versions_and_settings_and_nothing_private(): void {
		$this->service( SiteHealth::class )->register();
		$info = (array) apply_filters( 'debug_information', array( 'wp-core' => array( 'label' => 'WordPress' ) ) );
		self::assertSame( array( 'wp-core', 'creative-skateboard-builder' ), array_keys( $info ) );
		$section = $info['creative-skateboard-builder'];
		self::assertSame( 'Creative Skateboard Builder', $section['label'] );
		self::assertArrayNotHasKey( 'private', $section );
		self::assertSame( array( 'version', 'schema', 'order', 'mapping', 'discount', 'coupons', 'rate', 'proxy', 'assembly', 'index', 'failedJobs' ), array_keys( $section['fields'] ) );
		// Issue #29: the five settings of 0.4.0, at their defaults.
		self::assertSame( '0', $section['fields']['discount']['value'] );
		self::assertSame( array( 'The kit alone', 'kit' ), array( $section['fields']['coupons']['value'], $section['fields']['coupons']['debug'] ) );
		self::assertSame( 120, $section['fields']['rate']['value'] );
		self::assertSame( array( 'None', '' ), array( $section['fields']['proxy']['value'], $section['fields']['proxy']['debug'] ) );
		self::assertSame( array( 'No', false ), array( $section['fields']['assembly']['value'], $section['fields']['assembly']['debug'] ) );
		self::assertSame( CBUILD_VERSION, $section['fields']['version']['value'] );
		self::assertSame( get_option( Options::DB_VERSION ), $section['fields']['schema']['value'] );
		self::assertSame( 'table, field, attribute', $section['fields']['order']['debug'] );
		self::assertSame( 0, $section['fields']['mapping']['value'] );
		self::assertSame( 'Up to date', $section['fields']['index']['value'], 'words on the page' );
		self::assertSame( 'index_current', $section['fields']['index']['debug'], 'the code in what is copied' );
		self::assertSame( 0, $section['fields']['failedJobs']['value'] );
	}

	public function test_a_network_hears_when_the_must_use_file_cannot_be_written(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		\CreativeBranding\SkateboardBuilder\Install\NetworkFile::remove();
		$missing = $this->service( SiteHealth::class )->network();
		self::assertSame( 'recommended', $missing['status'] );
		self::assertStringContainsString( \CreativeBranding\SkateboardBuilder\Install\NetworkFile::NAME, $missing['description'] );
		self::assertTrue( \CreativeBranding\SkateboardBuilder\Install\NetworkFile::ensure() );
		try {
			self::assertSame( 'good', $this->service( SiteHealth::class )->network()['status'] );
		} finally {
			\CreativeBranding\SkateboardBuilder\Install\NetworkFile::remove();
		}
	}
}
