<?php
/**
 * The workshop's screens: the Build box, the assembly ticket, the bulk print and the Assembly column.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;
use CreativeBranding\SkateboardBuilder\Order\BuildBox;
use CreativeBranding\SkateboardBuilder\Order\OrdersList;
use CreativeBranding\SkateboardBuilder\Order\TicketPage;
use CreativeBranding\SkateboardBuilder\Order\WorkshopHooks;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Order;
use WC_Order_Item_Product;
use WP_Post;

/**
 * Spec, Admin, On orders; Pricing, stock, assembly and returns, Assembly and Returns. A user with `edit_shop_orders`
 * sees each build on the order by slot as sold, with the mismatch said where the lines have changed, prints the
 * assembly ticket behind a nonce, prints many from the orders list, and reads the Assembly column.
 *
 * The suite's HPOS variants run these with orders in posts, in their own tables, and in both.
 */
final class WorkshopTest extends CartTestCase {

	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		$this->service( WorkshopHooks::class )->register();
		update_option( 'woocommerce_ship_to_countries', 'disabled' );
		update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'yes' ) );
		WC()->payment_gateways()->init();
		$trucks = wc_get_product( $this->parts['trucks'] );
		$trucks->set_sku( 'TRK-149' );
		$trucks->save();
	}

	public function tear_down(): void {
		global $wp_meta_boxes;
		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress's own, emptied between tests.
		unset( $_GET['orders'], $_GET['more'], $_GET['_wpnonce'] );
		delete_option( 'woocommerce_ship_to_countries' );
		delete_option( 'woocommerce_cheque_settings' );
		WC()->payment_gateways()->init();
		wp_dequeue_style( BuildBox::STYLE );
		wp_deregister_style( BuildBox::STYLE );
		wp_deregister_style( TicketPage::HANDLE );
		wp_deregister_script( TicketPage::HANDLE );
		parent::tear_down();
	}

	/**
	 * Sign in as a user with a role.
	 *
	 * @param string $role The role.
	 */
	private function as( string $role ): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Place an order for the usual build by the classic checkout.
	 *
	 * @param bool $assembly Whether the shop assembles it.
	 * @param int  $quantity How many boards.
	 */
	private function ordered( bool $assembly = true, int $quantity = 1 ): WC_Order {
		$response = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => $quantity,
				'cbuild'   => array(
					'picks'    => $this->picks(),
					'assembly' => $assembly,
				),
			)
		);
		self::assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$id = WC()->checkout()->create_order(
			array(
				'billing_first_name' => 'Sam',
				'billing_last_name'  => 'Shopper',
				'billing_email'      => 'shopper+1@example.com',
				'payment_method'     => 'cheque',
				'order_comments'     => 'Tight trucks, please.',
			)
		);
		self::assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : '' );
		WC()->cart->empty_cart();
		$order = wc_get_order( $id );
		self::assertInstanceOf( WC_Order::class, $order );
		return $order;
	}

	/**
	 * What the order's screen hands to `add_meta_boxes` with HPOS on or off: the order, or its post.
	 *
	 * @param WC_Order $order The order.
	 * @return WC_Order|WP_Post
	 */
	private static function subject( WC_Order $order ): WC_Order|WP_Post {
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return $order;
		}
		$post = get_post( $order->get_id() );
		self::assertInstanceOf( WP_Post::class, $post );
		return $post;
	}

	/**
	 * Run `add_meta_boxes` as the order's screen does, and say whether the Build box was added.
	 *
	 * @param WC_Order $order The order.
	 */
	private static function added( WC_Order $order ): bool {
		global $wp_meta_boxes;
		$screen = wc_get_page_screen_id( 'shop-order' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, as the order's screen fires it.
		do_action( 'add_meta_boxes', $screen, self::subject( $order ) );
		return isset( $wp_meta_boxes[ $screen ]['normal']['default'][ BuildBox::ID ] );
	}

	/**
	 * The Build box as the order's screen draws it.
	 *
	 * @param WC_Order $order The order.
	 */
	private static function box( WC_Order $order ): string {
		global $wp_meta_boxes;
		self::assertTrue( self::added( $order ) );
		$box = $wp_meta_boxes[ wc_get_page_screen_id( 'shop-order' ) ]['normal']['default'][ BuildBox::ID ];
		self::assertIsCallable( $box['callback'] );
		ob_start();
		call_user_func( $box['callback'], self::subject( $order ), $box );
		return (string) ob_get_clean();
	}

	/**
	 * The ticket page for some orders, as admin-post.php runs it.
	 *
	 * @param list<int> $orders The orders' IDs.
	 * @param bool      $nonce  Whether the address carries the page's nonce.
	 */
	private static function tickets( array $orders, bool $nonce = true ): string {
		$_GET['orders'] = implode( ',', $orders );
		if ( $nonce ) {
			$_GET['_wpnonce'] = wp_create_nonce( TicketPage::ACTION );
		}
		ob_start();
		try {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, as admin-post.php fires it.
			do_action( 'admin_post_' . TicketPage::ACTION );
		} finally {
			$page = (string) ob_get_clean();
		}
		return $page;
	}

	/**
	 * A part's line on the order.
	 *
	 * @param WC_Order $order The order.
	 * @param string   $slot  The part's slot.
	 */
	private static function line( WC_Order $order, string $slot ): WC_Order_Item_Product {
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product && $slot === $item->get_meta( Options::ORDER_SLOT ) ) {
				return $item;
			}
		}
		self::fail( 'No ' . $slot . ' line on the order.' );
	}

	public function test_the_build_box_is_on_an_order_with_a_build_for_who_can_edit_orders(): void {
		$order = $this->ordered();

		$this->as( 'customer' );
		self::assertFalse( self::added( $order ), 'not for who cannot edit orders' );

		$this->as( 'shop_manager' );
		self::assertTrue( self::added( $order ) );
		self::assertTrue( wp_style_is( BuildBox::STYLE, 'enqueued' ) );
	}

	public function test_the_build_box_is_not_on_an_order_with_no_build_or_on_another_screen(): void {
		$this->as( 'shop_manager' );
		$plain = wc_create_order();
		$plain->add_product( wc_get_product( $this->parts['deck'] ), 1 );
		$plain->save();
		self::assertFalse( self::added( $plain ) );

		$order = $this->ordered();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own.
		do_action( 'add_meta_boxes', 'product', get_post( $this->kit ) );
		global $wp_meta_boxes;
		self::assertArrayNotHasKey( 'product', $wp_meta_boxes ?? array() );
		self::assertTrue( self::added( $order ) );
	}

	/**
	 * The deck drawn: a layer image of its own, so the build's picture has something to lay out.
	 */
	private function drawnDeck(): void {
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) \CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles::attachment( 420, 1626 ) );
		$deck->save();
		$this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->forget();
	}

	public function test_the_build_box_lists_each_slot_as_sold_with_the_picture_the_refund_help_and_the_ticket(): void {
		$this->as( 'shop_manager' );
		$this->drawnDeck();
		$order = $this->ordered();
		$box   = self::box( $order );
		self::assertStringContainsString( 'class="cbuild-order__board"', $box, 'the layered picture' );
		self::assertStringContainsString( 'cbuild-order__layer--deck', $box );

		self::assertStringContainsString( '<h3 id="cbuild-order-build-0">Complete</h3>', $box );
		self::assertStringContainsString( '1 board, assembled by the shop.', $box );
		self::assertMatchesRegularExpression( '#<th scope="row">Trucks</th>\s*<td>Trucks</td>\s*<td>TRK-149</td>#', $box );
		self::assertStringContainsString( 'Deck width: ', $box );
		self::assertStringNotContainsString( 'cbuild-order__mismatch', $box, 'the lines match the build' );
		self::assertStringContainsString( 'Restock refunded items covers a whole refund.', $box );
		self::assertStringContainsString( 'Print assembly ticket', $box );
		self::assertStringContainsString( '(opens in a new tab)', $box );
		self::assertStringContainsString( 'action=' . TicketPage::ACTION, $box );
		self::assertStringContainsString( 'orders=' . $order->get_id(), $box );
	}

	public function test_a_build_the_shop_does_not_assemble_has_no_refund_help(): void {
		$this->as( 'shop_manager' );
		$box = self::box( $this->ordered( false ) );

		self::assertStringContainsString( '1 board, not assembled. The parts ship as they are.', $box );
		self::assertStringNotContainsString( 'Restock refunded items', $box );
	}

	public function test_the_build_box_says_where_the_lines_no_longer_match_the_build(): void {
		$this->as( 'shop_manager' );
		$order = $this->ordered();
		$order->remove_item( self::line( $order, 'grip' )->get_id() );
		$wheels = self::line( $order, 'wheels' );
		$wheels->set_quantity( 2 );
		$wheels->save();
		$order->save();
		$order = wc_get_order( $order->get_id() );
		self::assertInstanceOf( WC_Order::class, $order );

		$box = self::box( $order );

		self::assertStringContainsString( 'cbuild-order__mismatch', $box );
		self::assertStringContainsString( 'The order&#039;s lines no longer match the build.', $box );
		self::assertStringContainsString( 'The Grip line is no longer on the order.', $box );
		self::assertStringContainsString( 'The Wheels line has 2, and the build needs 1.', $box );
	}

	public function test_a_line_copied_without_its_keys_is_said(): void {
		$this->as( 'shop_manager' );
		$order = $this->ordered();
		$order->remove_item( self::line( $order, 'deck' )->get_id() );
		$order->add_product( wc_get_product( $this->parts['deck'] ), 1 );
		$order->save();
		$order = wc_get_order( $order->get_id() );
		self::assertInstanceOf( WC_Order::class, $order );

		self::assertStringContainsString( 'was added again without the build&#039;s details', self::box( $order ) );
	}

	public function test_the_ticket_prints_the_order_the_customer_the_parts_the_note_and_a_tick_box_per_step(): void {
		$this->as( 'shop_manager' );
		$this->drawnDeck();
		$order = $this->ordered( true, 2 );
		$page  = self::tickets( array( $order->get_id() ) );
		self::assertSame( 2, substr_count( $page, 'cbuild-order__layer--deck' ), 'the layered picture on each board\'s page' );
		self::assertStringNotContainsString( 'loading="lazy"', $page, 'a page to print loads every image before it prints' );

		self::assertStringContainsString( '<title>Assembly tickets</title>', $page );
		self::assertStringContainsString( 'Order ' . $order->get_order_number(), $page );
		self::assertStringContainsString( 'Sam Shopper', $page );
		self::assertStringContainsString( 'TRK-149', $page );
		self::assertStringContainsString( 'Tight trucks, please.', $page );
		self::assertStringContainsString( 'Board 1 of 2', $page );
		self::assertStringContainsString( 'Board 2 of 2', $page );
		self::assertSame( 2, substr_count( $page, '<article class="cbuild-ticket"' ), 'one board to a page' );
		self::assertStringContainsString( 'Bolt the trucks to the deck.', $page );
		self::assertStringContainsString( '<input type="checkbox" id="cbuild-ticket-1-step-0">', $page );
		self::assertStringContainsString( '<label for="cbuild-ticket-1-step-0">Lay the grip tape on the deck', $page );
		self::assertStringContainsString( 'break-after:page', $page );
		self::assertStringContainsString( 'window.print()', $page );
	}

	public function test_the_ticket_filters_change_its_specs_and_steps(): void {
		$this->as( 'shop_manager' );
		$order = $this->ordered();
		$specs = static fn( array $keys, string $slot ): array => 'deck' === $slot ? array( 'deck_length' ) : $keys;
		$steps = static fn( array $listed ): array => array( ...$listed, 'Wax the <b>rails</b>.' );
		add_filter( 'cbuild_ticket_specs', $specs, 10, 2 );
		add_filter( 'cbuild_ticket_steps', $steps );

		$page = self::tickets( array( $order->get_id() ) );

		self::assertStringContainsString( 'Deck length: ', $page );
		self::assertStringNotContainsString( 'Deck width: ', $page );
		self::assertStringContainsString( '>Wax the rails.</label>', $page );
	}

	public function test_the_ticket_needs_the_capability_and_its_nonce(): void {
		$order = $this->ordered();

		$this->as( 'customer' );
		try {
			self::tickets( array( $order->get_id() ) );
			self::fail( 'The ticket printed for who cannot edit orders.' );
		} catch ( \WPDieException $stopped ) {
			self::assertStringContainsString( 'not allowed', $stopped->getMessage() );
		}

		$this->as( 'shop_manager' );
		try {
			self::tickets( array( $order->get_id() ), false );
			self::fail( 'The ticket printed without its nonce.' );
		} catch ( \WPDieException $stopped ) {
			self::assertStringContainsString( 'expired', $stopped->getMessage() );
		}
	}

	/**
	 * A role that may edit orders but not other people's (WorkshopHooks supports one on purpose) prints none it could
	 * not open: WooCommerce asks per order (src/Internal/Admin/Orders/PageController.php:67).
	 */
	public function test_the_ticket_prints_no_order_its_user_could_not_open(): void {
		$order = $this->ordered();
		add_role( 'cbuild_order_clerk', 'Order clerk', array( 'read' => true, 'edit_shop_orders' => true ) );
		try {
			$this->as( 'cbuild_order_clerk' );
			self::assertTrue( current_user_can( 'edit_shop_orders' ) );
			self::assertFalse( current_user_can( get_post_type_object( 'shop_order' )->cap->edit_post, $order->get_id() ), 'WooCommerce would not open it for them' );
			$page = self::tickets( array( $order->get_id() ) );
			self::assertSame( 0, substr_count( $page, '<article class="cbuild-ticket"' ), 'nothing of the order printed' );
			self::assertStringNotContainsString( 'Sam Shopper', $page );
			self::assertStringNotContainsString( 'Tight trucks', $page );
		} finally {
			remove_role( 'cbuild_order_clerk' );
		}
	}

	public function test_the_ticket_says_which_orders_have_no_build_and_prints_the_rest(): void {
		$this->as( 'shop_manager' );
		$order = $this->ordered();
		$plain = wc_create_order();
		$plain->save();

		$page = self::tickets( array( $plain->get_id(), $order->get_id(), 999999 ) );

		self::assertSame( 1, substr_count( $page, '<article class="cbuild-ticket"' ) );
		self::assertStringContainsString( 'have no build to print.', $page );
		self::assertStringContainsString( $plain->get_order_number(), $page );
	}

	public function test_the_orders_lists_offer_the_bulk_print_and_send_the_picked_orders_to_the_ticket(): void {
		$order = $this->ordered();
		$this->as( 'shop_manager' );
		foreach ( array( 'woocommerce_page_wc-orders', 'admin_page_wc-orders', 'edit-shop_order' ) as $screen ) {
			$actions = apply_filters( "bulk_actions-{$screen}", array( 'trash' => 'Move to Trash' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound,WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress's own.
			self::assertSame( 'Print assembly tickets', $actions[ TicketPage::ACTION ] ?? '', $screen );

			$sent = apply_filters( "handle_bulk_actions-{$screen}", 'https://shop.test/list', TicketPage::ACTION, array( $order->get_id(), '7' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound,WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress's own.
			self::assertIsString( $sent );
			self::assertStringStartsWith( admin_url( 'admin-post.php' ), $sent );
			parse_str( (string) wp_parse_url( $sent, PHP_URL_QUERY ), $query );
			self::assertSame( TicketPage::ACTION, $query['action'] ?? '' );
			self::assertSame( $order->get_id() . ',7', $query['orders'] ?? '' );
			self::assertSame( 1, wp_verify_nonce( is_string( $query['_wpnonce'] ?? null ) ? $query['_wpnonce'] : '', TicketPage::ACTION ) );

			$other = apply_filters( "handle_bulk_actions-{$screen}", 'https://shop.test/list', 'trash', array( $order->get_id() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound,WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress's own.
			self::assertSame( 'https://shop.test/list', $other, 'another bulk action is left alone' );
		}

		$this->as( 'customer' );
		self::assertArrayNotHasKey( TicketPage::ACTION, apply_filters( 'bulk_actions-edit-shop_order', array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress's own.
		self::assertSame( 'https://shop.test/list', apply_filters( 'handle_bulk_actions-edit-shop_order', 'https://shop.test/list', TicketPage::ACTION, array( $order->get_id() ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress's own.
	}

	public function test_the_bulk_print_sends_the_first_hundred_and_says_how_many_more(): void {
		$this->as( 'shop_manager' );
		$sent = apply_filters( 'handle_bulk_actions-edit-shop_order', '', TicketPage::ACTION, range( 1, 102 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress's own.
		self::assertIsString( $sent );
		parse_str( (string) wp_parse_url( $sent, PHP_URL_QUERY ), $query );

		self::assertSame( implode( ',', range( 1, 100 ) ), $query['orders'] ?? '' );
		self::assertSame( '2', $query['more'] ?? '' );
	}

	public function test_the_assembly_column_is_on_both_lists_and_counts_the_boards_to_assemble(): void {
		$this->as( 'shop_manager' );
		$assembled = $this->ordered( true, 2 );
		$loose     = $this->ordered( false );
		$plain     = wc_create_order();
		$plain->save();

		$columns = array(
			'order_number' => 'Order',
			'order_total'  => 'Total',
		);
		foreach ( array( 'woocommerce_page_wc-orders', 'admin_page_wc-orders', 'edit-shop_order' ) as $screen ) {
			$shown = apply_filters( "manage_{$screen}_columns", $columns ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WordPress's own.
			self::assertSame( array( 'order_number', OrdersList::COLUMN, 'order_total' ), array_keys( $shown ), $screen );
			self::assertSame( 'Assembly', $shown[ OrdersList::COLUMN ] );
		}

		$cell = static function ( string $hook, mixed $order ): string {
			ob_start();
			do_action( $hook, OrdersList::COLUMN, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WordPress's own.
			return (string) ob_get_clean();
		};
		self::assertSame( '2 boards to assemble', $cell( 'manage_woocommerce_page_wc-orders_custom_column', $assembled ) );
		self::assertSame( '2 boards to assemble', $cell( 'manage_admin_page_wc-orders_custom_column', $assembled ) );
		self::assertSame( '2 boards to assemble', $cell( 'manage_shop_order_posts_custom_column', $assembled->get_id() ) );
		self::assertSame( 'Not assembled', $cell( 'manage_shop_order_posts_custom_column', $loose->get_id() ) );
		self::assertSame( '', $cell( 'manage_shop_order_posts_custom_column', $plain->get_id() ) );

		$this->as( 'customer' );
		self::assertArrayNotHasKey( OrdersList::COLUMN, apply_filters( 'manage_edit-shop_order_columns', $columns ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own.
		self::assertSame( '', $cell( 'manage_shop_order_posts_custom_column', $assembled->get_id() ) );
	}
}
