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
	 * The arguments of the plugin's waiting actions with this hook.
	 *
	 * @param string $hook The action.
	 * @return list<array<mixed>>
	 */
	private static function waiting( string $hook ): array {
		$actions = as_get_scheduled_actions(
			array(
				'hook'     => $hook,
				'group'    => Options::QUEUE_GROUP,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => -1,
				'orderby'  => 'action_id',
				'order'    => 'ASC',
			)
		);
		return array_values( array_map( static fn( \ActionScheduler_Action $action ): array => $action->get_args(), $actions ) );
	}

	/**
	 * Run the plugin's due actions as the queue's runner does, until none is due.
	 *
	 * @return int How many ran.
	 */
	private static function work(): int {
		$ran = 0;
		do {
			$due = as_get_scheduled_actions(
				array(
					'group'        => Options::QUEUE_GROUP,
					'status'       => \ActionScheduler_Store::STATUS_PENDING,
					'date'         => as_get_datetime_object(),
					'date_compare' => '<=',
					'per_page'     => 50,
				),
				'ids'
			);
			foreach ( $due as $id ) {
				\ActionScheduler::runner()->process_action( (int) $id, 'tests' );
				++$ran;
			}
			self::assertLessThan( 200, $ran, 'the queue does not empty' );
		} while ( array() !== $due );
		return $ran;
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

		wp_update_term( $other['term_id'], 'product_cat', array( 'name' => 'Boards' ) );
		self::assertSame( ReindexState::DONE, $jobs->state()?->phase, 'a category is not a mapped attribute' );
		wp_update_term( $term['term_id'], 'pa_cbuild_test_width', array( 'name' => '8.75' ) );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase, 'every part with the term may read differently' );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		unregister_taxonomy( 'pa_cbuild_test_width' );
	}
}
