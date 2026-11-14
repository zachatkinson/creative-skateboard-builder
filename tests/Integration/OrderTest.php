<?php
/**
 * A build on an order.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product_Simple;
use WP_Error;
use WP_REST_Request;

/**
 * Spec, Cart, checkout and orders, Orders; Data model, Order items. Roadmap, milestone 5: both checkouts write the
 * build's keys and its snapshot, the cart's keys become the order's item IDs, and WooCommerce takes each part's
 * stock.
 *
 * The suite's HPOS variants run these with orders in posts, in their own tables, and in both.
 */
final class OrderTest extends CartTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'woocommerce_ship_to_countries', 'disabled' );
		update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'yes' ) );
		WC()->payment_gateways()->init();
		// One part with a SKU, for the snapshot; the rest have none.
		$trucks = wc_get_product( $this->parts['trucks'] );
		$trucks->set_sku( 'TRK-149' );
		$trucks->save();
	}

	public function tear_down(): void {
		delete_option( 'woocommerce_ship_to_countries' );
		delete_option( 'woocommerce_cheque_settings' );
		WC()->payment_gateways()->init();
		WC()->session->set( 'store_api_draft_order', null );
		WC()->session->set( 'order_awaiting_payment', null );
		parent::tear_down();
	}

	/**
	 * Add the usual build through the Store API.
	 *
	 * @param bool $assembly Whether the shop assembles it.
	 * @param int  $quantity How many.
	 */
	private function added( bool $assembly = false, int $quantity = 1 ): void {
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
	}

	/**
	 * Place the cart's order by the classic checkout.
	 */
	private function classic(): WC_Order {
		$id = WC()->checkout()->create_order(
			array(
				'billing_first_name' => 'Sam',
				'billing_last_name'  => 'Shopper',
				'billing_email'      => 'shopper+1@example.com',
				'payment_method'     => 'cheque',
			)
		);
		self::assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : '' );
		$order = wc_get_order( $id );
		self::assertInstanceOf( WC_Order::class, $order );
		return $order;
	}

	/**
	 * Place the cart's order by the Store API's checkout, and read the order afresh. WooCommerce makes the draft
	 * when the order is placed (src/StoreApi/Routes/V1/Checkout.php:807), and keeps it when the payment is refused.
	 *
	 * @param string $gateway The payment method: `cheque` is on, and any other is refused.
	 */
	private function checkout( string $gateway = 'cheque' ): WC_Order {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$address = array(
			'first_name' => 'Sam',
			'last_name'  => 'Shopper',
			'address_1'  => '1 Test Street',
			'city'       => 'Testville',
			'state'      => 'CA',
			'postcode'   => '90210',
			'country'    => 'US',
			'email'      => 'shopper+1@example.com',
			'phone'      => '5550100',
		);
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'billing_address'  => $address,
					'shipping_address' => $address,
					'payment_method'   => $gateway,
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );
		self::assertSame( 'cheque' === $gateway ? 200 : 400, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$order = wc_get_order( 'cheque' === $gateway ? $response->get_data()['order_id'] : WC()->session->get( 'store_api_draft_order' ) );
		self::assertInstanceOf( WC_Order::class, $order );
		return $order;
	}

	/**
	 * An order's builds as the plugin's keys tell them, read from the order as it was saved.
	 *
	 * @param WC_Order $order The order.
	 * @return list<array{item: WC_Order_Item_Product, parts: array<string, WC_Order_Item_Product>}>
	 */
	private static function told( WC_Order $order ): array {
		$builds = array();
		foreach ( $order->get_items() as $id => $item ) {
			self::assertInstanceOf( WC_Order_Item_Product::class, $item );
			if ( 'kit' !== $item->get_meta( '_cbuild_role' ) ) {
				continue;
			}
			$parts = array();
			foreach ( $item->get_meta( '_cbuild_children' ) as $child ) {
				$part = $order->get_item( $child );
				self::assertInstanceOf( WC_Order_Item_Product::class, $part, 'each child is an item of the order' );
				self::assertSame( 'part', $part->get_meta( '_cbuild_role' ) );
				self::assertSame( (string) $id, $part->get_meta( '_cbuild_parent' ), 'each part names its kit\'s item' );
				self::assertSame( $item->get_meta( '_cbuild_id' ), $part->get_meta( '_cbuild_id' ) );
				$parts[ $part->get_meta( '_cbuild_slot' ) ] = $part;
			}
			$builds[] = array(
				'item'  => $item,
				'parts' => $parts,
			);
		}
		return $builds;
	}

	/**
	 * What every checkout must have written of the usual build.
	 *
	 * @param WC_Order $order    The order.
	 * @param bool     $assembly Whether the shop assembles it.
	 */
	private function assertBuild( WC_Order $order, bool $assembly ): void {
		$builds = self::told( $order );
		self::assertCount( 1, $builds );
		self::assertCount( 7, $order->get_items(), 'the kit and a line a part' );
		[ 'item' => $kit, 'parts' => $parts ] = $builds[0];
		self::assertSame( $this->kit, $kit->get_product_id() );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'bearings', 'hardware', 'grip' ), array_keys( $parts ), 'the parts in step order' );
		self::assertSame( $this->parts['trucks'], $parts['trucks']->get_product_id() );
		self::assertSame( 2, $parts['trucks']->get_quantity() );
		self::assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $kit->get_meta( '_cbuild_id' ) );

		$snapshot = $kit->get_meta( '_cbuild_snapshot' );
		self::assertSame( array( 'schema_version', 'kit', 'assembly', 'picks', 'lead_time', 'returns_notice' ), array_keys( $snapshot ) );
		// The auditor's finding 6 on milestone 5: the document kept for as long as the order is has its schema.
		$schema = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/schema/order-snapshot.json' ), true );
		self::assertIsArray( $schema );
		$valid = rest_validate_value_from_schema( $snapshot, $schema, '_cbuild_snapshot' );
		self::assertTrue( $valid, is_wp_error( $valid ) ? $valid->get_error_message() : '' );
		$wrong = $snapshot;
		unset( $wrong['picks']['deck']['label'] );
		self::assertWPError( rest_validate_value_from_schema( $wrong, $schema, '_cbuild_snapshot' ), 'and the schema refuses what is not one' );
		self::assertSame( array( '', '' ), array( $snapshot['lead_time'], $snapshot['returns_notice'] ), 'the kit has neither' );
		self::assertSame( array( 1, $this->kit, $assembly ), array( $snapshot['schema_version'], $snapshot['kit'], $snapshot['assembly'] ) );
		self::assertSame(
			array(
				'label'    => 'Trucks',
				'name'     => 'Trucks',
				'sku'      => 'TRK-149',
				'part'     => $this->parts['trucks'],
				'quantity' => 2,
			),
			array_diff_key( $snapshot['picks']['trucks'], array( 'specs' => 0 ) )
		);
		self::assertSame( '8.5000', $snapshot['picks']['trucks']['specs']['axle_width'], 'the specs as sold' );
		self::assertSame( '', $snapshot['picks']['deck']['sku'], 'a part with no SKU' );

		// Claude in Cowork's recheck, N2 and N4: the order received page, My account and the emails list a line's meta
		// through wc_display_item_meta() (includes/wc-template-functions.php:4165), where the kit's line said its
		// assembly twice, and listed the parts its own lines list below it. Its total says the assembly.
		self::assertSame( array(), $kit->get_formatted_meta_data(), 'the customer reads neither, and no key of the plugin\'s' );
		$shown = self::screen( $kit );
		self::assertSame( array( 'Assembly', 'Build' ), array_keys( $shown ), 'the order screen keeps both for the workshop' );
		// N3, as Zach answered it: the order screen words the assembly as everywhere else.
		self::assertSame( $assembly ? wc_price( 15 ) : 'not added', $shown['Assembly'], 'the kit\'s fee is $15.00' );
		self::assertSame( 'Deck: Deck × 1, Trucks: Trucks × 2, Wheels: Wheels × 1, Bearings: Bearings × 1, Hardware: Hardware × 1, Grip: Grip × 1', $shown['Build'] );
		self::assertSame( array(), $parts['deck']->get_formatted_meta_data(), 'a part shows nothing of the plugin\'s' );
		self::assertSame( array(), self::screen( $parts['deck'] ), 'nor on the order screen' );
	}

	public function test_the_classic_checkout_writes_the_build_to_the_order(): void {
		$this->added( true );
		$order = $this->classic();
		$this->assertBuild( $order, true );
		self::assertSame( '198.00', $order->get_total(), 'the parts and the fee' );
	}

	public function test_the_store_api_checkout_writes_the_build_and_ties_it_again_when_the_cart_changes(): void {
		$this->added();
		$draft = $this->checkout( 'bacs' );
		$this->assertBuild( $draft, false );
		$first = self::told( $draft )[0]['item']->get_id();

		$plain = new WC_Product_Simple();
		$plain->set_name( 'Wax' );
		$plain->set_status( 'publish' );
		$plain->set_regular_price( '3' );
		$plain->save();
		WC()->cart->add_to_cart( $plain->get_id() );

		$placed = $this->checkout();
		self::assertSame( $draft->get_id(), $placed->get_id(), 'the draft is the order placed' );
		self::assertCount( 8, $placed->get_items() );
		$this->assertSame( 1, count( self::told( $placed ) ) );
		$kit = self::told( $placed )[0]['item'];
		self::assertNotSame( $first, $kit->get_id(), 'WooCommerce made the items again, and the tie is to the new ones' );
		self::assertCount( 6, self::told( $placed )[0]['parts'] );
		foreach ( $placed->get_items() as $item ) {
			if ( $item->get_product_id() === $plain->get_id() ) {
				self::assertSame( array(), array_filter( array_map( static fn( string $key ): mixed => $item->get_meta( $key ), Options::orderItemKeys() ) ), 'a product on its own gets no key' );
			}
		}
	}

	public function test_an_assembled_builds_order_keeps_the_lead_time_and_the_returns_notice_as_the_shopper_read_them(): void {
		$kit = wc_get_product( $this->kit );
		$kit->setDocument(
			Options::META_KIT_ASSEMBLY,
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '15',
				'lead_time'      => 'Two business days',
				'returns_notice' => 'An assembled board cannot be returned.',
			)
		);
		$kit->save();
		add_filter( 'cbuild_kit_text', static fn( string $text, string $field ): string => 'lead_time' === $field ? $text . ' (shop)' : $text, 10, 2 );
		$this->added( true );
		$this->added( false );
		$builds = self::told( $this->classic() );
		remove_all_filters( 'cbuild_kit_text' );

		$assembled = $builds[0]['item']->get_meta( '_cbuild_snapshot' );
		self::assertTrue( $assembled['assembly'] );
		self::assertSame( 'Two business days (shop)', $assembled['lead_time'] );
		self::assertSame( 'An assembled board cannot be returned.', $assembled['returns_notice'] );
		$loose = $builds[1]['item']->get_meta( '_cbuild_snapshot' );
		self::assertFalse( $loose['assembly'] );
		self::assertSame( array( '', '' ), array( $loose['lead_time'], $loose['returns_notice'] ), 'neither is said of a build the shop does not assemble' );
	}

	public function test_a_kits_line_on_the_order_says_what_its_amount_is_in_place_of_a_bare_price(): void {
		// Cowork's review, should-fix 3, as Zach answered it: the order received page, the emails and My account
		// print a line's subtotal through WC_Order::get_formatted_line_subtotal().
		$this->added( true );
		$this->added( false );
		$order  = $this->classic();
		$builds = self::told( $order );
		self::assertSame( 1, preg_match( '#\AAssembly: <span class="woocommerce-Price-amount amount">.*15\.00.*</span>\z#s', $order->get_formatted_line_subtotal( $builds[0]['item'] ) ), $order->get_formatted_line_subtotal( $builds[0]['item'] ) );
		self::assertSame( 'Assembly: not added', $order->get_formatted_line_subtotal( $builds[1]['item'] ) );
		self::assertStringNotContainsString( 'Assembly', $order->get_formatted_line_subtotal( $builds[0]['parts']['trucks'] ), 'a part keeps its own' );
		self::assertSame( 1, preg_match( '#\A<span class="woocommerce-Price-amount amount">.*15\.00.*</span>\z#s', self::screen( $builds[0]['item'] )['Assembly'] ), 'and the order screen says the fee after Assembly' );
		self::assertSame( 'not added', self::screen( $builds[1]['item'] )['Assembly'] );

		$kit = wc_get_product( $this->kit );
		$kit->setDocument(
			Options::META_KIT_ASSEMBLY,
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '0',
				'lead_time'      => '',
				'returns_notice' => '',
			)
		);
		$kit->save();
		WC()->cart->empty_cart();
		$this->added( true );
		$order = $this->classic();
		self::assertSame( 'Assembly: free', $order->get_formatted_line_subtotal( self::told( $order )[0]['item'] ) );
		self::assertSame( 'free', self::screen( self::told( $order )[0]['item'] )['Assembly'] );
	}

	/**
	 * What the order screen lists of an item, by key: what includes/admin/meta-boxes/views/html-order-item-meta.php:16
	 * asks for, less the keys it hides.
	 *
	 * @param WC_Order_Item_Product $item The order item.
	 * @return array<string, string>
	 */
	private static function screen( WC_Order_Item_Product $item ): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its order screen applies it.
		$hidden = apply_filters( 'woocommerce_hidden_order_itemmeta', array() );
		$shown  = array();
		foreach ( $item->get_all_formatted_meta_data( '' ) as $meta ) {
			if ( ! in_array( $meta->key, $hidden, true ) ) {
				// Less the paragraph wpautop() puts a value in (includes/class-wc-order-item.php:374).
				$shown[ $meta->key ] = trim( (string) preg_replace( '#</?p>#', '', $meta->display_value ) );
			}
		}
		return $shown;
	}

	public function test_two_builds_on_an_order_are_each_tied_to_their_own_parts(): void {
		$this->added();
		$this->added( true, 2 );
		$builds = self::told( $this->classic() );
		self::assertCount( 2, $builds );
		self::assertNotSame( $builds[0]['item']->get_meta( '_cbuild_id' ), $builds[1]['item']->get_meta( '_cbuild_id' ) );
		self::assertSame( array(), array_intersect( $builds[0]['item']->get_meta( '_cbuild_children' ), $builds[1]['item']->get_meta( '_cbuild_children' ) ) );
		self::assertCount( 6, $builds[0]['parts'] );
		self::assertSame( 4, $builds[1]['parts']['trucks']->get_quantity(), 'two builds take four trucks' );
	}

	public function test_tying_an_order_again_changes_nothing_and_saves_nothing(): void {
		$this->added();
		$order  = $this->classic();
		$before = self::told( $order )[0]['item']->get_meta( '_cbuild_children' );
		$saves  = 0;
		$count  = static function () use ( &$saves ): void {
			++$saves;
		};
		add_action( 'woocommerce_before_order_item_object_save', $count );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, fired as its checkout fires it.
		do_action( 'woocommerce_checkout_order_created', wc_get_order( $order->get_id() ) );
		remove_action( 'woocommerce_before_order_item_object_save', $count );
		self::assertSame( 0, $saves );
		self::assertSame( $before, self::told( wc_get_order( $order->get_id() ) )[0]['item']->get_meta( '_cbuild_children' ) );
	}

	public function test_a_part_is_named_with_its_slot_wherever_the_order_lists_it(): void {
		$this->added();
		$build = self::told( $this->classic() )[0];
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its templates apply it.
		self::assertSame( 'Grip: <a href="#">Grip</a>', apply_filters( 'woocommerce_order_item_name', '<a href="#">Grip</a>', $build['parts']['grip'], true ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		self::assertSame( 'Complete', apply_filters( 'woocommerce_order_item_name', 'Complete', $build['item'], true ), 'the kit keeps its name' );
	}

	public function test_woocommerce_takes_each_parts_stock_and_none_of_the_kits(): void {
		$trucks = wc_get_product( $this->parts['trucks'] );
		$trucks->set_manage_stock( true );
		$trucks->set_stock_quantity( 10 );
		$trucks->save();
		$this->added( false, 2 );
		$order = $this->classic();
		$stock = fn(): ?int => wc_get_product( $this->parts['trucks'] )->get_stock_quantity();
		$order->update_status( 'on-hold' );
		self::assertSame( 6, $stock(), 'two builds take four trucks' );
		self::assertFalse( wc_get_product( $this->kit )->managing_stock() );

		$order->update_status( 'failed' );
		self::assertSame( 10, $stock(), 'a failed order gives them back' );

		$order->update_status( 'processing' );
		self::assertSame( 6, $stock() );
		$trucks = self::told( wc_get_order( $order->get_id() ) )[0]['parts']['trucks'];
		$refund = wc_create_refund(
			array(
				'order_id'      => $order->get_id(),
				'amount'        => '54',
				'restock_items' => true,
				'line_items'    => array(
					$trucks->get_id() => array(
						'qty'          => 2,
						'refund_total' => '54',
					),
				),
			)
		);
		self::assertNotWPError( $refund );
		self::assertSame( 8, $stock(), 'a refund of one build\'s trucks restocks two' );
	}

	// Claude in Cowork's review of milestone 5, S3: WooCommerce works an order's coupons out again when a manager
	// adds or removes one (includes/abstracts/abstract-wc-order.php:1705, :1915 and :1950), and asks the same
	// filter with the order's item where the cart gave its line (includes/class-wc-discounts.php:115).
	public function test_a_coupon_worked_out_again_on_the_order_still_follows_the_kit_to_its_parts(): void {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'kitonly' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->set_product_ids( array( $this->kit ) );
		$coupon->save();
		$this->added( true );
		self::assertTrue( WC()->cart->apply_coupon( 'kitonly' ) );
		WC()->cart->calculate_totals();
		$order  = $this->classic();
		$shares = static function ( WC_Order $order ): array {
			$shares = array();
			foreach ( $order->get_items() as $item ) {
				$shares[ $item->get_name() ] = number_format( (float) $item->get_subtotal() - (float) $item->get_total(), 2 );
			}
			return $shares;
		};
		$placed = $shares( $order );
		self::assertSame( '19.80', number_format( (float) $order->get_discount_total(), 2 ), 'a tenth of the 183 of parts and the 15 of assembly' );
		self::assertSame( '7.00', $placed['Deck'] );

		$order->recalculate_coupons();
		self::assertSame( $placed, $shares( $order ), 'each line keeps its share' );
		self::assertSame( '19.80', number_format( (float) $order->get_discount_total(), 2 ) );

		// A part the coupon leaves out by name stays out on the order as in the cart (D-137).
		$coupon->set_excluded_product_ids( array( $this->parts['deck'] ) );
		$coupon->save();
		$order->recalculate_coupons();
		self::assertSame( '0.00', $shares( $order )['Deck'] );
		self::assertSame( '12.80', number_format( (float) $order->get_discount_total(), 2 ) );
	}

	public function test_a_kit_cannot_be_added_to_an_order_by_hand_and_any_other_product_can(): void {
		$order = wc_create_order();
		$kit   = wc_get_product( $this->kit );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its order screen applies it.
		$error = apply_filters( 'woocommerce_ajax_add_order_item_validation', new WP_Error(), $kit, $order, 1 );
		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'cbuild_kit_by_hand', $error->get_error_code() );
		self::assertSame( 'Complete is put together in the builder, so it cannot be added to an order here. Add its parts instead.', $error->get_error_message() );

		$deck = wc_get_product( $this->picks()['deck'] );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its order screen applies it.
		$other = apply_filters( 'woocommerce_ajax_add_order_item_validation', new WP_Error(), $deck, $order, 1 );
		self::assertInstanceOf( WP_Error::class, $other );
		self::assertSame( '', $other->get_error_code() );

		// Another plugin's refusal, made first, stands.
		$first = new WP_Error( 'theirs', 'No.' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its order screen applies it.
		self::assertSame( array( 'theirs', 'cbuild_kit_by_hand' ), apply_filters( 'woocommerce_ajax_add_order_item_validation', $first, $kit, $order, 1 )->get_error_codes() );
	}

	public function test_the_order_screen_neither_lists_nor_edits_the_plugins_keys(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its order screen applies it.
		$hidden = apply_filters( 'woocommerce_hidden_order_itemmeta', array( '_qty' ) );
		self::assertSame( array( '_qty', '_cbuild_id', '_cbuild_role', '_cbuild_parent', '_cbuild_children', '_cbuild_slot', '_cbuild_snapshot' ), $hidden );
	}
}
