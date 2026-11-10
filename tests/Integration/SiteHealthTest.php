<?php
/**
 * Site Health, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\CheckScreen;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Health\SiteHealth;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Support\Options;
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

	public function test_site_health_gains_two_direct_tests_and_keeps_its_own(): void {
		$tests = $this->tests();
		$ids = array_keys( $tests['direct'] );
		self::assertSame( 'core', $ids[0] );
		// WooCommerce adds its own between.
		self::assertSame( array( 'cbuild_index', 'cbuild_jobs' ), array_slice( $ids, -2 ) );
		self::assertSame( array( 'cbuild_catalogue' ), array_keys( $tests['async'] ) );
		self::assertSame( 'The skateboard builder\'s specs index', $tests['direct']['cbuild_index']['label'] );
		self::assertIsCallable( $tests['direct']['cbuild_index']['test'] );
		self::assertIsCallable( $tests['direct']['cbuild_jobs']['test'] );
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
		self::assertSame( array( 'version', 'schema', 'order', 'mapping', 'index', 'failedJobs' ), array_keys( $section['fields'] ) );
		self::assertSame( CBUILD_VERSION, $section['fields']['version']['value'] );
		self::assertSame( get_option( Options::DB_VERSION ), $section['fields']['schema']['value'] );
		self::assertSame( 'table, field, attribute', $section['fields']['order']['debug'] );
		self::assertSame( 0, $section['fields']['mapping']['value'] );
		self::assertSame( 'index_current', $section['fields']['index']['value'] );
		self::assertSame( 0, $section['fields']['failedJobs']['value'] );
	}
}
