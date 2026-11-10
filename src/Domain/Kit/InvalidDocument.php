<?php
/**
 * A kit document that was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use InvalidArgumentException;

/**
 * Thrown when a kit's slots, rules, presets or assembly document breaks one of the domain's own invariants. The edge
 * validates the document's shape against its JSON Schema first; this is what the schema cannot say, and a second
 * guard for what it can. The message is the reason's code, and the path names the field (`slots[2].units`).
 */
final class InvalidDocument extends InvalidArgumentException {

	/**
	 * Constructor.
	 *
	 * @param DocumentError $reason Why the document was refused.
	 * @param string        $path   The field, as a path from the document's root.
	 */
	private function __construct( public readonly DocumentError $reason, public readonly string $path ) {
		parent::__construct( $reason->value );
	}

	/**
	 * For a reason and a field.
	 *
	 * @since 0.2.0
	 *
	 * @param DocumentError $reason Why the document was refused.
	 * @param string        $path   The field, as a path from the document's root.
	 */
	public static function because( DocumentError $reason, string $path ): self {
		return new self( $reason, $path );
	}
}
