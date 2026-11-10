<?php
/**
 * One layer of the picture.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

/**
 * Where one part's image sits and how large it is drawn (spec: The builder, The picture). Every number is a whole
 * ten-thousandth of a millimetre. The centre is measured from the deck's centre: x grows to the right and y towards
 * the nose, which the picture draws at the top.
 */
final readonly class Layer {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param string $slot   The slot's key.
	 * @param int    $part   The part's id.
	 * @param int    $x      The centre, across the board.
	 * @param int    $y      The centre, along the board.
	 * @param int    $width  The size across the board.
	 * @param int    $height The size along the board.
	 */
	public function __construct( public string $slot, public int $part, public int $x, public int $y, public int $width, public int $height ) {}
}
