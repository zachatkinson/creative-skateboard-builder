<?php
/**
 * Why a part was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * The codes the edge reads when it cannot make a part of what WooCommerce gave it.
 */
enum PartError: string {

	case IdNotPositive = 'part_id_not_positive';
	case PriceNegative = 'part_price_negative';
}
