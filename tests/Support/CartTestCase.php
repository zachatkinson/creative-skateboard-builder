<?php
/**
 * A shop with a kit, and a cart to put its builds in.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

use CreativeBranding\SkateboardBuilder\Cart\CartHooks;
use CreativeBranding\SkateboardBuilder\Cart\LineHooks;
use CreativeBranding\SkateboardBuilder\Cart\PriceHooks;
use CreativeBranding\SkateboardBuilder\Hooks\CardHooks;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use WC_Form_Handler;
use WC_Product_Simple;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The fixture the cart's integration tests share: a published kit whose every slot has one part that fits, the
 * plugin's cart hooks registered, an empty cart, and both ways of adding a build.
 */
abstract class CartTestCase extends IntegrationTestCase {

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

	protected int $kit;

	/**
	 * The parts' IDs, by slot.
	 *
	 * @var array<string, int>
	 */
	protected array $parts = array();

	public function set_up(): void {
		parent::set_up();
		$this->service( CardHooks::class )->register();
		$this->service( CartHooks::class )->register();
		$this->service( LineHooks::class )->register();
		$this->service( PriceHooks::class )->register();
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
	protected function picks(): array {
		return array_diff_key( $this->parts, array( 'risers' => 0 ) );
	}

	/**
	 * Add through the Store API, as the builder's script will.
	 *
	 * @param array<string, mixed> $body The request's body.
	 */
	protected function store( array $body ): WP_REST_Response {
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
	protected function form( mixed $build, int $quantity = 1 ): void {
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
	protected static function builds(): array {
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
	protected static function errors(): array {
		return array_map( static fn( array $notice ): string => wp_strip_all_tags( $notice['notice'] ), wc_get_notices( 'error' ) );
	}
}
