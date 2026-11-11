<?php
/**
 * A build shown in the carts.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Cart\ShowHooks;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Product_Simple;
use WP_REST_Request;

/**
 * Spec, Cart, checkout and orders, Showing it. What the server gives each of the three carts to group a build by;
 * the browser tests see the carts draw it (tests/e2e/cart.spec.ts).
 */
final class CartShowTest extends CartTestCase {

	private int $plain;

	public function set_up(): void {
		parent::set_up();
		$plain = new WC_Product_Simple();
		$plain->set_name( 'Wax' );
		$plain->set_status( 'publish' );
		$plain->set_regular_price( '3' );
		$plain->save();
		$this->plain = $plain->get_id();
		$response    = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => 1,
				'cbuild'   => array( 'picks' => $this->picks() ),
			)
		);
		self::assertSame( 201, $response->get_status() );
		WC()->cart->add_to_cart( $this->plain );
	}

	public function tear_down(): void {
		wp_dequeue_style( ShowHooks::HANDLE );
		wp_dequeue_script( ShowHooks::HANDLE );
		parent::tear_down();
	}

	/**
	 * The cart's lines by what they are: the kit's, the trucks' and the product's that is of no build.
	 *
	 * @return array{kit: array{string, array<string, mixed>}, trucks: array{string, array<string, mixed>}, plain: array{string, array<string, mixed>}}
	 */
	private function lines(): array {
		$lines = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( isset( $item[ Options::CART_BUILD ] ) ) {
				$lines['kit'] = array( $key, $item );
			} elseif ( 'trucks' === ( $item[ Options::CART_SLOT ] ?? '' ) ) {
				$lines['trucks'] = array( $key, $item );
			} elseif ( $item['product_id'] === $this->plain ) {
				$lines['plain'] = array( $key, $item );
			}
		}
		self::assertCount( 3, $lines );
		return $lines;
	}

	public function test_the_store_api_says_what_each_line_is_of_a_build(): void {
		$cart  = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wc/store/v1/cart' ) )->get_data();
		$lines = $this->lines();
		$told  = array();
		foreach ( $cart['items'] as $item ) {
			$told[ $item['key'] ] = array( (array) $item['extensions']->{'creative-skateboard-builder'}, $item['item_data'] );
		}
		$build = $lines['kit'][1][ Options::CART_BUILD ]['id'];
		self::assertSame(
			array(
				'role'   => 'kit',
				'build'  => $build,
				'parent' => '',
				'slot'   => '',
				'name'   => '',
			),
			$told[ $lines['kit'][0] ][0]
		);
		self::assertSame(
			array(
				'role'   => 'part',
				'build'  => $build,
				'parent' => $lines['kit'][0],
				'slot'   => 'trucks',
				'name'   => 'Trucks: Trucks',
			),
			$told[ $lines['trucks'][0] ][0]
		);
		self::assertSame(
			array(
				'role'   => '',
				'build'  => '',
				'parent' => '',
				'slot'   => '',
				'name'   => '',
			),
			$told[ $lines['plain'][0] ][0]
		);

		self::assertSame( array(), $told[ $lines['kit'][0] ][1] );
		self::assertSame( array(), $told[ $lines['plain'][0] ][1] );
		self::assertSame(
			array(
				array(
					'key'     => 'Part of',
					'value'   => 'Complete (Trucks)',
					'display' => '<span class="cbuild-part">Complete (Trucks)</span>',
					'raw_key' => 'cbuild_slot',
				),
			),
			$told[ $lines['trucks'][0] ][1],
			'the class the Mini-Cart\'s styles go by comes through the Store API whole'
		);
	}

	public function test_the_schema_names_every_key_the_store_api_gives(): void {
		$schema = ShowHooks::schema();
		self::assertSame( Options::cartItemFields(), array_keys( $schema ) );
		foreach ( $schema as $field ) {
			self::assertSame( 'string', $field['type'] );
			self::assertTrue( $field['readonly'] );
			self::assertNotSame( '', $field['description'] );
		}
	}

	public function test_a_classic_cart_names_indents_and_locks_a_part(): void {
		[ 'kit' => $kit, 'trucks' => $trucks, 'plain' => $plain ] = $this->lines();
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its templates apply them.
		self::assertSame( 'Trucks: <a href="#">Trucks</a>', apply_filters( 'woocommerce_cart_item_name', '<a href="#">Trucks</a>', $trucks[1], $trucks[0] ) );
		self::assertSame( 'Complete', apply_filters( 'woocommerce_cart_item_name', 'Complete', $kit[1], $kit[0] ) );
		self::assertSame( 'Wax', apply_filters( 'woocommerce_cart_item_name', 'Wax', $plain[1], $plain[0] ) );

		self::assertSame( 'cart_item cbuild-part', apply_filters( 'woocommerce_cart_item_class', 'cart_item', $trucks[1], $trucks[0] ) );
		self::assertSame( 'cart_item cbuild-kit', apply_filters( 'woocommerce_cart_item_class', 'cart_item', $kit[1], $kit[0] ) );
		self::assertSame( 'cart_item', apply_filters( 'woocommerce_cart_item_class', 'cart_item', $plain[1], $plain[0] ) );

		self::assertSame( '<span class="cbuild-quantity">2</span>', apply_filters( 'woocommerce_cart_item_quantity', '<input>', $trucks[0], $trucks[1] ) );
		self::assertSame( '<input>', apply_filters( 'woocommerce_cart_item_quantity', '<input>', $kit[0], $kit[1] ) );
		self::assertSame( '<input>', apply_filters( 'woocommerce_cart_item_quantity', '<input>', $plain[0], $plain[1] ) );

		self::assertSame( '', apply_filters( 'woocommerce_cart_item_remove_link', '<a>x</a>', $trucks[0] ) );
		self::assertSame( '<a>x</a>', apply_filters( 'woocommerce_cart_item_remove_link', '<a>x</a>', $kit[0] ) );
		self::assertSame( '<a>x</a>', apply_filters( 'woocommerce_cart_item_remove_link', '<a>x</a>', $plain[0] ) );
		self::assertSame( '<a>x</a>', apply_filters( 'woocommerce_cart_item_remove_link', '<a>x</a>', 'no-such-line' ) );
		// phpcs:enable

		$data = wc_get_formatted_cart_item_data( $trucks[1] );
		self::assertStringContainsString( 'Part of', $data );
		self::assertStringContainsString( '<span class="cbuild-part">Complete (Trucks)</span>', $data );
		self::assertSame( '', wc_get_formatted_cart_item_data( $kit[1] ) );
	}

	public function test_a_cart_with_no_build_brings_nothing_of_the_plugin_to_the_page(): void {
		WC()->cart->remove_cart_item( $this->lines()['kit'][0] );
		self::assertCount( 1, WC()->cart->get_cart(), 'the product of no build is still there' );
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, as it applies them to a block.
		apply_filters( 'render_block_woocommerce/mini-cart', '<div></div>' );
		apply_filters( 'render_block_woocommerce/cart', '<div></div>' );
		apply_filters( 'render_block_woocommerce/checkout', '<div></div>' );
		// phpcs:enable
		self::assertFalse( wp_style_is( ShowHooks::HANDLE ) );
		self::assertFalse( wp_script_is( ShowHooks::HANDLE ) );
	}

	public function test_a_part_whose_kits_line_is_gone_is_shown_as_any_product(): void {
		$trucks = $this->lines()['trucks'];
		$orphan = array( Options::CART_PARENT => 'no-such-line' ) + $trucks[1];
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		self::assertSame( 'cart_item', apply_filters( 'woocommerce_cart_item_class', 'cart_item', $orphan, $trucks[0] ) );
		self::assertSame( '', wc_get_formatted_cart_item_data( $orphan ) );
	}

	public function test_the_styles_go_in_the_page_with_a_cart_and_the_script_comes_with_the_cart_block_alone(): void {
		self::assertFalse( wp_style_is( ShowHooks::HANDLE ), 'nothing before a cart is on the page' );
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, as it applies them to a block.
		self::assertSame( '<div></div>', apply_filters( 'render_block_woocommerce/mini-cart', '<div></div>' ) );
		self::assertTrue( wp_style_is( ShowHooks::HANDLE ) );
		self::assertFalse( wp_styles()->registered[ ShowHooks::HANDLE ]->src, 'the styles are in the page, and no file is asked for' );
		$styles = wp_styles()->get_data( ShowHooks::HANDLE, 'after' );
		self::assertCount( 1, $styles );
		self::assertStringContainsString( '.wc-block-cart-items__row:has(.cbuild-part)>:first-child', $styles[0] );
		self::assertStringContainsString( 'border-inline-start:1.5em solid transparent', $styles[0] );
		self::assertFalse( wp_script_is( ShowHooks::HANDLE ), 'the Mini-Cart brings no script' );

		wp_dequeue_style( ShowHooks::HANDLE );
		self::assertSame( '<div></div>', apply_filters( 'render_block_woocommerce/cart', '<div></div>' ) );
		// phpcs:enable
		self::assertTrue( wp_style_is( ShowHooks::HANDLE ) );
		self::assertCount( 1, wp_styles()->get_data( ShowHooks::HANDLE, 'after' ), 'the styles are put in the page once' );
		self::assertTrue( wp_script_is( ShowHooks::HANDLE ) );
		self::assertStringEndsWith( 'build/cart/lines.js', strtok( wp_scripts()->registered[ ShowHooks::HANDLE ]->src, '?' ) );
		self::assertSame( array( 'wc-blocks-checkout' ), wp_scripts()->registered[ ShowHooks::HANDLE ]->deps );
		self::assertTrue( (bool) wp_scripts()->get_data( ShowHooks::HANDLE, 'group' ), 'in the footer' );
	}
}
