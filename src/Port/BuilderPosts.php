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

	/**
	 * Whether a post is a page a visitor may see: published, public and with no password (1.2.0).
	 *
	 * @since 1.2.0
	 *
	 * @param int $post The post's ID.
	 */
	public function isPage( int $post ): bool;

	/**
	 * The site's published pages a visitor may see, by title (1.2.0): the pages a kit's Build tab may choose for its
	 * builder. At most a few hundred.
	 *
	 * @since 1.2.0
	 *
	 * @return list<int>
	 */
	public function pages(): array;
}
