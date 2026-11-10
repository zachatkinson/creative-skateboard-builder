<?php
/**
 * The specs the rules read.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;

use function array_column;

/**
 * One measured fact about a part (spec: Vocabulary, Spec; Compatibility rules). The value is the key the specs index,
 * the part's meta and the imports use, so it is public API and never renamed in place. Each key has one kind, a
 * measure has one unit, and a choice has a fixed set of values.
 */
enum SpecKey: string {

	case DeckWidth      = 'deck_width';
	case DeckLength     = 'deck_length';
	case Wheelbase      = 'wheelbase';
	case HolePattern    = 'hole_pattern';
	case AxleWidth      = 'axle_width';
	case DeckMin        = 'deck_min';
	case DeckMax        = 'deck_max';
	case TruckHeight    = 'truck_height';
	case HeightClass    = 'height_class';
	case MaxWheel       = 'max_wheel';
	case Baseplate      = 'baseplate';
	case AxleDiameter   = 'axle_diameter';
	case WheelDiameter  = 'wheel_diameter';
	case RiserThickness = 'riser_thickness';
	case BoltLength     = 'bolt_length';
	case BearingBore    = 'bearing_bore';
	case SpacersBuiltIn = 'spacers_built_in';
	case GripWidth      = 'grip_width';
	case GripLength     = 'grip_length';
	case SetSize        = 'set_size';

	/**
	 * Each measure's unit. A key that is not here is not a measure.
	 */
	private const UNITS = array(
		'deck_width'      => Unit::Inch,
		'deck_length'     => Unit::Inch,
		'wheelbase'       => Unit::Inch,
		'axle_width'      => Unit::Inch,
		'deck_min'        => Unit::Inch,
		'deck_max'        => Unit::Inch,
		'truck_height'    => Unit::Millimetre,
		'max_wheel'       => Unit::Millimetre,
		'axle_diameter'   => Unit::Millimetre,
		'wheel_diameter'  => Unit::Millimetre,
		'riser_thickness' => Unit::Inch,
		'bolt_length'     => Unit::Inch,
		'bearing_bore'    => Unit::Millimetre,
		'grip_width'      => Unit::Inch,
		'grip_length'     => Unit::Inch,
	);

	/**
	 * Each choice's enum, whose values are the choice's. A key that is not here is not a choice.
	 *
	 * @var array<string, class-string<HolePattern>|class-string<HeightClass>>
	 */
	private const CHOICES = array(
		'hole_pattern' => HolePattern::class,
		'baseplate'    => HolePattern::class,
		'height_class' => HeightClass::class,
	);

	/**
	 * Each key's kind. A table, as the units and the choices are (D-052).
	 */
	private const KINDS = array(
		'deck_width'       => SpecKind::Measure,
		'deck_length'      => SpecKind::Measure,
		'wheelbase'        => SpecKind::Measure,
		'hole_pattern'     => SpecKind::Choice,
		'axle_width'       => SpecKind::Measure,
		'deck_min'         => SpecKind::Measure,
		'deck_max'         => SpecKind::Measure,
		'truck_height'     => SpecKind::Measure,
		'height_class'     => SpecKind::Choice,
		'max_wheel'        => SpecKind::Measure,
		'baseplate'        => SpecKind::Choice,
		'axle_diameter'    => SpecKind::Measure,
		'wheel_diameter'   => SpecKind::Measure,
		'riser_thickness'  => SpecKind::Measure,
		'bolt_length'      => SpecKind::Measure,
		'bearing_bore'     => SpecKind::Measure,
		'spacers_built_in' => SpecKind::Flag,
		'grip_width'       => SpecKind::Measure,
		'grip_length'      => SpecKind::Measure,
		'set_size'         => SpecKind::Count,
	);

	/**
	 * What kind of value the spec holds.
	 *
	 * @since 0.2.0
	 */
	public function kind(): SpecKind {
		return self::KINDS[ $this->value ];
	}

	/**
	 * A measure's unit, and null for a spec that is not a measure.
	 *
	 * @since 0.2.0
	 */
	public function unit(): ?Unit {
		return self::UNITS[ $this->value ] ?? null;
	}

	/**
	 * A choice's values, and none for a spec that is not a choice.
	 *
	 * @since 0.2.0
	 *
	 * @return list<string>
	 */
	public function choices(): array {
		$enum = self::CHOICES[ $this->value ] ?? null;
		return null === $enum ? array() : array_column( $enum::cases(), 'value' );
	}
}
