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
	 * The most pages a kit's Build tab lists, by title, of those that hold the block or a synced pattern. Each is read
	 * for its blocks when the tab is drawn or saved.
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
	 * The site's published pages with no password that hold the Builder block or a synced pattern, by title, read with
	 * their content, so asking each for its blocks asks the database nothing more. Only those are read, so a Builder
	 * page is listed however many pages the site has (the auditor's M12 minor 8).
	 *
	 * @since 1.2.0
	 *
	 * @return list<int>
	 */
	public function pages(): array {
		$holding = array_values( array_unique( array( ...self::holding( '<!-- wp:' . Options::BLOCK . ' ' ), ...self::holding( '<!-- wp:block ' ) ) ) );
		if ( array() === $holding ) {
			return array();
		}
		return self::ids( self::query( array( 'post__in' => $holding ) ) );
	}

	/**
	 * The pages whose content holds this text.
	 *
	 * @param string $text The text, a block's opening comment.
	 * @return list<int>
	 */
	private static function holding( string $text ): array {
		return self::ids(
			self::query(
				array(
					's'              => $text,
					'sentence'       => true,
					'search_columns' => array( 'post_content' ),
					'fields'         => 'ids',
				)
			)
		);
	}

	/**
	 * Published pages with no password, by title, as many as a Build tab lists. Not the main query, so a search
	 * plugin that answers only a visitor's search leaves it to WordPress.
	 *
	 * @param array<string, mixed> $args What narrows them.
	 */
	private static function query( array $args ): WP_Query {
		return new WP_Query(
			$args + array(
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
	}

	/**
	 * The IDs a query found, whether it found posts or IDs.
	 *
	 * @param WP_Query $found The query.
	 * @return list<int>
	 */
	private static function ids( WP_Query $found ): array {
		$ids = array();
		foreach ( $found->posts ?? array() as $page ) {
			$id = $page instanceof WP_Post ? $page->ID : ( is_int( $page ) ? $page : 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}
}
