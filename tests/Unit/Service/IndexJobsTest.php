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
use CreativeBranding\SkateboardBuilder\Service\SavedSources;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
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

	private InMemoryKits $kits;

	private SavedSources $saved;

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
		$this->kits      = new InMemoryKits();
		$this->saved     = new SavedSources( array( '_cbuild_specs' ) );
		$this->jobs      = new IndexJobs(
			new SpecIndexer( $this->index, $this->settings, $this->clock, $this->source ),
			$this->index,
			$this->catalogue,
			$this->queue,
			$this->store,
			$this->settings,
			$this->clock,
			$this->logger,
			$this->kits,
			$this->saved
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

	// Issue #21.
	public function test_a_save_that_moved_no_source_queues_nothing_and_any_other_queues_the_part(): void {
		$this->saved->saving( 7, array( 'stock_status' ) );
		$this->jobs->updated( 7 );
		self::assertSame( array(), $this->queue->distinct, 'stock alone' );

		$this->saved->saving( 7, array( 'attributes' ) );
		$this->jobs->updated( 7 );
		$this->jobs->updated( 8 );
		self::assertSame(
			array(
				array( 'cbuild_index_part', array( 'part' => 7 ) ),
				array( 'cbuild_index_part', array( 'part' => 8 ) ),
			),
			$this->queue->distinct,
			'a source, and a save nothing is known of'
		);
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
		self::assertSame( array( array( 21, 22 ) ), $this->kits->forgotten, 'and what a build is told of them is read again' );
		// Issue #28: by the job, so that no visitor's request reads the shop for them.
		self::assertSame( array( array( 21, 22 ) ), $this->kits->warmed );
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
		$jobs    = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $failing ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger, $this->kits, $this->saved );
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
		self::assertSame( array( 100, 100, 50 ), array_map( 'count', $this->source->asked ), 'the stale row is dropped without a read' );
		self::assertEquals( new ReindexState( ReindexState::DONE, 0, 251, $started, $started + 30 ), $this->jobs->state() );
		self::assertSame( 3, $this->queue->plain['cbuild_reindex'], 'each batch but the last queues the next, plainly' );
		// The auditor's should-fix 7, milestone 4: each batch emptied the whole parts table, so every request between
		// two batches read the whole shop again. A batch tells of the parts it indexed and of no other.
		self::assertSame( array( range( 1, 100 ), range( 101, 200 ), range( 201, 250 ) ), $this->kits->forgotten );
		// Issue #28: and writes their rows again itself, a batch at a time.
		self::assertSame( array( range( 1, 100 ), range( 101, 200 ), range( 201, 250 ) ), $this->kits->warmed );
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
		$fresh       = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $this->source ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger, $this->kits, $this->saved );
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

	// Issue #19, D-096: a save is not a delete. It moves no page, and its own job indexes the part.
	public function test_a_save_under_a_running_reindex_queues_its_own_job_and_asks_for_no_second_pass(): void {
		$this->parts( ...range( 1, 150 ) );
		$this->jobs->requestReindex();
		unset( $this->queue->pending['cbuild_reindex'] );
		$this->jobs->runReindex();
		$this->jobs->runReindex();
		self::assertSame( ReindexState::CATALOGUE, $this->jobs->state()?->phase );
		$saves = $this->store->saves;

		$this->jobs->changed( 50 );

		self::assertFalse( $this->jobs->state()?->again );
		self::assertSame( $saves, $this->store->saves, 'the reindex is not told' );
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
		// A row of a part the shop still has, so the first batch reads it.
		$this->index->replace( 1, array( new IndexedSpec( SpecKey::DeckWidth, '8.0000', Origin::Field ) ), $this->clock->now() );
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
		$jobs         = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $asking ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger, $this->kits, $this->saved );
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

	// Claude in Cowork's review of milestone 3, minor 3: the command's own batches each queued the next, 22 actions
	// for 2,000 parts, which a queue runner could take and run beside it.
	public function test_a_reindex_run_now_reads_every_part_and_leaves_no_job_behind(): void {
		$this->parts( ...range( 1, 250 ) );
		$this->jobs->requestReindex();
		self::assertTrue( $this->queue->isPending( IndexJobs::JOB_REINDEX ), 'an earlier request, still waiting' );

		$state = $this->jobs->reindexNow();
		self::assertSame( ReindexState::DONE, $state?->phase );
		self::assertSame( 250, $state->parts );
		self::assertSame( 2, $state->run, 'a run of its own, from the start' );
		self::assertCount( 250, $this->index->rows );
		self::assertFalse( $this->queue->isPending( IndexJobs::JOB_REINDEX ), 'the earlier request\'s job is cancelled, and none is queued' );
		self::assertSame( array(), $this->queue->plain );
		self::assertSame( 1, $this->queue->calls[ IndexJobs::JOB_REINDEX ], 'the earlier request\'s alone' );
	}

	public function test_a_reindex_run_now_stops_at_a_batch_that_fails_and_leaves_its_retry_to_the_queue(): void {
		$this->parts( 1, 2, 3 );
		$failing = new class() implements \CreativeBranding\SkateboardBuilder\Port\SpecSource {
			public function read( int ...$partIds ): array {
				throw new RuntimeException( 'the database went away' );
			}
		};
		$jobs    = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $failing ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger, $this->kits, $this->saved );
		$state   = $jobs->reindexNow();
		self::assertSame( 1, $state?->failures );
		self::assertTrue( $state->isRunning() );
		self::assertSame( 1, $this->queue->plainScheduled[ IndexJobs::JOB_REINDEX ], 'tried again in the background, once' );
		self::assertSame( array(), $this->queue->plain );
	}

	// Claude in Cowork's review of milestone 3, minor 4: a simple part made variable while the plugin was off kept
	// rows under the parent's ID, rewritten from the parent's own fields by every reindex.
	public function test_the_first_pass_reads_each_row_as_the_part_its_product_stands_for_now(): void {
		$this->parts( 5, 8, 9 );
		$this->jobs->reindexNow();
		self::assertSame( array( 5, 8, 9 ), array_keys( $this->index->rows ) );

		// 5 became a variable product with two variations, and 8 was deleted, with nothing to tell the index.
		$this->parts( 6, 7 );
		$this->catalogue->products[5] = array( 6, 7 );
		unset( $this->catalogue->products[8] );

		$this->jobs->requestReindex();
		unset( $this->queue->pending['cbuild_reindex'] );
		$this->jobs->runReindex();
		$rows = array_keys( $this->index->rows );
		sort( $rows );
		self::assertSame( array( 6, 7, 9 ), $rows, 'after the pass over the rows alone' );
		self::assertSame( ReindexState::CATALOGUE, $this->jobs->state()?->phase );
		self::assertSame( 3, $this->jobs->state()->parts, 'the three rows it went through' );
	}

	// Issue #25: two rows that stand for the same parts now had each of them read twice in one batch.
	public function test_the_first_pass_reads_a_part_two_rows_stand_for_once(): void {
		$this->parts( 5, 8, 9 );
		$this->jobs->reindexNow();
		$this->parts( 6, 7 );
		$this->catalogue->products[5] = array( 6, 7 );
		$this->catalogue->products[8] = array( 6, 7 );

		$this->jobs->requestReindex();
		unset( $this->queue->pending['cbuild_reindex'] );
		$this->source->asked = array();
		$this->jobs->runReindex();
		self::assertSame( array( array( 6, 7, 9 ) ), $this->source->asked );
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
		$jobs           = new IndexJobs( new SpecIndexer( $this->index, $this->settings, $this->clock, $deleting ), $this->index, $this->catalogue, $this->queue, $this->store, $this->settings, $this->clock, $this->logger, $this->kits, $this->saved );
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

	// A line names the taxonomy in full; a name without pa_ is a product's own attribute, which has no terms (D-089).
	public function test_a_changed_term_reindexes_only_when_a_line_names_its_taxonomy(): void {
		$this->settings->mapping = array_values( array_filter( array( AttributeMap::read( array( 'attribute' => 'Width', 'spec' => 'deck_width' ) ) ) ) );
		$this->jobs->termChanged( 'pa_diameter' );
		$this->jobs->termChanged( 'pa_width' );
		self::assertNull( $this->jobs->state(), 'Width is a product\'s own attribute, not the Width taxonomy' );
		$this->settings->mapping = array_values( array_filter( array( AttributeMap::read( array( 'attribute' => 'PA_Width', 'spec' => 'deck_width' ) ) ) ) );
		$this->jobs->termChanged( 'width' );
		self::assertNull( $this->jobs->state() );
		$this->jobs->termChanged( 'pa_width' );
		self::assertSame( 1, $this->jobs->state()?->run );
	}

	// Issue #20: milestone 4 adds keys the index does not read to the option the mapping and the order are in.
	public function test_a_settings_save_reindexes_only_when_the_mapping_or_the_order_changed(): void {
		$line  = array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'in' );
		$saved = array(
			'attribute_mapping' => array( $line ),
			'source_order'      => array( 'table', 'field', 'attribute' ),
		);
		$this->jobs->settingsChanged( $saved, $saved + array( 'rate_limit' => 60 ) );
		$this->jobs->settingsChanged( $saved + array( 'rate_limit' => 60 ), $saved + array( 'rate_limit' => 120 ) );
		self::assertNull( $this->jobs->state(), 'a key the index does not read' );
		// What reads the same is the same: the default order left out or written, a line the reader drops.
		$this->jobs->settingsChanged( array( 'attribute_mapping' => array( $line ) ), $saved );
		$this->jobs->settingsChanged( $saved, array( 'attribute_mapping' => array( $line, array( 'attribute' => '', 'spec' => 'deck_width' ) ) ) );
		$this->jobs->settingsChanged( 'not settings', array() );
		$this->jobs->settingsChanged( array(), null );
		self::assertNull( $this->jobs->state() );

		$this->jobs->settingsChanged( $saved, array( 'source_order' => array( 'field', 'table', 'attribute' ) ) + $saved );
		self::assertSame( 1, $this->jobs->state()?->run, 'the order' );
		$this->jobs->settingsChanged( $saved, array( 'attribute_mapping' => array( array( 'unit' => 'mm' ) + $line ) ) + $saved );
		self::assertSame( 2, $this->jobs->state()?->run, 'a line\'s unit' );
		$this->jobs->settingsChanged( $saved, array( 'attribute_mapping' => array() ) + $saved );
		self::assertSame( 3, $this->jobs->state()?->run, 'a line removed' );
		$this->jobs->settingsChanged( array(), $saved );
		self::assertSame( 4, $this->jobs->state()?->run, 'the settings saved for the first time, with a mapping' );
		self::assertTrue( $this->queue->isPending( IndexJobs::JOB_REINDEX ) );
	}
}
