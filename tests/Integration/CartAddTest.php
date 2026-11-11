<?php
/**
 * A build added to the cart, by both routes.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Cart\CartHooks;
use CreativeBranding\SkateboardBuilder\Hooks\CardHooks;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Form_Handler;
use WC_Product_Simple;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Spec, Cart, checkout and orders, Adding a build: the same three steps whichever way it arrives, the Store API
 * or the classic form. Roadmap, milestone 5: a bare kit is refused. Issue #26: the cart reads a picked part from
 * WooCommerce itself.
 */
final class CartAddTest extends IntegrationTestCase {

	/**
	 * Each slot's part's specs, and its price.
	 */
	private const PARTS = array(
		'deck'     => array( '70', array( 'deck_width' => '8.5000', 'deck_length' => '32.0000', 'hole_pattern' => 'new_school' ) ),
		'trucks'   => array( '27', array( 'axle_width' => '8.5000', 'deck_min' => '8.3750', 'deck_max' => '8.6000', 'baseplate' => 'new_school', 'max_wheel' => '56.0000', 'axle_diameter' => '8.0000', 'set_size' => '1' ) ),
		'wheels'   => array( '32', array( 'wheel_diameter' => '54.0000', 'set_size' => '4' ) ),
		'risers'   => array( '5', array( 'riser_thickness' => '0.1250', 'set_size' => '2' ) ),
		'bearings' => array( '15', array( 'bearing_bore' => '8.0000', 'set_size' => '8' ) ),
		'hardware' => array( '4', array( 'bolt_length' => '1.0000', 'set_size' => '8' ) ),
		'grip'     => array( '8', array( 'grip_width' => '9.0000', 'grip_length' => '33.0000' ) ),
	);

	private int $kit;

	/**
	 * The parts' IDs, by slot.
	 *
	 * @var array<string, int>
	 */
	private array $parts = array();

	public function set_up(): void {
		parent::set_up();
		$this->service( CardHooks::class )->register();
		$this->service( CartHooks::class )->register();
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		rest_get_server();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );

		$slots = KitDocuments::slots();
		foreach ( $slots['slots'] as $at => $slot ) {
			$category                          = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
			$slots['slots'][ $at ]['sources']   = array( 'categories' => array( $category ) );
			[ $price, $specs ]                 = self::PARTS[ $slot['key'] ];
			$product                           = new WC_Product_Simple();
			$product->set_name( ucfirst( $slot['key'] ) );
			$product->set_status( 'publish' );
			$product->set_regular_price( $price );
			$product->update_meta_data( Options::META_SPECS, $specs );
			$product->save();
			wp_set_object_terms( $product->get_id(), array( $category ), 'product_cat' );
			$this->service( SpecIndexer::class )->index( $product->get_id() );
			$this->parts[ $slot['key'] ] = $product->get_id();
		}
		$this->kit = KitFactory::kit(
			'Complete',
			array(
				Options::META_KIT_SLOTS    => $slots,
				Options::META_KIT_PRESETS  => array(
					'schema_version' => 1,
					'presets'        => array(),
				),
				Options::META_KIT_ASSEMBLY => array(
					'schema_version' => 1,
					'default_on'     => false,
					'fee'            => '15',
					'lead_time'      => '',
					'returns_notice' => '',
				),
			)
		)->get_id();

		wc_load_cart();
		WC()->cart->empty_cart();
		wc_clear_notices();
	}

	public function tear_down(): void {
		WC()->cart->empty_cart();
		wc_clear_notices();
		unset( $_POST['cbuild'], $_POST['quantity'], $_REQUEST['add-to-cart'], $_REQUEST['quantity'] );
		parent::tear_down();
	}

	/**
	 * The picks of a build that fits, with no risers: they are optional.
	 *
	 * @return array<string, int>
	 */
	private function picks(): array {
		return array_diff_key( $this->parts, array( 'risers' => 0 ) );
	}

	/**
	 * Add through the Store API, as the builder's script will.
	 *
	 * @param array<string, mixed> $body The request's body.
	 */
	private function store( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/add-item' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Add through WooCommerce's own form, as a page with no script does.
	 *
	 * @param mixed $build    The field `cbuild`, or null for none.
	 * @param int   $quantity The field `quantity`.
	 */
	private function form( mixed $build, int $quantity = 1 ): void {
		$_REQUEST['add-to-cart'] = (string) $this->kit;
		$_REQUEST['quantity']    = (string) $quantity;
		$_POST['quantity']       = (string) $quantity;
		if ( null !== $build ) {
			$_POST['cbuild'] = wp_slash( $build );
		}
		WC_Form_Handler::add_to_cart_action();
	}

	/**
	 * The cart's lines as the plugin's keys tell them: each kit's line with its build, and its parts by slot.
	 *
	 * @return list<array{build: Build, quantity: int, price: string, parts: array<string, array{int, int}>}>
	 */
	private static function builds(): array {
		$cart   = WC()->cart->get_cart();
		$builds = array();
		foreach ( $cart as $key => $item ) {
			$build = Build::read( $item[ Options::CART_BUILD ] ?? null );
			if ( null === $build ) {
				continue;
			}
			$parts = array();
			foreach ( $item[ Options::CART_CHILDREN ] ?? array() as $child ) {
				self::assertSame( $key, $cart[ $child ][ Options::CART_PARENT ], 'each part names its kit' );
				$parts[ $cart[ $child ][ Options::CART_SLOT ] ] = array( $cart[ $child ]['product_id'], $cart[ $child ]['quantity'] );
			}
			$builds[] = array(
				'build'    => $build,
				'quantity' => $item['quantity'],
				'price'    => $item['data']->get_price(),
				'parts'    => $parts,
			);
		}
		return $builds;
	}

	/**
	 * The error notices waiting, as text.
	 *
	 * @return list<string>
	 */
	private static function errors(): array {
		return array_map( static fn( array $notice ): string => wp_strip_all_tags( $notice['notice'] ), wc_get_notices( 'error' ) );
	}

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
		self::assertContains( $response->get_status(), array( 400, 404 ) );
		self::assertSame( array(), WC()->cart->get_cart() );
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
