<?php
/**
 * A build on an order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use Closure;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildCart;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes a build to the order (spec: Cart, checkout and orders, Orders). Both checkouts make an order's items
 * with `WC_Checkout::create_order_line_items()`, so one hook copies the build's keys to each item. Once the items
 * are saved and have IDs, each part is tied to its kit's item and the kit's to its parts.
 *
 * Every write is to an order item through WooCommerce's CRUD, so it is the same with HPOS on, off or syncing.
 *
 * @since 0.5.0
 */
final class OrderHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Closure(): BuildCart $builds Builds the service, when an order has a build in it.
	 * @param Closure(): Kits      $kits   The shop's kits, for a slot's label.
	 */
	public function __construct( private Closure $builds, private Closure $kits ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// includes/class-wc-checkout.php:612, which src/StoreApi/Utilities/OrderController.php:845 also runs.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'item' ), 10, 3 );
		// The order is saved, and its items have IDs: includes/class-wc-checkout.php:522 for the classic checkout;
		// src/StoreApi/Routes/V1/Checkout.php:871 and :708 for the Store API's, which makes a draft's items again
		// each time the cart changes, so the tie is made again on each (D-131).
		add_action( 'woocommerce_checkout_order_created', array( $this, 'tie' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'tie' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'tie' ), 10, 1 );
		// templates/order/order-details-item.php:33, templates/emails/email-order-items.php:92 and :176,
		// includes/admin/meta-boxes/views/html-order-item.php:30.
		add_filter( 'woocommerce_order_item_name', array( $this, 'name' ), 10, 2 );
		// src/Internal/Utilities/OrderItemMetaUtil.php:30: the order screen lists every key but these, underscore
		// or not, and lets a manager edit what it lists.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hidden' ) );
	}

	/**
	 * The keys the order screen neither lists nor lets be edited: the plugin's own.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $keys The keys hidden so far.
	 * @return list<string>
	 */
	public function hidden( mixed $keys ): array {
		return array_values( array_unique( array_merge( Cast::strings( $keys ), Options::orderItemKeys() ) ) );
	}

	/**
	 * An order item is made from a cart line: a line of a build gets the build's keys.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $item   The order item.
	 * @param mixed $key    The line's cart key.
	 * @param mixed $values The cart item.
	 */
	public function item( mixed $item, mixed $key = '', mixed $values = null ): void {
		unset( $key );
		if ( ! $item instanceof WC_Order_Item_Product || ! is_array( $values ) ) {
			return;
		}
		$cart  = WC()->cart->get_cart();
		$build = Build::read( $values[ Options::CART_BUILD ] ?? null );
		if ( null !== $build ) {
			$this->kit( $item, $build, $values, $cart );
			return;
		}
		$parent = $values[ Options::CART_PARENT ] ?? null;
		$slot   = $values[ Options::CART_SLOT ] ?? null;
		$of     = is_string( $parent ) ? Build::read( $cart[ $parent ][ Options::CART_BUILD ] ?? null ) : null;
		if ( null !== $of && is_string( $slot ) ) {
			$item->update_meta_data( Options::ORDER_BUILD, $of->id );
			$item->update_meta_data( Options::ORDER_ROLE, Options::ROLE_PART );
			$item->update_meta_data( Options::ORDER_SLOT, $slot );
		}
	}

	/**
	 * Tie an order's builds together by their items' IDs: each part's `_cbuild_parent` is its kit's item, and the
	 * kit's `_cbuild_children` its parts' items, in step order. An item is saved only where its tie changes, so
	 * asking again costs nothing.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $order The order.
	 */
	public function tie( mixed $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$kits  = array();
		$parts = array();
		foreach ( $order->get_items() as $id => $item ) {
			$build = $item->get_meta( Options::ORDER_BUILD );
			if ( ! is_string( $build ) || '' === $build ) {
				continue;
			}
			if ( Options::ROLE_KIT === $item->get_meta( Options::ORDER_ROLE ) ) {
				$kits[ $build ] = $item;
			} else {
				$parts[ $build ][ $id ] = $item;
			}
		}
		foreach ( $kits as $build => $kit ) {
			$children = array_keys( $parts[ $build ] ?? array() );
			foreach ( $parts[ $build ] ?? array() as $part ) {
				if ( Cast::int( $part->get_meta( Options::ORDER_PARENT ) ) !== $kit->get_id() ) {
					$part->update_meta_data( Options::ORDER_PARENT, (string) $kit->get_id() );
					$part->save();
				}
			}
			if ( $kit->get_meta( Options::ORDER_CHILDREN ) !== $children ) {
				$kit->update_meta_data( Options::ORDER_CHILDREN, $children );
				$kit->save();
			}
		}
	}

	/**
	 * An order item's name, wherever an order's items are listed: a part is named with the slot it fills.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $name The item's name, which may be a link.
	 * @param mixed $item The order item.
	 */
	public function name( mixed $name, mixed $item = null ): mixed {
		if ( ! is_string( $name ) || ! $item instanceof WC_Order_Item_Product || Options::ROLE_PART !== $item->get_meta( Options::ORDER_ROLE ) ) {
			return $name;
		}
		$order = $item->get_order();
		$kit   = $order instanceof WC_Order ? $order->get_item( Cast::int( $item->get_meta( Options::ORDER_PARENT ) ) ) : false;
		$picks = $kit instanceof WC_Order_Item_Product ? Cast::map( Cast::map( $kit->get_meta( Options::ORDER_SNAPSHOT ) )['picks'] ?? null ) : array();
		$label = Cast::str( Cast::map( $picks[ Cast::str( $item->get_meta( Options::ORDER_SLOT ) ) ] ?? null )['label'] ?? null );
		if ( '' === $label ) {
			return $name;
		}
		/* translators: 1: a slot's label, such as Deck; 2: the product's name, which may be a link. */
		return sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), esc_html( $label ), $name );
	}

	/**
	 * Write the kit's item: the build's keys, the snapshot, and what the customer and the workshop read.
	 *
	 * @param WC_Order_Item_Product               $item   The order item.
	 * @param Build                               $build  The build.
	 * @param array<array-key, mixed>             $values The kit's line.
	 * @param array<string, array<string, mixed>> $cart   The cart's lines.
	 */
	private function kit( WC_Order_Item_Product $item, Build $build, array $values, array $cart ): void {
		$snapshot = ( $this->builds )()->snapshot( $build );
		$kit      = ( $this->kits )()->kit( $build->kit );
		$slots    = $kit?->slots;
		// The owner's words on an assembled build, as the shopper read them, for the order's emails (milestone 8).
		$words = array(
			'lead_time'      => $kit?->assembly->leadTime ?? '',
			'returns_notice' => $kit?->assembly->returnsNotice ?? '',
		);
		foreach ( $words as $field => $text ) {
			$snapshot[ $field ] = $build->assembly && '' !== $text ? StateAnswer::text( $text, $field, $build->kit ) : '';
		}
		$names = array();
		foreach ( is_array( $values[ Options::CART_CHILDREN ] ?? null ) ? $values[ Options::CART_CHILDREN ] : array() as $child ) {
			$line    = is_string( $child ) ? ( $cart[ $child ] ?? array() ) : array();
			$product = $line['data'] ?? null;
			$slot    = $line[ Options::CART_SLOT ] ?? null;
			if ( $product instanceof WC_Product && is_string( $slot ) ) {
				$names[ $slot ] = $product->get_name();
			}
		}
		$told = array();
		foreach ( $snapshot['picks'] as $key => $pick ) {
			$slot                      = $slots?->get( $key );
			$label                     = null === $slot ? $key : StateAnswer::label( $slot->label, $slot->key, $build->kit );
			$name                      = $names[ $key ] ?? '';
			$snapshot['picks'][ $key ] = array(
				'label' => $label,
				'name'  => $name,
			) + $pick;
			/* translators: 1: a slot's label, such as Trucks; 2: the part's name; 3: how many of it a build takes. */
			$told[] = sprintf( __( '%1$s: %2$s × %3$d', 'creative-skateboard-builder' ), $label, $name, $pick['quantity'] );
		}
		$item->update_meta_data( Options::ORDER_BUILD, $build->id );
		$item->update_meta_data( Options::ORDER_ROLE, Options::ROLE_KIT );
		$item->update_meta_data( Options::ORDER_SNAPSHOT, $snapshot );
		$item->update_meta_data( __( 'Assembly', 'creative-skateboard-builder' ), $build->assembly ? __( 'Yes', 'creative-skateboard-builder' ) : __( 'No', 'creative-skateboard-builder' ) );
		$item->update_meta_data( __( 'Build', 'creative-skateboard-builder' ), implode( wp_get_list_item_separator(), $told ) );
	}
}
