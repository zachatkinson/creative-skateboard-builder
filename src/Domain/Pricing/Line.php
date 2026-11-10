<?php
/**
 * One part's line in a build's price.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Pricing;

/**
 * A part's line: how many, its own price for one, and what one is charged at once the build discount is off. The cart
 * shows the first struck through beside the second (spec: Pricing). All money is whole minor units.
 */
final readonly class Line {

	/**
	 * What the line comes to: the charged price times the quantity.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public int $total;

	/**
	 * What the build discount takes off the line.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public int $saved;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param string $slot     The slot's key.
	 * @param int    $part     The part's id.
	 * @param int    $quantity How many of the part the build takes.
	 * @param int    $price    The part's own price for one.
	 * @param int    $charged  What one is charged at.
	 */
	public function __construct( public string $slot, public int $part, public int $quantity, public int $price, public int $charged ) {
		$this->total = $charged * $quantity;
		$this->saved = ( $price - $charged ) * $quantity;
	}
}
