<?php
/**
 * The units a spec is measured in.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Measure;

/**
 * Inches for decks, axles, risers, bolts and grip; millimetres for wheels, truck heights and bearings (spec:
 * Vocabulary, Spec). The value is the unit's code, as the index and the documents store it.
 */
enum Unit: string {

	case Inch       = 'in';
	case Millimetre = 'mm';

	/**
	 * Base steps per ten-thousandth, by unit. A table, not a `match`: a `match` with no default compiles a branch for
	 * the case that cannot happen, and the domain's branch floor counts it (D-052). Untyped: PHPCompatibility reads a typed
	 * constant in an enum as a constant named `array`.
	 */
	private const BASE = array(
		'in' => 254,
		'mm' => 10,
	);

	/**
	 * How many base steps one ten-thousandth of this unit is. The base is a hundred-thousandth of a millimetre, the
	 * largest step both units divide into evenly: an inch is 25.4 mm exactly.
	 *
	 * @since 0.2.0
	 */
	public function base(): int {
		return self::BASE[ $this->value ];
	}
}
