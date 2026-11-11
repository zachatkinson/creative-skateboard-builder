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
 * What is kept of each part in the parts table (`Port\Kits::offered()`, D-116) is forgotten with its card, and
 * also when:
 *
 * - a product or a variation is made, since a new variation changes what its parent's row answers for
 *   (`woocommerce_new_product`, class-wc-product-data-store-cpt.php:263; `woocommerce_new_product_variation`,
 *   class-wc-product-variation-data-store-cpt.php:174);
 * - a product's terms are set, for its brand (`set_object_terms`, wp-includes/taxonomy.php:3007);
 * - a term is edited or deleted, for a brand's slug and a variation's attribute values (`edited_term` and
 *   `delete_term`, wp-includes/taxonomy.php:3515, :2235);
 * - a product's status moves by any road, the bin included (`transition_post_status`, wp-includes/post.php:5922);
 * - the index is written: the part its job indexed, and every part after a batch of a reindex, since a row holds
 *   its part's specs;
 * - a plugin says so with `cbuild_parts_changed`, for what it knows and WooCommerce does not.
 *
 * Which products a slot's categories hold is asked of the shop on every request and needs nothing forgotten.
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
		foreach ( array( 'woocommerce_new_product', 'woocommerce_new_product_variation', 'set_object_terms' ) as $kept ) {
			add_action( $kept, array( $this, 'changed' ), 10, 1 );
		}
		// After the jobs themselves, which Hooks\IndexHooks runs at 10: a row holds its part's specs as the index had them.
		add_action( Options::JOB_INDEX_PART, array( $this, 'changed' ), 20, 1 );
		add_action( Options::JOB_REINDEX, array( $this, 'changed' ), 20, 0 );
		add_action( 'edited_term', array( $this, 'term' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'term' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'status' ), 10, 3 );
		/**
		 * Do this action when something the builder keeps of a part changed and WooCommerce was not told: a plugin's
		 * answer to `cbuild_part_available`, or a price a plugin sets with no product saved. What is kept of the
		 * products named is read from the shop again by the next request that needs it, or of every product when
		 * none is named.
		 *
		 * @since 0.4.0
		 *
		 * @param int[] $ids The products' and variations' IDs. Left out or empty: every part.
		 */
		add_action( 'cbuild_parts_changed', array( $this, 'changed' ), 10, 1 );
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
		if ( array() !== $ids ) {
			( $this->kits )()->forget( ...array_values( $ids ) );
		}
	}

	/**
	 * What is kept of these parts may have moved, where their cards have not: of every part when none is named,
	 * which only `cbuild_parts_changed` does.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $what A product's ID or a list of them, as each hook gives it; nothing, for every part.
	 */
	public function changed( mixed $what = array() ): void {
		( $this->kits )()->forget( ...array_values( Cast::ints( is_array( $what ) ? $what : array( $what ) ) ) );
	}

	/**
	 * A term was edited or deleted.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $term     The term's ID.
	 * @param mixed $ttId     The term taxonomy ID.
	 * @param mixed $taxonomy The taxonomy.
	 */
	public function term( mixed $term, mixed $ttId, mixed $taxonomy ): void {
		unset( $term, $ttId );
		( $this->kits )()->termMoved( Cast::str( $taxonomy ) );
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
			( $this->kits )()->forget( $post->ID );
		}
	}
}
