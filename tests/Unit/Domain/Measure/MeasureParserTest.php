<?php
/**
 * MeasureParser.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Measure;

use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a shop manager types into a spec field, read at the edge: decimals, fractions and unit marks.
 */
final class MeasureParserTest extends TestCase {

	/**
	 * @return iterable<string, array{string, Unit, int}>
	 */
	public static function readable(): iterable {
		yield 'a whole number' => array( '8', Unit::Inch, 80000 );
		yield 'a decimal' => array( '8.25', Unit::Inch, 82500 );
		yield 'three places' => array( '8.375', Unit::Inch, 83750 );
		yield 'four places' => array( '8.1234', Unit::Inch, 81234 );
		yield 'a fifth place under half goes down' => array( '8.12344', Unit::Inch, 81234 );
		yield 'a fifth place of half goes up' => array( '8.12345', Unit::Inch, 81235 );
		yield 'a fifth place over half goes up' => array( '8.12346', Unit::Inch, 81235 );
		yield 'later places do not carry twice' => array( '8.123449', Unit::Inch, 81234 );
		yield 'a rounding that carries into the whole' => array( '8.99995', Unit::Inch, 90000 );
		yield 'one place' => array( '8.5', Unit::Inch, 85000 );
		yield 'a leading zero' => array( '0.875', Unit::Inch, 8750 );
		yield 'zero' => array( '0', Unit::Inch, 0 );
		yield 'six whole digits' => array( '123456', Unit::Millimetre, 1234560000 );
		yield 'a fraction' => array( '7/8', Unit::Inch, 8750 );
		yield 'a mixed number with a space' => array( '8 1/4', Unit::Inch, 82500 );
		yield 'a mixed number with a hyphen' => array( '8-1/4', Unit::Inch, 82500 );
		yield 'eighths' => array( '8 3/8', Unit::Inch, 83750 );
		yield 'a sixteenth' => array( '1/16', Unit::Inch, 625 );
		yield 'a thirty-second rounds half up' => array( '1/32', Unit::Inch, 313 );
		yield 'a third rounds down' => array( '1/3', Unit::Inch, 3333 );
		yield 'two thirds round up' => array( '2/3', Unit::Inch, 6667 );
		yield 'an improper fraction' => array( '9/8', Unit::Inch, 11250 );
		yield 'a one-character fraction' => array( '⅞', Unit::Inch, 8750 );
		yield 'a whole and a one-character fraction' => array( '8¼', Unit::Inch, 82500 );
		yield 'a whole, a space and a one-character fraction' => array( '8 ½', Unit::Inch, 85000 );
		yield 'three quarters' => array( '¾', Unit::Inch, 7500 );
		yield 'an eighth' => array( '1⅛', Unit::Inch, 11250 );
		yield 'three eighths' => array( '8⅜', Unit::Inch, 83750 );
		yield 'five eighths' => array( '8⅝', Unit::Inch, 86250 );
		yield 'spaces around it' => array( "  8.25\t", Unit::Inch, 82500 );
		yield 'an inch mark' => array( '8.25"', Unit::Inch, 82500 );
		yield 'a double prime' => array( '8.25″', Unit::Inch, 82500 );
		yield 'in' => array( '8.25 in', Unit::Inch, 82500 );
		yield 'in with a full stop' => array( '8.25in.', Unit::Inch, 82500 );
		yield 'inch' => array( '1 inch', Unit::Inch, 10000 );
		yield 'inches' => array( '8.25 inches', Unit::Inch, 82500 );
		yield 'capitals' => array( '8.25 IN', Unit::Inch, 82500 );
		yield 'mm' => array( '54mm', Unit::Millimetre, 540000 );
		yield 'mm with a space' => array( '54 mm', Unit::Millimetre, 540000 );
		yield 'capital mm' => array( '54 MM', Unit::Millimetre, 540000 );
		yield 'a fraction with a mark' => array( '7/8"', Unit::Inch, 8750 );
		yield 'a mixed number with a mark' => array( '1 1/8 in', Unit::Inch, 11250 );
		yield 'millimetres given for an inch spec' => array( '25.4 mm', Unit::Inch, 10000 );
		yield 'inches given for a millimetre spec' => array( '2 in', Unit::Millimetre, 508000 );
		yield 'an inch mark on a millimetre spec' => array( '1/4"', Unit::Millimetre, 63500 );
	}

