<?php
/**
 * Cast.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Support;

use CreativeBranding\SkateboardBuilder\Support\Cast;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every cast, with the values hooks and options hand over.
 */
final class CastTest extends TestCase {

	/**
	 * @return iterable<string, array{mixed, int}>
	 */
	public static function ints(): iterable {
		yield 'int' => array( 5, 5 );
		yield 'numeric string' => array( '12', 12 );
		yield 'float' => array( 3.9, 3 );
		yield 'text' => array( 'abc', 0 );
		yield 'null' => array( null, 0 );
		yield 'array' => array( array( 1 ), 0 );
	}

	#[DataProvider( 'ints' )]
	public function test_int( mixed $value, int $expected ): void {
		self::assertSame( $expected, Cast::int( $value ) );
	}

	public function test_int_fallback(): void {
		self::assertSame( -1, Cast::int( 'x', -1 ) );
	}

	/**
	 * @return iterable<string, array{mixed, string}>
	 */
	public static function strs(): iterable {
		yield 'string' => array( 'a', 'a' );
		yield 'int' => array( 3, '3' );
		yield 'bool' => array( true, '1' );
		yield 'null' => array( null, '' );
		yield 'array' => array( array( 'a' ), '' );
		yield 'object' => array( new \stdClass(), '' );
	}

	#[DataProvider( 'strs' )]
	public function test_str( mixed $value, string $expected ): void {
		self::assertSame( $expected, Cast::str( $value ) );
	}

	public function test_str_fallback(): void {
		self::assertSame( 'none', Cast::str( null, 'none' ) );
	}

	public function test_float(): void {
		self::assertSame( 8.25, Cast::float( '8.25' ) );
		self::assertSame( 2.0, Cast::float( 2 ) );
		self::assertSame( 0.0, Cast::float( 'wide' ) );
		self::assertSame( 1.5, Cast::float( null, 1.5 ) );
	}

	/**
	 * @return iterable<string, array{mixed, bool}>
	 */
	public static function bools(): iterable {
		yield 'true' => array( true, true );
		yield 'false' => array( false, false );
		yield 'one' => array( 1, true );
		yield 'two' => array( 2, false );
		yield 'zero' => array( 0, false );
		yield 'yes' => array( 'yes', true );
		yield 'YES' => array( ' YES ', true );
		yield 'on' => array( 'on', true );
		yield 'string true' => array( 'true', true );
		yield 'string one' => array( '1', true );
		yield 'no' => array( 'no', false );
		yield 'empty' => array( '', false );
		yield 'null' => array( null, false );
		yield 'array' => array( array( true ), false );
		yield 'float' => array( 1.0, false );
	}

	#[DataProvider( 'bools' )]
	public function test_bool( mixed $value, bool $expected ): void {
		self::assertSame( $expected, Cast::bool( $value ) );
	}

	public function test_ints_drops_what_is_not_numeric(): void {
		self::assertSame( array( 1, 2, 3 ), Cast::ints( array( '1', 2, 'x', null, 3.7 ) ) );
		self::assertSame( array(), Cast::ints( 'not a list' ) );
	}

	public function test_strings_drops_what_is_not_scalar(): void {
		self::assertSame( array( 'a', '1', '' ), Cast::strings( array( 'a', 1, false, array(), null ) ) );
		self::assertSame( array(), Cast::strings( null ) );
	}

	public function test_map_keeps_string_keys_only(): void {
		self::assertSame( array( 'a' => 1 ), Cast::map( array( 'a' => 1, 0 => 'dropped' ) ) );
		self::assertSame(
			array(
				'a' => 1,
				'b' => 2,
			),
			Cast::map(
				array(
					'a' => 1,
					'b' => 2,
				)
			),
			'every string key, not the first alone'
		);
		self::assertSame( array(), Cast::map( 'x' ) );
	}
}
