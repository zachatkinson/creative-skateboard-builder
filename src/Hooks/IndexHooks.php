<?php
/**
 * The hooks that keep the specs index.
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
 * What queues a part's job or a reindex, and the two jobs themselves. A product is saved from wp-admin, the REST API,
 * WP-CLI, an import or another plugin, and the jobs run wherever Action Scheduler does, so these fire on any request.
 * Checked in WooCommerce 11.2.2: `woocommerce_new_product` and `woocommerce_update_product`
 * (includes/data-stores/class-wc-product-data-store-cpt.php:263, :398), the two for variations
 * (class-wc-product-variation-data-store-cpt.php:174, :279); and in WordPress 7.1.4, `deleted_post`
 * (wp-includes/post.php:3996), which fires however the product was deleted.
 *
 * @since 0.3.0
 */
final class IndexHooks implements Hookable {

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
		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation' ) as $saved ) {
			add_action( $saved, array( $this->jobs, 'changed' ), 10, 1 );
		}
		add_action( 'deleted_post', array( $this, 'deleted' ), 10, 2 );
		add_action( 'edited_term', array( $this, 'termEdited' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'termEdited' ), 10, 3 );
		add_action( 'add_option_' . Options::SETTINGS, array( $this->jobs, 'requestReindex' ), 10, 0 );
		add_action( 'update_option_' . Options::SETTINGS, array( $this->jobs, 'requestReindex' ), 10, 0 );
		add_action( Options::JOB_INDEX_PART, array( $this->jobs, 'indexPart' ), 10, 2 );
		add_action( Options::JOB_REINDEX, array( $this->jobs, 'runReindex' ), 10, 0 );
	}

	/**
	 * A post was deleted.
	 *
	 * @since 0.3.0
	 *
	 * @param int     $id   The post's ID.
	 * @param WP_Post $post The post as it was.
	 */
	public function deleted( int $id, WP_Post $post ): void {
		$this->jobs->removed( $id, $post->post_type );
	}

	/**
	 * A term was edited, or deleted: `delete_term` passes the same three first (WordPress 7.1.4,
	 * wp-includes/taxonomy.php:2235), and no product is saved when a term it carried goes.
	 *
	 * @since 0.3.0
	 *
	 * @param int    $term     The term's ID.
	 * @param int    $ttId     The term taxonomy ID.
	 * @param string $taxonomy The taxonomy.
	 */
	public function termEdited( int $term, int $ttId, string $taxonomy ): void {
		unset( $term, $ttId );
		$this->jobs->termChanged( $taxonomy );
	}
}
