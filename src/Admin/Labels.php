<?php
/**
 * The admin's names for what the domain knows by code.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\HeightClass;
use CreativeBranding\SkateboardBuilder\Domain\Spec\HolePattern;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKind;
use CreativeBranding\SkateboardBuilder\Service\Health;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The domain returns codes; the words are here, each through the text domain (docs/STANDARDS.md: Strings). Every
 * admin screen that names a spec, a unit or a source reads this one list, so a spec has one name everywhere.
 *
 * @since 0.3.0
 */
final class Labels {

	/**
	 * A spec's name.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key The spec.
	 */
	public static function spec( SpecKey $key ): string {
		$labels = array(
			SpecKey::DeckWidth->value      => __( 'Deck width', 'creative-skateboard-builder' ),
			SpecKey::DeckLength->value     => __( 'Deck length', 'creative-skateboard-builder' ),
			SpecKey::Wheelbase->value      => __( 'Wheelbase', 'creative-skateboard-builder' ),
			SpecKey::HolePattern->value    => __( 'Deck hole pattern', 'creative-skateboard-builder' ),
			SpecKey::AxleWidth->value      => __( 'Axle width', 'creative-skateboard-builder' ),
			SpecKey::DeckMin->value        => __( 'Narrowest deck', 'creative-skateboard-builder' ),
			SpecKey::DeckMax->value        => __( 'Widest deck', 'creative-skateboard-builder' ),
			SpecKey::TruckHeight->value    => __( 'Truck height', 'creative-skateboard-builder' ),
			SpecKey::HeightClass->value    => __( 'Truck height class', 'creative-skateboard-builder' ),
			SpecKey::MaxWheel->value       => __( 'Largest wheel without risers', 'creative-skateboard-builder' ),
			SpecKey::Baseplate->value      => __( 'Baseplate hole pattern', 'creative-skateboard-builder' ),
			SpecKey::AxleDiameter->value   => __( 'Axle diameter', 'creative-skateboard-builder' ),
			SpecKey::WheelDiameter->value  => __( 'Wheel diameter', 'creative-skateboard-builder' ),
			SpecKey::RiserThickness->value => __( 'Riser thickness', 'creative-skateboard-builder' ),
			SpecKey::BoltLength->value     => __( 'Bolt length', 'creative-skateboard-builder' ),
			SpecKey::BearingBore->value    => __( 'Bearing bore', 'creative-skateboard-builder' ),
			SpecKey::SpacersBuiltIn->value => __( 'Spacers built in', 'creative-skateboard-builder' ),
			SpecKey::GripWidth->value      => __( 'Grip width', 'creative-skateboard-builder' ),
			SpecKey::GripLength->value     => __( 'Grip length', 'creative-skateboard-builder' ),
			SpecKey::SetSize->value        => __( 'Number in a set', 'creative-skateboard-builder' ),
		);
		return $labels[ $key->value ] ?? $key->value;
	}

	/**
	 * A unit's name, with its sign.
	 *
	 * @since 0.3.0
	 *
	 * @param Unit $unit The unit.
	 */
	public static function unit( Unit $unit ): string {
		return Unit::Inch === $unit
			? __( 'Inches (in)', 'creative-skateboard-builder' )
			: __( 'Millimetres (mm)', 'creative-skateboard-builder' );
	}

	/**
	 * A source's name.
	 *
	 * @since 0.3.0
	 *
	 * @param Origin $origin The source.
	 */
	public static function source( Origin $origin ): string {
		$labels = array(
			Origin::Table->value     => __( 'The maker\'s chart', 'creative-skateboard-builder' ),
			Origin::Field->value     => __( 'The part\'s own field', 'creative-skateboard-builder' ),
			Origin::Attribute->value => __( 'A mapped attribute', 'creative-skateboard-builder' ),
		);
		return $labels[ $origin->value ] ?? $origin->value;
	}

