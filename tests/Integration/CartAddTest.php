<?php
/**
 * A build added to the cart, by both routes.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use Closure;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use RuntimeException;
use WC_AJAX;
use WC_Product_Simple;

/**
 * Spec, Cart, checkout and orders, Adding a build: the same three steps whichever way it arrives, the Store API
 * or the classic form. Roadmap, milestone 5: a bare kit is refused. Issue #26: the cart reads a picked part from
 * WooCommerce itself.
 */
final class CartAddTest extends CartTestCase {

	public function test_the_store_api_adds_the_kit_with_its_build_and_a_line_for_each_part(): void {
		$response = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => 2,
				'cbuild'   => array(
					'picks'    => $this->picks(),
					'assembly' => true,
				),
			)
		);
		self::assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		$builds = self::builds();
		self::assertCount( 1, $builds );
		self::assertCount( 7, WC()->cart->get_cart(), 'the kit and six parts' );
		self::assertSame( $this->kit, $builds[0]['build']->kit );
		self::assertSame( $this->picks(), $builds[0]['build']->picks );
		self::assertTrue( $builds[0]['build']->assembly );
		self::assertTrue( wp_is_uuid( $builds[0]['build']->id, 4 ) );
		self::assertSame( 2, $builds[0]['quantity'] );
		self::assertSame( '15.00', $builds[0]['price'], 'the assembly fee' );
		$expected = array();
		foreach ( $this->picks() as $slot => $part ) {
			$expected[ $slot ] = array( $part, 2 * $builds[0]['build']->quantities[ $slot ] );
		}
		self::assertSame( $expected, $builds[0]['parts'], 'each part at what two builds take of it' );
		self::assertSame( 2, $builds[0]['build']->quantities['trucks'], 'trucks sold singly: a build takes a pair' );

		WC()->cart->calculate_totals();
		self::assertSame( 2 * ( 70 + 2 * 27 + 32 + 15 + 4 + 8 + 15 ), (int) WC()->cart->get_subtotal(), "each part at the shop's own price, and the fee" );
	}

	public function test_the_form_adds_the_same_build_with_the_kits_default_for_assembly(): void {
		$this->form( array( 'picks' => array_map( 'strval', $this->picks() ) ) );
		self::assertSame( array(), self::errors() );

		$builds = self::builds();
		self::assertCount( 1, $builds );
		self::assertCount( 7, WC()->cart->get_cart() );
		self::assertSame( $this->picks(), $builds[0]['build']->picks );
		self::assertFalse( $builds[0]['build']->assembly );
		self::assertSame( '0.00', $builds[0]['price'], 'no assembly, no fee' );
		self::assertSame( array_keys( $this->picks() ), array_keys( $builds[0]['parts'] ) );
		self::assertNotSame( array(), wc_get_notices( 'success' ), "WooCommerce's own message that it was added" );
	}

	public function test_two_builds_alike_are_two_lines_each_with_parts_of_its_own(): void {
		$body = array(
			'id'     => $this->kit,
			'cbuild' => array( 'picks' => $this->picks() ),
		);
		self::assertSame( 201, $this->store( $body )->get_status() );
		self::assertSame( 201, $this->store( $body )->get_status() );
		$builds = self::builds();
		self::assertCount( 2, $builds );
		self::assertCount( 14, WC()->cart->get_cart() );
		self::assertNotSame( $builds[0]['build']->id, $builds[1]['build']->id );
		self::assertSame( array( 1, 1 ), array_column( $builds, 'quantity' ) );
	}

	public function test_edit_builds_add_takes_the_place_of_the_build_it_came_from_by_both_routes(): void {
		$add = fn( array $build ) => $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => $build,
			)
		);
		self::assertSame( 201, $add( array( 'picks' => $this->picks() ) )->get_status() );
		self::assertSame( 201, $add( array( 'picks' => $this->picks() ) )->get_status() );
		[ $first, $second ] = array_column( self::builds(), 'build' );

		$response = $add(
			array(
				'picks'    => $this->picks(),
				'assembly' => true,
				'replace'  => $first->id,
			)
		);
		self::assertSame( 201, $response->get_status() );
		$builds = array_column( self::builds(), 'build' );
		self::assertCount( 2, $builds, 'the first is gone, its parts with it' );
		self::assertCount( 14, WC()->cart->get_cart() );
		self::assertSame( $second->id, $builds[0]->id, 'the other build stays' );
		self::assertTrue( $builds[1]->assembly, 'and the edited one is in its place' );
		self::assertSame( 14, count( $response->get_data()['items'] ), 'the Store API\'s answer is the cart after the swap' );

		$this->form(
			array(
				'picks'   => $this->picks(),
				'replace' => $builds[1]->id,
			)
		);
		$after = array_column( self::builds(), 'build' );
		self::assertCount( 2, $after, 'the form replaces as the Store API does' );
		self::assertFalse( $after[1]->assembly );
		self::assertNotSame( $builds[1]->id, $after[1]->id );
	}

	public function test_an_edited_build_keeps_the_quantity_the_cart_held_of_the_one_it_replaces_by_both_routes(): void {
		$body = array(
			'id'     => $this->kit,
			'cbuild' => array( 'picks' => $this->picks() ),
		);
		self::assertSame( 201, $this->store( $body )->get_status() );
		$old = self::builds()[0];
		WC()->cart->set_quantity( $old['key'], 3 );

		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array(
					'picks'    => $this->picks(),
					'assembly' => true,
					'replace'  => $old['build']->id,
				),
			)
		);
		self::assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$builds = self::builds();
		self::assertCount( 1, $builds );
		self::assertSame( 3, $builds[0]['quantity'], 'the review sends 1; the cart held 3, and keeps 3 (D-157)' );
		foreach ( $builds[0]['parts'] as $slot => [ , $quantity ] ) {
			self::assertSame( 3 * $builds[0]['build']->quantities[ $slot ], $quantity, "each part at what three builds take of it: $slot" );
		}

		$this->form(
			array(
				'picks'   => $this->picks(),
				'replace' => $builds[0]['build']->id,
			)
		);
		self::assertSame( array(), self::errors() );
		$after = self::builds();
		self::assertCount( 1, $after );
		self::assertSame( 3, $after[0]['quantity'], 'the form keeps it as the Store API does' );
	}

	public function test_an_edited_build_whose_parts_the_old_one_holds_the_last_of_is_taken(): void {
		self::assertSame(
			201,
			$this->store(
				array(
					'id'     => $this->kit,
					'cbuild' => array( 'picks' => $this->picks() ),
				)
			)->get_status()
		);
		$old = self::builds()[0];
		WC()->cart->set_quantity( $old['key'], 2 );
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->set_manage_stock( true );
		$deck->set_stock_quantity( 2 );
		$deck->save();

		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array(
					'picks'    => $this->picks(),
					'assembly' => true,
					'replace'  => $old['build']->id,
				),
			)
		);
		self::assertSame( 201, $response->get_status(), 'the two decks the old build holds are the new one\'s once it goes: ' . wp_json_encode( $response->get_data() ) );
		$builds = self::builds();
		self::assertCount( 1, $builds );
		self::assertTrue( $builds[0]['build']->assembly );
		self::assertSame( 2, $builds[0]['quantity'] );
	}

	public function test_an_edited_build_with_a_part_woocommerce_will_not_add_puts_the_old_one_back_whole(): void {
		self::assertSame(
			201,
			$this->store(
				array(
					'id'     => $this->kit,
					'cbuild' => array( 'picks' => $this->picks() ),
				)
			)->get_status()
		);
		$old = self::builds()[0];
		WC()->cart->set_quantity( $old['key'], 2 );
		$before = self::builds();
		$wheels = $this->parts['wheels'];
		add_action(
			'woocommerce_add_to_cart',
			static function () use ( $wheels ): void {
				add_filter( 'woocommerce_is_purchasable', static fn( $purchasable, $product ) => $wheels === $product->get_id() ? false : $purchasable, 99, 2 );
			},
			5
		);

		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array(
					'picks'   => $this->picks(),
					'replace' => $old['build']->id,
				),
			)
		);
		remove_all_filters( 'woocommerce_is_purchasable', 99 );
		self::assertSame( 'cbuild_not_added', $response->get_data()['code'] ?? null );
		self::assertEquals( $before, self::builds(), 'the old build, its parts and its quantity, as they were' );
	}

	public function test_an_edited_build_the_cart_refuses_leaves_the_one_it_came_from_in_the_cart(): void {
		self::assertSame(
			201,
			$this->store(
				array(
					'id'     => $this->kit,
					'cbuild' => array( 'picks' => $this->picks() ),
				)
			)->get_status()
		);
		$before = WC()->cart->get_cart();
		$old    = self::builds()[0]['build'];
		$sold   = wc_get_product( $this->parts['wheels'] );
		$sold->set_stock_status( 'outofstock' );
		$sold->save();
		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array(
					'picks'   => $this->picks(),
					'replace' => $old->id,
				),
			)
		);
		self::assertSame( 400, $response->get_status() );
		self::assertSame( array_keys( $before ), array_keys( WC()->cart->get_cart() ), 'nothing taken out, nothing added' );
	}

	public function test_a_kit_with_no_build_is_refused_by_every_route(): void {
		$response = $this->store( array( 'id' => $this->kit ) );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'cbuild_no_build', $response->get_data()['code'] );
		self::assertSame( 'Choose the parts for this build first, then add it to the cart.', $response->get_data()['message'] );

		$this->form( null );
		self::assertSame( array( 'Choose the parts for this build first, then add it to the cart.' ), self::errors() );
		wc_clear_notices();

		self::assertFalse( WC()->cart->add_to_cart( $this->kit ), 'another plugin, or an express button, adding the product itself' );
		self::assertFalse( WC()->cart->add_to_cart( $this->kit, 1, 0, array(), array( Options::CART_BUILD => ( new Build( wp_generate_uuid4(), $this->kit, $this->picks(), array_fill_keys( array_keys( $this->picks() ), 1 ), false ) )->toArray() ) ), 'nor with a build it made up' );
		self::assertSame( array(), WC()->cart->get_cart() );
		self::assertFalse( wc_get_product( $this->kit )->is_purchasable(), 'and the kit is still not for sale as a product' );
	}

	public function test_a_build_with_something_wrong_is_refused_and_says_what(): void {
		$sold = wc_get_product( $this->parts['wheels'] );
		$sold->set_stock_status( 'outofstock' );
		$sold->save();
		$picks = array_diff_key( $this->picks(), array( 'grip' => 0 ) );

		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array( 'picks' => $picks ),
			)
		);
		self::assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		self::assertSame( 'cbuild_build_refused', $data['code'] );
		$codes = array_column( $data['data']['problems'], 'slot', 'code' );
		self::assertSame( 'wheels', $codes['part_sold_out'] );
		self::assertSame( 'grip', $codes['slot_empty'] );
		self::assertSame( array(), WC()->cart->get_cart() );

		$this->form( array( 'picks' => $picks ) );
		self::assertEqualsCanonicalizing( array( 'Wheels: the part chosen is sold out.', 'Grip: nothing is chosen yet.' ), self::errors() );
		self::assertSame( array(), WC()->cart->get_cart() );
		self::assertSame( array(), wc_get_notices( 'success' ) );
	}

	public function test_a_kit_that_is_not_published_is_not_found(): void {
		$draft = wc_get_product( $this->kit );
		$draft->set_status( 'draft' );
		$draft->save();
		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array( 'picks' => $this->picks() ),
			)
		);
		self::assertSame( 404, $response->get_status() );
		self::assertSame( 'cbuild_kit_not_found', $response->get_data()['code'] );
		self::assertSame( 'This build is no longer sold. Nothing was added to the cart.', $response->get_data()['message'] );
		self::assertSame( array(), WC()->cart->get_cart() );

		$this->form( array( 'picks' => $this->picks() ) );
		self::assertSame( array(), WC()->cart->get_cart() );
		self::assertSame( array(), wc_get_notices( 'success' ) );
	}

	/**
	 * Each way a pick is refused, by both routes: the code a script reads and the sentence a shopper does.
	 *
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function refused(): iterable {
		yield 'slot_unknown' => array( 'slot_unknown', 'nose', 'This kit has no slot called "nose".' );
		yield 'part_not_in_slot' => array( 'part_not_in_slot', 'deck', 'Deck: the part chosen is not one this kit offers here.' );
		yield 'part_short' => array( 'part_short', 'trucks', 'Trucks: the shop has fewer of the part chosen than a build takes.' );
		yield 'part_blocked' => array( 'part_blocked', 'deck', 'Deck: the part chosen does not fit the rest of the build.' );
	}

	/**
	 * @dataProvider refused
	 */
	public function test_each_refusal_of_a_pick_carries_its_code_and_its_sentence_by_both_routes( string $code, string $slot, string $sentence ): void {
		$picks = $this->picks();
		switch ( $code ) {
			case 'slot_unknown':
				$picks['nose'] = $this->parts['deck'];
				break;
			case 'part_not_in_slot':
				$picks['deck'] = $this->parts['wheels'];
				break;
			case 'part_short':
				$trucks = wc_get_product( $this->parts['trucks'] );
				$trucks->set_manage_stock( true );
				$trucks->set_stock_quantity( 1 );
				$trucks->save();
				break;
			case 'part_blocked':
				$wide = new WC_Product_Simple();
				$wide->set_name( 'Wide trucks' );
				$wide->set_status( 'publish' );
				$wide->set_regular_price( '30' );
				$wide->update_meta_data( Options::META_SPECS, array( 'axle_width' => '10.0000', 'deck_min' => '9.7500', 'deck_max' => '10.2500', 'baseplate' => 'new_school', 'max_wheel' => '60.0000', 'axle_diameter' => '8.0000', 'set_size' => '1' ) );
				$wide->save();
				wp_set_object_terms( $wide->get_id(), wp_get_object_terms( $this->parts['trucks'], 'product_cat', array( 'fields' => 'ids' ) ), 'product_cat' );
				$this->service( SpecIndexer::class )->index( $wide->get_id() );
				$picks['trucks'] = $wide->get_id();
				break;
		}

		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => array( 'picks' => $picks ),
			)
		);
		self::assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		self::assertSame( 'cbuild_build_refused', $data['code'] );
		// The Store API's messages are HTML, as WooCommerce's own are: the Cart block decodes them.
		self::assertSame( esc_html( $sentence ), $data['message'] );
		self::assertContains( array( $code, $slot ), array_map( static fn( array $problem ): array => array( $problem['code'], $problem['slot'] ), $data['data']['problems'] ) );
		self::assertSame( array(), WC()->cart->get_cart() );

		$this->form( array( 'picks' => $picks ) );
		self::assertContains( esc_html( $sentence ), self::errors(), 'a notice is HTML too' );
		self::assertSame( array(), WC()->cart->get_cart() );
		self::assertSame( array(), wc_get_notices( 'success' ) );
	}

	public function test_a_build_the_shop_can_no_longer_price_is_refused_by_both_routes(): void {
		// A shop set to more decimals than a price can be kept in: the fee has no such minor unit.
		update_option( 'woocommerce_price_num_decimals', '9' );
		$build = array(
			'picks'    => $this->picks(),
			'assembly' => true,
		);

		$response = $this->store(
			array(
				'id'     => $this->kit,
				'cbuild' => $build,
			)
		);
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'cbuild_no_price', $response->get_data()['code'] );
		self::assertSame( 'This build cannot be priced at the moment. Nothing was added to the cart.', $response->get_data()['message'] );

		$this->form( $build );
		self::assertSame( array( 'This build cannot be priced at the moment. Nothing was added to the cart.' ), self::errors() );
		self::assertSame( array(), WC()->cart->get_cart() );
	}

	public function test_woocommerces_ajax_add_refuses_a_kit_as_the_form_does(): void {
		$_POST['product_id'] = (string) $this->kit;
		add_filter( 'wp_doing_ajax', '__return_true' );
		$thrower = static fn(): Closure => static function (): never {
			throw new RuntimeException( 'sent' );
		};
		add_filter( 'wp_die_ajax_handler', $thrower );
		ob_start();
		try {
			WC_AJAX::add_to_cart();
			self::fail( 'the answer was not sent' );
		} catch ( RuntimeException $sent ) {
			self::assertSame( 'sent', $sent->getMessage() );
		} finally {
			while ( ob_get_level() > 1 ) {
				$answer = (string) ob_get_clean();
				if ( '' !== $answer ) {
					$said = $answer;
				}
			}
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $thrower );
			unset( $_POST['product_id'] );
		}
		self::assertTrue( json_decode( $said ?? '', true )['error'] ?? null, 'WooCommerce says the add failed' );
		self::assertSame( array(), WC()->cart->get_cart() );
		self::assertSame( array( 'Choose the parts for this build first, then add it to the cart.' ), self::errors() );
	}

	// Issue #26: a price changed where no hook fires. The builder shows what is kept; the cart charges what is.
	public function test_the_cart_charges_what_woocommerce_holds_whatever_the_builder_was_shown(): void {
		$deck  = $this->parts['deck'];
		$state = $this->service( BuildState::class )->of( $this->kit, $this->picks() );
		self::assertSame( 7000, $state?->quote->lines[0]->price );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the test's point: a write that fires nothing.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = '99' WHERE post_id = %d AND meta_key IN ( '_price', '_regular_price' )", $deck ) );
		wp_cache_flush();
		self::assertSame( 7000, $this->service( BuildState::class )->of( $this->kit, $this->picks() )?->quote->lines[0]->price, 'the builder still shows what is kept' );

		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $deck === $item['product_id'] ) {
				self::assertSame( '99', $item['data']->get_price(), 'the cart charges what the shop holds' );
			}
		}
	}

	public function test_a_part_woocommerce_will_not_add_takes_the_whole_build_out_and_fails_the_add(): void {
		$wheels = $this->parts['wheels'];
		// Once the build has been judged and its kit added, the wheels stop being for sale.
		add_action(
			'woocommerce_add_to_cart',
			static function () use ( $wheels ): void {
				add_filter( 'woocommerce_is_purchasable', static fn( $purchasable, $product ) => $wheels === $product->get_id() ? false : $purchasable, 99, 2 );
			},
			5
		);
		$this->form( array( 'picks' => $this->picks() ) );
		self::assertSame( array(), WC()->cart->get_cart(), 'nothing of the build stays' );
		self::assertContains( 'One of the parts could not be added, so nothing of the build was. Check the parts and add the build again.', self::errors() );
		self::assertSame( array(), wc_get_notices( 'success' ) );
		remove_all_filters( 'woocommerce_is_purchasable', 99 );

		$response = $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) );
		self::assertSame( 400, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		self::assertSame( 'cbuild_not_added', $response->get_data()['code'] );
		self::assertSame( array(), WC()->cart->get_cart() );
	}

	public function test_a_build_comes_back_from_the_session_whole_priced_and_for_sale(): void {
		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks(), 'assembly' => true ) ) )->get_status() );
		$before = self::builds();
		WC()->cart->set_session();
		WC()->cart->cart_contents = array();
		wc_clear_notices();
		WC()->cart->get_cart_from_session();

		self::assertSame( array(), self::errors(), 'WooCommerce does not remove the kit as one that cannot be bought' );
		$after = self::builds();
		self::assertEquals( $before, $after );
		self::assertSame( '15.00', $after[0]['price'] );
		foreach ( WC()->cart->get_cart() as $item ) {
			self::assertTrue( $item['data']->is_purchasable() );
		}

		// A kit's line whose build another version wrote is not one this version sells.
		$key = array_key_first( WC()->cart->get_cart() );
		WC()->cart->cart_contents[ $key ][ Options::CART_BUILD ]['schema_version'] = 2;
		WC()->cart->set_session();
		WC()->cart->cart_contents = array();
		WC()->cart->get_cart_from_session();
		self::assertSame( array(), self::builds() );
		self::assertCount( 1, self::errors() );
	}
}
