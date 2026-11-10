<?php
/**
 * One of a shop's sources of part specs.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What one source says of some parts: the part's own fields, the attributes the shop mapped, or the spec table and
 * size a part names (spec: Data model, Part specs). A source reads; which one wins is the domain's
 * (`Domain\Spec\Resolution`).
 *
 * @since 0.3.0
 */
interface SpecSource {

	/**
	 * What the source holds for each of these parts: a reading per value it can read, and what it holds and cannot.
	 * A part the source has nothing for, or that is no longer a product, has an empty list.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds Products' and variations' IDs.
	 * @return array<int, list<Reading|Unread>> By part, every part asked for.
	 */
	public function read( int ...$partIds ): array;
}
