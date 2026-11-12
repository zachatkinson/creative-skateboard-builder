<?php
/**
 * No express pay on a kit's page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps Apple Pay and Google Pay buttons off a kit's page (spec: Compatibility, Express payment buttons). A button
 * there would add the kit to the cart with no build. The cart refuses that whichever way it arrives (`CartHooks`),
 * so this is for the shopper, who should not be offered a button that cannot work.
 *
 * Neither plugin is needed for these to be answered, and neither is loaded: a filter nobody applies costs nothing.
 * Both plugins list the product types their buttons take and a kit's is in neither list; these hold where a shop's
 * code has added it. The filters were read in each plugin's public source on 11 November 2026 (D-133).
 *
 * @since 0.5.0
 */
final class ExpressHooks implements Hookable {

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// WooCommerce Stripe Gateway, includes/payment-methods/class-wc-stripe-express-checkout-helper.php:
		// should_show_ece_on_product_pages() and supported_product_types().
		add_filter( 'wc_stripe_hide_payment_request_on_product_page', array( $this, 'hide' ), 10, 2 );
		add_filter( 'wc_stripe_payment_request_supported_types', array( $this, 'types' ), 999, 1 );
		// WooPayments, includes/express-checkout/class-wc-payments-express-checkout-button-helper.php:
		// is_product_supported() and supported_product_types().
		add_filter( 'wcpay_payment_request_is_product_supported', array( $this, 'supported' ), 10, 2 );
		add_filter( 'wcpay_payment_request_supported_types', array( $this, 'types' ), 999, 1 );
	}

	/**
	 * Stripe asks whether to hide its buttons on a product's page: on a kit's, yes.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $hide Whether they are hidden so far.
	 * @param mixed $post The page's post.
	 */
	public function hide( mixed $hide, mixed $post = null ): mixed {
		return $post instanceof WP_Post && self::kit( wc_get_product( $post->ID ) ) ? true : $hide;
	}

	/**
	 * WooPayments asks whether its buttons take a product: a kit, no.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $supported Whether it is taken so far.
	 * @param mixed $product   The product.
	 */
	public function supported( mixed $supported, mixed $product = null ): mixed {
		return self::kit( $product ) ? false : $supported;
	}

	/**
	 * The product types either plugin's buttons take: never a kit's.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $types The types so far.
	 */
	public function types( mixed $types ): mixed {
		return is_array( $types ) ? array_values( array_diff( Cast::strings( $types ), Options::productTypes() ) ) : $types;
	}

	/**
	 * Whether a product is a kit.
	 *
	 * @param mixed $product The product, or anything else.
	 */
	private static function kit( mixed $product ): bool {
		return $product instanceof WC_Product && in_array( $product->get_type(), Options::productTypes(), true );
	}
}
