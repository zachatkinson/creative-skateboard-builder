<?php
/**
 * Readings, the source order and how a part's sources resolve.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Disagreement;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\InvalidSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Resolution;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec: Admin, Part specs (8.25, 8,25 and 8 1/4 are the same) and Compatibility rules (where sources disagree).
 */
final class ReadingTest extends TestCase {

	public function test_a_reading_holds_its_value_as_the_index_stores_it(): void {
		$reading = Reading::of( SpecKey::WheelDiameter, Measure::inches( 21260 ), Origin::Attribute, 'pa_diameter' );
		self::assertSame( SpecKey::WheelDiameter, $reading->key );
		self::assertSame( '54.0004', $reading->text, 'in the key\'s millimetres' );
		self::assertSame( Origin::Attribute, $reading->origin );
		self::assertSame( 'pa_diameter', $reading->ref );
		self::assertEquals( new IndexedSpec( SpecKey::WheelDiameter, '54.0004', Origin::Attribute, 'pa_diameter' ), $reading->row() );
		self::assertSame( '', Reading::of( SpecKey::SetSize, 2, Origin::Field )->ref );
		self::assertSame( '2', Reading::of( SpecKey::SetSize, 2, Origin::Field )->text );
		self::assertSame( '0', Reading::of( SpecKey::SpacersBuiltIn, false, Origin::Field )->text );
	}

	public function test_a_value_the_key_cannot_hold_is_refused(): void {
		$this->expectException( InvalidSpec::class );
		Reading::of( SpecKey::DeckWidth, 'wide', Origin::Field );
	}

	/**
	 * What is typed, and the index's text for it.
	 *
	 * @return array<string, array{SpecKey, string, string, string, Unit|null}>
	 */
	public static function typed(): array {
		return array(
			'a decimal'                       => array( SpecKey::DeckWidth, '8.25', '.', '8.2500', null ),
			'the shop\'s comma'               => array( SpecKey::DeckWidth, '8,25', ',', '8.2500', null ),
			'a fraction'                      => array( SpecKey::DeckWidth, '8 1/4', '.', '8.2500', null ),
			'spaces around it'                => array( SpecKey::DeckWidth, "  8.25\t", '.', '8.2500', null ),
			'its own mark'                    => array( SpecKey::DeckWidth, '8.25"', '.', '8.2500', null ),
			'millimetres typed for inches'    => array( SpecKey::DeckWidth, '209.55 mm', '.', '8.2500', null ),
			'an attribute mapped in mm'       => array( SpecKey::DeckWidth, '209.55', '.', '8.2500', Unit::Millimetre ),
			'a mark beats the mapping\'s unit' => array( SpecKey::DeckWidth, '8.25 in', '.', '8.2500', Unit::Millimetre ),
			'a wheel'                         => array( SpecKey::WheelDiameter, '54', '.', '54.0000', null ),
			'a choice'                        => array( SpecKey::HolePattern, 'both', '.', 'both', null ),
			'a choice in capitals'            => array( SpecKey::HeightClass, ' Mid ', '.', 'mid', null ),
			'a flag as yes'                   => array( SpecKey::SpacersBuiltIn, 'Yes', '.', '1', null ),
			'a flag as true'                  => array( SpecKey::SpacersBuiltIn, 'true', '.', '1', null ),
			'a flag as 1'                     => array( SpecKey::SpacersBuiltIn, '1', '.', '1', null ),
			'a flag as no'                    => array( SpecKey::SpacersBuiltIn, 'no', '.', '0', null ),
			'a flag as false'                 => array( SpecKey::SpacersBuiltIn, 'FALSE', '.', '0', null ),
			'a flag as 0'                     => array( SpecKey::SpacersBuiltIn, '0', '.', '0', null ),
			'a count'                         => array( SpecKey::SetSize, ' 4 ', '.', '4', null ),
		);
	}

	#[DataProvider( 'typed' )]
	public function test_what_is_typed_reads_as_one_value( SpecKey $key, string $typed, string $separator, string $text, ?Unit $unit ): void {
		$reading = Reading::from( $key, $typed, Origin::Field, 'r', $separator, $unit );
		self::assertInstanceOf( Reading::class, $reading );
		self::assertSame( $text, $reading->text );
		self::assertSame( $key, $reading->key );
		self::assertSame( Origin::Field, $reading->origin );
		self::assertSame( 'r', $reading->ref );
	}

