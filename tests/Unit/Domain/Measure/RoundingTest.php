<?php
/**
 * Rounding.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Measure;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Rounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The one place the domain rounds: half up, in whole numbers.
 */
final class RoundingTest extends TestCase {

	/**
	 * @return iterable<string, array{int, int, int}>
	 */
	public static function divisions(): iterable {
		yield 'exact' => array( 10, 5, 2 );
		yield 'under a half goes down' => array( 149, 100, 1 );
		yield 'a half goes up' => array( 150, 100, 2 );
		yield 'over a half goes up' => array( 151, 100, 2 );
		yield 'a half of an odd divisor' => array( 7, 2, 4 );
		yield 'a third' => array( 1, 3, 0 );
		yield 'two thirds' => array( 2, 3, 1 );
		yield 'nothing' => array( 0, 7, 0 );
		yield 'by one' => array( 9, 1, 9 );
	}

	#[DataProvider( 'divisions' )]
	public function test_a_division_rounds_half_up( int $of, int $by, int $is ): void {
		self::assertSame( $is, Rounding::halfUp( $of, $by ) );
	}

	/**
	 * @return iterable<string, array{string, string, int}>
	 */
	public static function decimals(): iterable {
		yield 'a whole number' => array( '8', '', 80000 );
		yield 'one place' => array( '8', '5', 85000 );
		yield 'four places' => array( '8', '1255', 81255 );
		yield 'a fifth place under five is dropped' => array( '8', '12554', 81255 );
		yield 'a fifth place of five rounds up' => array( '8', '12555', 81256 );
		yield 'a fifth place over five rounds up' => array( '8', '12559', 81256 );
		yield 'only the fifth place decides' => array( '8', '125549', 81255 );
		yield 'nines carry' => array( '8', '99995', 90000 );
		yield 'no whole part' => array( '0', '25', 2500 );
		yield 'leading zeros in the places' => array( '15', '005', 150050 );
	}

	#[DataProvider( 'decimals' )]
	public function test_decimal_digits_become_ten_thousandths( string $whole, string $places, int $is ): void {
		self::assertSame( $is, Rounding::decimal( $whole, $places ) );
	}
}
