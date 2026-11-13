<?php
/**
 * Posts, as far as finding a builder on them goes.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A post's blocks, and whether a product is a kit: what Front\BuilderPlace needs to say where a builder is.
 *
 * @since 0.6.0
 */
interface BuilderPosts {

	/**
	 * A post's blocks, parsed: none where there is no such post.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post The post's ID.
	 * @return array<mixed>
	 */
	public function blocks( int $post ): array;

	/**
	 * Whether a post is a kit's product.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post The post's ID.
	 */
	public function isKit( int $post ): bool;
}
