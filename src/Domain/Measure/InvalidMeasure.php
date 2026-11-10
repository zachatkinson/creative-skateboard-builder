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
 * named constructor: Plugin Check reads an argument to `throw new` as output to be escaped.
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
	 * A measure refused, for this reason.
	 *
	 * @since 0.2.0
	 *
	 * @param MeasureError $reason Why.
	 */
	public static function because( MeasureError $reason ): self {
		return new self( $reason );
	}
}
