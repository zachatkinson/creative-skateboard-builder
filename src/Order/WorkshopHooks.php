<?php
/**
 * The workshop's hooks on the order screens.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ties the Build box, the assembly ticket, the Assembly column and the bulk print to WordPress and WooCommerce
 * (spec: Admin, On orders). It holds no logic; each hook hands over to its piece, built when first needed.
 *
 * @since 0.8.0
 */
final class WorkshopHooks implements Hookable {

	/**
	 * The orders list's screens: WooCommerce's with HPOS on, under its menu for a user with `edit_others_shop_orders`
	 * and outside it for one without (includes/admin/wc-admin-functions.php:82), and WordPress's posts list with HPOS
	 * off.
	 */
	private const array LISTS = array( 'woocommerce_page_wc-orders', 'admin_page_wc-orders', 'edit-shop_order' );

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param Closure(): BuildBox   $box     Builds the Build box.
	 * @param Closure(): TicketPage $tickets Builds the ticket page.
	 * @param Closure(): OrdersList $orders  Builds the orders list's column and bulk action.
	 */
	public function __construct( private Closure $box, private Closure $tickets, private Closure $orders ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.8.0
	 */
	public function register(): void {
		// src/Internal/Admin/Orders/Edit.php:170 with HPOS on; wp-admin/includes/meta-boxes.php:1692 with it off.
		add_action( 'add_meta_boxes', array( $this, 'box' ), 30, 2 );
		// wp-admin/admin-post.php:82.
		add_action( 'admin_post_' . TicketPage::ACTION, array( $this, 'tickets' ) );
		foreach ( self::LISTS as $screen ) {
			// src/Internal/Admin/Orders/ListTable.php:112 with HPOS on; wp-admin/includes/screen.php:37 with it off.
			add_filter( "manage_{$screen}_columns", array( $this, 'columns' ), 20 );
			// wp-admin/includes/class-wp-list-table.php:598.
			add_filter( "bulk_actions-{$screen}", array( $this, 'actions' ) );
			// src/Internal/Admin/Orders/ListTable.php:1522 with HPOS on; wp-admin/edit.php:222 with it off.
			add_filter( "handle_bulk_actions-{$screen}", array( $this, 'handle' ), 10, 3 );
		}
		// src/Internal/Admin/Orders/ListTable.php:205, which hands over the order.
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'cell' ), 10, 2 );
		add_action( 'manage_admin_page_wc-orders_custom_column', array( $this, 'cell' ), 10, 2 );
		// wp-admin/includes/class-wp-posts-list-table.php:1508, which hands over the post's ID.
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'cell' ), 10, 2 );
	}

	/**
	 * The Build box.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $screen The screen's ID, or the post type.
	 * @param mixed $subject The order, or its post.
	 */
	public function box( mixed $screen, mixed $subject = null ): void {
		( $this->box )()->add( $screen, $subject );
	}

	/**
	 * The ticket page. admin-post.php ends the request once its action has run.
	 *
	 * @since 0.8.0
	 */
	public function tickets(): void {
		( $this->tickets )()->print();
	}

	/**
	 * The Assembly column.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $columns The list's columns.
	 * @return array<mixed>
	 */
	public function columns( mixed $columns ): array {
		return ( $this->orders )()->columns( $columns );
	}

	/**
	 * An order's Assembly cell.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $column The column's key.
	 * @param mixed $order  The order, or its ID.
	 */
	public function cell( mixed $column, mixed $order = null ): void {
		( $this->orders )()->cell( $column, $order );
	}

	/**
	 * The bulk action.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $actions The list's bulk actions.
	 * @return array<mixed>
	 */
	public function actions( mixed $actions ): array {
		return ( $this->orders )()->actions( $actions );
	}

	/**
	 * The bulk action, done.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $redirect Where the list would go next.
	 * @param mixed $action   The bulk action.
	 * @param mixed $ids      The picked orders' IDs.
	 * @return mixed
	 */
	public function handle( mixed $redirect, mixed $action, mixed $ids = array() ): mixed {
		return ( $this->orders )()->handle( $redirect, $action, $ids );
	}
}
