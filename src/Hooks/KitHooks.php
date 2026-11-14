<?php
/**
 * Tells WooCommerce of the kit product type.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\KitVisibility;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;

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
	 * @param class-string  $kit        The kit's product class.
	 * @param KitVisibility $visibility Keeps kits out of what lists products.
	 */
	public function __construct( private string $kit, private KitVisibility $visibility ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.4.0
	 */
	public function register(): void {
		add_filter( 'product_type_selector', array( $this, 'types' ), 10, 1 );
		add_filter( 'woocommerce_product_class', array( $this, 'productClass' ), 10, 2 );
		// WordPress 7.1.4, wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php:238.
		add_filter( 'wp_sitemaps_posts_query_args', array( $this->visibility, 'sitemapQuery' ), 10, 2 );
		// Yoast SEO 28.8, inc/sitemaps/class-post-type-sitemap-provider.php:296. It fires only where Yoast is.
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this->visibility, 'excluded' ), 10, 1 );
		// WooCommerce 11.2.2, includes/class-wc-structured-data.php:545.
		add_filter( 'woocommerce_structured_data_product', array( $this, 'markup' ), 10, 2 );
		// PayPal Payments 4.2.2, modules/ppcp-wc-gateway/src/Helper/ProductPaymentSupportTrait.php:33. It fires only
		// where PayPal Payments is.
		add_filter( 'woocommerce_paypal_payments_product_supports_payment_request_button', array( $this, 'express' ), 10, 2 );
	}

	/**
	 * No express payment buttons, and no Pay Later message, on a kit's page (D-164). PayPal Payments offers them on a
	 * product's page whenever its type is not external or grouped and it is in stock, without asking whether it can be
	 * bought; each would offer the kit alone, which the cart refuses. A kit is bought through its builder.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $offer   Whether PayPal Payments would offer them.
	 * @param mixed $product The product on view.
	 * @return mixed
	 */
	public function express( mixed $offer, mixed $product ): mixed {
		return $product instanceof WC_Product && $product->is_type( Options::PRODUCT_TYPE_KIT ) ? false : $offer;
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
	 * A product's structured data, with none for a kit.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $markup  The markup WooCommerce built.
	 * @param mixed $product The product.
	 * @return mixed
	 */
	public function markup( mixed $markup, mixed $product ): mixed {
		return $product instanceof WC_Product ? $this->visibility->markup( $markup, $product->get_type() ) : $markup;
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
