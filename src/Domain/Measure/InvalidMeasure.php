<?php
/**
 * A measure that could not be read.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Measure;

use InvalidArgumentException;

/**
 * Thrown by the parser, and for a length below nothing. Its message is the reason's code, never a sentence: the edge has the words. Made through a
 * named constructor for each reason: Plugin Check reads an argument to `throw new` as output to be escaped.
 */
final class InvalidMeasure extends InvalidArgumentException {

	/**
	 * Constructor.
	 *
	 * @param MeasureError $reason Why the text could not be read.
	 */
	private function __construct( public readonly MeasureError $reason ) {
		parent::__construct( $reason->value );
	}

	/**
	 * Not a measure at all.
	 *
	 * @since 0.2.0
	 */
	public static function malformed(): self {
		return new self( MeasureError::Malformed );
	}

	/**
	 * A fraction over zero.
	 *
	 * @since 0.2.0
	 */
	public static function zeroDenominator(): self {
		return new self( MeasureError::ZeroDenominator );
	}

	/**
	 * A length below nothing.
	 *
	 * @since 0.2.0
	 */
	public static function negative(): self {
		return new self( MeasureError::Negative );
	}
}
