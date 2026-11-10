<?php
/**
 * The index's jobs, on real products and the real queue.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Roadmap, milestone 3: a save queues the part's job and indexes nothing itself; the jobs run on Action Scheduler and
 * are idempotent; a reindex covers the shop.
 */
final class IndexJobsTest extends IntegrationTestCase {

	public function tear_down(): void {
		delete_option( Options::SETTINGS );
		delete_option( Options::REINDEX );
		parent::tear_down();
	}

	/**
	 * A simple deck of this width.
	 *
	 * @param string $width  The width's field.
	 * @param string $status The product's status.
	 */
	private static function deck( string $width = '8.0000', string $status = 'publish' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Deck' );
		$product->set_status( $status );
		$product->set_regular_price( '80' );
		$product->update_meta_data( Options::META_SPECS, array( 'deck_width' => $width ) );
		$product->save();
		return $product;
	}

	/**
	 * A variable deck with a variation of each width.
	 *
	 * @param string ...$widths The widths' fields.
	 * @return array{0: \WC_Product_Variable, 1: list<int>} The parent and its variations' IDs.
	 */
	private static function variable( string ...$widths ): array {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Deck' );
		$parent->save();
		$ids = array();
		foreach ( $widths as $width ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_regular_price( '80' );
			$variation->update_meta_data( Options::META_SPECS, array( 'deck_width' => $width ) );
			$variation->save();
			$ids[] = $variation->get_id();
		}
		return array( $parent, $ids );
	}

	/**
	 * The widths the index holds, by part.
	 *
	 * @param int ...$ids The parts.
	 * @return array<int, list<string>>
	 */
	private function indexed( int ...$ids ): array {
		$index = $this->service( SpecIndex::class );
		$found = array();
		foreach ( $ids as $id ) {
			$found[ $id ] = array_map( static fn( IndexedSpec $row ): string => $row->key->value . '=' . $row->text, $index->rowsOf( $id ) );
		}
		return $found;
	}

	public function test_a_save_queues_the_parts_job_once_and_touches_the_index_with_no_query(): void {
		$queries = array();
		$watch   = static function ( string $query ) use ( &$queries ): string {
			if ( str_contains( $query, Tables::specs() ) ) {
				$queries[] = $query;
			}
			return $query;
		};
		add_filter( 'query', $watch );
		$deck = self::deck();
		$deck->set_name( 'Deck, renamed' );
		$deck->save();
		remove_filter( 'query', $watch );

		self::assertSame( array(), $queries, 'the request that saves a product does not index it' );
		self::assertSame( array( array( 'part' => $deck->get_id() ) ), self::waiting( Options::JOB_INDEX_PART ), 'two saves, one job' );
		self::assertSame( array( $deck->get_id() => array() ), $this->indexed( $deck->get_id() ) );
	}

	public function test_two_parts_saved_together_both_get_a_job_and_the_jobs_index_them(): void {
		$first  = self::deck( '8.0000' );
		$second = self::deck( '8.2500' );
		self::assertSame(
			array( array( 'part' => $first->get_id() ), array( 'part' => $second->get_id() ) ),
			self::waiting( Options::JOB_INDEX_PART ),
			'the unique flag would have dropped the second'
		);
		self::assertSame( 2, self::work() );
		self::assertSame(
			array(
				$first->get_id()  => array( 'deck_width=8.0000' ),
				$second->get_id() => array( 'deck_width=8.2500' ),
			),
			$this->indexed( $first->get_id(), $second->get_id() )
		);
		// The same job again leaves the same rows.
		do_action( Options::JOB_INDEX_PART, $first->get_id() );
		self::assertSame( array( 'deck_width=8.0000' ), $this->indexed( $first->get_id() )[ $first->get_id() ] );
	}

