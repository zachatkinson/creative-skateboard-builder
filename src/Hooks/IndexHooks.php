<?php
/**
 * The hooks that keep the specs index.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\SavedSources;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;
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
	 * @param Closure(): IndexJobs $jobs  Builds the jobs, when one of the hooks fires.
	 * @param SavedSources         $saved Notes what each save changes. It is told of every post's meta and terms,
	 *                                    so it is built with the hooks and builds nothing itself.
	 */
	public function __construct( private Closure $jobs, private SavedSources $saved ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		foreach ( array( 'woocommerce_new_product', 'woocommerce_new_product_variation' ) as $made ) {
			add_action( $made, array( $this, 'changed' ), 10, 1 );
		}
		foreach ( array( 'woocommerce_update_product', 'woocommerce_update_product_variation' ) as $saved ) {
			add_action( $saved, array( $this, 'updated' ), 10, 1 );
		}
		add_action( 'woocommerce_before_product_object_save', array( $this, 'saving' ), 10, 1 );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $written ) {
			add_action( $written, array( $this, 'meta' ), 10, 3 );
		}
		add_action( 'set_object_terms', array( $this, 'terms' ), 10, 6 );
		add_action( 'deleted_post', array( $this, 'deleted' ), 10, 2 );
		add_action( 'edited_term', array( $this, 'termEdited' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'termEdited' ), 10, 3 );
		add_action( 'add_option_' . Options::SETTINGS, array( $this, 'settingsAdded' ), 10, 2 );
		add_action( 'update_option_' . Options::SETTINGS, array( $this, 'settingsChanged' ), 10, 2 );
		add_action( Options::JOB_INDEX_PART, array( $this, 'indexPart' ), 10, 2 );
		add_action( Options::JOB_REINDEX, array( $this, 'runReindex' ), 10, 0 );
	}

	/**
	 * A product or a variation was made.
	 *
	 * @since 0.4.0
	 *
	 * @param int $id Its ID.
	 */
	public function changed( int $id ): void {
		( $this->jobs )()->changed( $id );
	}

	/**
	 * A product or a variation was saved over what was stored.
	 *
	 * @since 0.5.0
	 *
	 * @param int $id Its ID.
	 */
	public function updated( int $id ): void {
		( $this->jobs )()->updated( $id );
	}

	/**
	 * A product or a variation is about to be saved (WooCommerce 11.2.2,
	 * includes/abstracts/abstract-wc-product.php:1559, after its properties are validated).
	 *
	 * @since 0.5.0
	 *
	 * @param WC_Product $product The product, with what the save changes.
	 */
	public function saving( WC_Product $product ): void {
		$this->saved->saving( $product->get_id(), array_keys( $product->get_changes() ) );
	}

	/**
	 * A post's meta was added, changed or deleted (WordPress 7.1.4, wp-includes/meta.php). The first argument is
	 * one meta ID, or a list of them for a delete.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $meta The meta ID or IDs.
	 * @param mixed $id   The post's ID.
	 * @param mixed $key  The meta key.
	 */
	public function meta( mixed $meta, mixed $id, mixed $key ): void {
		unset( $meta );
		if ( is_numeric( $id ) && is_string( $key ) ) {
			$this->saved->meta( (int) $id, $key );
		}
	}

	/**
	 * A post's terms were set (WordPress 7.1.4, wp-includes/taxonomy.php).
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $id       The post's ID.
	 * @param mixed $terms    The terms asked for.
	 * @param mixed $now      The term taxonomy IDs it has now.
	 * @param mixed $taxonomy The taxonomy.
	 * @param mixed $append   Whether they were added to what was there.
	 * @param mixed $before   The term taxonomy IDs it had.
	 */
	public function terms( mixed $id, mixed $terms, mixed $now, mixed $taxonomy, mixed $append, mixed $before ): void {
		unset( $terms, $append );
		if ( is_numeric( $id ) && is_string( $taxonomy ) && is_array( $now ) && is_array( $before ) ) {
			$this->saved->terms( (int) $id, $taxonomy, array_map( 'intval', array_filter( $now, 'is_numeric' ) ), array_map( 'intval', array_filter( $before, 'is_numeric' ) ) );
		}
	}

	/**
	 * The settings were saved over what was there.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $before The option as it was.
	 * @param mixed $after  The option as it is.
	 */
	public function settingsChanged( mixed $before, mixed $after ): void {
		( $this->jobs )()->settingsChanged( $before, $after );
	}

	/**
	 * The job that indexes one part.
	 *
	 * @since 0.4.0
	 *
	 * @param int $id      The product's or variation's ID.
	 * @param int $attempt How many times it has failed before.
	 */
	public function indexPart( int $id, int $attempt = 0 ): void {
		( $this->jobs )()->indexPart( $id, $attempt );
	}

	/**
	 * The job that runs a batch of the reindex.
	 *
	 * @since 0.4.0
	 */
	public function runReindex(): void {
		( $this->jobs )()->runReindex();
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
		( $this->jobs )()->removed( $id, $post->post_type );
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
		( $this->jobs )()->termChanged( $taxonomy );
	}

	/**
	 * The settings saved for the first time: until now the index was built with no mapping and the default order.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $option The option's name.
	 * @param mixed $value  What was saved.
	 */
	public function settingsAdded( mixed $option, mixed $value ): void {
		unset( $option );
		( $this->jobs )()->settingsChanged( array(), $value );
	}
}