	/**
	 * What cannot be read, and why.
	 *
	 * @return array<string, array{SpecKey, string, string}>
	 */
	public static function unreadable(): array {
		return array(
			'words for a measure'      => array( SpecKey::DeckWidth, 'wide', 'measure_malformed' ),
			'nothing for a measure'    => array( SpecKey::DeckWidth, '', 'measure_malformed' ),
			'a fraction over nothing'  => array( SpecKey::DeckWidth, '8 1/0', 'measure_zero_denominator' ),
			'a choice nobody offers'   => array( SpecKey::HolePattern, 'square', 'spec_unknown_choice' ),
			'a flag that is a word'    => array( SpecKey::SpacersBuiltIn, 'maybe', 'spec_wrong_kind' ),
			'a flag that is a number'  => array( SpecKey::SpacersBuiltIn, '2', 'spec_wrong_kind' ),
			'a count that is a word'   => array( SpecKey::SetSize, 'pair', 'spec_wrong_kind' ),
			'a count with a point'     => array( SpecKey::SetSize, '2.0', 'spec_wrong_kind' ),
			'a count of none'          => array( SpecKey::SetSize, '0', 'spec_not_positive' ),
			'a count past the most'    => array( SpecKey::SetSize, '1001', 'spec_too_many' ),
			'a flag\'s word as a count' => array( SpecKey::SetSize, 'yes', 'spec_wrong_kind' ),
		);
	}

	#[DataProvider( 'unreadable' )]
	public function test_what_cannot_be_read_says_why( SpecKey $key, string $typed, string $reason ): void {
		$unread = Reading::from( $key, $typed, Origin::Attribute, 'pa_width' );
		self::assertEquals( new Unread( $key->value, $typed, Origin::Attribute, 'pa_width', $reason ), $unread );
	}

	public function test_from_defaults_to_the_parts_own_field_typed_with_a_point(): void {
		$reading = Reading::from( SpecKey::DeckWidth, '8.25', Origin::Field );
		self::assertInstanceOf( Reading::class, $reading );
		self::assertSame( '', $reading->ref );
		self::assertInstanceOf( Unread::class, Reading::from( SpecKey::DeckWidth, '8,25', Origin::Field ), 'a comma is not the default separator' );
	}

	public function test_what_a_person_types_reads_with_the_shops_separator_or_a_point(): void {
		foreach ( array( '8,25', '8.25', '8 1/4' ) as $typed ) {
			$reading = Reading::typed( SpecKey::DeckWidth, $typed, Origin::Attribute, 'pa_width', ',' );
			self::assertInstanceOf( Reading::class, $reading, $typed );
			self::assertSame( '8.2500', $reading->text, $typed );
			self::assertSame( Origin::Attribute, $reading->origin );
			self::assertSame( 'pa_width', $reading->ref );
		}
		$metric = Reading::typed( SpecKey::DeckWidth, '209.55', Origin::Attribute, 'pa_width', ',', Unit::Millimetre );
		self::assertInstanceOf( Reading::class, $metric );
		self::assertSame( '8.2500', $metric->text, 'with a point as with a comma, an unmarked number is in the unit given' );
		$comma = Reading::typed( SpecKey::DeckWidth, '209,55', Origin::Attribute, 'pa_width', ',', Unit::Millimetre );
		self::assertInstanceOf( Reading::class, $comma );
		self::assertSame( '8.2500', $comma->text );

		$unread = Reading::typed( SpecKey::DeckWidth, 'wide', Origin::Table, '7:149', ',' );
		self::assertInstanceOf( Unread::class, $unread );
		self::assertSame( array( 'deck_width', 'wide', Origin::Table, '7:149', 'measure_malformed' ), array( $unread->key, $unread->text, $unread->origin, $unread->ref, $unread->reason ) );

		$plain = Reading::typed( SpecKey::DeckWidth, '8.25', Origin::Field );
		self::assertInstanceOf( Reading::class, $plain );
		self::assertSame( array( '8.2500', '' ), array( $plain->text, $plain->ref ), 'a point and no reference, unless told' );
		self::assertInstanceOf( Unread::class, Reading::typed( SpecKey::DeckWidth, '8,25', Origin::Field ), 'a comma is not read in a shop that writes a point' );
	}

	public function test_the_standard_order_is_the_chart_the_field_the_attribute(): void {
		$order = SourceOrder::standard();
		self::assertSame( array( Origin::Table, Origin::Field, Origin::Attribute ), $order->origins );
		self::assertSame( array( 'table', 'field', 'attribute' ), $order->names() );
		self::assertSame( 0, $order->rank( Origin::Table ) );
		self::assertSame( 1, $order->rank( Origin::Field ) );
		self::assertSame( 2, $order->rank( Origin::Attribute ) );
	}

