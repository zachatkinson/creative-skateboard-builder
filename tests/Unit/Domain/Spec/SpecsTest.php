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
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
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
		yield 'a count past a thousand' => array( SpecKey::SetSize, 1001, SpecError::TooMany );
		yield 'a measure of nothing' => array( SpecKey::DeckWidth, Measure::inches( 0 ), SpecError::NotPositive );
		yield 'a measure of nothing in another unit' => array( SpecKey::WheelDiameter, Measure::inches( 0 ), SpecError::NotPositive );
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

	public function test_a_count_of_a_thousand_is_the_largest(): void {
		self::assertSame( 1000, Specs::MAX_COUNT );
		self::assertSame( 1000, Specs::none()->with( SpecKey::SetSize, 1000 )->count( SpecKey::SetSize ) );
	}

	public function test_the_smallest_measure_is_one_ten_thousandth(): void {
		self::assertEquals( Measure::inches( 1 ), Specs::none()->with( SpecKey::DeckWidth, Measure::inches( 1 ) )->measure( SpecKey::DeckWidth ) );
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

	public function test_a_row_for_a_key_this_version_does_not_know_is_passed_over(): void {
		// A newer version may have written it. After a rollback the part keeps the specs this version reads.
		$specs = Specs::fromIndex( array( 'concave' => 'steep', 'deck_width' => '8.25', 'DECK_LENGTH' => '32', '' => 'x' ) );
		self::assertEquals( Measure::inches( 82500 ), $specs->measure( SpecKey::DeckWidth ) );
		self::assertNull( $specs->measure( SpecKey::DeckLength ), 'a key is its exact name' );
		self::assertEquals( Specs::fromIndex( array( 'deck_width' => '8.25' ) ), $specs );
		self::assertEquals( Specs::none(), Specs::fromIndex( array( 'concave' => 'steep' ) ) );
	}

	public function test_no_rows_is_no_specs(): void {
		self::assertFalse( Specs::fromIndex( array() )->has( SpecKey::DeckWidth ) );
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function unreadableRows(): iterable {
		yield 'a choice nobody offers' => array( 'hole_pattern', 'mixed' );
		yield 'a baseplate nobody offers' => array( 'baseplate', 'six_hole' );
		yield 'a count past a thousand' => array( 'set_size', '1200' );
		yield 'a count too long to be a number' => array( 'set_size', '99999999999999999999' );
		yield 'a count of none' => array( 'set_size', '0' );
		yield 'a count that is a decimal' => array( 'set_size', '2.0' );
		yield 'a count that is a word' => array( 'set_size', 'pair' );
		yield 'a count that is nothing' => array( 'set_size', '' );
		yield 'a negative count' => array( 'set_size', '-2' );
		yield 'a length of nothing' => array( 'deck_length', '0.0000' );
		yield 'a length that is a word' => array( 'deck_length', 'long' );
		yield 'a length that is nothing' => array( 'deck_length', '' );
		yield 'a flag that is a word' => array( 'spacers_built_in', 'yes' );
		yield 'a flag that is two' => array( 'spacers_built_in', '2' );
		yield 'a flag that is nothing' => array( 'spacers_built_in', '' );
	}

	#[DataProvider( 'unreadableRows' )]
	public function test_a_row_this_version_cannot_read_is_passed_over_and_the_part_keeps_its_other_specs( string $key, string $text ): void {
		// A newer version may store a choice, a size or a form this one does not know. After a rollback the part keeps
		// what can be read, and a rule that needs the rest says the spec is missing.
		$specs = Specs::fromIndex( array( 'deck_width' => '8.25', $key => $text, 'wheelbase' => '14.25' ) );
		self::assertEquals( Specs::fromIndex( array( 'deck_width' => '8.25', 'wheelbase' => '14.25' ) ), $specs );
		self::assertFalse( $specs->has( SpecKey::from( $key ) ) );
		self::assertEquals( Specs::none(), Specs::fromIndex( array( $key => $text ) ) );
	}

	public function test_specs_go_to_the_index_as_text_and_come_back_the_same(): void {
		$specs = Specs::none()
			->with( SpecKey::DeckWidth, Measure::inches( 82500 ) )
			->with( SpecKey::MaxWheel, Measure::millimetres( 560000 ) )
			->with( SpecKey::HolePattern, 'both' )
			->with( SpecKey::SpacersBuiltIn, true )
			->with( SpecKey::SetSize, 4 );
		$rows  = $specs->toIndex();
		ksort( $rows );
		self::assertSame(
			array(
				'deck_width'       => '8.2500',
				'hole_pattern'     => 'both',
				'max_wheel'        => '56.0000',
				'set_size'         => '4',
				'spacers_built_in' => '1',
			),
			$rows
		);
		self::assertEquals( $specs, Specs::fromIndex( $specs->toIndex() ) );
		self::assertSame( array( 'spacers_built_in' => '0' ), Specs::none()->with( SpecKey::SpacersBuiltIn, false )->toIndex() );
		self::assertSame( array(), Specs::none()->toIndex() );
	}

	public function test_an_index_row_holds_what_it_was_given_and_the_sources_are_the_columns_values(): void {
		$row = new IndexedSpec( SpecKey::AxleWidth, '8.5000', Origin::Table, 'independent:149' );
		self::assertSame( SpecKey::AxleWidth, $row->key );
		self::assertSame( '8.5000', $row->text );
		self::assertSame( Origin::Table, $row->origin );
		self::assertSame( 'independent:149', $row->ref );
		self::assertSame( '', ( new IndexedSpec( SpecKey::AxleWidth, '8.5000', Origin::Field ) )->ref );
		self::assertSame( array( 'field', 'attribute', 'table' ), array_column( Origin::cases(), 'value' ) );
	}
}
