<?php
/**
 * What draws the picture's layout.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The picture is laid out once, by the domain, and drawn by a renderer (spec: The picture; D-060, D-195): the page's
 * gives the template's layers, and the composer's gives one file. A 3D view after 1.0 would be a third. It is the
 * plugin's own, with no filter to swap it, until a module needs one.
 *
 * @since 0.7.0
 * @internal
 *
 * @template T
 */
interface PictureRenderer {

	/**
	 * Draw a layout with the art of the parts that have a layer image. A part with none is not drawn.
	 *
	 * @since 0.7.0
	 *
	 * @param Layout               $layout The layout.
	 * @param array<int, LayerArt> $art    By part ID.
	 * @return T
	 */
	public function render( Layout $layout, array $art ): mixed;
}
