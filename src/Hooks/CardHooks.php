<?php
/**
 * The hooks that keep parts' cards current.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A part's card is kept for an hour (`Adapter\WooPartCards`), and forgotten the moment its product is saved, its
 * stock or its stock status moves, a sale on it starts or ends, or it is deleted. Checked in WooCommerce 11.2.2:
 * `woocommerce_update_product` (includes/data-stores/class-wc-product-data-store-cpt.php:398) and
 * `woocommerce_update_product_variation` (class-wc-product-variation-data-store-cpt.php:279);
 * `woocommerce_product_set_stock` and `woocommerce_variation_set_stock` with the product
 * (includes/wc-stock-functions.php:71, :75; class-wc-product-data-store-cpt.php:934, :943);
 * `woocommerce_product_set_stock_status` and `woocommerce_variation_set_stock_status` with its ID first (:958, :969);
 * `wc_after_products_starting_sales` and `wc_after_products_ending_sales` with the IDs
 * (includes/wc-product-functions.php:876, :896). And in WordPress 7.1.4, `deleted_post` (wp-includes/post.php:3996).
 *
 * Each slot's list of parts is kept for an hour too (`Port\Kits::kept()`, D-116), and all of them are forgotten on
 * any of those and on what else decides which parts a slot has and what the engine reads of them:
 *
 * - a product or a variation is made (`woocommerce_new_product`, class-wc-product-data-store-cpt.php:263;
 *   `woocommerce_new_product_variation`, class-wc-product-variation-data-store-cpt.php:174);
 * - a product's terms are set, or a term is edited or deleted (`set_object_terms`, `edited_term` and `delete_term`,
 *   wp-includes/taxonomy.php:3007, :3515, :2235);
 * - a product's status moves by any road, the bin included (`transition_post_status`, wp-includes/post.php:5922);
 * - the index was written: after the job that indexes a part, and after each batch of a reindex;
 * - a plugin says so with `cbuild_parts_changed`, for what it knows and WooCommerce does not.
 *
 * @since 0.4.0
 */
final class CardHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Closure(): PartCards $cards Builds the cards, when one of the hooks fires.
	 * @param Closure(): Kits      $kits  Builds the kits, when one does.
	 */
	public function __construct( private Closure $cards, private Closure $kits ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.4.0
	 */
	public function register(): void {
		foreach ( array(
			'woocommerce_update_product',
			'woocommerce_update_product_variation',
			'woocommerce_product_set_stock',
			'woocommerce_variation_set_stock',
			'woocommerce_product_set_stock_status',
			'woocommerce_variation_set_stock_status',
			'wc_after_products_starting_sales',
			'wc_after_products_ending_sales',
			'deleted_post',
		) as $moved ) {
			add_action( $moved, array( $this, 'moved' ), 10, 1 );
		}
		foreach ( array( 'woocommerce_new_product', 'woocommerce_new_product_variation', 'set_object_terms', 'edited_term', 'delete_term' ) as $listed ) {
			add_action( $listed, array( $this, 'listed' ), 10, 0 );
		}
		// After the jobs themselves, which Hooks\IndexHooks runs at 10.
		add_action( Options::JOB_INDEX_PART, array( $this, 'listed' ), 20, 0 );
		add_action( Options::JOB_REINDEX, array( $this, 'listed' ), 20, 0 );
		add_action( 'transition_post_status', array( $this, 'status' ), 10, 3 );
		/**
		 * Do this action when something the builder keeps of a part changed and WooCommerce was not told: a plugin's
		 * answer to `cbuild_part_available`, or a price a plugin sets with no product saved. Every slot's kept parts
		 * are then read again by the next request, and so is each card as its hour ends. It takes no arguments.
		 *
		 * @since 0.4.0
		 */
		add_action( 'cbuild_parts_changed', array( $this, 'listed' ), 10, 0 );
	}

	/**
	 * Something a card shows may have moved.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $what A product, its ID, or a list of IDs, as each hook gives it.
	 */
	public function moved( mixed $what ): void {
		$ids = $what instanceof WC_Product ? array( $what->get_id() ) : Cast::ints( is_array( $what ) ? $what : array( $what ) );
		( $this->cards )()->forget( ...array_values( $ids ) );
		( $this->kits )()->forget();
	}

	/**
	 * Which parts a slot has, or what the engine reads of one, may have moved.
	 *
	 * @since 0.4.0
	 */
	public function listed(): void {
		( $this->kits )()->forget();
	}

	/**
	 * A post's status moved: a product's or a variation's decides whether it is a part.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $now    The status it has.
	 * @param mixed $before The status it had.
	 * @param mixed $post   The post.
	 */
	public function status( mixed $now, mixed $before, mixed $post ): void {
		if ( $now !== $before && $post instanceof WP_Post && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			( $this->kits )()->forget();
		}
	}
}
