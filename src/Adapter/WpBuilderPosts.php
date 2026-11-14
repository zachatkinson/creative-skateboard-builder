<?php
/**
 * Posts, from WordPress and WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\BuilderPosts;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A post's content through parse_blocks(), and a product's type as WooCommerce keeps it.
 *
 * @since 0.6.0
 */
final class WpBuilderPosts implements BuilderPosts {

	/**
	 * A post's blocks, parsed: none where there is no such post.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post The post's ID.
	 * @return array<mixed>
	 */
	public function blocks( int $post ): array {
		$found = $post > 0 ? get_post( $post ) : null;
		return null === $found ? array() : parse_blocks( $found->post_content );
	}

	/**
	 * Whether a post is a kit's product.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post The post's ID.
	 */
	public function isKit( int $post ): bool {
		return $post > 0 && Options::PRODUCT_TYPE_KIT === WC_Product_Factory::get_product_type( $post );
	}
}
