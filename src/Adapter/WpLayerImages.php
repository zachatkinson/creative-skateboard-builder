<?php
/**
 * Layer images from the Media Library.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\ProductMeta;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use Imagick;
use ImagickException;
use WC_Product;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads an attachment's file. The type is WordPress's for the attachment, which it checked on upload; the pixels are
 * the file's own. Whether the background is transparent is read from the four corners, where a cut-out shot nose up
 * has no board: GD where PHP has it, Imagick where it reads the format, and not known with neither. Only the corners are read, so a
 * large image costs one decode and four lookups (D-190).
 *
 * @since 0.7.0
 */
final class WpLayerImages implements LayerImages {

	/**
	 * The libraries that may read the corners: `gd` and `imagick`, in the order tried.
	 */
	public const LIBRARIES = array( 'gd', 'imagick' );

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param list<string>     $libraries The libraries to try, from LIBRARIES; tests name one, or none.
	 * @param ProductMeta|null $meta      Reads several parts' meta in one query; without it each is read alone.
	 */
	public function __construct( private array $libraries = self::LIBRARIES, private ?ProductMeta $meta = null ) {}

	/**
	 * What is known of an attachment's file.
	 *
	 * @since 0.7.0
	 *
	 * @param int $attachment The attachment's ID.
	 */
	public function facts( int $attachment ): ?ImageFacts {
		$post = get_post( $attachment );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $post ) ) {
			return null;
		}
		$file = get_attached_file( $attachment );
		$size = is_string( $file ) && is_readable( $file ) ? wp_getimagesize( $file ) : false;
		if ( ! is_string( $file ) || ! is_array( $size ) ) {
			return null;
		}
		$type = (string) get_post_mime_type( $post );
		return new ImageFacts( $type, (int) $size[0], (int) $size[1], $this->clear( $file, $type ) );
	}

	/**
	 * Whether the four corners are fully transparent; null when no library can tell, or the type has no transparency to read.
	 *
	 * @param string $file The file.
	 * @param string $type Its media type.
	 */
	private function clear( string $file, string $type ): ?bool {
		foreach ( $this->libraries as $library ) {
			if ( 'gd' === $library && function_exists( 'imagecreatefrompng' ) ) {
				return self::gd( $file, $type );
			}
			// The extension can load with no coder for the format, as in wp-env's cli image (D-191).
			if ( 'imagick' === $library && class_exists( Imagick::class ) && array() !== Imagick::queryFormats( strtoupper( substr( $type, 6 ) ) ) ) {
				return self::imagick( $file );
			}
		}
		return null;
	}

	/**
	 * The corners, read by GD. GD's alpha runs from 0, opaque, to 127, transparent.
	 *
	 * @param string $file The file.
	 * @param string $type Its media type.
	 */
	private static function gd( string $file, string $type ): ?bool {
		// A corrupt file makes GD warn as well as fail; the failure is the answer, so the warning is not shown.
		set_error_handler( static fn(): bool => true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Held for one decode, and put back below.
		try {
			$image = match ( $type ) {
				'image/png'  => imagecreatefrompng( $file ),
				'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $file ) : false,
				default      => false,
			};
		} finally {
			restore_error_handler();
		}
		if ( false === $image ) {
			return null;
		}
		$right = imagesx( $image ) - 1;
		$foot  = imagesy( $image ) - 1;
		foreach ( array( array( 0, 0 ), array( $right, 0 ), array( 0, $foot ), array( $right, $foot ) ) as $corner ) {
			$colour = imagecolorat( $image, $corner[0], $corner[1] );
			if ( false === $colour || 127 !== ( imagecolorsforindex( $image, $colour )['alpha'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The corners, read by Imagick. Its alpha runs from 0, transparent, to 1, opaque.
	 *
	 * @param string $file The file.
	 */
	private static function imagick( string $file ): ?bool {
		try {
			$image = new Imagick( $file );
			$right = $image->getImageWidth() - 1;
			$foot  = $image->getImageHeight() - 1;
			foreach ( array( array( 0, 0 ), array( $right, 0 ), array( 0, $foot ), array( $right, $foot ) ) as $corner ) {
				if ( $image->getImagePixelColor( $corner[0], $corner[1] )->getColorValue( Imagick::COLOR_ALPHA ) > 0.0 ) {
					return false;
				}
			}
			return true;
		} catch ( ImagickException ) {
			return null;
		}
	}

	/**
	 * The parts among these that have a layer image, their own or their parent's, still an image in the Media Library.
	 *
	 * @since 0.7.0
	 *
	 * @param int ...$parts Product or variation IDs.
	 * @return list<int>
	 */
	public function layered( int ...$parts ): array {
		$found = $this->attachments( ...$parts );
		return array_values( array_filter( $parts, static fn( int $part ): bool => isset( $found[ $part ] ) ) );
	}

	/**
	 * A part's layer image.
	 *
	 * @since 0.7.0
	 *
	 * @param int $part A product's or variation's ID.
	 */
	public function attachment( int $part ): ?int {
		return $this->attachments( $part )[ $part ] ?? null;
	}

	/**
	 * The layer images of these parts, read together: the parts' meta in one query, their parents' in another where
	 * a variation has none of its own, and the images' posts and meta in two more, however many parts there are
	 * (D-202).
	 *
	 * @since 0.7.0
	 *
	 * @param int ...$parts Product or variation IDs.
	 * @return array<int, int> The attachment's ID by part, for the parts that have one.
	 */
	public function attachments( int ...$parts ): array {
		$this->meta?->prime( ...$parts );
		$images  = array();
		$parents = array();
		foreach ( $parts as $part ) {
			$product = wc_get_product( $part );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$image = Cast::int( $product->get_meta( Options::META_LAYER, true ) );
			if ( $image > 0 ) {
				$images[ $part ] = $image;
			} elseif ( $product->is_type( 'variation' ) ) {
				$parents[ $part ] = $product->get_parent_id();
			}
		}
		$this->meta?->prime( ...array_values( $parents ) );
		foreach ( $parents as $part => $parent ) {
			$product = wc_get_product( $parent );
			$image   = $product instanceof WC_Product ? Cast::int( $product->get_meta( Options::META_LAYER, true ) ) : 0;
			if ( $image > 0 ) {
				$images[ $part ] = $image;
			}
		}
		if ( count( array_unique( $images ) ) > 1 ) {
			_prime_post_caches( array_values( array_unique( $images ) ), false, true );
		}
		return array_filter( $images, static fn( int $image ): bool => wp_attachment_is_image( $image ) );
	}

	/**
	 * An image's own file.
	 *
	 * @since 0.7.0
	 *
	 * @param int $attachment The attachment's ID.
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function image( int $attachment ): ?array {
		$source = wp_attachment_is_image( $attachment ) ? wp_get_attachment_image_src( $attachment, 'full' ) : false;
		if ( ! is_array( $source ) || $source[1] < 1 || $source[2] < 1 ) {
			return null;
		}
		return array(
			'url'    => $source[0],
			'width'  => $source[1],
			'height' => $source[2],
		);
	}
}