	public function test_a_saved_order_is_read_and_anything_else_is_the_standard_one(): void {
		$saved = SourceOrder::read( array( 'attribute', 'table', 'field' ) );
		self::assertSame( array( Origin::Attribute, Origin::Table, Origin::Field ), $saved->origins );
		self::assertSame( array( 'attribute', 'table', 'field' ), $saved->names() );
		self::assertSame( 2, $saved->rank( Origin::Field ) );
		self::assertEquals( $saved, SourceOrder::read( $saved->names() ) );
		self::assertEquals( $saved, SourceOrder::of( array( 'attribute', 'table', 'field' ) ) );
		$junk = array(
			'nothing'             => null,
			'a word'              => 'table',
			'one missing'         => array( 'field', 'table' ),
			'one twice'           => array( 'field', 'table', 'table' ),
			'one twice and all'   => array( 'field', 'table', 'attribute', 'table' ),
			'one unknown'         => array( 'field', 'table', 'feed' ),
			'an unknown and all'  => array( 'field', 'table', 'attribute', 'feed' ),
			'not names'           => array( 1, 2, 3 ),
			'a name and not-names' => array( 'field', 'table', 'attribute', 7 ),
		);
		foreach ( $junk as $what => $names ) {
			self::assertEquals( SourceOrder::standard(), SourceOrder::read( $names ), $what );
			self::assertNull( SourceOrder::of( $names ), $what );
		}
	}

	public function test_the_earlier_source_wins_each_spec_and_the_rows_follow_the_keys(): void {
		$chart  = Reading::of( SpecKey::AxleWidth, Measure::inches( 85000 ), Origin::Table, '12:149' );
		$field  = Reading::of( SpecKey::AxleWidth, Measure::inches( 85000 ), Origin::Field );
		$height = Reading::of( SpecKey::TruckHeight, Measure::millimetres( 550000 ), Origin::Attribute, 'pa_height' );
		$size   = Reading::of( SpecKey::SetSize, 2, Origin::Field );

		$resolved = Resolution::of( SourceOrder::standard(), $size, $height, $field, $chart );
		self::assertSame( array( $chart, $height, $size ), $resolved->kept, 'in the order of the keys, the chart before the field' );
		self::assertSame( array(), $resolved->disagreements, 'two sources that agree do not disagree' );
		self::assertEquals(
			array(
				new IndexedSpec( SpecKey::AxleWidth, '8.5000', Origin::Table, '12:149' ),
				new IndexedSpec( SpecKey::TruckHeight, '55.0000', Origin::Attribute, 'pa_height' ),
				new IndexedSpec( SpecKey::SetSize, '2', Origin::Field ),
			),
			$resolved->rows()
		);

		$fieldFirst = Resolution::of( SourceOrder::read( array( 'field', 'attribute', 'table' ) ), $chart, $field );
		self::assertSame( array( $field ), $fieldFirst->kept, 'the shop\'s order decides' );
	}

	public function test_sources_that_say_different_things_are_recorded_with_the_one_kept(): void {
		$attribute = Reading::of( SpecKey::DeckWidth, Measure::inches( 82500 ), Origin::Attribute, 'pa_width' );
		$field     = Reading::of( SpecKey::DeckWidth, Measure::inches( 85000 ), Origin::Field );
		$second    = Reading::of( SpecKey::DeckWidth, Measure::inches( 80000 ), Origin::Attribute, 'pa_size' );
		$agrees    = Reading::of( SpecKey::DeckWidth, Measure::millimetres( 2159000 ), Origin::Table, '3:8.5' );
		$length    = Reading::of( SpecKey::DeckLength, Measure::inches( 320000 ), Origin::Field );

		$resolved = Resolution::of( SourceOrder::standard(), $attribute, $length, $field, $second, $agrees );
		self::assertSame( array( $agrees, $length ), $resolved->kept );
		self::assertEquals( array( new Disagreement( $agrees, array( $attribute, $second ) ) ), $resolved->disagreements, 'the field says the same in another unit; the two attributes do not' );
		self::assertSame( $agrees, $resolved->disagreements[0]->kept );
		self::assertSame( array( $attribute, $second ), $resolved->disagreements[0]->others );
	}

	public function test_two_readings_from_one_source_keep_the_first_and_record_the_other(): void {
		$first  = Reading::of( SpecKey::DeckWidth, Measure::inches( 82500 ), Origin::Attribute, 'pa_width' );
		$second = Reading::of( SpecKey::DeckWidth, Measure::inches( 80000 ), Origin::Attribute, 'pa_size' );
		$one    = Resolution::of( SourceOrder::standard(), $first, $second );
		self::assertSame( array( $first ), $one->kept );
		self::assertEquals( array( new Disagreement( $first, array( $second ) ) ), $one->disagreements );
		$other = Resolution::of( SourceOrder::standard(), $second, $first );
		self::assertSame( array( $second ), $other->kept );
	}

	public function test_no_readings_resolve_to_nothing(): void {
		$resolved = Resolution::of( SourceOrder::standard() );
		self::assertSame( array(), $resolved->kept );
		self::assertSame( array(), $resolved->rows() );
		self::assertSame( array(), $resolved->disagreements );
	}
}
