<?php
/**
 * A finding a rule may not make.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use LogicException;

/**
 * Thrown when a rule gives a finding a value its code does not name. That is a fault in the rule, never in what a shop
 * or a shopper entered: the names are public, and a translation is written against them (docs/HOOKS.md).
 */
final class InvalidFinding extends LogicException {

	/**
	 * Constructor.
	 */
	private function __construct() {
		parent::__construct( 'finding_value_not_named' );
	}

	/**
	 * A value the code does not name.
	 *
	 * @since 0.2.0
	 */
	public static function valueNotNamed(): self {
		return new self();
	}
}
