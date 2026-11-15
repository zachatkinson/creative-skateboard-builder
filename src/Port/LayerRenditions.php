<?php
/**
 * The layer images' renditions.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page shows a layer image as AVIF and WebP files at the sizes the picture draws it, made from the image in the
 * Media Library (spec: The picture; D-194). They are the plugin's own files, beside its composed pictures, and named
 * by a hash of the image's file and the size: WordPress's handling of the shop's other uploads is not changed.
 *
 * @since 0.7.0
 */
interface LayerRenditions {

	/**
	 * Make the renditions of an image at these sizes that are not made yet, in each type the host can write. A type
	 * the host cannot write is passed over, and so is an image whose file cannot be read.
	 *
	 * @since 0.7.0
	 *
	 * @param int                   $attachment The image's attachment ID.
	 * @param list<array{int, int}> $sizes      Pixels across and along.
	 */
	public function make( int $attachment, array $sizes ): void;

	/**
	 * The renditions of an image at these sizes that are made, by type, smallest first. Nothing is made here.
	 *
	 * @since 0.7.0
	 *
	 * @param int                   $attachment The image's attachment ID.
	 * @param list<array{int, int}> $sizes      Pixels across and along.
	 * @return array<string, list<array{url: string, width: int, height: int}>> By MIME type, in `Renditions::TYPES`'s order.
	 */
	public function sources( int $attachment, array $sizes ): array;
}
