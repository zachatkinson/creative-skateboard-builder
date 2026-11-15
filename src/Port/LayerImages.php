<?php
/**
 * The images a part's layer may be.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Images in the Media Library, read for the Layer image field's checks (spec: Admin; D-190).
 *
 * @since 0.7.0
 */
interface LayerImages {

	/**
	 * What is known of an attachment's file: its type, its pixels and whether its corners are transparent.
	 *
	 * @since 0.7.0
	 *
	 * @param int $attachment The attachment's ID.
	 * @return ImageFacts|null Null when it is not an image in the Media Library, or its file cannot be read.
	 */
	public function facts( int $attachment ): ?ImageFacts;
}
