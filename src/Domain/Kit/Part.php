<?php
/**
 * A product or variation that can fill a slot.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;

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
	 * @param int      $id      The product's or variation's id.
	 * @param string   $brand   The brand's key, or empty when the part has none.
	 * @param Specs    $specs   The part's specs.
	 * @param int      $price   The price of one, in the currency's minor units.
	 * @param bool     $inStock Whether it can be bought now.
	 * @param int|null $stock   How many the shop holds, when it counts them; null when it does not (issue #14).
	 *
	 * @throws InvalidPart When the id is not positive or the price is negative.
	 */
	public function __construct( public int $id, public string $brand, public Specs $specs, public int $price, public bool $inStock, public ?int $stock = null ) {
		if ( $id < 1 ) {
			$refused = InvalidPart::because( PartError::IdNotPositive );
			throw $refused;
		}
		if ( $price < 0 ) {
			$refused = InvalidPart::because( PartError::PriceNegative );
			throw $refused;
		}
	}

	/**
	 * Whether a build can take this many of it now: it is in stock, and the shop holds as many where it counts them
	 * (issue #14).
	 *
	 * @since 0.4.0
	 *
	 * @param int $quantity How many the build takes.
	 */
	public function covers( int $quantity ): bool {
		return $this->inStock && ( null === $this->stock || $this->stock >= $quantity );
	}
}
