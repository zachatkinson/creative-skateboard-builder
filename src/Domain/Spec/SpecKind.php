<?php
/**
 * The kinds of spec.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * What a spec's value is: a length, one of a few named choices, yes or no, or a whole number.
 */
enum SpecKind: string {

	case Measure = 'measure';
	case Choice  = 'choice';
	case Flag    = 'flag';
	case Count   = 'count';
}
