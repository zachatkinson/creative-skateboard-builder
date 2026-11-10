<?php
/**
 * The jobs that keep the index.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCatalogue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryReindexStore;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Roadmap, milestone 3: the jobs are idempotent, and a reindex stopped halfway resumes.
 */
final class IndexJobsTest extends TestCase {

	private InMemorySpecIndex $index;

	private InMemorySpecSource $source;

	private InMemoryPartCatalogue $catalogue;

	private FakeQueue $queue;

	private InMemoryReindexStore $store;

	private InMemoryShopSettings $settings;

	private FakeClock $clock;

	private FakeLogger $logger;

	private IndexJobs $jobs;

	protected function setUp(): void {
		$this->index     = new InMemorySpecIndex();
		$this->source    = new InMemorySpecSource();
		$this->catalogue = new InMemoryPartCatalogue();
		$this->queue     = new FakeQueue();
		$this->store     = new InMemoryReindexStore();
		$this->settings  = new InMemoryShopSettings();
		$this->clock     = new FakeClock();
		$this->logger    = new FakeLogger();
		$this->jobs      = new IndexJobs(
			new SpecIndexer( $this->index, $this->settings, $this->clock, $this->source ),
			$this->index,
			$this->catalogue,
			$this->queue,
			$this->store,
			$this->settings,
			$this->clock,
			$this->logger
		);
	}

	/**
	 * Simple parts with a width each.
	 *
	 * @param int ...$ids Their IDs.
	 */
	private function parts( int ...$ids ): void {
		foreach ( $ids as $id ) {
			$this->catalogue->products[ $id ] = array( $id );
			$this->source->says[ $id ]        = array( Reading::of( SpecKey::DeckWidth, Measure::inches( 80000 + $id ), Origin::Field ) );
		}
	}

	/**
	 * Run the reindex's job until it queues no more, as the queue would.
	 *
	 * @return int How many batches ran.
	 */
	private function drain(): int {
		$batches = 0;
		while ( isset( $this->queue->pending[ IndexJobs::JOB_REINDEX ] ) ) {
			unset( $this->queue->pending[ IndexJobs::JOB_REINDEX ] );
			$this->jobs->runReindex();
			++$batches;
			self::assertLessThan( 50, $batches, 'the reindex does not end' );
		}
		return $batches;
	}

	public function test_the_jobs_names_are_the_ones_deactivation_and_uninstall_cancel(): void {
		self::assertSame( Options::JOB_INDEX_PART, IndexJobs::JOB_PART );
		self::assertSame( Options::JOB_REINDEX, IndexJobs::JOB_REINDEX );
		self::assertSame( 100, IndexJobs::BATCH );
		self::assertSame( 3, IndexJobs::RETRIES );
		self::assertSame( 60, IndexJobs::RETRY_AFTER );
	}

	public function test_a_save_queues_one_job_per_part_and_reads_nothing(): void {
		$this->parts( 7, 8 );
		$this->jobs->changed( 7 );
		$this->jobs->changed( 8 );
		$this->jobs->changed( 7 );
		self::assertSame(
			array(
				array( 'cbuild_index_part', array( 'part' => 7 ) ),
				array( 'cbuild_index_part', array( 'part' => 8 ) ),
			),
			$this->queue->distinct,
			'the second part is not dropped beside the first, and the first is queued once'
		);
		self::assertSame( array(), $this->source->asked );
		self::assertSame( array(), $this->index->rows );
	}

	public function test_a_parts_job_indexes_it_and_twice_is_the_same(): void {
		$this->parts( 7 );
		$this->jobs->indexPart( 7 );
		$once = $this->index->rows;
		self::assertEquals( array( new IndexedSpec( SpecKey::DeckWidth, '8.0007', Origin::Field ) ), $this->index->rowsOf( 7 ) );
		$this->jobs->indexPart( 7 );
		self::assertEquals( $once, $this->index->rows );
		self::assertSame( array(), $this->logger->lines );
	}

