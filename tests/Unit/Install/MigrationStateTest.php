<?php
/**
 * MigrationState.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationState;
use PHPUnit\Framework\TestCase;

/**
 * The transitions and the round trip through the store.
 */
final class MigrationStateTest extends TestCase {

	public function test_pending_then_started_advanced_finished(): void {
		$state = MigrationState::pending( 'm' );
		self::assertSame( MigrationState::PENDING, $state->status );
		self::assertFalse( $state->isDone() );
		self::assertSame( 0, $state->attempts );

		$state = $state->started( 100 );
		self::assertSame( MigrationState::RUNNING, $state->status );
		self::assertSame( 100, $state->startedAt );
		self::assertSame( 1, $state->attempts );

		$state = $state->advanced( Cursor::at( 3 ) );
		self::assertSame( 3, $state->cursor->position );
		self::assertSame( MigrationState::RUNNING, $state->status );

		$state = $state->finished( 150 );
		self::assertTrue( $state->isDone() );
		self::assertSame( 150, $state->finishedAt );
		self::assertSame( 100, $state->startedAt );
		self::assertTrue( $state->cursor->done );
	}

	public function test_a_second_start_keeps_the_first_start_and_counts_the_attempt(): void {
		$state = MigrationState::pending( 'm' )->started( 100 )->advanced( Cursor::at( 2 ) )->failed( 'boom' );
		self::assertSame( MigrationState::FAILED, $state->status );
		self::assertSame( 'boom', $state->error );
		self::assertSame( 2, $state->cursor->position, 'the cursor stays for the resume' );

		$again = $state->started( 200 );
		self::assertSame( 100, $again->startedAt );
		self::assertSame( 2, $again->attempts );
		self::assertSame( '', $again->error );
		self::assertSame( 2, $again->cursor->position );
	}

	public function test_round_trips_through_the_store(): void {
		$state = MigrationState::pending( 'm' )->started( 100 )->advanced( Cursor::at( 'p' ) )->failed( 'e' );
		$back  = MigrationState::fromArray( 'm', $state->toArray() );
		self::assertSame( $state->toArray(), $back->toArray() );
		self::assertSame( 'm', $back->key );

		$done = MigrationState::pending( 'd' )->started( 1 )->finished( 2 );
		self::assertTrue( MigrationState::fromArray( 'd', $done->toArray() )->isDone() );
	}

	public function test_malformed_data_reads_as_pending(): void {
		self::assertSame( MigrationState::PENDING, MigrationState::fromArray( 'm', 'junk' )->status );
		$odd = MigrationState::fromArray(
			'm',
			array(
				'status'      => 'weird',
				'cursor'      => 'no',
				'started_at'  => '100',
				'finished_at' => 1.5,
				'attempts'    => 'two',
				'error'       => array(),
			)
		);
		self::assertSame( MigrationState::PENDING, $odd->status );
		self::assertNull( $odd->cursor->position );
		self::assertNull( $odd->startedAt );
		self::assertNull( $odd->finishedAt );
		self::assertSame( 0, $odd->attempts );
		self::assertSame( '', $odd->error );
	}
}
