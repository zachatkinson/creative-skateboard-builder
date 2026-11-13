<?php
/**
 * A build shown in the carts.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
use CreativeBranding\SkateboardBuilder\Cart\LinesIntegration;
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
		// The handle as the integration registered it, with no file, for the next test.
		wp_deregister_script( ShowHooks::HANDLE );
		( new LinesIntegration() )->initialize();
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

		// WooCommerce 11.3.0 gives each Store API cart item a `parent_item_key`, by a filter
		// (src/StoreApi/Schemas/V1/CartItemSchema.php:132): a part's is its kit's line. The filter is asked here as
		// WooCommerce asks it, so the oldest leg, which has no such field, holds the answer too.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		self::assertSame( $lines['kit'][0], apply_filters( 'woocommerce_store_api_cart_item_parent_item_key', null, $lines['trucks'][1], $lines['trucks'][0] ) );
		self::assertNull( apply_filters( 'woocommerce_store_api_cart_item_parent_item_key', null, $lines['kit'][1], $lines['kit'][0] ) );
		self::assertSame( 'another', apply_filters( 'woocommerce_store_api_cart_item_parent_item_key', 'another', $lines['plain'][1], $lines['plain'][0] ), 'a line of no build is given back as it came' );
		// phpcs:enable
		foreach ( $cart['items'] as $item ) {
			if ( array_key_exists( 'parent_item_key', $item ) ) {
				self::assertSame( $lines['trucks'][0] === $item['key'] ? $lines['kit'][0] : ( in_array( $item['key'], array( $lines['kit'][0], $lines['plain'][0] ), true ) ? null : $lines['kit'][0] ), $item['parent_item_key'], 'and the Store API says so' );
			}
		}

		self::assertSame( array( 'Build' ), array_column( $told[ $lines['kit'][0] ][1], 'key' ), 'the kit\'s line has Edit build, below' );
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
		// The classic mini-cart widget's rows, which have a filter of their own (templates/cart/mini-cart.php:56).
		self::assertSame( 'mini_cart_item cbuild-part', apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $trucks[1], $trucks[0] ) );
		self::assertSame( 'mini_cart_item cbuild-kit', apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $kit[1], $kit[0] ) );
		self::assertSame( 'mini_cart_item', apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $plain[1], $plain[0] ) );
		wp_dequeue_style( 'cbuild-cart' );
		ob_start();
		do_action( 'woocommerce_before_mini_cart' );
		ob_end_clean();
		self::assertTrue( wp_style_is( 'cbuild-cart', 'enqueued' ), 'and the styles that indent them go with the widget' );
		self::assertStringContainsString( '.woocommerce-mini-cart-item.cbuild-part', implode( '', (array) wp_styles()->get_data( 'cbuild-cart', 'after' ) ) );

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
		self::assertStringContainsString( '>Edit build</a>', wc_get_formatted_cart_item_data( $kit[1] ) );
		self::assertSame( '', wc_get_formatted_cart_item_data( $plain[1] ) );
	}

	public function test_a_kits_line_has_an_edit_build_link_that_reopens_the_builder_to_replace_it(): void {
		[ 'kit' => $kit ] = $this->lines();
		$build            = $kit[1][ Options::CART_BUILD ];
		$data             = apply_filters( 'woocommerce_get_item_data', array(), $kit[1] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		self::assertCount( 1, $data );
		self::assertSame( array( 'Build', 'Edit build', 'cbuild_edit' ), array( $data[0]['key'], $data[0]['value'], $data[0]['raw_key'] ) );
		self::assertSame( 1, preg_match( '#\A<a class="cbuild-edit" href="([^"]+)" aria-label="([^"]+)">Edit build</a>\z#', $data[0]['display'], $found ) );
		// Two builds in a cart give two links: each is named for its kit, beginning with the words it shows (W8, P11).
		self::assertSame( 'Edit build: ' . esc_attr( wc_get_product( $this->kit )->get_name() ), $found[2] );
		$href = html_entity_decode( $found[1] );
		self::assertStringStartsWith( get_permalink( $this->kit ) . ( str_contains( (string) get_permalink( $this->kit ), '?' ) ? '&' : '?' ), $href, 'the kit\'s builder, where cbuild_builder_url says it is' );
		parse_str( (string) wp_parse_url( $href, PHP_URL_QUERY ), $query );
		self::assertSame(
			array_map( 'strval', $build['picks'] ) + array(
				'step'     => 'review',
				'assembly' => $build['assembly'] ? '1' : '0',
				'_replace' => $build['id'],
			),
			$query['cbuild'],
			'The build at the review, with the build it replaces (D-157).'
		);

		// Where the builder is on another page of the site, the link goes there.
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$to   = static fn(): string => (string) get_permalink( $page );
		add_filter( 'cbuild_builder_url', $to );
		$data = apply_filters( 'woocommerce_get_item_data', array(), $kit[1] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- as above.
		remove_filter( 'cbuild_builder_url', $to );
		self::assertStringContainsString( 'href="' . esc_url( get_permalink( $page ) ), $data[0]['display'] );
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
		$this->show()->lines();
		self::assertFalse( wp_scripts()->registered[ ShowHooks::HANDLE ]->src, 'the blocks wait on a handle that prints nothing' );
	}

	public function test_a_part_whose_kits_line_is_gone_is_shown_as_any_product(): void {
		$trucks = $this->lines()['trucks'];
		$orphan = array( Options::CART_PARENT => 'no-such-line' ) + $trucks[1];
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		self::assertSame( 'cart_item', apply_filters( 'woocommerce_cart_item_class', 'cart_item', $orphan, $trucks[0] ) );
		self::assertSame( '', wc_get_formatted_cart_item_data( $orphan ) );
	}

	public function test_the_styles_go_in_the_page_with_a_cart_and_the_script_comes_before_the_blocks_own(): void {
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

		// Cowork's review, blocker 1: the script is a dependency of each block's own, so it runs before the block
		// draws (D-173), and it has its file where the cart holds a build.
		// WooCommerce makes these the block's script's dependencies (src/Blocks/BlockTypes/AbstractBlock.php:211-215).
		foreach ( array( 'cart_block', 'checkout_block' ) as $block ) {
			$registry = new IntegrationRegistry();
			$registry->initialize( $block );
			self::assertSame( array( ShowHooks::HANDLE ), array_values( $registry->get_all_registered_script_handles() ), $block );
		}
		self::assertTrue( wp_script_is( ShowHooks::HANDLE, 'registered' ) );
		$this->show()->lines();
		self::assertStringEndsWith( 'build/cart/lines.js', strtok( (string) wp_scripts()->registered[ ShowHooks::HANDLE ]->src, '?' ) );
		self::assertSame( array( 'wc-blocks-checkout' ), wp_scripts()->registered[ ShowHooks::HANDLE ]->deps );
		self::assertTrue( (bool) wp_scripts()->get_data( ShowHooks::HANDLE, 'group' ), 'in the footer' );
	}

	/**
	 * The hooks' service, as the plugin built it.
	 */
	private function show(): ShowHooks {
		return $this->service( ShowHooks::class );
	}
}
