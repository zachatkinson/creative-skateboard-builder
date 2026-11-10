<?php
/**
 * Tells WooCommerce of the kit product type.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce builds a product from its type's class wherever a product is read, so this fires on any request.
 *
 * @since 0.4.0
 */
final class KitHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param class-string $kit The kit's product class.
	 */
	public function __construct( private string $kit ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.4.0
	 */
	public function register(): void {
		add_filter( 'product_type_selector', array( $this, 'types' ), 10, 1 );
		add_filter( 'woocommerce_product_class', array( $this, 'productClass' ), 10, 2 );
	}

	/**
	 * The product types, with the kit among them (WooCommerce 11.2.2, includes/wc-product-functions.php:1027).
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $types The types, each with its name.
	 * @return mixed
	 */
	public function types( mixed $types ): mixed {
		if ( is_array( $types ) ) {
			$types[ Options::PRODUCT_TYPE_KIT ] = __( 'Builder kit', 'creative-skateboard-builder' );
		}
		return $types;
	}

	/**
	 * The class WooCommerce builds a kit from (WooCommerce 11.2.2, includes/class-wc-product-factory.php:90).
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $name The class WooCommerce would use.
	 * @param mixed $type The product's type.
	 * @return mixed
	 */
	public function productClass( mixed $name, mixed $type ): mixed {
		return Options::PRODUCT_TYPE_KIT === $type ? $this->kit : $name;
	}
}
