<?php
/**
 * The parts' own layer images, and the Media Library's images by file name, for the specs CSV.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The `layer_image` column of the specs CSV reads and writes a part's own Layer image, as the Part specs panel's field
 * does, and finds an image by its file name (Cowork's 1.0.0 request; D-287).
 *
 * @since 1.0.0
 */
interface PartLayers {

	/**
	 * Each part's own layer image, not its parent's, and whether it is a variation.
	 *
	 * @since 1.0.0
	 *
	 * @param int ...$parts Product or variation IDs.
	 * @return array<int, array{own: int, variation: bool}> By part, for the parts that are products; `own` is 0 for none.
	 */
	public function owned( int ...$parts ): array;

	/**
	 * The images in the Media Library whose file has this name: the file uploaded, or the copy WordPress scaled
	 * from it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file A file's name, without a folder.
	 * @return list<int> Attachment IDs, lowest first.
	 */
	public function named( string $file ): array;

	/**
	 * An image's file name as it was uploaded, or an empty string when it is no image in the Media Library.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment The attachment's ID.
	 */
	public function fileName( int $attachment ): string;

	/**
	 * Set a part's own layer image, or remove it with 0, through the product's own save, so its index job, and with
	 * it the image's renditions, is queued as for any product saved.
	 *
	 * @since 1.0.0
	 *
	 * @param int $part       A product's or variation's ID.
	 * @param int $attachment The image's attachment ID, or 0.
	 */
	public function assign( int $part, int $attachment ): void;
}
