<?php
/**
 * The shopper's cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce's cart, as much of it as keeping a build together needs. Adapter\WooCart is the live one.
 *
 * @since 0.5.0
 */
interface Cart {

	/**
	 * Every line, by its cart key, in the cart's order.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, CartLine>
	 */
	public function lines(): array;

	/**
	 * Add a part as a line of a build.
	 *
	 * @since 0.5.0
	 *
	 * @param int    $part     The product's or the variation's ID.
	 * @param int    $quantity How many.
	 * @param string $kit The kit's line's cart key.
	 * @param string $slot     The slot it fills.
	 * @return string|null The new line's cart key, or null when WooCommerce would not add it.
	 */
	public function add( int $part, int $quantity, string $kit, string $slot ): ?string;

	/**
	 * Remove a line. One that is not there is not an error.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function remove( string $key ): void;

	/**
	 * Put back a line the cart removed in this session, as its Undo does.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function restore( string $key ): void;

	/**
	 * Set how many a line holds.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key      The line's cart key.
	 * @param int    $quantity How many, one or more.
	 */
	public function setQuantity( string $key, int $quantity ): void;

	/**
	 * Write the key `cbuild_children` on a kit's line.
	 *
	 * @since 0.5.0
	 *
	 * @param string       $key      The kit's cart key.
	 * @param list<string> $children Its parts' cart keys.
	 */
	public function setChildren( string $key, array $children ): void;
}