	#[DataProvider( 'readable' )]
	public function test_it_reads( string $text, Unit $unit, int $tenThousandths ): void {
		$measure = MeasureParser::parse( $text, $unit );
		self::assertSame( $tenThousandths, $measure->tenThousandths );
		self::assertSame( $unit, $measure->unit );
	}

	public function test_a_decimal_comma_is_read_when_the_shop_uses_one(): void {
		self::assertSame( 82500, MeasureParser::parse( '8,25', Unit::Inch, ',' )->tenThousandths );
		self::assertSame( 540000, MeasureParser::parse( '54,0 mm', Unit::Millimetre, ',' )->tenThousandths );
	}

	/**
	 * @return iterable<string, array{string, string, MeasureError}>
	 */
	public static function refused(): iterable {
		yield 'nothing' => array( '', '.', MeasureError::Malformed );
		yield 'spaces' => array( '   ', '.', MeasureError::Malformed );
		yield 'words' => array( 'wide', '.', MeasureError::Malformed );
		yield 'a product name' => array( 'Independent 149', '.', MeasureError::Malformed );
		yield 'a negative' => array( '-8', '.', MeasureError::Malformed );
		yield 'a plus sign' => array( '+8', '.', MeasureError::Malformed );
		yield 'a range' => array( '8.0 - 8.5', '.', MeasureError::Malformed );
		yield 'two numbers' => array( '8 9', '.', MeasureError::Malformed );
		yield 'the other separator' => array( '8,25', '.', MeasureError::Malformed );
		yield 'a point when the shop uses a comma' => array( '8.25', ',', MeasureError::Malformed );
		yield 'a decimal with nothing after it' => array( '8.', '.', MeasureError::Malformed );
		yield 'a decimal with nothing before it' => array( '.5', '.', MeasureError::Malformed );
		yield 'two points' => array( '8.2.5', '.', MeasureError::Malformed );
		yield 'an exponent' => array( '8e2', '.', MeasureError::Malformed );
		yield 'seven whole digits' => array( '1234567', '.', MeasureError::Malformed );
		yield 'a decimal and a fraction' => array( '8.5 1/4', '.', MeasureError::Malformed );
		yield 'a fraction without its bottom' => array( '7/', '.', MeasureError::Malformed );
		yield 'a fraction without its top' => array( '/8', '.', MeasureError::Malformed );
		yield 'a five-digit fraction' => array( '10000/3', '.', MeasureError::Malformed );
		yield 'a five-digit bottom' => array( '3/10000', '.', MeasureError::Malformed );
		yield 'two one-character fractions' => array( '¼½', '.', MeasureError::Malformed );
		yield 'an unknown unit' => array( '8 cm', '.', MeasureError::Malformed );
		yield 'a unit alone' => array( 'mm', '.', MeasureError::Malformed );
		yield 'a unit first' => array( 'mm 54', '.', MeasureError::Malformed );
		yield 'text after the unit' => array( '54 mm wheels', '.', MeasureError::Malformed );
		yield 'text before the number' => array( 'x8.25', '.', MeasureError::Malformed );
		yield 'a line break inside' => array( "8\n.25", '.', MeasureError::Malformed );
		yield 'a trailing second line' => array( "8.25\n9", '.', MeasureError::Malformed );
		yield 'no separator at all' => array( '12345678', '', MeasureError::Malformed );
		yield 'no separator, a plain number' => array( '8', '', MeasureError::Malformed );
		yield 'a zero bottom' => array( '1/0', '.', MeasureError::ZeroDenominator );
		yield 'a zero bottom in a mixed number' => array( '8 1/0', '.', MeasureError::ZeroDenominator );
	}

	#[DataProvider( 'refused' )]
	public function test_it_refuses( string $text, string $separator, MeasureError $reason ): void {
		try {
			MeasureParser::parse( $text, Unit::Inch, $separator );
			self::fail( 'read ' . $text );
		} catch ( InvalidMeasure $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $reason->value, $e->getMessage() );
		}
	}

	public function test_a_separator_that_means_something_in_a_pattern_is_taken_literally(): void {
		// A point is "any character" to a pattern: 8x25 must not read as 8.25.
		$this->expectException( InvalidMeasure::class );
		MeasureParser::parse( '8x25', Unit::Inch, '.' );
	}
}
