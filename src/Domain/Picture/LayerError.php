<?php
/**
 * Why a layer image was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

/**
 * The codes the edge turns into words.
 */
enum LayerError: string {

	case NotPngOrWebp = 'layer_not_png_or_webp';
	case Opaque       = 'layer_opaque';
	case TooSmall     = 'layer_too_small';
}
