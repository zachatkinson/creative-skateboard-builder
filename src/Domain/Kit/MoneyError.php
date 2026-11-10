<?php
/**
 * Why a sum was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * The codes the edge reads when a discount or the shop's price decimals are outside what the domain can add up.
 */
enum MoneyError: string {

	case DiscountOutOfRange = 'discount_out_of_range';
	case TooManyDecimals    = 'price_decimals_over_eight';
}
