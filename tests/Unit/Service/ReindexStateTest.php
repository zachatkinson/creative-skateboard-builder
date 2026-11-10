<?php
/**
 * The reindex's state.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use PHPUnit\Framework\TestCase;

/**
 * Each step, and the round trip through the store.
 */
final class ReindexStateTest extends TestCase {

	public function test_it_begins_at_the_indexs_rows(): void {
		$state = ReindexState::begin( 100 );
		self::assertEquals( new ReindexState( 'rows', 0, 0, 100 ), $state );
		self::assertTrue( $state->isRunning() );
		self::assertNull( $state->finishedAt );
		self::assertFalse( $state->again );
		self::assertSame( 0, $state->failures );
		self::assertSame( '', $state->error );
		self::assertSame( 5, ReindexState::MAX_FAILURES );
		self::assertSame( 1, $state->run );
		self::assertSame( 4, ReindexState::begin( 100, 4 )->run );
	}

	public function test_every_step_keeps_the_run(): void {
		$state = ReindexState::begin( 100, 3 );
		self::assertSame( 3, $state->advanced( 5, 5, false, 110 )->run );
		self::assertSame( 3, $state->advanced( 5, 5, true, 110 )->run );
		self::assertSame( 3, $state->disturbed()->run );
		self::assertSame( 3, $state->failed( 'x' )->run );
		$pages = $state->advanced( 5, 5, true, 110 );
		self::assertSame( 3, $pages->advanced( 1, 5, true, 120 )->run, 'done' );
		self::assertSame( 3, $pages->disturbed()->advanced( 1, 5, true, 120 )->run, 'passing once more' );
	}

	public function test_each_phase_moves_to_the_next_at_its_last_batch(): void {
		$rows = ReindexState::begin( 100 )->advanced( 340, 100, false, 110 );
		self::assertEquals( new ReindexState( 'rows', 340, 100, 100 ), $rows );
		$catalogue = $rows->advanced( 395, 40, true, 120 );
		self::assertEquals( new ReindexState( 'catalogue', 0, 140, 100 ), $catalogue );
		self::assertTrue( $catalogue->isRunning() );
		$paged = $catalogue->advanced( 1, 100, false, 130 );
		self::assertEquals( new ReindexState( 'catalogue', 1, 240, 100 ), $paged );
		$done = $paged->advanced( 2, 7, true, 140 );
		self::assertEquals( new ReindexState( 'done', 0, 247, 100, 140 ), $done );
		self::assertFalse( $done->isRunning() );
	}

	public function test_a_disturbed_reindex_keeps_its_place_and_passes_once_more(): void {
		$paged     = ReindexState::begin( 100 )->advanced( 0, 0, true, 110 )->advanced( 1, 100, false, 120 );
		$disturbed = $paged->failed( 'x' )->disturbed();
		self::assertEquals( new ReindexState( 'catalogue', 1, 100, 100, null, true, 1, 'x' ), $disturbed );
		self::assertTrue( $paged->disturbed()->advanced( 2, 100, false, 130 )->again, 'kept from batch to batch' );
		$restarted = $paged->disturbed()->advanced( 2, 30, true, 130 );
		self::assertEquals( new ReindexState( 'rows', 0, 130, 100 ), $restarted, 'back to the rows, the flag cleared' );
		self::assertTrue( ReindexState::begin( 100 )->disturbed()->advanced( 9, 1, true, 110 )->again, 'kept from the rows to the pages' );
		$done = new ReindexState( 'done', 0, 5, 100, 140 );
		self::assertSame( 140, $done->disturbed()->finishedAt );
	}

	public function test_failures_in_a_row_stop_it_and_a_batch_that_works_clears_them(): void {
		$state = ReindexState::begin( 100 )->disturbed();
		foreach ( array( 1, 2, 3, 4 ) as $failures ) {
			$state = $state->failed( 'boom ' . $failures );
			self::assertSame( 'rows', $state->phase );
			self::assertSame( $failures, $state->failures );
			self::assertSame( 'boom ' . $failures, $state->error );
			self::assertTrue( $state->again );
		}
		$stopped = $state->failed( 'last' );
		self::assertSame( 'failed', $stopped->phase );
		self::assertFalse( $stopped->isRunning() );
		self::assertSame( 5, $stopped->failures );
		$cleared = $state->advanced( 12, 3, false, 110 );
		self::assertSame( 0, $cleared->failures );
		self::assertSame( '', $cleared->error );
	}

	public function test_it_round_trips_through_the_store(): void {
		$state = new ReindexState( 'catalogue', 3, 300, 100, null, true, 2, 'slow', 6 );
		self::assertSame(
			array( 'phase' => 'catalogue', 'position' => 3, 'parts' => 300, 'started_at' => 100, 'finished_at' => null, 'again' => true, 'failures' => 2, 'error' => 'slow', 'run' => 6 ),
			$state->toArray()
		);
		self::assertEquals( $state, ReindexState::fromArray( $state->toArray() ) );
		$done = new ReindexState( 'done', 0, 300, 100, 180 );
		self::assertEquals( $done, ReindexState::fromArray( $done->toArray() ) );
		foreach ( array( 'rows', 'catalogue', 'done', 'failed' ) as $phase ) {
			self::assertSame( $phase, ReindexState::fromArray( array( 'phase' => $phase ) )?->phase );
		}
	}

	public function test_what_is_not_a_state_reads_as_none_and_a_malformed_field_as_its_default(): void {
		self::assertNull( ReindexState::fromArray( null ) );
		self::assertNull( ReindexState::fromArray( array() ) );
		self::assertNull( ReindexState::fromArray( array( 'phase' => 'weird' ) ) );
		self::assertNull( ReindexState::fromArray( array( 'phase' => 1 ) ) );
		$odd = ReindexState::fromArray( array( 'phase' => 'rows', 'position' => -4, 'parts' => '9', 'started_at' => 1.5, 'finished_at' => '7', 'again' => 1, 'failures' => null, 'error' => array() ) );
		self::assertEquals( new ReindexState( 'rows', 0, 0, 0 ), $odd );
		self::assertSame( 1, ReindexState::fromArray( array( 'phase' => 'rows', 'position' => 1 ) )?->position );
		self::assertSame( 1, ReindexState::fromArray( array( 'phase' => 'rows', 'run' => 0 ) )?->run );
		self::assertSame( 2, ReindexState::fromArray( array( 'phase' => 'rows', 'run' => 2 ) )?->run );
	}
}
