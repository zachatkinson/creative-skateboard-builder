<?php
/**
 * What Site Health says of the plugin.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use CreativeBranding\SkateboardBuilder\Service\Health;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryImportStore;
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

	private InMemoryImportStore $imports;

	private Health $health;

	protected function setUp(): void {
		$this->store  = new InMemoryReindexStore();
		$this->queue  = new FakeQueue();
		$this->imports = new InMemoryImportStore();
		$this->health  = new Health( $this->store, $this->queue, $this->imports );
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

	public function test_a_job_that_failed_before_the_last_reindex_finished_is_not_counted(): void {
		$this->health->jobs();
		self::assertSame( 0, $this->queue->since, 'no reindex yet: from the beginning' );
		$this->store->state = ( new ReindexState( ReindexState::CATALOGUE, 3, 300, 100 ) )->toArray();
		$this->health->jobs();
		self::assertSame( 0, $this->queue->since, 'one under way has not read every part yet' );
		$this->store->state = ( new ReindexState( ReindexState::FAILED, 3, 300, 100, 150 ) )->toArray();
		$this->health->jobs();
		self::assertSame( 0, $this->queue->since );
		$this->store->state = ( new ReindexState( ReindexState::DONE, 3, 300, 100, 250 ) )->toArray();
		$this->health->jobs();
		self::assertSame( 250, $this->queue->since, 'from when it finished, not when it started' );
		self::assertSame( 0, Health::since( null ) );
		self::assertSame( 0, Health::since( new ReindexState( ReindexState::DONE, 3, 300, 100 ) ), 'done, with no time kept for it' );
	}

	public function test_an_import_that_stopped_or_lost_its_job_is_said_and_one_at_work_is_not(): void {
		$import = static fn( string $status ): array => ( new ImportState( $status, 't', 'id', '.', 1, array(), array(), 1 ) )->toArray();
		foreach ( array( ImportState::WAITING, ImportState::DONE ) as $status ) {
			$this->imports->state = $import( $status );
			self::assertSame( 'jobs_fine', $this->health->jobs()['code'], $status );
		}
		$this->imports->state = $import( ImportState::FAILED );
		self::assertSame(
			array(
				'status' => 'recommended',
				'code'   => 'import_failed',
				'failed' => 0,
			),
			$this->health->jobs()
		);
		// Its job waiting changes nothing for one that has stopped.
		$this->queue->enqueueUnique( SpecImport::JOB );
		self::assertSame( 'import_failed', $this->health->jobs()['code'] );

		$this->imports->state = $import( ImportState::RUNNING );
		self::assertSame( 'jobs_fine', $this->health->jobs()['code'], 'running, with its job' );
		$this->queue->cancelAll( SpecImport::JOB );
		$this->queue->enqueueUnique( IndexJobs::JOB_REINDEX );
		self::assertSame(
			array(
				'status' => 'recommended',
				'code'   => 'import_stalled',
				'failed' => 0,
			),
			$this->health->jobs()
		);
		// A failed job is said first: it is the one with a part behind it.
		$this->queue->failures = 2;
		self::assertSame( 'jobs_failed', $this->health->jobs()['code'] );
		self::assertSame( 'recommended', $this->health->jobs()['status'] );
	}
	public function test_the_catalogue_checks_findings_are_counted_by_finding_and_by_part(): void {
		self::assertSame(
			array(
				'status'   => Health::GOOD,
				'code'     => Health::CHECK_CLEAR,
				'findings' => 0,
				'parts'    => 0,
			),
			Health::catalogue( array() )
		);
		$found = array(
			new CheckFinding( CatalogueCheck::DISAGREE, 12, spec: 'axle_width' ),
			new CheckFinding( CatalogueCheck::UNREAD, 12, spec: 'deck_min' ),
			new CheckFinding( CatalogueCheck::UNREAD, 15, spec: 'deck_min' ),
			new CheckFinding( CatalogueCheck::SLOT_EMPTY, 0, 7, 'trucks' ),
		);
		self::assertSame(
			array(
				'status'   => Health::RECOMMENDED,
				'code'     => Health::CHECK_FOUND,
				'findings' => 4,
				'parts'    => 2,
			),
			Health::catalogue( $found )
		);
		self::assertSame( 1, Health::catalogue( array( $found[3] ) )['findings'] );
		self::assertSame( 0, Health::catalogue( array( $found[3] ) )['parts'], 'a kit\'s finding names no part' );
	}

	public function test_background_work_is_the_other_two_tests_to_report(): void {
		$work = array(
			new CheckFinding( CatalogueCheck::REINDEX_FAILED ),
			new CheckFinding( CatalogueCheck::IMPORT_FAILED ),
			new CheckFinding( CatalogueCheck::JOBS_FAILED, values: array( 'count' => '3' ) ),
		);
		self::assertSame( Health::CHECK_CLEAR, Health::catalogue( $work )['code'] );
		self::assertSame( Health::GOOD, Health::catalogue( $work )['status'] );
		foreach ( $work as $one ) {
			$with = Health::catalogue( array( $one, new CheckFinding( CatalogueCheck::DISAGREE, 12 ) ) );
			self::assertSame( 1, $with['findings'], $one->code );
			self::assertSame( Health::CHECK_FOUND, $with['code'] );
		}
	}
}
