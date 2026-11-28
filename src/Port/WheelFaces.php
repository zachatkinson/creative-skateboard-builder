<?php
/**
 * The wheels' outer faces, from their photos.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 3D view draws a wheel's outer face from the wheel's featured photo, where the domain reads it as one wheel seen
 * face-on (Domain\Scene\WheelFace). The photo is read once for each file it is, and the answer kept.
 *
 * @since 1.5.0
 */
interface WheelFaces {

	/**
	 * A wheel part's face: its photo's address, the face's box in ten-thousandths of the photo's sides and the tread's
	 * colour; or null, where the part has no photo, the photo cannot be read, or it is no single wheel face-on.
	 *
	 * @since 1.5.0
	 *
	 * @param int $part The wheel part's product ID.
	 * @return array{url: string, x: int, y: int, size: int, tread: string}|null
	 */
	public function of( int $part ): ?array;
}