	public function test_a_save_while_the_parts_job_runs_queues_another(): void {
		$deck    = self::deck();
		$running = as_get_scheduled_actions( array( 'hook' => Options::JOB_INDEX_PART, 'group' => Options::QUEUE_GROUP ), 'ids' );
		self::assertCount( 1, $running );
		\ActionScheduler::store()->log_execution( (int) reset( $running ) );
		self::assertSame( array(), self::waiting( Options::JOB_INDEX_PART ), 'it is running now, not waiting' );

		// The running job may already have read the product, so this save needs a job of its own.
		$deck->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.5000' ) );
		$deck->save();
		self::assertSame( array( array( 'part' => $deck->get_id() ) ), self::waiting( Options::JOB_INDEX_PART ) );
	}

	public function test_deactivation_cancels_the_parts_jobs_though_they_carry_arguments(): void {
		self::deck();
		self::deck();
		$this->service( IndexJobs::class )->requestReindex();
		self::assertCount( 2, self::waiting( Options::JOB_INDEX_PART ) );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		\CreativeBranding\SkateboardBuilder\Install\Deactivator::deactivate( false );
		self::assertSame( array(), self::waiting( Options::JOB_INDEX_PART ) );
		self::assertSame( array(), self::waiting( Options::JOB_REINDEX ) );
	}

	public function test_a_retrys_arguments_reach_the_job_in_order(): void {
		$deck = self::deck();
		$this->service( Queue::class )->cancelAll( Options::JOB_INDEX_PART );
		$this->service( Queue::class )->schedule( 1, Options::JOB_INDEX_PART, array( 'part' => $deck->get_id(), 'attempt' => 2 ) );
		self::assertSame( 1, self::work() );
		self::assertSame( array( 'deck_width=8.0000' ), $this->indexed( $deck->get_id() )[ $deck->get_id() ] );
	}

	public function test_a_variable_products_job_indexes_its_variations_and_a_deleted_products_removes_its_rows(): void {
		[ $parent, $variations ] = self::variable( '8.0000', '8.2500' );
		$this->service( Queue::class )->cancelAll( Options::JOB_INDEX_PART );
		$parent->save();
		self::assertSame( array( array( 'part' => $parent->get_id() ) ), self::waiting( Options::JOB_INDEX_PART ) );
		self::work();
		self::assertSame(
			array(
				$parent->get_id() => array(),
				$variations[0]    => array( 'deck_width=8.0000' ),
				$variations[1]    => array( 'deck_width=8.2500' ),
			),
			$this->indexed( $parent->get_id(), ...$variations )
		);

		$deck = self::deck();
		self::work();
		self::assertNotSame( array(), $this->indexed( $deck->get_id() )[ $deck->get_id() ] );
		wp_delete_post( $deck->get_id(), true );
		wp_delete_post( $variations[0], true );
		$page = self::factory()->post->create();
		wp_delete_post( $page, true );
		self::assertSame(
			array( array( 'part' => $deck->get_id() ), array( 'part' => $variations[0] ) ),
			self::waiting( Options::JOB_INDEX_PART ),
			'deleted outside WooCommerce, and still noticed; another post type is not'
		);
		self::work();
		self::assertSame(
			array(
				$deck->get_id() => array(),
				$variations[0]  => array(),
				$variations[1]  => array( 'deck_width=8.2500' ),
			),
			$this->indexed( $deck->get_id(), ...$variations )
		);
	}

