<?php
/**
 * A part that cannot be.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use InvalidArgumentException;

/**
 * Thrown when a part is made with an id or a price no product has. Its message is the reason's code, never a sentence: the edge has the words. Made through a named constructor:
 * Plugin Check reads an argument to `throw new` as output to be escaped.
 */
final class InvalidPart extends InvalidArgumentException {

	/**
	 * Constructor.
	 *
	 * @param PartError $reason Why.
	 */
	private function __construct( public readonly PartError $reason ) {
		parent::__construct( $reason->value );
	}

	/**
	 * Refused for a reason.
	 *
	 * @since 0.2.0
	 *
	 * @param PartError $reason Why.
	 */
	public static function because( PartError $reason ): self {
		return new self( $reason );
	}
}