	public function test_a_variable_products_job_indexes_its_variations_and_not_itself(): void {
		$this->parts( 21, 22 );
		$this->catalogue->products[20] = array( 21, 22 );
		$this->source->says[20]        = array( Reading::of( SpecKey::DeckLength, Measure::inches( 320000 ), Origin::Field ) );
		// Rows from when it was a simple product.
		$this->index->replace( 20, array( new IndexedSpec( SpecKey::DeckLength, '32.0000', Origin::Field ) ), $this->clock->now() );

		$this->jobs->indexPart( 20 );
		self::assertSame( array( 21, 22 ), array_keys( $this->index->rows ) );
		self::assertSame( array( array( 21, 22 ) ), $this->source->asked, 'one batch for the variations' );
	}

	public function test_a_deleted_products_job_removes_its_rows(): void {
		$this->parts( 7 );
		$this->jobs->indexPart( 7 );
		unset( $this->catalogue->products[7], $this->source->says[7] );
		$this->jobs->indexPart( 7 );
		self::assertSame( array(), $this->index->rows );
		self::assertSame( array( array( 7 ) ), $this->source->asked, 'nothing is read for a product that is gone' );
	}

	public function test_a_deleted_product_queues_its_job_and_another_post_does_not(): void {
		$this->jobs->removed( 7, 'product' );
		$this->jobs->removed( 8, 'product_variation' );
		$this->jobs->removed( 9, 'post' );
		$this->jobs->removed( 10, 'shop_order' );
		self::assertSame( array( 7, 8 ), array_map( static fn( array $queued ): int => $queued[1]['part'], $this->queue->distinct ) );
		self::assertSame( 0, $this->store->saves, 'no reindex is running, so none is told' );
	}

	public function test_a_parts_job_that_fails_is_logged_and_tried_again_after_a_longer_wait_each_time(): void {
		$failing = new class() implements \CreativeBranding\SkateboardBuilder\Port\SpecSource {
			public function read( int ...$partIds ): array {
				throw new RuntimeException( 'the database went away' );
			}
		};
		$jobs    = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $failing ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger );
		$this->catalogue->products[7] = array( 7 );
		$now                          = $this->clock->now()->getTimestamp();

		$jobs->indexPart( 7 );
		self::assertSame( $now + 60, $this->queue->scheduled['cbuild_index_part'] );
		self::assertSame(
			array(
				'level'   => 'error',
				'message' => 'A part could not be indexed.',
				'context' => array( 'part' => 7, 'attempt' => 0, 'error' => 'the database went away' ),
			),
			$this->logger->lines[0]
		);
		self::assertSame( array( 'part' => 7, 'attempt' => 1 ), $this->queue->scheduledWith['cbuild_index_part'] );
		$jobs->indexPart( 7, 1 );
		self::assertSame( $now + 120, $this->queue->scheduled['cbuild_index_part'] );
		self::assertSame( array( 'part' => 7, 'attempt' => 2 ), $this->queue->scheduledWith['cbuild_index_part'] );
		$jobs->indexPart( 7, 2 );
		self::assertSame( $now + 240, $this->queue->scheduled['cbuild_index_part'] );
		self::assertSame( array( 'part' => 7, 'attempt' => 3 ), $this->queue->scheduledWith['cbuild_index_part'] );
		self::assertSame( 3, $this->queue->plainScheduled['cbuild_index_part'] );

