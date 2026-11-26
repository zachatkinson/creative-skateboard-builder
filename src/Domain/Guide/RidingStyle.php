<?php
/**
 * Where a shopper skates.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Guide;

/**
 * The guided start's question "Where do you skate?" (#134), in the order it asks: each an answer the kit's wheel guide
 * gives a wheel size range for. The value is the code the build's link and the settings carry; the words are the edge's.
 *
 * @since 1.3.0
 */
enum RidingStyle: string {

	case Street   = 'street';
	case Park     = 'park';
	case Bowl     = 'bowl';
	case Cruising = 'cruising';
}
