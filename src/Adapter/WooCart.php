<?php
/**
 * WooCommerce's cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Cart;
use CreativeBranding\SkateboardBuilder\Port\CartLine;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Cart;
use WC_Product;
use WC_Product_Variation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shopper's cart, through WooCommerce's own methods: a line is added, removed and counted as WooCommerce does
 * it, so its stock checks, its hooks and its session all run. On a request with no cart (wp-admin, a job) it is
 * an empty one.
 *
 * @since 0.5.0
 */
final class WooCart implements Cart {

	/**
	 * Every line, by its cart key.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, CartLine>
	 */
	public function lines(): array {
		$lines = array();
		foreach ( self::cart()?->get_cart() ?? array() as $key => $item ) {
			$item          = Cast::map( $item );
			$key           = (string) $key;
			$lines[ $key ] = new CartLine(
				$key,
				Cast::int( $item['variation_id'] ?? 0 ) > 0 ? Cast::int( $item['variation_id'] ) : Cast::int( $item['product_id'] ?? 0 ),
				Cast::int( $item['quantity'] ?? 0 ),
				$item[ Options::CART_BUILD ] ?? null,
				Cast::strings( $item[ Options::CART_CHILDREN ] ?? null ),
				Cast::str( $item[ Options::CART_PARENT ] ?? '' ),
				Cast::str( $item[ Options::CART_SLOT ] ?? '' )
			);
		}
		return $lines;
	}

	/**
	 * Add a part as a line of a build. WooCommerce says why it will not in a notice of its own.
	 *
	 * @since 0.5.0
	 *
	 * @param int    $part     The product's or the variation's ID.
	 * @param int    $quantity How many.
	 * @param string $kit      The kit's line's cart key.
	 * @param string $slot     The slot it fills.
	 */
	public function add( int $part, int $quantity, string $kit, string $slot ): ?string {
		$cart    = self::cart();
		$product = wc_get_product( $part );
		if ( null === $cart || ! $product instanceof WC_Product ) {
			return null;
		}
		$variation = $product instanceof WC_Product_Variation;
		$key       = $cart->add_to_cart(
			$variation ? $product->get_parent_id() : $part,
			$quantity,
			$variation ? $part : 0,
			$variation ? $product->get_variation_attributes() : array(),
			array(
				Options::CART_PARENT => $kit,
				Options::CART_SLOT   => $slot,
			)
		);
		return is_string( $key ) && '' !== $key ? $key : null;
	}

	/**
	 * Remove a line.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function remove( string $key ): void {
		self::cart()?->remove_cart_item( $key );
	}

	/**
	 * Put back a line the cart removed in this session.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function restore( string $key ): void {
		self::cart()?->restore_cart_item( $key );
	}

	/**
	 * Set how many a line holds.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key      The line's cart key.
	 * @param int    $quantity How many.
	 */
	public function setQuantity( string $key, int $quantity ): void {
		self::cart()?->set_quantity( $key, $quantity, false );
	}

	/**
	 * Write the key `cbuild_children` on a kit's line. The cart's contents are a public property, written as
	 * WooCommerce's own extensions write a line's data after it is added; the session is saved with the totals,
	 * which WooCommerce works out after every add.
	 *
	 * @since 0.5.0
	 *
	 * @param string       $key      The kit's cart key.
	 * @param list<string> $children Its parts' cart keys.
	 */
	public function setChildren( string $key, array $children ): void {
		$cart = self::cart();
		if ( null !== $cart && isset( $cart->cart_contents[ $key ] ) ) {
			$cart->cart_contents[ $key ][ Options::CART_CHILDREN ] = $children;
		}
	}

	/**
	 * The cart, where this request has one.
	 */
	private static function cart(): ?WC_Cart {
		// The property is null on a request with no cart, though WooCommerce declares it a cart: read as it is.
		$cart = get_object_vars( WC() )['cart'] ?? null;
		return $cart instanceof WC_Cart ? $cart : null;
	}
}
