<?php
/**
 * What a finding says.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

/**
 * The reason behind a finding, as a code. The domain holds no words: the edge turns a code and the finding's values
 * into a sentence in the shopper's language. The values are public API, carried by the state route and the
 * translations, and are never renamed in place.
 */
enum MessageCode: string {

	// Any rule: a part lacks a spec the rule needs, so it is not offered.
	case SpecMissing = 'spec_missing';

	// R1, deck width and truck size.
	case DeckBelowRange = 'deck_below_range';
	case DeckAboveRange = 'deck_above_range';
	case AxleNarrower   = 'axle_narrower';
	case AxleWider      = 'axle_wider';

	// R2, mounting holes.
	case PatternMismatch = 'pattern_mismatch';
	case PatternAssumed  = 'pattern_assumed';

	// R3, wheels, truck height and risers.
	case WheelNeedsRiser = 'wheel_needs_riser';
	case WheelNoRiser    = 'wheel_no_riser';
	case LimitFromClass  = 'limit_from_class';

	// R4, bolts and risers.
	case BoltsTooShort = 'bolts_too_short';
	case BoltsTooLong  = 'bolts_too_long';

	// R5, bearings.
	case BoreMismatch   = 'bore_mismatch';
	case AxleAssumed    = 'axle_assumed';
	case SpacersBuiltIn = 'spacers_built_in';

	// R6, grip.
	case GripTooNarrow   = 'grip_too_narrow';
	case GripTooShort    = 'grip_too_short';
	case LengthUnchecked = 'length_unchecked';

	/**
	 * The names of the values each code carries, in the order the docs give them. A translation is written against
	 * these names, so they are public API and never renamed in place. A finding may leave one out (a maker's range
	 * with one end has no other), and may not add one.
	 */
	private const VALUES = array(
		'spec_missing'      => array( 'spec' ),
		'deck_below_range'  => array( 'deck_width', 'deck_min', 'deck_max' ),
		'deck_above_range'  => array( 'deck_width', 'deck_min', 'deck_max' ),
		'axle_narrower'     => array( 'deck_width', 'axle_width', 'gap' ),
		'axle_wider'        => array( 'deck_width', 'axle_width', 'gap' ),
		'pattern_mismatch'  => array( 'hole_pattern', 'baseplate' ),
		'pattern_assumed'   => array( 'hole_pattern' ),
		'wheel_needs_riser' => array( 'wheel_diameter', 'limit', 'over', 'riser' ),
		'wheel_no_riser'    => array( 'wheel_diameter', 'limit', 'over' ),
		'limit_from_class'  => array( 'height_class', 'limit' ),
		'bolts_too_short'   => array( 'bolt_length', 'shortest', 'usual', 'riser_thickness' ),
		'bolts_too_long'    => array( 'bolt_length', 'shortest', 'usual', 'riser_thickness' ),
		'bore_mismatch'     => array( 'bearing_bore', 'axle_diameter' ),
		'axle_assumed'      => array( 'axle_diameter' ),
		'grip_too_narrow'   => array( 'grip_width', 'deck_width' ),
		'grip_too_short'    => array( 'grip_length', 'deck_length' ),
	);

	/**
	 * The names of the values a finding with this code may carry; none for a code that carries no figure.
	 *
	 * @since 0.2.0
	 *
	 * @return list<string>
	 */
	public function values(): array {
		return self::VALUES[ $this->value ] ?? array();
	}
}
