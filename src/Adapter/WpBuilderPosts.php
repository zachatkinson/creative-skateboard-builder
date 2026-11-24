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
use WP_Post;
use WP_Query;

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
	 * The most pages a kit's Build tab lists, by title. Each is read for its blocks when the tab is drawn or saved.
	 */
	private const PAGES = 500;

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

	/**
	 * Whether a post is a page a visitor may see: published where anyone can read it, and with no password. A page
	 * a visitor has unlocked is still locked to the next.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post The post's ID.
	 */
	public function isPage( int $post ): bool {
		$found = $post > 0 ? get_post( $post ) : null;
		return $found instanceof WP_Post && 'page' === $found->post_type && 'publish' === $found->post_status && is_post_publicly_viewable( $found ) && '' === $found->post_password;
	}

	/**
	 * The site's published pages with no password, by title, read with their content, so asking each for its blocks
	 * asks the database nothing more.
	 *
	 * @since 1.2.0
	 *
	 * @return list<int>
	 */
	public function pages(): array {
		$found = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'has_password'           => false,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'posts_per_page'         => self::PAGES,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$pages = array();
		foreach ( $found->posts ?? array() as $page ) {
			if ( $page instanceof WP_Post ) {
				$pages[] = $page->ID;
			}
		}
		return $pages;
	}
}
