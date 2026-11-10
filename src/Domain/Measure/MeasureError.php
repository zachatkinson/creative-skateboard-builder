<?php
/**
 * Why a measure could not be read.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Measure;

/**
 * The codes the edge turns into words for the person who typed the value.
 */
enum MeasureError: string {

	case Malformed       = 'measure_malformed';
	case ZeroDenominator = 'measure_zero_denominator';
	case Negative        = 'measure_negative';
}
