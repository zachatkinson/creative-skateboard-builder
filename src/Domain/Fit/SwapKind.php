<?php
/**
 * What a swap is for.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

/**
 * Which swap a build is offered (#135): one slot's part for less, one slot's better part, or the same board for less,
 * every slot but the deck at once. The value is what the state route carries.
 */
enum SwapKind: string {

	// One slot's part, the cheapest in stock that fits the whole build.
	case Less = 'less';

	// One slot's part, the next up by the shop's rank, or by price where the slot has no ranks.
	case Better = 'better';

	// Every slot but the deck, each the cheapest in stock that fits, the board checked as a whole.
	case Board = 'board';
}
