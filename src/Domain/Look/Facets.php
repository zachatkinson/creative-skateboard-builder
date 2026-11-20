<?php
/**
 * A look as the chips see it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

/**
 * A look's key and the values it comes in on each facet a chip can filter by: its brand, its sizes, and the global
 * attributes of its variations (D-247).
 */
final readonly class Facets {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string                      $key    The look's key.
	 * @param array<string, list<string>> $facets The values it comes in, by facet.
	 */
	public function __construct( public string $key, public array $facets ) {}
}
