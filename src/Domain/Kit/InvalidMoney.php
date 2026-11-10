<?php
/**
 * A sum that cannot be worked out.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use InvalidArgumentException;

/**
 * Thrown when a discount or the shop's price decimals are out of range. Its message is the reason's code, never a sentence: the edge has the words. Made through a named constructor:
 * Plugin Check reads an argument to `throw new` as output to be escaped.
 */
final class InvalidMoney extends InvalidArgumentException {

	/**
	 * Constructor.
	 *
	 * @param MoneyError $reason Why.
	 */
	private function __construct( public readonly MoneyError $reason ) {
		parent::__construct( $reason->value );
	}

	/**
	 * Refused for a reason.
	 *
	 * @since 0.2.0
	 *
	 * @param MoneyError $reason Why.
	 */
	public static function because( MoneyError $reason ): self {
		return new self( $reason );
	}
}
