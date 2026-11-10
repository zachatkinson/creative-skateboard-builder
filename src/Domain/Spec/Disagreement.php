<?php
/**
 * Two sources that say different things of one spec.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * The reading the index kept for a spec, and each reading that says otherwise (the Width attribute says 8.25 in, the
 * field 8.5 in). The Catalogue check lists it (spec: Admin, Catalogue check).
 *
 * @since 0.3.0
 */
final readonly class Disagreement {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Reading       $kept   The reading that won.
	 * @param list<Reading> $others Each reading with another value, in the order given.
	 */
	public function __construct( public Reading $kept, public array $others ) {}
}
