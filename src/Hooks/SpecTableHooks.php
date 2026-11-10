<?php
/**
 * The spec tables' post type, and what a change to a table does.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `cbuild_spec_table` and asks for a reindex when a table is saved, binned, restored or deleted, since any
 * part may name it (spec: Admin, Spec tables). Checked in WordPress 7.1.4: `wp_after_insert_post`
 * (wp-includes/post.php:6021) fires once a post and its meta are saved, from the editor, the REST API or code, and
 * for the bin and back, which are updates; `deleted_post` (wp-includes/post.php:3996) when a post is deleted for
 * good. The type is registered on every request, as a post type is.
 *
 * @since 0.3.0
 */
final class SpecTableHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param IndexJobs $jobs The jobs.
	 */
	public function __construct( private IndexJobs $jobs ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'registerType' ), 10, 0 );
		add_action( 'wp_after_insert_post', array( $this, 'saved' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'deleted' ), 10, 2 );
	}

	/**
	 * Register the post type: the shop's own records, never a page. Nothing on the storefront, in search, in a
	 * sitemap or in the REST API shows one, and only who manages WooCommerce may touch one.
	 *
	 * @since 0.3.0
	 */
	public function registerType(): void {
		$capabilities = array();
		foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ) as $capability ) {
			$capabilities[ $capability ] = 'manage_woocommerce';
		}
		// phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral -- The constant is 'cbuild_spec_table'; OptionsTest holds its prefix and its length.
		register_post_type(
			Options::POST_TYPE_SPEC_TABLE,
			array(
				'labels'              => array(
					'name'          => __( 'Spec tables', 'creative-skateboard-builder' ),
					'singular_name' => __( 'Spec table', 'creative-skateboard-builder' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'supports'            => array( 'title' ),
				'capabilities'        => $capabilities,
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * A post was saved.
	 *
	 * @since 0.3.0
	 *
	 * @param int     $id   The post's ID.
	 * @param WP_Post $post The post.
	 */
	public function saved( int $id, WP_Post $post ): void {
		unset( $id );
		// An auto-draft is the editor opening, not a table.
		if ( Options::POST_TYPE_SPEC_TABLE === $post->post_type && 'auto-draft' !== $post->post_status ) {
			$this->jobs->requestReindex();
		}
	}

	/**
	 * A post was deleted for good.
	 *
	 * @since 0.3.0
	 *
	 * @param int     $id   The post's ID.
	 * @param WP_Post $post The post as it was.
	 */
	public function deleted( int $id, WP_Post $post ): void {
		unset( $id );
		if ( Options::POST_TYPE_SPEC_TABLE === $post->post_type ) {
			$this->jobs->requestReindex();
		}
	}
}
