<?php
/**
 * A ready build's tier.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Guide;

/**
 * What a guided start's ready build is (#134, D-401): the cheapest part that fits in each slot, the middle of each
 * slot's prices, or the dearest. Two alike are one, named for what it is, and a shop that makes only one build makes
 * the only one. The value is a code; the words are the edge's.
 *
 * @since 1.3.0
 */
enum Tier: string {

	case Budget  = 'budget';
	case Mid     = 'mid';
	case Premium = 'premium';
	case Only    = 'only';
}