	public function test_the_catalogue_pages_every_part_by_id_whatever_its_status_and_no_variable_parent(): void {
		$first                   = self::deck();
		[ $parent, $variations ] = self::variable( '8.0000', '8.2500' );
		$draft                   = self::deck( '8.5000', 'draft' );
		$private                 = self::deck( '8.5000', 'private' );
		$catalogue               = $this->service( PartCatalogue::class );

		self::assertSame( array( $first->get_id(), $variations[0] ), $catalogue->page( 1, 2 ) );
		self::assertSame( array( $variations[1], $draft->get_id() ), $catalogue->page( 2, 2 ) );
		self::assertSame( array( $private->get_id() ), $catalogue->page( 3, 2 ) );
		self::assertSame( array(), $catalogue->page( 4, 2 ) );

		self::assertSame( $variations, $catalogue->partsOf( $parent->get_id() ) );
		self::assertSame( array( $first->get_id() ), $catalogue->partsOf( $first->get_id() ) );
		self::assertSame( array( $variations[0] ), $catalogue->partsOf( $variations[0] ) );
		self::assertSame( array(), $catalogue->partsOf( 999999 ) );
		self::assertSame( array(), $catalogue->partsOf( self::factory()->post->create() ) );
		self::assertTrue( $catalogue->holds( 'product' ) );
		self::assertTrue( $catalogue->holds( 'product_variation' ) );
		self::assertFalse( $catalogue->holds( 'page' ) );
	}

	public function test_a_reindex_covers_the_shop_and_clears_rows_whose_product_is_gone(): void {
		$first                   = self::deck( '8.0000' );
		$second                  = self::deck( '8.2500', 'draft' );
		[ $parent, $variations ] = self::variable( '8.3750', '8.5000' );
		$this->service( Queue::class )->cancelAll( Options::JOB_INDEX_PART );
		$this->service( SpecIndex::class )->replace( 999999, array( new IndexedSpec( SpecKey::DeckWidth, '9.0000', Origin::Field ) ), new \DateTimeImmutable( '@1800000000' ) );

		$jobs = $this->service( IndexJobs::class );
		$jobs->requestReindex();
		$jobs->requestReindex();
		self::assertSame( array( array() ), self::waiting( Options::JOB_REINDEX ), 'asked for twice, queued once' );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase );

		self::assertSame( 2, self::work(), 'the index\'s rows, then the one page; each batch queued the next while it ran' );
		self::assertSame(
			array(
				$first->get_id()  => array( 'deck_width=8.0000' ),
				$second->get_id() => array( 'deck_width=8.2500' ),
				$parent->get_id() => array(),
				$variations[0]    => array( 'deck_width=8.3750' ),
				$variations[1]    => array( 'deck_width=8.5000' ),
				999999            => array(),
			),
			$this->indexed( $first->get_id(), $second->get_id(), $parent->get_id(), $variations[0], $variations[1], 999999 )
		);
		$state = $jobs->state();
		self::assertSame( ReindexState::DONE, $state?->phase );
		self::assertSame( 5, $state?->parts, 'the stale row and the four parts' );
		self::assertSame( array(), self::waiting( Options::JOB_REINDEX ) );

