<?php
/**
 * Measure.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Measure;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use PHPUnit\Framework\TestCase;

/**
 * A length as a whole number of ten-thousandths of its unit: exact comparison across inches and millimetres.
 */
final class MeasureTest extends TestCase {

	public function test_a_measure_holds_ten_thousandths_of_its_unit(): void {
		$deck = Measure::inches( 82500 );
		self::assertSame( 82500, $deck->tenThousandths );
		self::assertSame( Unit::Inch, $deck->unit );
		$wheel = Measure::millimetres( 540000 );
		self::assertSame( 540000, $wheel->tenThousandths );
		self::assertSame( Unit::Millimetre, $wheel->unit );
		self::assertSame( 0, Measure::inches( 0 )->tenThousandths, 'no riser is a riser of nothing' );
	}

	public function test_a_measure_is_never_negative(): void {
		try {
			Measure::inches( -1 );
			self::fail( 'accepted' );
		} catch ( InvalidMeasure $e ) {
			self::assertSame( MeasureError::Negative, $e->reason );
			self::assertSame( 'measure_negative', $e->getMessage() );
		}
	}

	public function test_an_inch_is_25_4_millimetres_exactly(): void {
		self::assertSame( 254000, Measure::inches( 10000 )->to( Unit::Millimetre )->tenThousandths );
		self::assertSame( Unit::Millimetre, Measure::inches( 10000 )->to( Unit::Millimetre )->unit );
		self::assertSame( 10000, Measure::millimetres( 254000 )->to( Unit::Inch )->tenThousandths );
		self::assertSame( Unit::Inch, Measure::millimetres( 254000 )->to( Unit::Inch )->unit );
		// 8.25 in is 209.55 mm.
		self::assertSame( 2095500, Measure::inches( 82500 )->to( Unit::Millimetre )->tenThousandths );
	}

	public function test_converting_to_its_own_unit_changes_nothing(): void {
		$deck = Measure::inches( 82500 );
		self::assertSame( $deck, $deck->to( Unit::Inch ) );
	}

	public function test_a_conversion_rounds_half_up_at_the_fourth_place(): void {
		// 54 mm is 2.12598... in.
		self::assertSame( 21260, Measure::millimetres( 540000 )->to( Unit::Inch )->tenThousandths );
		// 0.0127 mm is 0.0005 in exactly: the half goes up.
		self::assertSame( 5, Measure::millimetres( 127 )->to( Unit::Inch )->tenThousandths );
		// 0.0126 mm is 0.000496 in: down.
		self::assertSame( 5, Measure::millimetres( 126 )->to( Unit::Inch )->tenThousandths );
		self::assertSame( 4, Measure::millimetres( 114 )->to( Unit::Inch )->tenThousandths, '0.0114 mm is 0.000449 in' );
		// 0.0001 in is 0.00254 mm: 0.0025 mm.
		self::assertSame( 25, Measure::inches( 1 )->to( Unit::Millimetre )->tenThousandths );
		// 0.0002 in is 0.00508 mm: 0.0051 mm.
		self::assertSame( 51, Measure::inches( 2 )->to( Unit::Millimetre )->tenThousandths );
		// 0.0005 in is 0.0127 mm exactly.
		self::assertSame( 127, Measure::inches( 5 )->to( Unit::Millimetre )->tenThousandths );
		// 0.0025 in is 0.0635 mm exactly: a half that goes up.
		self::assertSame( 635, Measure::inches( 25 )->to( Unit::Millimetre )->tenThousandths );
	}

	public function test_measures_compare_exactly_across_units(): void {
		$inch = Measure::inches( 10000 );
		self::assertSame( 0, $inch->compare( Measure::millimetres( 254000 ) ) );
		self::assertSame( 1, $inch->compare( Measure::millimetres( 253999 ) ) );
		self::assertSame( -1, $inch->compare( Measure::millimetres( 254001 ) ) );
		self::assertSame( -1, Measure::inches( 82500 )->compare( Measure::inches( 83750 ) ) );
		self::assertSame( 1, Measure::millimetres( 560000 )->compare( Measure::millimetres( 540000 ) ) );
		self::assertTrue( $inch->equals( Measure::millimetres( 254000 ) ) );
		self::assertFalse( $inch->equals( Measure::millimetres( 254001 ) ) );
	}

	public function test_at_least_and_at_most_include_the_boundary(): void {
		$deck = Measure::inches( 83750 );
		self::assertTrue( $deck->atLeast( Measure::inches( 83750 ) ) );
		self::assertTrue( $deck->atMost( Measure::inches( 83750 ) ) );
		self::assertTrue( $deck->atLeast( Measure::inches( 83749 ) ) );
		self::assertFalse( $deck->atLeast( Measure::inches( 83751 ) ) );
		self::assertTrue( $deck->atMost( Measure::inches( 83751 ) ) );
		self::assertFalse( $deck->atMost( Measure::inches( 83749 ) ) );
		self::assertTrue( $deck->moreThan( Measure::inches( 83749 ) ) );
		self::assertFalse( $deck->moreThan( Measure::inches( 83750 ) ) );
		self::assertTrue( $deck->lessThan( Measure::inches( 83751 ) ) );
		self::assertFalse( $deck->lessThan( Measure::inches( 83750 ) ) );
	}

	public function test_plus_adds_in_the_first_measures_unit(): void {
		$sum = Measure::inches( 8750 )->plus( Measure::inches( 2500 ) );
		self::assertSame( 11250, $sum->tenThousandths );
		self::assertSame( Unit::Inch, $sum->unit );
		$mixed = Measure::inches( 10000 )->plus( Measure::millimetres( 254000 ) );
		self::assertSame( 20000, $mixed->tenThousandths );
		self::assertSame( Unit::Inch, $mixed->unit );
	}

	public function test_the_gap_between_two_measures_is_the_same_either_way(): void {
		$deck = Measure::inches( 82500 );
		$axle = Measure::inches( 85000 );
		self::assertSame( 2500, $deck->gap( $axle )->tenThousandths );
		self::assertSame( 2500, $axle->gap( $deck )->tenThousandths );
		self::assertSame( Unit::Inch, $axle->gap( $deck )->unit );
		self::assertSame( 0, $deck->gap( $deck )->tenThousandths );
		// 58 mm over a 56 mm limit, with the limit given in inches by some part.
		$over = Measure::millimetres( 580000 )->gap( Measure::millimetres( 560000 )->to( Unit::Inch ) );
		self::assertSame( Unit::Millimetre, $over->unit );
		self::assertSame( 20006, $over->tenThousandths, '56 mm is 2.2047 in, which is 55.9994 mm' );
	}

	public function test_a_measure_reads_as_a_decimal_string_for_the_index(): void {
		self::assertSame( '8.2500', Measure::inches( 82500 )->decimal() );
		self::assertSame( '0.8750', Measure::inches( 8750 )->decimal() );
		self::assertSame( '54.0000', Measure::millimetres( 540000 )->decimal() );
		self::assertSame( '0.0000', Measure::inches( 0 )->decimal() );
		self::assertSame( '10.0001', Measure::inches( 100001 )->decimal() );
		self::assertSame( '0.0010', Measure::inches( 10 )->decimal() );
		self::assertSame( '0.9999', Measure::inches( 9999 )->decimal() );
		self::assertSame( '1.0000', Measure::inches( 10000 )->decimal() );
	}
}
