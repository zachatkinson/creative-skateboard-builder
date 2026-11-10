<?php
/**
 * A spec value that was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use InvalidArgumentException;

/**
 * Thrown when a part's specs are built from something that is not one: an unknown key, a value of the wrong kind, a
 * choice that is not offered, a count below one. Its message is the reason's code; the key is beside it.
 */
final class InvalidSpec extends InvalidArgumentException {

	/**
	 * Constructor.
	 *
	 * @param SpecError $reason Why the value was refused.
	 * @param string    $key    The spec's key as it was given.
	 */
	private function __construct( public readonly SpecError $reason, public readonly string $key ) {
		parent::__construct( $reason->value );
	}

	/**
	 * For a reason and a key.
	 *
	 * @since 0.2.0
	 *
	 * @param SpecError $reason Why the value was refused.
	 * @param string    $key    The spec's key as it was given.
	 */
	public static function because( SpecError $reason, string $key ): self {
		return new self( $reason, $key );
	}
}
