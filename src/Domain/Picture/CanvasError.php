<?php
/**
 * Why a canvas was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

/**
 * The reasons, as codes.
 *
 * @since 0.7.0
 */
enum CanvasError: string {

	case NoPixels      = 'no_pixels';
	case NotJpegOrWebp = 'not_jpeg_or_webp';
}
