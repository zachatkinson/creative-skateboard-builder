<?php
/**
 * One row of the specs index.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * One spec of one part as the index holds it: the value as text (`Specs::toIndex()` writes it and
 * `Specs::fromIndex()` reads it), the source it came from, and what in that source: the attribute's name, or the
 * spec table and size. A part's own field needs no more said, so its reference is empty.
 *
 * @since 0.3.0
 */
final readonly class IndexedSpec {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key    The spec.
	 * @param string  $text   Its value, as the index stores it.
	 * @param Origin  $origin The source it came from.
	 * @param string  $ref    What in that source, '' when there is nothing to name.
	 */
	public function __construct(
		public SpecKey $key,
		public string $text,
		public Origin $origin,
		public string $ref = ''
	) {}
}
