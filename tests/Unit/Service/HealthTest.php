<?php
/**
 * What Site Health says of the plugin.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\Health;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryReindexStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Resilience: a failure shows in Site Health. Roadmap, milestone 3: Site Health tests, the index up to date
 * and failed jobs.
 */
#[CoversClass( Health::class )]
final class HealthTest extends TestCase {

	private InMemoryReindexStore $store;

	private FakeQueue $queue;

	private Health $health;

	protected function setUp(): void {
		$this->store  = new InMemoryReindexStore();
		$this->queue  = new FakeQueue();
		$this->health = new Health( $this->store, $this->queue );
	}

	public function test_an_index_never_rebuilt_or_rebuilt_to_its_end_is_up_to_date(): void {
		self::assertSame(
			array(
				'status' => 'good',
				'code'   => 'index_current',
				'parts'  => 0,
			),
			$this->health->index()
		);
		$this->store->state = ( new ReindexState( ReindexState::DONE, 40, 37, 1, 2 ) )->toArray();
		self::assertSame(
			array(
				'status' => 'good',
				'code'   => 'index_current',
				'parts'  => 37,
			),
			$this->health->index()
		);
	}

	public function test_a_reindex_that_stopped_after_its_failures_is_critical(): void {
		$this->store->state = ( new ReindexState( ReindexState::FAILED, 100, 100, 1, null, false, ReindexState::MAX_FAILURES, 'gone' ) )->toArray();
		// Its job being queued again by hand changes nothing until it has run.
		$this->queue->enqueueUnique( IndexJobs::JOB_REINDEX );
		self::assertSame(
			array(
				'status' => 'critical',
				'code'   => 'index_failed',
				'parts'  => 100,
			),
			$this->health->index()
		);
	}

	public function test_a_reindex_under_way_is_fine_while_its_job_waits_or_runs_and_stalled_when_it_has_none(): void {
		foreach ( array( ReindexState::ROWS, ReindexState::CATALOGUE ) as $phase ) {
			$this->store->state = ( new ReindexState( $phase, 200, 150, 1 ) )->toArray();
			$this->queue->cancelAll( IndexJobs::JOB_REINDEX );
			self::assertSame(
				array(
					'status' => 'recommended',
					'code'   => 'index_stalled',
					'parts'  => 150,
				),
				$this->health->index(),
				$phase
			);
			// Another job waiting is not the reindex's.
			$this->queue->enqueueUnique( IndexJobs::JOB_PART );
			self::assertSame( 'index_stalled', $this->health->index()['code'], $phase );
			$this->queue->enqueueUnique( IndexJobs::JOB_REINDEX );
			self::assertSame(
				array(
					'status' => 'good',
					'code'   => 'index_building',
					'parts'  => 150,
				),
				$this->health->index(),
				$phase
			);
		}
	}

	public function test_failed_jobs_are_counted_and_none_is_good(): void {
		self::assertSame(
			array(
				'status' => 'good',
				'code'   => 'jobs_fine',
				'failed' => 0,
			),
			$this->health->jobs()
		);
		$this->queue->failures = 1;
		self::assertSame(
			array(
				'status' => 'recommended',
				'code'   => 'jobs_failed',
				'failed' => 1,
			),
			$this->health->jobs()
		);
		$this->queue->failures = 12;
		self::assertSame( 12, $this->health->jobs()['failed'] );
	}
}
