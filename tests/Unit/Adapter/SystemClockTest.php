<?php
/**
 * The live clock.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Adapter;

use CreativeBranding\SkateboardBuilder\Adapter\SystemClock;
use PHPUnit\Framework\TestCase;

/**
 * UTC, now.
 */
final class SystemClockTest extends TestCase {

	public function test_now_is_utc(): void {
		$now = ( new SystemClock() )->now();
		self::assertSame( 'UTC', $now->getTimezone()->getName() );
		self::assertSame( '+00:00', $now->format( 'P' ) );
	}

	public function test_now_is_the_present(): void {
		$before = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$now    = ( new SystemClock() )->now();
		$after  = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		self::assertGreaterThanOrEqual( $before->getTimestamp(), $now->getTimestamp() );
		self::assertLessThanOrEqual( $after->getTimestamp(), $now->getTimestamp() );
	}

	public function test_mysql_format_is_utc_datetime(): void {
		self::assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', ( new SystemClock() )->now()->format( 'Y-m-d H:i:s' ) );
	}
}
