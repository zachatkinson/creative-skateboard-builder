<?php
/**
 * The picture's layout placed on a canvas, for the composer.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Canvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Port\PictureRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The second of the picture's renderers (D-195, D-196): it draws the same layout as the page, as one image on a canvas.
 * What it gives is the drawing; `PictureFiles` makes the file from it, with whichever library the host has.
 *
 * @since 0.7.0
 * @internal
 *
 * @implements PictureRenderer<Composition>
 */
final readonly class ComposedRenderer implements PictureRenderer {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param Canvas $canvas What it is drawn on.
	 */
	public function __construct( private Canvas $canvas ) {}

	/**
	 * Draw the layout on the canvas.
	 *
	 * @since 0.7.0
	 *
	 * @param Layout                                                                  $layout The layout.
	 * @param array<int, \CreativeBranding\SkateboardBuilder\Domain\Picture\LayerArt> $art    By part ID.
	 */
	public function render( Layout $layout, array $art ): Composition {
		return Composition::of( $this->canvas, $layout, $art );
	}
}
