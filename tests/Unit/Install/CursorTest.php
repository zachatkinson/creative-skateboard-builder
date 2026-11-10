<?php
/**
 * Cursor.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use PHPUnit\Framework\TestCase;

/**
 * Where a migration stopped, to and from the store.
 */
final class CursorTest extends TestCase {

	public function test_start_at_and_done(): void {
		self::assertNull( Cursor::start()->position );
		self::assertFalse( Cursor::start()->done );
		self::assertSame( 40, Cursor::at( 40 )->position );
		self::assertFalse( Cursor::at( 40 )->done );
		self::assertSame( 'page-2', Cursor::at( 'page-2' )->position );
		self::assertTrue( Cursor::done()->done );
		self::assertNull( Cursor::done()->position );
	}

	public function test_round_trips_through_the_store(): void {
		foreach ( array( Cursor::start(), Cursor::at( 7 ), Cursor::at( 'k' ), Cursor::done() ) as $cursor ) {
			$back = Cursor::fromArray( $cursor->toArray() );
			self::assertSame( $cursor->position, $back->position );
			self::assertSame( $cursor->done, $back->done );
		}
	}

	public function test_malformed_data_reads_as_the_start(): void {
		self::assertNull( Cursor::fromArray( 'x' )->position );
		self::assertFalse( Cursor::fromArray( null )->done );
		self::assertNull( Cursor::fromArray( array( 'position' => 1.5 ) )->position, 'a float is not a position' );
		self::assertNull( Cursor::fromArray( array( 'position' => array( 1 ) ) )->position );
		self::assertFalse( Cursor::fromArray( array( 'done' => 'yes' ) )->done, 'only true is done' );
	}

	public function test_done_wins_over_a_position(): void {
		$cursor = Cursor::fromArray(
			array(
				'position' => 9,
				'done'     => true,
			)
		);
		self::assertTrue( $cursor->done );
		self::assertNull( $cursor->position );
	}
}
