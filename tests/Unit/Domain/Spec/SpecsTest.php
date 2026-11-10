<?php
/**
 * Specs.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\InvalidSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecError;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A part's specs: one value per key, each of its key's kind.
 */
final class SpecsTest extends TestCase {

	public function test_no_specs_has_nothing(): void {
		$specs = Specs::none();
		foreach ( SpecKey::cases() as $key ) {
			self::assertFalse( $specs->has( $key ) );
			self::assertNull( $specs->measure( $key ) );
			self::assertNull( $specs->choice( $key ) );
			self::assertFalse( $specs->flag( $key ) );
			self::assertNull( $specs->count( $key ) );
		}
	}

	public function test_each_kind_reads_back_through_its_own_accessor_only(): void {
		$specs = Specs::none()
			->with( SpecKey::DeckWidth, Measure::inches( 82500 ) )
			->with( SpecKey::HolePattern, 'old_school' )
			->with( SpecKey::SpacersBuiltIn, true )
			->with( SpecKey::SetSize, 4 );

		self::assertTrue( $specs->has( SpecKey::DeckWidth ) );
		self::assertSame( 82500, $specs->measure( SpecKey::DeckWidth )?->tenThousandths );
		self::assertNull( $specs->choice( SpecKey::DeckWidth ) );
		self::assertFalse( $specs->flag( SpecKey::DeckWidth ) );
		self::assertNull( $specs->count( SpecKey::DeckWidth ) );

		self::assertSame( 'old_school', $specs->choice( SpecKey::HolePattern ) );
		self::assertNull( $specs->measure( SpecKey::HolePattern ) );
		self::assertNull( $specs->count( SpecKey::HolePattern ) );

		self::assertTrue( $specs->flag( SpecKey::SpacersBuiltIn ) );
		self::assertNull( $specs->choice( SpecKey::SpacersBuiltIn ) );
		self::assertNull( $specs->count( SpecKey::SpacersBuiltIn ) );

		self::assertSame( 4, $specs->count( SpecKey::SetSize ) );
		self::assertNull( $specs->measure( SpecKey::SetSize ) );
		self::assertFalse( $specs->flag( SpecKey::SetSize ) );
	}

	public function test_a_flag_that_is_off_is_held_and_reads_as_off(): void {
		$specs = Specs::none()->with( SpecKey::SpacersBuiltIn, false );
		self::assertTrue( $specs->has( SpecKey::SpacersBuiltIn ) );
		self::assertFalse( $specs->flag( SpecKey::SpacersBuiltIn ) );
	}

	public function test_with_leaves_the_original_alone_and_replaces_a_value(): void {
		$first  = Specs::none()->with( SpecKey::DeckWidth, Measure::inches( 80000 ) );
		$second = $first->with( SpecKey::DeckWidth, Measure::inches( 85000 ) )->with( SpecKey::DeckLength, Measure::inches( 320000 ) );
		self::assertSame( 80000, $first->measure( SpecKey::DeckWidth )?->tenThousandths );
		self::assertFalse( $first->has( SpecKey::DeckLength ) );
		self::assertSame( 85000, $second->measure( SpecKey::DeckWidth )?->tenThousandths );
		self::assertSame( 320000, $second->measure( SpecKey::DeckLength )?->tenThousandths );
	}

	public function test_a_measure_is_kept_in_its_keys_unit(): void {
		$specs = Specs::none()
			->with( SpecKey::WheelDiameter, Measure::inches( 20000 ) )
			->with( SpecKey::DeckWidth, Measure::millimetres( 2095500 ) );
		self::assertSame( Unit::Millimetre, $specs->measure( SpecKey::WheelDiameter )?->unit );
		self::assertSame( 508000, $specs->measure( SpecKey::WheelDiameter )?->tenThousandths );
		self::assertSame( Unit::Inch, $specs->measure( SpecKey::DeckWidth )?->unit );
		self::assertSame( 82500, $specs->measure( SpecKey::DeckWidth )?->tenThousandths );
	}

	/**
	 * @return iterable<string, array{SpecKey, Measure|string|bool|int, SpecError}>
	 */
	public static function refusedValues(): iterable {
		yield 'text for a measure' => array( SpecKey::DeckWidth, '8.25', SpecError::WrongKind );
		yield 'a number for a measure' => array( SpecKey::DeckWidth, 8, SpecError::WrongKind );
		yield 'a flag for a measure' => array( SpecKey::DeckWidth, true, SpecError::WrongKind );
		yield 'a measure for a choice' => array( SpecKey::HolePattern, Measure::inches( 1 ), SpecError::WrongKind );
		yield 'a number for a choice' => array( SpecKey::HolePattern, 1, SpecError::WrongKind );
		yield 'a flag for a choice' => array( SpecKey::Baseplate, true, SpecError::WrongKind );
		yield 'a choice nobody offers' => array( SpecKey::HolePattern, 'six_hole', SpecError::UnknownChoice );
		yield 'another choice\'s value' => array( SpecKey::HeightClass, 'both', SpecError::UnknownChoice );
		yield 'a pattern for a class' => array( SpecKey::Baseplate, 'low', SpecError::UnknownChoice );
		yield 'text for a flag' => array( SpecKey::SpacersBuiltIn, 'yes', SpecError::WrongKind );
		yield 'a number for a flag' => array( SpecKey::SpacersBuiltIn, 1, SpecError::WrongKind );
		yield 'a measure for a flag' => array( SpecKey::SpacersBuiltIn, Measure::inches( 1 ), SpecError::WrongKind );
		yield 'text for a count' => array( SpecKey::SetSize, '4', SpecError::WrongKind );
		yield 'a flag for a count' => array( SpecKey::SetSize, true, SpecError::WrongKind );
		yield 'a measure for a count' => array( SpecKey::SetSize, Measure::inches( 4 ), SpecError::WrongKind );
		yield 'a count of none' => array( SpecKey::SetSize, 0, SpecError::NotPositive );
		yield 'a count below none' => array( SpecKey::SetSize, -2, SpecError::NotPositive );
	}

