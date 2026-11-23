<?php
/**
 * Where one layer image goes on a canvas.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

/**
 * An image and the rectangle it is drawn into, in the canvas's pixels from its top left, sharp or, from 1.1.0, blurred
 * so its artwork can't be made out and its shape stays (D-309).
 *
 * @since 0.7.0
 */
final readonly class Placement {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param int    $attachment The image's attachment ID.
	 * @param string $url        Its own file's address, which names that file.
	 * @param int    $x          The left edge.
	 * @param int    $y          The top edge.
	 * @param int    $width      Pixels across.
	 * @param int    $height     Pixels down.
	 * @param bool   $blurred    Whether it is drawn blurred, since 1.1.0.
	 */
	public function __construct( public int $attachment, public string $url, public int $x, public int $y, public int $width, public int $height, public bool $blurred = false ) {}
}
