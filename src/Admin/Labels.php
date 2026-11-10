<?php
/**
 * The admin's names for what the domain knows by code.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

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
}
