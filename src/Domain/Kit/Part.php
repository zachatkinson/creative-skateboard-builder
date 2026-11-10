<?php
/**
 * A product or variation that can fill a slot.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use InvalidArgumentException;

/**
 * A part as the engine sees it (spec: Vocabulary, Part): its id, its brand, its specs, and the price and stock the
 * server read from WooCommerce for this request. The domain never looks either up.
 */
final readonly class Part {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param int    $id      The product's or variation's id.
	 * @param string $brand   The brand's key, or empty when the part has none.
	 * @param Specs  $specs   The part's specs.
	 * @param int    $price   The price of one, in the currency's minor units.
	 * @param bool   $inStock Whether it can be bought now.
	 *
	 * @throws InvalidArgumentException When the id is not positive or the price is negative.
	 */
	public function __construct( public int $id, public string $brand, public Specs $specs, public int $price, public bool $inStock ) {
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'part_id_not_positive' );
		}
		if ( $price < 0 ) {
			throw new InvalidArgumentException( 'part_price_negative' );
		}
	}
}