		try {
			$jobs->indexPart( 7, 3 );
			self::fail( 'the last failure is thrown, so the queue records it' );
		} catch ( RuntimeException $failure ) {
			self::assertSame( 'the database went away', $failure->getMessage() );
		}
		self::assertSame( 3, $this->queue->plainScheduled['cbuild_index_part'], 'not tried a fifth time' );
		self::assertCount( 4, $this->logger->lines );
		self::assertSame( 3, $this->logger->lines[3]['context']['attempt'] );
	}

	public function test_a_reindex_asked_for_twice_starts_once(): void {
		$this->jobs->requestReindex();
		$this->jobs->requestReindex();
		self::assertSame( 2, $this->queue->calls['cbuild_reindex'], 'asked for with the unique flag both times' );
		self::assertArrayNotHasKey( 'cbuild_reindex', $this->queue->plain );
		self::assertEquals( ReindexState::begin( $this->clock->now()->getTimestamp(), 2 ), $this->jobs->state(), 'the second request\'s run' );
	}

	public function test_a_reindex_walks_the_indexs_rows_then_the_shops_parts_a_hundred_at_a_time(): void {
		$this->parts( ...range( 1, 250 ) );
		// Rows of a product deleted while the plugin was off.
		$this->index->replace( 900, array( new IndexedSpec( SpecKey::DeckWidth, '8.0000', Origin::Field ) ), $this->clock->now() );

		$this->jobs->requestReindex();
		self::assertSame( 1, $this->store->state['run'] ?? null, 'the first request is run 1 as stored' );
		$started = $this->clock->now()->getTimestamp();
		$this->clock->advance( 30 );
		$batches = $this->drain();

		self::assertSame( 4, $batches, 'the one row, then pages of 100, 100 and 50' );
		self::assertSame( array( 1, 2, 3 ), $this->catalogue->pages );
		self::assertSame( range( 1, 250 ), array_keys( $this->index->rows ), 'every part, and the stale row gone' );
		self::assertSame( array( 1, 100, 100, 50 ), array_map( 'count', $this->source->asked ) );
		self::assertEquals( new ReindexState( ReindexState::DONE, 0, 251, $started, $started + 30 ), $this->jobs->state() );
		self::assertSame( 3, $this->queue->plain['cbuild_reindex'], 'each batch but the last queues the next, plainly' );
	}

	public function test_a_full_last_page_is_followed_by_an_empty_one(): void {
		$this->parts( ...range( 1, 100 ) );
		$this->jobs->requestReindex();
		self::assertSame( 3, $this->drain(), 'no rows, a full page, an empty page' );
		self::assertSame( array( 1, 2 ), $this->catalogue->pages );
		self::assertSame( ReindexState::DONE, $this->jobs->state()?->phase );
		self::assertSame( 100, $this->jobs->state()?->parts );
	}

	public function test_a_reindex_stopped_halfway_resumes_from_its_place_and_ends_as_an_uninterrupted_one(): void {
		$this->parts( ...range( 1, 250 ) );
		$this->jobs->requestReindex();
		unset( $this->queue->pending['cbuild_reindex'] );
		$this->jobs->runReindex();
		$this->jobs->runReindex();
		self::assertEquals( new ReindexState( ReindexState::CATALOGUE, 1, 100, $this->clock->now()->getTimestamp() ), $this->jobs->state() );

		// The process dies: the same batch runs again when the queue retries the action, and the place is still there.
		$halfway     = $this->index->rows;
		$fresh       = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $this->source ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger );
		$fresh->runReindex();
		self::assertSame( 2, $fresh->state()?->position );
		self::assertSame( array_keys( $halfway ), range( 1, 100 ) );
		self::assertSame( range( 1, 200 ), array_keys( $this->index->rows ) );
		$this->drain();

		$uninterrupted = new InMemorySpecIndex();
		foreach ( range( 1, 250 ) as $id ) {
			$uninterrupted->replace( $id, array( new IndexedSpec( SpecKey::DeckWidth, Measure::inches( 80000 + $id )->decimal(), Origin::Field ) ), $this->clock->now() );
		}
		self::assertEquals( $uninterrupted->rows, $this->index->rows );
		self::assertSame( ReindexState::DONE, $this->jobs->state()?->phase );
	}

	public function test_the_reindexs_job_does_nothing_when_none_is_running(): void {
		$this->parts( 1 );
		$this->jobs->runReindex();
		self::assertSame( array(), $this->source->asked );
		self::assertSame( 0, $this->store->saves );
		$this->jobs->requestReindex();
		$this->drain();
		$saves = $this->store->saves;
		$this->jobs->runReindex();
		self::assertSame( $saves, $this->store->saves, 'a finished one is not run again' );
		$this->store->state = array( 'phase' => 'weird' );
		self::assertNull( $this->jobs->state() );
		$this->jobs->runReindex();
		self::assertSame( $saves, $this->store->saves );
	}

	public function test_a_delete_under_a_running_reindex_makes_it_pass_once_more(): void {
		$this->parts( ...range( 1, 150 ) );
		$this->jobs->requestReindex();
		unset( $this->queue->pending['cbuild_reindex'] );
		$this->jobs->runReindex();
		$this->jobs->runReindex();
		self::assertSame( ReindexState::CATALOGUE, $this->jobs->state()?->phase );

		// Part 50 is deleted: the pages shift, and part 101 is now on the page already done.
		unset( $this->catalogue->products[50], $this->source->says[50] );
		$this->jobs->removed( 50, 'product' );
		$this->jobs->removed( 51, 'product' );
		self::assertTrue( $this->jobs->state()?->again );
		self::assertSame( 4, $this->store->saves, 'the request, two batches, and told once, not for every delete' );

		$this->queue->pending['cbuild_reindex'] = true;
		$this->drain();
		self::assertSame( ReindexState::DONE, $this->jobs->state()?->phase );
		self::assertFalse( $this->jobs->state()?->again );
		$expected = array_values( array_diff( range( 1, 150 ), array( 50 ) ) );
		self::assertSame( $expected, array_keys( $this->index->rows ), 'part 101 was not missed, and part 50 is gone' );
		self::assertSame( array( 1, 2, 1, 2 ), $this->catalogue->pages, 'the pages are walked a second time' );
	}

	public function test_a_reindex_asked_for_while_a_batch_runs_starts_over_and_is_not_overwritten(): void {
		$this->parts( ...range( 1, 150 ) );
		$this->index->replace( 500, array( new IndexedSpec( SpecKey::DeckWidth, '8.0000', Origin::Field ) ), $this->clock->now() );
		$this->jobs->requestReindex();
		$asking = new class() implements \CreativeBranding\SkateboardBuilder\Port\SpecSource {
			public ?IndexJobs $jobs = null;
			public function read( int ...$partIds ): array {
				// The settings are saved while the batch is being read.
				$this->jobs?->requestReindex();
				$this->jobs = null;
				return array_fill_keys( $partIds, array() );
			}
		};
		$jobs         = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $asking ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger );
		$asking->jobs = $jobs;
		$this->queue->running['cbuild_reindex'] = true;
		unset( $this->queue->pending['cbuild_reindex'] );
		$jobs->runReindex();
		// Not a second has passed, and the batch was the first: the new request's state differs from the batch's
		// own only by its run.
		self::assertEquals( ReindexState::begin( $this->clock->now()->getTimestamp(), 2 ), $jobs->state(), 'the new request\'s state stands' );
		self::assertTrue( $this->queue->pending['cbuild_reindex'] ?? false, 'and its batch is queued, though the unique one was refused' );
		self::assertSame( 1, $this->queue->plain['cbuild_reindex'] );
	}

	public function test_a_batch_that_fails_keeps_its_place_and_is_tried_again_then_stops(): void {
		$this->parts( ...range( 1, 150 ) );
		$this->jobs->requestReindex();
		unset( $this->queue->pending['cbuild_reindex'] );
		$this->jobs->runReindex();
		$now = $this->clock->now()->getTimestamp();

		$this->catalogue->fails = new RuntimeException( 'out of memory' );
		$this->jobs->runReindex();
		$state = $this->jobs->state();
		self::assertEquals( new ReindexState( ReindexState::CATALOGUE, 0, 0, $now, null, false, 1, 'out of memory' ), $state );
		self::assertSame( $now + 60, $this->queue->scheduled['cbuild_reindex'] );
		self::assertSame(
			array( 'phase' => 'catalogue', 'position' => 0, 'failures' => 1, 'error' => 'out of memory' ),
			$this->logger->lines[0]['context']
		);
		self::assertSame( 'A batch of the reindex failed.', $this->logger->lines[0]['message'] );

		// The retry works, and the count of failures in a row starts again.
		$this->jobs->runReindex();
		self::assertEquals( new ReindexState( ReindexState::CATALOGUE, 1, 100, $now ), $this->jobs->state() );

		foreach ( array( 60, 120, 240, 480 ) as $wait ) {
			$this->catalogue->fails = new RuntimeException( 'still' );
			$this->jobs->runReindex();
			self::assertSame( $now + $wait, $this->queue->scheduled['cbuild_reindex'] );
		}
		$this->catalogue->fails = new RuntimeException( 'still' );
		$this->jobs->runReindex();
		self::assertSame( ReindexState::FAILED, $this->jobs->state()?->phase, 'five in a row and it stops' );
		self::assertSame( 5, $this->jobs->state()?->failures );
		self::assertSame( 1, $this->jobs->state()?->position, 'with its place, for Site Health and the next request' );
		self::assertSame( $now + 480, $this->queue->scheduled['cbuild_reindex'], 'no sixth try is scheduled' );
		$pages = $this->catalogue->pages;
		$this->jobs->runReindex();
		self::assertSame( $pages, $this->catalogue->pages, 'a stopped reindex does not run' );
	}

	public function test_a_batch_that_is_disturbed_and_then_fails_keeps_both(): void {
		$this->parts( 1 );
		$this->jobs->requestReindex();
		$this->jobs->runReindex();
		$deleting       = new class() implements \CreativeBranding\SkateboardBuilder\Port\SpecSource {
			public ?IndexJobs $jobs = null;
			public function read( int ...$partIds ): array {
				$this->jobs?->removed( 99, 'product' );
				throw new RuntimeException( 'gone' );
			}
		};
		$jobs           = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $deleting ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger );
		$deleting->jobs = $jobs;
		$jobs->runReindex();
		$state = $jobs->state();
		self::assertTrue( $state?->again, 'the delete during the batch is not lost to the failure' );
		self::assertSame( 1, $state?->failures );
		self::assertSame( 'gone', $state?->error );
	}

	public function test_an_edited_term_of_a_mapped_attribute_asks_for_a_reindex_and_any_other_does_not(): void {
		$this->jobs->termChanged( 'pa_width' );
		self::assertNull( $this->jobs->state(), 'nothing is mapped' );
		$this->settings->mapping = array_values(
			array_filter(
				array(
					AttributeMap::read( array( 'attribute' => 'pa_diameter', 'spec' => 'wheel_diameter' ) ),
					AttributeMap::read( array( 'attribute' => 'pa_width', 'spec' => 'deck_width' ) ),
					AttributeMap::read( array( 'attribute' => 'pa_width', 'spec' => 'grip_width' ) ),
				)
			)
		);
		$this->jobs->termChanged( 'product_cat' );
		self::assertNull( $this->jobs->state() );
		$this->jobs->termChanged( 'pa_width' );
		self::assertNotNull( $this->jobs->state() );
		self::assertSame( 1, $this->queue->calls['cbuild_reindex'], 'once, though two lines name the attribute' );
	}

	public function test_an_attribute_mapped_by_the_name_the_shop_gave_it_is_matched_as_the_catalogue_reads_it(): void {
		$this->settings->mapping = array_values( array_filter( array( AttributeMap::read( array( 'attribute' => 'Width', 'spec' => 'deck_width' ) ) ) ) );
		$this->jobs->termChanged( 'pa_diameter' );
		self::assertNull( $this->jobs->state() );
		$this->jobs->termChanged( 'pa_width' );
		self::assertSame( 1, $this->jobs->state()?->run );
		$this->jobs->termChanged( 'width' );
		self::assertSame( 2, $this->jobs->state()?->run );
	}
}
