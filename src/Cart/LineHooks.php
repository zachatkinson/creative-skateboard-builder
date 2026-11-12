<?php
/**
 * A build's lines kept together, in WooCommerce's cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Closure;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildLines;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WP_Error;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the service of every change to the cart, whichever cart made it, and answers the carts' questions about
 * a line's quantity (spec: Cart, checkout and orders, Keeping it together). The hooks hold no rule of their own.
 *
 * A line with none of the plugin's keys is passed by before the service is built, so a cart with no build in it
 * costs a few array reads.
 *
 * @since 0.5.0
 */
final class LineHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Closure(): BuildLines $lines    Builds the service, when a build's line is touched.
	 * @param Refusals              $refusals Puts a refusal in words.
	 */
	public function __construct( private Closure $lines, private Refusals $refusals ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// includes/class-wc-cart.php:1562: after the quantity is set and before the totals, so they are worked out
		// once, with the parts' lines already following. The spec names woocommerce_cart_item_set_quantity (:1576),
		// which fires after them (D-129).
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'resized' ), 10, 1 );
		// includes/class-wc-cart.php:1509: the line is still in the cart.
		add_action( 'woocommerce_remove_cart_item', array( $this, 'removing' ), 10, 1 );
		// includes/class-wc-cart.php:1536. Before the totals, at 20 (:137).
		add_action( 'woocommerce_cart_item_restored', array( $this, 'restored' ), 10, 1 );
		// src/StoreApi/Utilities/QuantityLimits.php:281 and :309.
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'editable' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'maximum' ), 10, 3 );
		// src/StoreApi/Utilities/QuantityLimits.php:211, since WooCommerce 11.2.0.
		add_filter( 'woocommerce_store_api_cart_item_quantity_validation', array( $this, 'validate' ), 10, 4 );
		// includes/class-wc-form-handler.php:859: the classic cart's Update cart.
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'update' ), 10, 4 );
		// includes/shortcodes/class-wc-shortcode-cart.php:93, includes/class-wc-checkout.php:367,
		// src/StoreApi/Utilities/CartController.php:531. After WooCommerce's own checks, at 1.
		add_action( 'woocommerce_check_cart_items', array( $this, 'check' ), 10, 0 );
	}

	/**
	 * A line's quantity was set.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $key The line's cart key.
	 */
	public function resized( mixed $key ): void {
		if ( is_string( $key ) && self::ours( $key ) ) {
			( $this->lines )()->resized( $key );
		}
	}

	/**
	 * A line is about to be removed.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $key The line's cart key.
	 */
	public function removing( mixed $key ): void {
		if ( is_string( $key ) && self::ours( $key ) ) {
			( $this->lines )()->removing( $key );
		}
	}

	/**
	 * A removed line was put back.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $key The line's cart key.
	 */
	public function restored( mixed $key ): void {
		if ( is_string( $key ) && self::ours( $key ) ) {
			( $this->lines )()->restored( $key );
		}
	}

	/**
	 * Whether the Store API's carts let a shopper set a line's quantity: not a part's.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $editable What WooCommerce says.
	 * @param mixed $product  The product.
	 * @param mixed $item     The cart item, or null for a product not in the cart.
	 */
	public function editable( mixed $editable, mixed $product = null, mixed $item = null ): mixed {
		unset( $product );
		$key = self::key( $item );
		return '' === $key || ( $this->lines )()->editable( $key ) ? $editable : false;
	}

	/**
	 * The most of a line the Store API's carts allow: for a kit's line, no more than its scarcest part allows.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $maximum What WooCommerce says.
	 * @param mixed $product The product.
	 * @param mixed $item    The cart item, or null for a product not in the cart.
	 */
	public function maximum( mixed $maximum, mixed $product = null, mixed $item = null ): mixed {
		unset( $product );
		$key   = self::key( $item );
		$limit = '' === $key ? null : ( $this->lines )()->limit( $key );
		// At least one: a build the shop can no longer fill is said by the check below, not by a quantity of none.
		return null === $limit || ! is_numeric( $maximum ) ? $maximum : max( 1, min( (int) $maximum, $limit ) );
	}

	/**
	 * Whether the Store API may set a line's quantity.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $valid    True, or another plugin's error.
	 * @param mixed $quantity The quantity asked for.
	 * @param mixed $product  The product.
	 * @param mixed $item     The cart item.
	 * @return mixed A WP_Error when it may not.
	 */
	public function validate( mixed $valid, mixed $quantity = 0, mixed $product = null, mixed $item = null ): mixed {
		unset( $product );
		$key     = self::key( $item );
		$verdict = '' === $key || ! is_numeric( $quantity ) || is_wp_error( $valid ) ? null : ( $this->lines )()->mayResize( $key, (int) $quantity );
		if ( null === $verdict ) {
			return $valid;
		}
		return new WP_Error(
			$verdict->refusal->value ?? CartRefusal::BuildRefused->value,
			implode( ' ', $this->refusals->sentences( self::kit( $item ), $verdict ) ),
			array(
				'status'   => 400,
				'problems' => Refusals::problems( $verdict ),
			)
		);
	}

	/**
	 * Whether the classic cart's Update cart may set a line's quantity. Why not is said in notices.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $passed   Whether it has passed so far.
	 * @param mixed $key      The line's cart key.
	 * @param mixed $item     The cart item.
	 * @param mixed $quantity The quantity asked for.
	 */
	public function update( mixed $passed, mixed $key = '', mixed $item = null, mixed $quantity = 0 ): mixed {
		$ours    = '' !== self::key( $item ) && is_string( $key );
		$verdict = ! $ours || true !== $passed || ! is_numeric( $quantity ) ? null : ( $this->lines )()->mayResize( $key, (int) $quantity );
		if ( null === $verdict ) {
			return $passed;
		}
		foreach ( $this->refusals->sentences( self::kit( $item ), $verdict ) as $sentence ) {
			wc_add_notice( esc_html( $sentence ), 'error' );
		}
		return false;
	}

	/**
	 * The cart is looked over, on its page and before it is paid for: a build that has come apart is taken out,
	 * and one the shop would no longer sell as it is stops the order. Each is said in a notice, which the Store
	 * API turns into its error.
	 *
	 * @since 0.5.0
	 */
	public function check(): void {
		$cart = WC()->cart->get_cart();
		$any  = false;
		foreach ( $cart as $item ) {
			$any = $any || '' !== self::key( $item );
		}
		if ( ! $any ) {
			return;
		}
		$tidied = ( $this->lines )()->tidy();
		if ( $tidied->dropped > 0 ) {
			wc_add_notice( esc_html( Words::refusal( CartRefusal::BuildIncomplete ) ), 'error' );
		}
		foreach ( $tidied->refused as $key => $verdict ) {
			$product = $cart[ $key ]['data'] ?? null;
			foreach ( $this->refusals->held( self::kit( $cart[ $key ] ?? null ), $product instanceof WC_Product ? $product->get_name() : '', $verdict ) as $sentence ) {
				wc_add_notice( esc_html( $sentence ), 'error' );
			}
		}
	}

	/**
	 * Whether the cart's line under a key is one of a build's.
	 *
	 * @param string $key The line's cart key.
	 */
	private static function ours( string $key ): bool {
		return '' !== self::key( WC()->cart->get_cart_item( $key ) );
	}

	/**
	 * The cart key of a cart item that is a line of a build: one that carries any of the plugin's keys. '' for any
	 * other.
	 *
	 * @param mixed $item The cart item.
	 */
	private static function key( mixed $item ): string {
		if ( ! is_array( $item ) || ! is_string( $item['key'] ?? null ) ) {
			return '';
		}
		return isset( $item[ Options::CART_BUILD ] ) || isset( $item[ Options::CART_PARENT ] ) ? $item['key'] : '';
	}

	/**
	 * The kit product's ID a kit's line's build names, or 0.
	 *
	 * @param mixed $item The cart item.
	 */
	private static function kit( mixed $item ): int {
		return Build::read( is_array( $item ) ? ( $item[ Options::CART_BUILD ] ?? null ) : null )->kit ?? 0;
	}
}
