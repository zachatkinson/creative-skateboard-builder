<?php
/**
 * The fit rules.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

/**
 * The six fit tests between two slots (spec: Compatibility rules). R7, the quantities, is checked when a kit is saved,
 * and R8 is a note, so neither is a fit rule. The value is the rule's key in a kit's rules document.
 */
enum RuleId: string {

	case R1 = 'r1';
	case R2 = 'r2';
	case R3 = 'r3';
	case R4 = 'r4';
	case R5 = 'r5';
	case R6 = 'r6';
}
