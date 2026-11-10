<?php
/**
 * Puts the Build tab on the product screen.
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
 * The tab is one of the product data box's, shown on kits, and is saved in WooCommerce's own hook, which fires just
 * before it writes the product, so the kit's documents go in with the same save (WooCommerce 11.2.2,
 * `includes/admin/meta-boxes/class-wc-meta-box-product-data.php:444`). The tab is built when one of the hooks fires,
 * not on every admin page.
 *
 * @since 0.4.0
 */
final class BuildTabHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Closure(): BuildTab $tab Builds the tab.
	 */
	public function __construct( private Closure $tab ) {}

	/**
	 * Attach.
	 *
	 * @since 0.4.0
	 */
	public function register(): void {
		// After WooCommerce registers its admin styles, at the default priority.
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ), 20, 0 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'tab' ), 10, 1 );
		add_action( 'woocommerce_product_data_panels', array( $this, 'panel' ), 10, 0 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Style the tab and load its script, on the product screen alone.
	 *
	 * @since 0.4.0
	 */
	public function styles(): void {
		$screen = get_current_screen();
		if ( null !== $screen && 'product' === $screen->id ) {
			( $this->tab )()->styles();
		}
	}

	/**
	 * Add the tab.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $tabs The product data box's tabs.
	 * @return mixed
	 */
	public function tab( mixed $tabs ): mixed {
		return is_array( $tabs ) ? ( $this->tab )()->tab( $tabs ) : $tabs;
	}

	/**
	 * Draw the tab's panel.
	 *
	 * @since 0.4.0
	 */
	public function panel(): void {
		( $this->tab )()->panel();
	}

	/**
	 * Save the tab.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $product The product about to be saved.
	 */
	public function save( mixed $product ): void {
		( $this->tab )()->save( $product );
	}
}
