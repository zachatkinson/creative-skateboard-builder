<?php
/**
 * An order's lines, as the order's builds are read from them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use CreativeBranding\SkateboardBuilder\Service\OrderLine;
use CreativeBranding\SkateboardBuilder\Service\OrderReading;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hands `Service\OrderReading` what an order's product lines say, through the order's CRUD alone, so it is the same
 * with HPOS on, off or syncing (D-138). A line's product is asked its type only where the line has no build's keys,
 * and then by WooCommerce's own type lookup, which reads no product: that is how a kit's line with no `_cbuild_id`
 * is found (D-139).
 *
 * @since 0.8.0
 */
final class OrderLines {

	/**
	 * An order's builds, read from its lines as they are now.
	 *
	 * @since 0.8.0
	 *
	 * @param WC_Order $order The order.
	 */
	public static function read( WC_Order $order ): OrderReading {
		return OrderReading::of( self::of( $order ) );
	}

	/**
	 * An order's product lines, in the order's order.
	 *
	 * @since 0.8.0
	 *
	 * @param WC_Order $order The order.
	 * @return list<OrderLine>
	 */
	public static function of( WC_Order $order ): array {
		$lines = array();
		foreach ( $order->get_items() as $id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$build    = Cast::str( $item->get_meta( Options::ORDER_BUILD ) );
			$snapshot = $item->get_meta( Options::ORDER_SNAPSHOT );
			$lines[]  = new OrderLine(
				(int) $id,
				0 === $item->get_variation_id() ? $item->get_product_id() : $item->get_variation_id(),
				(int) $item->get_quantity(),
				$item->get_name(),
				$build,
				Cast::str( $item->get_meta( Options::ORDER_ROLE ) ),
				Cast::str( $item->get_meta( Options::ORDER_SLOT ) ),
				'' === $build && $item->get_product_id() > 0 && Options::PRODUCT_TYPE_KIT === WC_Product_Factory::get_product_type( $item->get_product_id() ),
				'' === $snapshot ? null : $snapshot
			);
		}
		return $lines;
	}
}
