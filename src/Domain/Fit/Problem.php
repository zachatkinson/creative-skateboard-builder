<?php
/**
 * Why a build cannot go to the cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

/**
 * What the validator can find wrong with a build, as a code. The edge turns it into words. The values are public
 * API, carried by the cart's notices and the state route, and are never renamed in place.
 */
enum Problem: string {

	// The build holds a pick for a slot the kit does not have.
	case SlotUnknown = 'slot_unknown';

	// A slot the kit requires is empty.
	case SlotEmpty = 'slot_empty';

	// The part is not one of the slot's parts.
	case PartNotInSlot = 'part_not_in_slot';

	// The part cannot be bought now.
	case PartSoldOut = 'part_sold_out';

	// The part does not fit the rest of the build.
	case PartBlocked = 'part_blocked';
}
