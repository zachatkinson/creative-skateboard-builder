<?php
/**
 * A value a source holds that cannot be read.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * What a source has for a spec when it is not a value the spec can hold: a width of "wide", a hole pattern nobody
 * offers, a table or size that is not there. The part goes without that spec from that source, and the Catalogue
 * check lists it with the reason's code.
 *
 * @since 0.3.0
 */
final readonly class Unread {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string $key    The spec's key as the source names it.
	 * @param string $text   What the source holds.
	 * @param Origin $origin The source.
	 * @param string $ref    What in the source.
	 * @param string $reason Why, as a code.
	 */
	public function __construct(
		public string $key,
		public string $text,
		public Origin $origin,
		public string $ref,
		public string $reason
	) {}
}
