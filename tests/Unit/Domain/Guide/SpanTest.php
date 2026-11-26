<?php
/**
 * Span.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Guide;

use CreativeBranding\SkateboardBuilder\Domain\Guide\Span;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use PHPUnit\Framework\TestCase;

/**
 * #134: the sizes a recommendation names, both ends kept, or from one size and larger.
 */
final class SpanTest extends TestCase {

	public function test_a_size_is_held_from_the_smallest_to_the_largest_both_kept(): void {
		$span = new Span( Measure::millimetres( 520000 ), Measure::millimetres( 540000 ) );
		self::assertFalse( $span->holds( Measure::millimetres( 519999 ) ) );
		self::assertTrue( $span->holds( Measure::millimetres( 520000 ) ) );
		self::assertTrue( $span->holds( Measure::millimetres( 540000 ) ) );
		self::assertFalse( $span->holds( Measure::millimetres( 540001 ) ) );
		self::assertTrue( $span->holds( Measure::inches( 20866 ) ), 'in any unit: 2.0866 in is 53 mm' );
	}

	public function test_a_range_with_no_end_holds_every_larger_size(): void {
		$span = new Span( Measure::inches( 82500 ), null );
		self::assertFalse( $span->holds( Measure::inches( 82499 ) ) );
		self::assertTrue( $span->holds( Measure::inches( 82500 ) ) );
		self::assertTrue( $span->holds( Measure::inches( 120000 ) ) );
	}

	public function test_the_distance_from_the_middle_is_twice_it_in_the_smallests_unit(): void {
		$span = new Span( Measure::inches( 80000 ), Measure::inches( 82500 ) );
		self::assertSame( 0, $span->fromMiddle( Measure::inches( 81250 ) ) );
		self::assertSame( 2500, $span->fromMiddle( Measure::inches( 80000 ) ) );
		self::assertSame( 2500, $span->fromMiddle( Measure::inches( 82500 ) ) );
		self::assertSame( 12500, $span->fromMiddle( Measure::inches( 87500 ) ) );
		self::assertSame( 0, ( new Span( Measure::millimetres( 540000 ), Measure::inches( 21260 ) ) )->fromMiddle( Measure::millimetres( 540002 ) ), 'the largest in its own unit: 2.126 in is 54.0004 mm' );
		$open = new Span( Measure::inches( 82500 ), null );
		self::assertSame( 0, $open->fromMiddle( Measure::inches( 82500 ) ), 'with no end, from the smallest' );
		self::assertSame( 10000, $open->fromMiddle( Measure::inches( 87500 ) ) );
	}
}
