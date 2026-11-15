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

	/**
	 * A part's layer image: its own, or a variation's parent product's, while it is still an image in the Media
	 * Library.
	 *
	 * @since 0.7.0
	 *
	 * @param int $part A product's or variation's ID.
	 * @return int|null The attachment's ID, or null when it has none.
	 */
	public function attachment( int $part ): ?int;

	/**
	 * The parts among these that have a layer image, their own or their parent product's, that is still an image in
	 * the Media Library.
	 *
	 * @since 0.7.0
	 *
	 * @param int ...$parts Product or variation IDs.
	 * @return list<int> In the order asked.
	 */
	public function layered( int ...$parts ): array;
}
