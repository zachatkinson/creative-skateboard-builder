<?php
/**
 * Where a rule's figure came from.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

/**
 * The figure a finding rests on is the maker's (a spec on the part), the kit's (a setting the shop can change) or the
 * trade's usual one (a published table, or a default taken where a part gives none). The page says which, so a shop
 * can tell a maker's limit from its own.
 */
enum Source: string {

	case Maker    = 'maker';
	case Kit      = 'kit';
	case Standard = 'standard';
}