	/**
	 * The specs index's state, for Site Health's Info tab. A code this version does not know is shown as it is.
	 *
	 * @since 0.4.0
	 *
	 * @param string $code One of `Service\Health`'s index codes.
	 */
	public static function index( string $code ): string {
		$labels = array(
			Health::INDEX_CURRENT  => __( 'Up to date', 'creative-skateboard-builder' ),
			Health::INDEX_BUILDING => __( 'Being rebuilt', 'creative-skateboard-builder' ),
			Health::INDEX_STALLED  => __( 'Stopped part way', 'creative-skateboard-builder' ),
			Health::INDEX_FAILED   => __( 'Not rebuilt: it stopped after its failures', 'creative-skateboard-builder' ),
		);
		return $labels[ $code ] ?? $code;
	}

	/**
	 * A spec's name with the unit its field is in, for a field's label: "Deck width (in)". A spec that is not a
	 * measure is its name alone.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key The spec.
	 */
	public static function field( SpecKey $key ): string {
		$unit = $key->unit();
		if ( null === $unit ) {
			return self::spec( $key );
		}
		$short = Unit::Inch === $unit
			? _x( 'in', 'inches, in the label of a field', 'creative-skateboard-builder' )
			: _x( 'mm', 'millimetres, in the label of a field', 'creative-skateboard-builder' );
		/* translators: 1: a spec's name, such as Deck width. 2: its unit, such as in or mm. */
		return sprintf( _x( '%1$s (%2$s)', 'the label of a field, with its unit', 'creative-skateboard-builder' ), self::spec( $key ), $short );
	}

	/**
	 * One of a spec's choices.
	 *
	 * @since 0.3.0
	 *
	 * @param string $choice The choice, as stored.
	 */
	public static function choice( string $choice ): string {
		$labels = array(
			HolePattern::NewSchool->value => __( 'New school', 'creative-skateboard-builder' ),
			HolePattern::OldSchool->value => __( 'Old school', 'creative-skateboard-builder' ),
			HolePattern::Both->value      => __( 'Both', 'creative-skateboard-builder' ),
			HeightClass::Low->value       => __( 'Low', 'creative-skateboard-builder' ),
			HeightClass::Mid->value       => __( 'Mid', 'creative-skateboard-builder' ),
			HeightClass::High->value      => __( 'High', 'creative-skateboard-builder' ),
		);
		return $labels[ $choice ] ?? $choice;
	}

	/**
	 * A spec's value as a person reads it: "8.25 in", "55 mm", "New school", "Yes", "8".
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key  The spec.
	 * @param string  $text Its value, as the index writes it.
	 */
	public static function value( SpecKey $key, string $text ): string {
		$unit = $key->unit();
		if ( null !== $unit ) {
			$number = number_format_i18n( (float) $text, strlen( self::decimals( $text ) ) );
			if ( Unit::Inch === $unit ) {
				/* translators: %s: a number of inches. */
				return sprintf( _x( '%s in', 'inches', 'creative-skateboard-builder' ), $number );
			}
			/* translators: %s: a number of millimetres. */
			return sprintf( _x( '%s mm', 'millimetres', 'creative-skateboard-builder' ), $number );
		}
		if ( SpecKind::Flag === $key->kind() ) {
			return '1' === $text ? __( 'Yes', 'creative-skateboard-builder' ) : __( 'No', 'creative-skateboard-builder' );
		}
		return SpecKind::Count === $key->kind() ? number_format_i18n( (int) $text ) : self::choice( $text );
	}

	/**
	 * A measure as its field shows it: no trailing zeros, and the shop's decimal separator.
	 *
	 * @since 0.3.0
	 *
	 * @param string $text The measure, as the index writes it.
	 */
	public static function typed( string $text ): string {
		$decimals = self::decimals( $text );
		$whole    = explode( '.', $text )[0];
		return '' === $decimals ? $whole : $whole . wc_get_price_decimal_separator() . $decimals;
	}

	/**
	 * The digits after a decimal's point, without trailing zeros.
	 *
	 * @param string $text A decimal with a point.
	 */
	private static function decimals( string $text ): string {
		return rtrim( explode( '.', $text )[1] ?? '', '0' );
	}
}
