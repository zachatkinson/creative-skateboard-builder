<?php
/**
 * A canvas refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

use InvalidArgumentException;

/**
 * Thrown when a canvas has no pixels or a type the composer does not write. Its message is the reason's code. Made
 * through a named constructor: Plugin Check reads an argument to `throw new` as output to be escaped.
 *
 * @since 0.7.0
 */
final class InvalidCanvas extends InvalidArgumentException {

	/**
	 * Constructor.
	 *
	 * @param CanvasError $reason Why.
	 */
	private function __construct( public readonly CanvasError $reason ) {
		parent::__construct( $reason->value );
	}

	/**
	 * Refused for a reason.
	 *
	 * @since 0.7.0
	 *
	 * @param CanvasError $reason Why.
	 */
	public static function because( CanvasError $reason ): self {
		return new self( $reason );
	}
}
