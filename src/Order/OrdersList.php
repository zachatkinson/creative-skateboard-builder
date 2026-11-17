<?php
/**
 * The Assembly column and the bulk print on the orders list.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The orders list, with HPOS on or off (spec: Admin, On orders): an Assembly column that says how many boards of an
 * order the shop assembles, and the bulk action Print assembly tickets, which sends the picked orders to the ticket
 * page. Both are for users with `edit_shop_orders`.
 *
 * @since 0.8.0
 */
final class OrdersList {

	/**
	 * The column's key.
	 *
	 * @since 0.8.0
	 */
	public const string COLUMN = 'cbuild_assembly';

	/**
	 * Add the Assembly column before the order's total, or at the end where there is no total.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $columns The list's columns, by key.
	 * @return array<mixed>
	 */
	public function columns( mixed $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return $columns;
		}
		$label = __( 'Assembly', 'creative-skateboard-builder' );
		$out   = array();
		foreach ( $columns as $key => $column ) {
			if ( 'order_total' === $key ) {
				$out[ self::COLUMN ] = $label;
			}
			$out[ $key ] = $column;
		}
		$out[ self::COLUMN ] ??= $label;
		return $out;
	}

	/**
	 * Fill an order's Assembly cell. The HPOS list hands over the order (src/Internal/Admin/Orders/ListTable.php:205);
	 * the posts list the post's ID (wp-admin/includes/class-wp-posts-list-table.php:1508).
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $column The column's key.
	 * @param mixed $order  The order, or its ID.
	 */
	public function cell( mixed $column, mixed $order = null ): void {
		if ( self::COLUMN !== $column || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : ( is_numeric( $order ) ? wc_get_order( (int) $order ) : false );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$reading = OrderLines::read( $order );
		echo esc_html( WorkshopWords::column( count( $reading->builds ), $reading->toAssemble() ) );
	}

	/**
	 * Add Print assembly tickets to the list's bulk actions.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $actions The list's bulk actions, by key.
	 * @return array<mixed>
	 */
	public function actions( mixed $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();
		if ( current_user_can( 'edit_shop_orders' ) ) {
			$actions[ TicketPage::ACTION ] = __( 'Print assembly tickets', 'creative-skateboard-builder' );
		}
		return $actions;
	}

	/**
	 * Send the picked orders to the ticket page. WooCommerce's HPOS list has checked its own nonce and
	 * `edit_others_shop_orders` before it asks (src/Internal/Admin/Orders/ListTable.php:1434); WordPress's posts list its
	 * nonce (wp-admin/edit.php:77). The ticket page checks its own nonce and
	 * `edit_shop_orders` again when it is opened.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $redirect Where the list would go next.
	 * @param mixed $action   The bulk action.
	 * @param mixed $ids      The picked orders' IDs.
	 * @return mixed
	 */
	public function handle( mixed $redirect, mixed $action, mixed $ids = array() ): mixed {
		if ( TicketPage::ACTION !== $action || ! current_user_can( 'edit_shop_orders' ) ) {
			return $redirect;
		}
		$picked = array();
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			if ( is_numeric( $id ) && (int) $id > 0 ) {
				$picked[] = (int) $id;
			}
		}
		return array() === $picked ? $redirect : TicketPage::url( array_values( array_unique( $picked ) ) );
	}
}
