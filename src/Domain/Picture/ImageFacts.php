<?php
/**
 * What is known of an image file.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

/**
 * An image's type, its size in pixels, and whether its background is transparent, as the adapter read them.
 */
final readonly class ImageFacts {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param string    $type     The file's media type, such as `image/png`.
	 * @param int       $width    Its pixels across.
	 * @param int       $height   Its pixels along.
	 * @param bool|null $clear    Whether its four corners are fully transparent; null where no image library could tell.
	 * @param bool      $readable Whether one of the host's image libraries reads its type.
	 */
	public function __construct( public string $type, public int $width, public int $height, public ?bool $clear, public bool $readable = true ) {}
}
