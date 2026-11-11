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
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WC_Product;

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
 * @since 0.4.0
 */
final class CardHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Closure(): PartCards $cards Builds the cards, when one of the hooks fires.
	 */
	public function __construct( private Closure $cards ) {}

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
	}
}
