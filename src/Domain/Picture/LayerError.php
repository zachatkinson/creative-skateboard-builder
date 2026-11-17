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

	case WrongType  = 'layer_wrong_type';
	case Unreadable = 'layer_unreadable';
	case Opaque     = 'layer_opaque';
	case TooSmall   = 'layer_too_small';
}