		// Its state survives as the option uninstall removes, autoloaded by nobody.
		self::assertIsArray( get_option( Options::REINDEX ) );
		self::assertNotContains( Options::REINDEX, array_keys( wp_load_alloptions() ) );
	}

	public function test_a_reindex_stopped_halfway_goes_on_from_its_place_and_ends_as_one_never_stopped(): void {
		// More than a batch of parts, so the row pass and the catalogue pass each take more than one job.
		$ids = array();
		for ( $n = 0; $n < IndexJobs::BATCH + 20; $n++ ) {
			$ids[] = self::deck( number_format( 7 + $n / 1000, 4 ) )->get_id();
		}
		$this->service( Queue::class )->cancelAll( Options::JOB_INDEX_PART );
		$jobs = $this->service( IndexJobs::class );
		$rows = function () use ( $ids ): array {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The test's own read of the plugin's table.
			return $wpdb->get_results( 'SELECT part_id, spec, number, text, source, source_ref FROM ' . Tables::name( Tables::SPECS ) . ' WHERE part_id IN (' . implode( ',', $ids ) . ') ORDER BY part_id, spec', ARRAY_A );
		};

		// The run nothing interrupts.
		$jobs->requestReindex();
		self::work();
		self::assertSame( ReindexState::DONE, $jobs->state()?->phase );
		$whole = $rows();
		self::assertCount( count( $ids ), $whole );

		// The index emptied, so every row below is one the second run wrote.
		$this->service( SpecIndex::class )->forget( ...$ids );
		self::assertSame( array(), $rows() );

		// Again, stopped after each batch as a deactivation or a dead request stops it: the job is gone, the place is
		// kept, and the job put back goes on from there.
		$jobs->requestReindex();
		$store   = $this->service( ReindexStore::class );
		$batches = 0;
		$again   = true;
		while ( $jobs->state()?->isRunning() ) {
			self::assertLessThan( 20, ++$batches );
			$before = $store->load();
			$jobs->runReindex();
			$this->service( Queue::class )->cancelAll( Options::JOB_REINDEX );
			// The request died after the batch's rows were written and before its place was saved: the batch runs a
			// second time from the place before it.
			if ( 2 === $batches && $again && is_array( $before ) ) {
				$again = false;
				$store->save( $before );
				--$batches;
			}
		}
		self::assertGreaterThanOrEqual( 3, $batches, 'the catalogue took more than one batch' );
		self::assertSame( $whole, $rows(), 'the same rows as the run nothing stopped, none twice' );
		self::assertSame( count( $ids ), $jobs->state()?->parts, 'the batch that ran twice is counted once' );
		self::assertSame( ReindexState::DONE, $jobs->state()?->phase );
	}

	public function test_the_place_another_process_saved_is_read_past_this_requests_cache(): void {
		global $wpdb;
		$store = $this->service( ReindexStore::class );
		self::assertNull( $store->load(), 'none yet, which WordPress now remembers as a missing option' );
		// Another process writes the option: this request's cache does not hear of it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The other process.
		$wpdb->insert( $wpdb->options, array( 'option_name' => Options::REINDEX, 'option_value' => maybe_serialize( ReindexState::begin( 1_800_000_000, 7 )->toArray() ), 'autoload' => 'off' ) );
		self::assertFalse( get_option( Options::REINDEX ), 'the cache still says there is none' );
		self::assertSame( 7, $store->load()['run'] ?? null );

		// And changes it: the cached copy is stale.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The other process.
		$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( ReindexState::begin( 1_800_000_000, 8 )->toArray() ) ), array( 'option_name' => Options::REINDEX ) );
		self::assertSame( 7, get_option( Options::REINDEX )['run'] );
		self::assertSame( 8, $store->load()['run'] ?? null );
		self::assertSame( 8, $this->service( IndexJobs::class )->state()?->run );
	}

	public function test_a_job_that_failed_before_the_last_reindex_finished_is_no_longer_counted(): void {
		$queue = $this->service( Queue::class );
		$fails = static function (): void {
			throw new \RuntimeException( 'no' );
		};
		add_action( 'cbuild_test_fails', $fails );
		as_enqueue_async_action( 'cbuild_test_fails', array(), Options::QUEUE_GROUP );
		self::work();
		remove_action( 'cbuild_test_fails', $fails );
		$now = time(); // phpcs:ignore -- The queue's own clock stamps the attempt.
		self::assertSame( 1, $queue->failed(), 'from the beginning' );
		self::assertSame( 1, $queue->failed( $now - 60 ), 'it failed after that moment' );
		self::assertSame( 0, $queue->failed( $now + 60 ), 'and before this one' );
	}

	public function test_saving_the_settings_or_a_mapped_attributes_term_asks_for_a_reindex(): void {
		$jobs = $this->service( IndexJobs::class );
		self::assertNull( $jobs->state() );

		register_taxonomy( 'pa_cbuild_test_width', array( 'product' ) );
		$term  = wp_insert_term( '8.25', 'pa_cbuild_test_width' );
		$other = wp_insert_term( 'Decks', 'product_cat' );
		self::assertIsArray( $term );
		self::assertIsArray( $other );
		wp_update_term( $term['term_id'], 'pa_cbuild_test_width', array( 'name' => '8.5' ) );
		self::assertNull( $jobs->state(), 'the attribute is not mapped yet' );

		$mapping = array( array( 'attribute' => 'pa_cbuild_test_width', 'spec' => 'deck_width' ) );
		update_option( Options::SETTINGS, array( Options::SETTING_ATTRIBUTE_MAPPING => $mapping ) );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase, 'the settings saved for the first time' );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		self::work();
		self::assertSame( ReindexState::DONE, $jobs->state()?->phase );

		update_option( Options::SETTINGS, array( Options::SETTING_ATTRIBUTE_MAPPING => $mapping, Options::SETTING_SOURCE_ORDER => array( 'attribute', 'field', 'table' ) ) );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase, 'the settings changed' );
		self::work();
		$run = $jobs->state()?->run;
		update_option( Options::SETTINGS, array( Options::SETTING_ATTRIBUTE_MAPPING => $mapping, Options::SETTING_SOURCE_ORDER => array( 'attribute', 'field', 'table' ) ) );
		self::assertSame( $run, $jobs->state()?->run, 'saved as they were: WordPress writes nothing, and nothing is asked' );
		// Issue #20: a key the index does not read is saved, and nothing is asked.
		update_option( Options::SETTINGS, array( Options::SETTING_ATTRIBUTE_MAPPING => $mapping, Options::SETTING_SOURCE_ORDER => array( 'attribute', 'field', 'table' ), 'cbuild_test_other' => 60 ) );
		self::assertSame( 60, get_option( Options::SETTINGS )['cbuild_test_other'] );
		self::assertSame( $run, $jobs->state()?->run, 'a setting the index is not built from' );

		wp_update_term( $other['term_id'], 'product_cat', array( 'name' => 'Boards' ) );
		self::assertSame( ReindexState::DONE, $jobs->state()?->phase, 'a category is not a mapped attribute' );
		wp_update_term( $term['term_id'], 'pa_cbuild_test_width', array( 'name' => '8.75' ) );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase, 'every part with the term may read differently' );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		unregister_taxonomy( 'pa_cbuild_test_width' );
	}

	public function test_a_deleted_term_and_an_attribute_mapped_by_its_name_ask_for_a_reindex(): void {
		$jobs = $this->service( IndexJobs::class );
		register_taxonomy( 'pa_cbuild-test-width', array( 'product' ) );
		$term  = wp_insert_term( '8.25', 'pa_cbuild-test-width' );
		$gone  = wp_insert_term( '8.5', 'pa_cbuild-test-width' );
		$other = wp_insert_term( 'Decks to delete', 'product_cat' );
		self::assertIsArray( $term );
		self::assertIsArray( $gone );
		self::assertIsArray( $other );

		// By the taxonomy's own name, whatever its case: a name without pa_ is a product's own attribute (D-089).
		$mapping = array( array( 'attribute' => 'PA_Cbuild-Test-Width', 'spec' => 'deck_width' ) );
		update_option( Options::SETTINGS, array( Options::SETTING_ATTRIBUTE_MAPPING => $mapping ) );
		self::work();
		self::assertSame( ReindexState::DONE, $jobs->state()?->phase );
		$run = $jobs->state()->run;

		wp_delete_term( $other['term_id'], 'product_cat' );
		self::assertSame( $run, $jobs->state()?->run, 'a category is not a mapped attribute' );

		wp_update_term( $term['term_id'], 'pa_cbuild-test-width', array( 'name' => '8.75' ) );
		self::assertSame( $run + 1, $jobs->state()?->run, 'an edited term of an attribute mapped by its name' );
		self::work();

		wp_delete_term( $gone['term_id'], 'pa_cbuild-test-width' );
		self::assertSame( $run + 2, $jobs->state()?->run, 'a deleted term: no product is saved when its term goes' );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		unregister_taxonomy( 'pa_cbuild-test-width' );
	}
}
