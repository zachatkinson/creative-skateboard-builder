<?php
/**
 * The fit rules.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

/**
 * The numbered rules that can find something (spec: Compatibility rules): the six fit tests between slots, R1 to R6,
 * and R7, the quantities, which the engine answers itself because it needs the slot's units. R8 is a note and finds
 * nothing. The value is the rule's key in a kit's rules document, for the six a kit can switch.
 */
enum RuleId: string {

	case R1 = 'r1';
	case R2 = 'r2';
	case R3 = 'r3';
	case R4 = 'r4';
	case R5 = 'r5';
	case R6 = 'r6';
	case R7 = 'r7';

	/**
	 * The fit rules a kit's rules document switches on or off: R1 to R6.
	 *
	 * @since 0.2.0
	 *
	 * @return list<RuleId>
	 */
	public static function fit(): array {
		return array( self::R1, self::R2, self::R3, self::R4, self::R5, self::R6 );
	}
}
