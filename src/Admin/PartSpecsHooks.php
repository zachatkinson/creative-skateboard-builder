<?php
/**
 * Puts the Part specs panel on the product screen.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The panel is a tab of the product data box on simple and variable products, and a group of fields inside each
 * variation. Both are saved in WooCommerce's own hooks, which fire just before it writes the object, so the specs go
 * in with the same save (WooCommerce 11.2.2, `includes/admin/meta-boxes/class-wc-meta-box-product-data.php:444` and
 * `:629`). The panel is built when one of the hooks fires, not on every admin page.
 *
 * @since 0.3.0
 */
final class PartSpecsHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): PartSpecsPanel $panel Builds the panel.
	 */
	public function __construct( private Closure $panel ) {}

	/**
	 * Attach.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		// After WooCommerce registers its admin styles, at the default priority.
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ), 20, 0 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'tab' ), 10, 1 );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product' ), 10, 0 );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation' ), 10, 3 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'saveProduct' ), 10, 1 );
		add_action( 'woocommerce_admin_process_variation_object', array( $this, 'saveVariation' ), 10, 2 );
	}

	/**
	 * Style the panel's groups, on the product screen alone.
	 *
	 * @since 0.3.0
	 */
	public function styles(): void {
		$screen = get_current_screen();
		if ( null !== $screen && 'product' === $screen->id ) {
			( $this->panel )()->styles();
		}
	}

	/**
	 * Add the tab.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $tabs The product data box's tabs.
	 * @return mixed
	 */
	public function tab( mixed $tabs ): mixed {
		return is_array( $tabs ) ? ( $this->panel )()->tab( $tabs ) : $tabs;
	}

	/**
	 * Draw the tab's panel.
	 *
	 * @since 0.3.0
	 */
	public function product(): void {
		( $this->panel )()->product();
	}

	/**
	 * Draw a variation's fields.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $loop      The variation's row number.
	 * @param mixed $data      Its data, which the panel does not read.
	 * @param mixed $variation Its post.
	 */
	public function variation( mixed $loop, mixed $data, mixed $variation ): void {
		unset( $data );
		( $this->panel )()->variation( $loop, $variation );
	}

	/**
	 * Save the tab's panel.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $product The product about to be saved.
	 */
	public function saveProduct( mixed $product ): void {
		( $this->panel )()->saveProduct( $product );
	}

	/**
	 * Save a variation's fields.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $variation The variation about to be saved.
	 * @param mixed $loop      Its row number.
	 */
	public function saveVariation( mixed $variation, mixed $loop ): void {
		( $this->panel )()->saveVariation( $variation, $loop );
	}
}