	#[DataProvider( 'refusedValues' )]
	public function test_a_value_of_the_wrong_kind_is_refused( SpecKey $key, Measure|string|bool|int $value, SpecError $reason ): void {
		try {
			Specs::none()->with( $key, $value );
			self::fail( 'accepted' );
		} catch ( InvalidSpec $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $key->value, $e->key );
			self::assertSame( $reason->value, $e->getMessage() );
		}
	}

	public function test_a_count_of_one_is_the_smallest(): void {
		self::assertSame( 1, Specs::none()->with( SpecKey::SetSize, 1 )->count( SpecKey::SetSize ) );
	}

	public function test_specs_are_read_from_the_indexs_rows(): void {
		$specs = Specs::fromIndex(
			array(
				'axle_width'       => '8.5000',
				'deck_min'         => '8.3750',
				'deck_max'         => '8.6000',
				'max_wheel'        => '56.0000',
				'baseplate'        => 'new_school',
				'height_class'     => 'high',
				'spacers_built_in' => '1',
				'set_size'         => '2',
			)
		);
		self::assertSame( 85000, $specs->measure( SpecKey::AxleWidth )?->tenThousandths );
		self::assertSame( Unit::Inch, $specs->measure( SpecKey::AxleWidth )?->unit );
		self::assertSame( 83750, $specs->measure( SpecKey::DeckMin )?->tenThousandths );
		self::assertSame( 86000, $specs->measure( SpecKey::DeckMax )?->tenThousandths );
		self::assertSame( 560000, $specs->measure( SpecKey::MaxWheel )?->tenThousandths );
		self::assertSame( Unit::Millimetre, $specs->measure( SpecKey::MaxWheel )?->unit );
		self::assertSame( 'new_school', $specs->choice( SpecKey::Baseplate ) );
		self::assertSame( 'high', $specs->choice( SpecKey::HeightClass ) );
		self::assertTrue( $specs->flag( SpecKey::SpacersBuiltIn ) );
		self::assertSame( 2, $specs->count( SpecKey::SetSize ) );
		self::assertFalse( $specs->has( SpecKey::DeckWidth ) );
	}

	public function test_a_flag_of_zero_in_the_index_is_off(): void {
		$specs = Specs::fromIndex( array( 'spacers_built_in' => '0' ) );
		self::assertTrue( $specs->has( SpecKey::SpacersBuiltIn ) );
		self::assertFalse( $specs->flag( SpecKey::SpacersBuiltIn ) );
	}

	public function test_no_rows_is_no_specs(): void {
		self::assertFalse( Specs::fromIndex( array() )->has( SpecKey::DeckWidth ) );
	}

	/**
	 * @return iterable<string, array{array<string, string>, SpecError, string}>
	 */
	public static function refusedRows(): iterable {
		yield 'a key nobody knows' => array( array( 'colour' => 'red' ), SpecError::UnknownKey, 'colour' );
		yield 'a key in capitals' => array( array( 'DECK_WIDTH' => '8.25' ), SpecError::UnknownKey, 'DECK_WIDTH' );
		yield 'a flag that is a word' => array( array( 'spacers_built_in' => 'yes' ), SpecError::WrongKind, 'spacers_built_in' );
		yield 'a flag that is two' => array( array( 'spacers_built_in' => '2' ), SpecError::WrongKind, 'spacers_built_in' );
		yield 'a flag that is nothing' => array( array( 'spacers_built_in' => '' ), SpecError::WrongKind, 'spacers_built_in' );
		yield 'a count that is a decimal' => array( array( 'set_size' => '2.0' ), SpecError::WrongKind, 'set_size' );
		yield 'a count that is a word' => array( array( 'set_size' => 'pair' ), SpecError::WrongKind, 'set_size' );
		yield 'a count that is nothing' => array( array( 'set_size' => '' ), SpecError::WrongKind, 'set_size' );
		yield 'a negative count' => array( array( 'set_size' => '-2' ), SpecError::WrongKind, 'set_size' );
		yield 'a count of none' => array( array( 'set_size' => '0' ), SpecError::NotPositive, 'set_size' );
		yield 'a choice nobody offers' => array( array( 'baseplate' => 'six_hole' ), SpecError::UnknownChoice, 'baseplate' );
		yield 'the second of two rows' => array( array( 'deck_width' => '8.25', 'hole_pattern' => 'none' ), SpecError::UnknownChoice, 'hole_pattern' );
	}

	/**
	 * @param array<string, string> $rows
	 */
	#[DataProvider( 'refusedRows' )]
	public function test_a_row_that_is_not_a_spec_is_refused( array $rows, SpecError $reason, string $key ): void {
		try {
			Specs::fromIndex( $rows );
			self::fail( 'accepted' );
		} catch ( InvalidSpec $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $key, $e->key );
		}
	}

	public function test_a_measure_the_parser_cannot_read_is_its_own_error(): void {
		$this->expectException( InvalidMeasure::class );
		Specs::fromIndex( array( 'deck_width' => 'wide' ) );
	}
}
