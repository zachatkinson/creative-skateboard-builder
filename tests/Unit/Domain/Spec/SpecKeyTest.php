<?php
/**
 * SpecKey.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKind;
use PHPUnit\Framework\TestCase;

/**
 * Every spec key has one kind; a measure one unit; a choice its values.
 */
final class SpecKeyTest extends TestCase {

	/**
	 * The spec's table, key by key (spec: Compatibility rules, each rule's "Reads").
	 */
	private const EXPECTED = array(
		'deck_width'       => array( 'measure', 'in' ),
		'deck_length'      => array( 'measure', 'in' ),
		'wheelbase'        => array( 'measure', 'in' ),
		'hole_pattern'     => array( 'choice', null ),
		'axle_width'       => array( 'measure', 'in' ),
		'deck_min'         => array( 'measure', 'in' ),
		'deck_max'         => array( 'measure', 'in' ),
		'truck_height'     => array( 'measure', 'mm' ),
		'height_class'     => array( 'choice', null ),
		'max_wheel'        => array( 'measure', 'mm' ),
		'baseplate'        => array( 'choice', null ),
		'axle_diameter'    => array( 'measure', 'mm' ),
		'wheel_diameter'   => array( 'measure', 'mm' ),
		'bearings_included' => array( 'flag', null ),
		'riser_thickness'  => array( 'measure', 'in' ),
		'bolt_length'      => array( 'measure', 'in' ),
		'bearing_bore'     => array( 'measure', 'mm' ),
		'spacers_built_in' => array( 'flag', null ),
		'grip_width'       => array( 'measure', 'in' ),
		'grip_length'      => array( 'measure', 'in' ),
		'set_size'         => array( 'count', null ),
	);

	public function test_every_key_has_the_kind_and_unit_the_spec_gives_it(): void {
		$seen = array();
		foreach ( SpecKey::cases() as $key ) {
			self::assertArrayHasKey( $key->value, self::EXPECTED, $key->value . ' is not in the spec' );
			[ $kind, $unit ] = self::EXPECTED[ $key->value ];
			self::assertSame( SpecKind::from( $kind ), $key->kind(), $key->value );
			self::assertSame( null === $unit ? null : Unit::from( $unit ), $key->unit(), $key->value );
			$seen[] = $key->value;
		}
		self::assertSame( array_keys( self::EXPECTED ), $seen, 'every key in the spec exists, in its order' );
	}

	public function test_a_key_is_a_measure_exactly_when_it_has_a_unit_and_a_choice_when_it_has_values(): void {
		foreach ( SpecKey::cases() as $key ) {
			self::assertSame( SpecKind::Measure === $key->kind(), null !== $key->unit(), $key->value );
			self::assertSame( SpecKind::Choice === $key->kind(), array() !== $key->choices(), $key->value );
		}
	}

	public function test_a_choice_names_its_values_and_nothing_else_has_any(): void {
		self::assertSame( array( 'new_school', 'old_school', 'both' ), SpecKey::HolePattern->choices() );
		self::assertSame( array( 'new_school', 'old_school', 'both' ), SpecKey::Baseplate->choices() );
		self::assertSame( array( 'low', 'mid', 'high' ), SpecKey::HeightClass->choices() );
		foreach ( SpecKey::cases() as $key ) {
			if ( SpecKind::Choice !== $key->kind() ) {
				self::assertSame( array(), $key->choices(), $key->value );
			}
		}
	}
}
