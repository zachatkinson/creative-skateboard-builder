<?php
/**
 * What a composed picture is drawn on.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

/**
 * A composed picture's pixels and its type: a JPEG for email, a WebP everywhere else (spec: The picture; D-196). Its
 * ground is white, which every place it goes shows well.
 *
 * @since 0.7.0
 */
final readonly class Canvas {

	/**
	 * The types, with their files' extensions.
	 */
	public const array TYPES = array(
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
	);

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param int    $width  Pixels across.
	 * @param int    $height Pixels down.
	 * @param string $type   `image/jpeg` or `image/webp`.
	 *
	 * @throws InvalidCanvas When it has no pixels or another type.
	 */
	public function __construct( public int $width, public int $height, public string $type ) {
		$refused = null;
		if ( ! isset( self::TYPES[ $type ] ) ) {
			$refused = InvalidCanvas::because( CanvasError::NotJpegOrWebp );
		} elseif ( $width < 1 || $height < 1 ) {
			$refused = InvalidCanvas::because( CanvasError::NoPixels );
		}
		if ( null !== $refused ) {
			throw $refused;
		}
	}

	/**
	 * Its file's extension.
	 *
	 * @since 0.7.0
	 */
	public function extension(): string {
		return self::TYPES[ $this->type ];
	}
}
