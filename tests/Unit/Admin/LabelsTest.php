<?php
/**
 * The names the admin gives the domain's keys.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Admin;

use CreativeBranding\SkateboardBuilder\Admin\Labels;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use PHPUnit\Framework\Attributes\DataProvider;
use CreativeBranding\SkateboardBuilder\Service\Health;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin: every spec, unit, source and choice has a name a shop manager reads, and a value is written with
 * its unit as the shop's locale writes numbers.
 */
final class LabelsTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_decimal'] );
	}

	public function test_every_spec_has_its_own_name_and_a_measure_names_its_unit_in_a_fields_label(): void {
		$names = array(
			'deck_width'       => array( 'Deck width', 'Deck width (in)' ),
			'deck_length'      => array( 'Deck length', 'Deck length (in)' ),
			'wheelbase'        => array( 'Wheelbase', 'Wheelbase (in)' ),
			'hole_pattern'     => array( 'Deck hole pattern', 'Deck hole pattern' ),
			'axle_width'       => array( 'Axle width', 'Axle width (in)' ),
			'deck_min'         => array( 'Narrowest deck', 'Narrowest deck (in)' ),
			'deck_max'         => array( 'Widest deck', 'Widest deck (in)' ),
			'truck_height'     => array( 'Truck height', 'Truck height (mm)' ),
			'height_class'     => array( 'Truck height class', 'Truck height class' ),
			'max_wheel'        => array( 'Largest wheel without risers', 'Largest wheel without risers (mm)' ),
			'baseplate'        => array( 'Baseplate hole pattern', 'Baseplate hole pattern' ),
			'axle_diameter'    => array( 'Axle diameter', 'Axle diameter (mm)' ),
			'wheel_diameter'   => array( 'Wheel diameter', 'Wheel diameter (mm)' ),
			'bearings_included' => array( 'Bearings included', 'Bearings included' ),
			'riser_thickness'  => array( 'Riser thickness', 'Riser thickness (in)' ),
			'bolt_length'      => array( 'Bolt length', 'Bolt length (in)' ),
			'bearing_bore'     => array( 'Bearing bore', 'Bearing bore (mm)' ),
			'spacers_built_in' => array( 'Spacers built in', 'Spacers built in' ),
			'grip_width'       => array( 'Grip width', 'Grip width (in)' ),
			'grip_length'      => array( 'Grip length', 'Grip length (in)' ),
			'set_size'         => array( 'Number in a set', 'Number in a set' ),
		);
		self::assertSame( array_column( SpecKey::cases(), 'value' ), array_keys( $names ), 'every spec, and none twice' );
		foreach ( SpecKey::cases() as $key ) {
			self::assertSame( $names[ $key->value ][0], Labels::spec( $key ), $key->value );
			self::assertSame( $names[ $key->value ][1], Labels::field( $key ), $key->value );
		}
	}

	public function test_units_sources_and_choices_have_names_and_an_unknown_choice_is_shown_as_it_is(): void {
		self::assertSame( 'Inches (in)', Labels::unit( Unit::Inch ) );
		self::assertSame( 'Millimetres (mm)', Labels::unit( Unit::Millimetre ) );
		self::assertSame( 'The maker\'s chart', Labels::source( Origin::Table ) );
		self::assertSame( 'The part\'s own field', Labels::source( Origin::Field ) );
		self::assertSame( 'A mapped attribute', Labels::source( Origin::Attribute ) );
		self::assertSame(
			array( 'New school', 'Old school', 'Both', 'Low', 'Mid', 'High', 'kingpinless', '' ),
			array_map( Labels::choice( ... ), array( 'new_school', 'old_school', 'both', 'low', 'mid', 'high', 'kingpinless', '' ) )
		);
	}

	/**
	 * A stored value, and how the admin shows it.
	 *
	 * @return array<string, array{0: SpecKey, 1: string, 2: string}>
	 */
	public static function values(): array {
		return array(
			'inches'                    => array( SpecKey::DeckWidth, '8.25', '8.25 in' ),
			'inches, zeros dropped'     => array( SpecKey::DeckWidth, '8.500', '8.5 in' ),
			'inches, whole'             => array( SpecKey::DeckWidth, '8.000', '8 in' ),
			'inches, no point'          => array( SpecKey::DeckWidth, '8', '8 in' ),
			'inches, three places'      => array( SpecKey::RiserThickness, '0.125', '0.125 in' ),
			'millimetres'               => array( SpecKey::WheelDiameter, '52', '52 mm' ),
			'millimetres, with a part'  => array( SpecKey::TruckHeight, '53.50', '53.5 mm' ),
			'a flag set'                => array( SpecKey::SpacersBuiltIn, '1', 'Yes' ),
			'a flag not set'            => array( SpecKey::SpacersBuiltIn, '0', 'No' ),
			'a flag with anything else' => array( SpecKey::SpacersBuiltIn, 'yes', 'No' ),
			'a count'                   => array( SpecKey::SetSize, '4', '4' ),
			'a count in the thousands'  => array( SpecKey::SetSize, '1000', '1,000' ),
			'a count is whole'          => array( SpecKey::SetSize, '4.9', '4' ),
			'a choice'                  => array( SpecKey::HeightClass, 'low', 'Low' ),
			'a choice not known'        => array( SpecKey::HolePattern, 'other', 'other' ),
		);
	}

	#[DataProvider( 'values' )]
	public function test_a_value_is_shown_with_its_unit_and_no_more_places_than_it_has( SpecKey $key, string $stored, string $shown ): void {
		self::assertSame( $shown, Labels::value( $key, $stored ) );
	}

	public function test_a_number_is_written_as_the_shops_locale_writes_it(): void {
		$GLOBALS['cbuild_test_decimal'] = ',';
		self::assertSame( '8,25 in', Labels::value( SpecKey::DeckWidth, '8.25' ) );
		self::assertSame( '53,5 mm', Labels::value( SpecKey::TruckHeight, '53.5' ) );
		self::assertSame( '8,25', Labels::typed( '8.25' ) );
		self::assertSame( '8,5', Labels::typed( '8.500' ) );
		self::assertSame( '8', Labels::typed( '8.000' ) );
	}

	public function test_a_field_shows_what_was_typed_with_the_shops_decimal_point_and_no_trailing_zeros(): void {
		self::assertSame( '8.25', Labels::typed( '8.25' ) );
		self::assertSame( '8.5', Labels::typed( '8.50' ) );
		self::assertSame( '8', Labels::typed( '8.00' ) );
		self::assertSame( '8', Labels::typed( '8' ) );
		self::assertSame( '10.05', Labels::typed( '10.050' ) );
		self::assertSame( '0.125', Labels::typed( '0.125' ) );
		self::assertSame( '', Labels::typed( '' ) );
	}

	// Issue #19: Site Health's Info tab showed the code.
	public function test_the_indexs_state_has_words_and_an_unknown_one_is_shown_as_it_is(): void {
		self::assertSame( 'Up to date', Labels::index( Health::INDEX_CURRENT ) );
		self::assertSame( 'Being rebuilt', Labels::index( Health::INDEX_BUILDING ) );
		self::assertSame( 'Stopped part way', Labels::index( Health::INDEX_STALLED ) );
		self::assertSame( 'Not rebuilt: it stopped after its failures', Labels::index( Health::INDEX_FAILED ) );
		self::assertSame( 'index_new', Labels::index( 'index_new' ) );
	}
}
