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
}
