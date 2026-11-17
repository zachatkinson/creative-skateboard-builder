<?php
/**
 * A part's layer image, as a renderer draws it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

/**
 * The image in the Media Library, its pixels, and the renditions of it that are made (D-194, D-195).
 *
 * @since 0.7.0
 */
final readonly class LayerArt {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param int                                                              $attachment The attachment's ID.
	 * @param string                                                           $url        The image's own file, as the Media Library serves it.
	 * @param int                                                              $width      Its pixels across.
	 * @param int                                                              $height     Its pixels along.
	 * @param array<string, list<array{url: string, width: int, height: int}>> $sources    The renditions made, by MIME type, smallest first.
	 */
	public function __construct( public int $attachment, public string $url, public int $width, public int $height, public array $sources ) {}
}
