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
use CreativeBranding\SkateboardBuilder\Service\OrderBuilds;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WP_Error;

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
	 * @param Closure(): OrderBuilds $orders Builds the service, when an order has a build in it.
	 * @param Closure(): Kits        $kits   The shop's kits, for a slot's label and the owner's words.
	 */
	public function __construct( private Closure $orders, private Closure $kits ) {}

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
		// includes/class-wc-ajax.php:1292: a manager adds a product to an order on the order's screen. A kit added
		// there would be a kit's line with no build behind it (D-139).
		add_filter( 'woocommerce_ajax_add_order_item_validation', array( $this, 'byHand' ), 10, 2 );
	}

	/**
	 * A kit is refused on the order screen's Add products: its line means nothing without its build.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $error   WooCommerce's error, empty unless something refused the product already.
	 * @param mixed $product The product being added.
	 * @return mixed The error, with the plugin's reason on it for a kit.
	 */
	public function byHand( mixed $error, mixed $product = null ): mixed {
		if ( $error instanceof WP_Error && $product instanceof WC_Product && $product->is_type( Options::PRODUCT_TYPE_KIT ) ) {
			/* translators: %s: the kit's name. */
			$error->add( 'cbuild_kit_by_hand', sprintf( __( '%s is put together in the builder, so it cannot be added to an order here. Add its parts instead.', 'creative-skateboard-builder' ), $product->get_name() ) );
		}
		return $error;
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
		$build  = Build::read( $values[ Options::CART_BUILD ] ?? null );
		$parent = $values[ Options::CART_PARENT ] ?? null;
		if ( null === $build && ! is_string( $parent ) ) {
			return;
		}
		// Only now the cart, and as it is: a plugin may make an order's items with no cart at all, and WooCommerce
		// declares a cart where the property is null.
		$held = get_object_vars( WC() )['cart'] ?? null;
		$cart = $held instanceof WC_Cart ? $held->get_cart() : array();
		if ( null !== $build ) {
			$this->kit( $item, $build, $values, $cart );
			return;
		}
		$slot = $values[ Options::CART_SLOT ] ?? null;
		$of   = is_string( $parent ) ? Build::read( $cart[ $parent ][ Options::CART_BUILD ] ?? null ) : null;
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
		$items = array();
		$found = array();
		foreach ( $order->get_items() as $id => $item ) {
			$build = $item->get_meta( Options::ORDER_BUILD );
			if ( is_string( $build ) && '' !== $build ) {
				$found[ $id ] = $item;
				$items[ $id ] = array(
					'build'    => $build,
					'kit'      => Options::ROLE_KIT === $item->get_meta( Options::ORDER_ROLE ),
					'parent'   => Cast::int( $item->get_meta( Options::ORDER_PARENT ) ),
					'children' => $item->get_meta( Options::ORDER_CHILDREN ),
				);
			}
		}
		$ties = OrderBuilds::ties( $items );
		foreach ( $ties['parents'] as $part => $kit ) {
			$found[ $part ]->update_meta_data( Options::ORDER_PARENT, (string) $kit );
			$found[ $part ]->save();
		}
		foreach ( $ties['children'] as $kit => $children ) {
			$found[ $kit ]->update_meta_data( Options::ORDER_CHILDREN, $children );
			$found[ $kit ]->save();
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
		$kit    = ( $this->kits )()->kit( $build->kit );
		$labels = array();
		$names  = array();
		$skus   = array();
		foreach ( array_keys( $build->picks ) as $key ) {
			$slot = $kit?->slots->get( $key );
			if ( null !== $slot ) {
				$labels[ $key ] = StateAnswer::label( $slot->label, $slot->key, $build->kit );
			}
		}
		foreach ( is_array( $values[ Options::CART_CHILDREN ] ?? null ) ? $values[ Options::CART_CHILDREN ] : array() as $child ) {
			$line    = is_string( $child ) ? ( $cart[ $child ] ?? array() ) : array();
			$product = $line['data'] ?? null;
			$slot    = $line[ Options::CART_SLOT ] ?? null;
			if ( $product instanceof WC_Product && is_string( $slot ) ) {
				$names[ $slot ] = $product->get_name();
				$skus[ $slot ]  = $product->get_sku();
			}
		}
		// The owner's words on an assembled build, as the shopper read them, for the order's emails (milestone 8).
		$lead     = $kit?->assembly->leadTime ?? '';
		$notice   = $kit?->assembly->returnsNotice ?? '';
		$snapshot = ( $this->orders )()->snapshot(
			$build,
			$labels,
			$names,
			$skus,
			'' === $lead ? '' : StateAnswer::text( $lead, 'lead_time', $build->kit ),
			'' === $notice ? '' : StateAnswer::text( $notice, 'returns_notice', $build->kit )
		);
		$told     = array();
		foreach ( $snapshot['picks'] as $pick ) {
			/* translators: 1: a slot's label, such as Trucks; 2: the part's name; 3: how many of it a build takes. */
			$told[] = sprintf( __( '%1$s: %2$s × %3$s', 'creative-skateboard-builder' ), $pick['label'], $pick['name'], number_format_i18n( $pick['quantity'] ) );
		}
		$item->update_meta_data( Options::ORDER_BUILD, $build->id );
		$item->update_meta_data( Options::ORDER_ROLE, Options::ROLE_KIT );
		$item->update_meta_data( Options::ORDER_SNAPSHOT, $snapshot );
		$item->update_meta_data( __( 'Assembly', 'creative-skateboard-builder' ), $build->assembly ? __( 'Yes', 'creative-skateboard-builder' ) : __( 'No', 'creative-skateboard-builder' ) );
		$item->update_meta_data( __( 'Build', 'creative-skateboard-builder' ), implode( wp_get_list_item_separator(), $told ) );
	}
}
