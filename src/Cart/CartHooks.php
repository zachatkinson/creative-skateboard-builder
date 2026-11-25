<?php
/**
 * The hooks that put a build in the cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Closure;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildCart;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Cart;
use WC_Product;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A build reaches the cart by the Store API's add-item or by WooCommerce's own form, and by either it goes
 * through the same service (spec: Cart, checkout and orders, Adding a build). Checked in WooCommerce 11.2.2;
 * each hook's file and line is at its method. These hold no rule of their own: they read the request, ask
 * Service\BuildCart, and say its answer in the way each route says a refusal.
 *
 * The order the two routes run them in differs, which is why the build is judged where a route first has it:
 *
 * - the form: `woocommerce_add_to_cart_validation`, then in `WC_Cart::add_to_cart()` the item data, the question
 *   whether the product may be bought, `woocommerce_add_cart_item` and `woocommerce_add_to_cart`;
 * - the Store API: `woocommerce_store_api_add_to_cart_data`, the item data, whether it may be bought, the
 *   validation, `woocommerce_add_cart_item` and `woocommerce_add_to_cart`.
 *
 * @since 0.5.0
 */
final class CartHooks implements Hookable {

	/**
	 * The name a build is sent under: a field of the form, a parameter of the Store API's request.
	 */
	public const string REQUEST = 'cbuild';

	/**
	 * The Store API's route that adds a line, which the builder's script posts a build to.
	 */
	private const string ADD_ROUTE = '/wc/store/v1/cart/add-item';

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Closure(): BuildCart $builds Builds the service, when a kit is on its way to the cart or in it.
	 * @param Refusals             $refusals Puts a refusal in words.
	 */
	public function __construct( private Closure $builds, private Refusals $refusals ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// src/StoreApi/Routes/V1/CartAddItem.php:118.
		add_filter( 'woocommerce_store_api_add_to_cart_data', array( $this, 'sent' ), 10, 2 );
		// includes/class-wc-form-handler.php:1088, includes/class-wc-ajax.php:556, src/StoreApi/Utilities/CartController.php:337.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate' ), 10, 6 );
		// includes/class-wc-cart.php:1331, src/StoreApi/Utilities/CartController.php:1232.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'itemData' ), 10, 4 );
		// includes/abstracts/abstract-wc-product.php:1785, which Adapter\KitProduct runs with false.
		add_filter( 'woocommerce_is_purchasable', array( $this, 'purchasable' ), 10, 2 );
		// includes/class-wc-cart-session.php:198.
		add_filter( 'woocommerce_cart_item_is_purchasable', array( $this, 'linePurchasable' ), 10, 4 );
		// includes/class-wc-cart.php:1457, src/StoreApi/Utilities/CartController.php:171; includes/class-wc-cart-session.php.
		add_filter( 'woocommerce_add_cart_item', array( $this, 'priced' ), 10, 1 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'priced' ), 10, 1 );
		// includes/class-wc-cart-session.php:713: a line Order again writes, which no add prices.
		add_filter( 'woocommerce_add_order_again_cart_item', array( $this, 'priced' ), 10, 1 );
		// includes/class-wc-cart.php:1484, src/StoreApi/Utilities/CartController.php:217. Before the totals, at 20.
		add_action( 'woocommerce_add_to_cart', array( $this, 'added' ), 10, 1 );
		// includes/class-wc-cart.php:1532: a line put back by the cart's Undo has a product read afresh.
		add_action( 'woocommerce_restore_cart_item', array( $this, 'restoring' ), 10, 2 );
		// wp-includes/rest-api/class-wp-rest-server.php:1318: the route has run, and its answer is not yet sent.
		add_filter( 'rest_request_after_callbacks', array( $this, 'answering' ), 10, 3 );
	}

	/**
	 * The Store API's add-item has run with a build: the cart is kept before the answer goes out, and not only as
	 * the request ends, so a cart page asked for as soon as the add answers holds the build (issues #51 and #108).
	 * The answer is passed on as it is.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $response The route's answer.
	 * @param mixed $handler  The route's handler.
	 * @param mixed $request  The request.
	 */
	public function answering( mixed $response, mixed $handler, mixed $request ): mixed {
		unset( $handler );
		if ( $request instanceof WP_REST_Request && self::ADD_ROUTE === $request->get_route() && null !== $request->get_param( self::REQUEST ) ) {
			( $this->builds )()->answering();
		}
		return $response;
	}

	/**
	 * The Store API's add-item, as its request arrives: a kit's build is judged here, the first place the route
	 * has it, and a refusal is the route's own kind of error.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $data    What the route will add: `id`, `quantity`, `variation`, `cart_item_data`.
	 * @param mixed $request The request.
	 *
	 * @throws RouteException When the build is refused.
	 */
	public function sent( mixed $data, mixed $request ): mixed {
		$id = is_array( $data ) && is_numeric( $data['id'] ?? null ) ? (int) $data['id'] : 0;
		if ( ! is_array( $data ) || ! $request instanceof WP_REST_Request || ! self::isKit( $id ) ) {
			return $data;
		}
		$verdict = ( $this->builds )()->consider( $id, $request->get_param( self::REQUEST ), is_numeric( $data['quantity'] ?? null ) ? (int) $data['quantity'] : 1 );
		if ( null === $verdict->build ) {
			$refused = new RouteException(
				esc_html( $verdict->refusal->value ?? CartRefusal::NoBuild->value ),
				esc_html( $this->refusals->sentences( $id, $verdict )[0] ),
				CartRefusal::KitNotFound === $verdict->refusal ? 404 : 400,
				array(
					'problems' => Refusals::problems( $verdict ),
				) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- codes, slot keys and IDs, sent as JSON.
			);
			throw $refused;
		}
		$item                        = is_array( $data['cart_item_data'] ?? null ) ? $data['cart_item_data'] : array();
		$item[ Options::CART_BUILD ] = $verdict->build->toArray();
		$data['cart_item_data']      = $item;
		return $data;
	}

	/**
	 * Whether a product may be added. A kit may with a build that is taken: one the Store API's request brought,
	 * judged already, or one the form posted, judged here and said in notices when it is refused.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $passed    Whether it has passed so far.
	 * @param mixed $product   The product's ID.
	 * @param mixed $quantity  How many.
	 * @param mixed $variation The variation's ID, from the routes that send it.
	 * @param mixed $chosen    The variation's attributes, from the routes that send them.
	 * @param mixed $item      The cart item data, from the Store API.
	 */
	public function validate( mixed $passed, mixed $product, mixed $quantity = 1, mixed $variation = 0, mixed $chosen = array(), mixed $item = array() ): mixed {
		unset( $variation, $chosen );
		$id = is_numeric( $product ) ? (int) $product : 0;
		if ( true !== $passed || ! self::isKit( $id ) ) {
			return $passed;
		}
		$builds = ( $this->builds )();
		$sent   = Build::read( is_array( $item ) ? ( $item[ Options::CART_BUILD ] ?? null ) : null );
		if ( null !== $sent && $id === $sent->kit && $builds->admits( $id ) ) {
			return true;
		}
		$verdict = $builds->consider( $id, self::posted(), is_numeric( $quantity ) ? (int) $quantity : 1 );
		if ( null !== $verdict->build ) {
			return true;
		}
		foreach ( $this->refusals->sentences( $id, $verdict ) as $sentence ) {
			wc_add_notice( esc_html( $sentence ), 'error' );
		}
		return false;
	}

	/**
	 * A line's data, before it is added: a kit's gets its build, as the form posted it and as it was taken. The
	 * Store API's line has it already.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $item      The cart item data.
	 * @param mixed $product   The product's ID.
	 * @param mixed $variation The variation's ID.
	 * @param mixed $quantity  How many.
	 */
	public function itemData( mixed $item, mixed $product, mixed $variation = 0, mixed $quantity = 1 ): mixed {
		unset( $variation );
		$id = is_numeric( $product ) ? (int) $product : 0;
		if ( ! is_array( $item ) || isset( $item[ Options::CART_BUILD ] ) || ! self::isKit( $id ) ) {
			return $item;
		}
		$verdict = ( $this->builds )()->consider( $id, self::posted(), is_numeric( $quantity ) ? (int) $quantity : 1 );
		if ( null !== $verdict->build ) {
			$item[ Options::CART_BUILD ] = $verdict->build->toArray();
		}
		return $item;
	}

	/**
	 * Whether a product may be bought. A kit may in two cases alone: a build of it was taken in this request and
	 * is on its way into the cart, or this is the product of a cart line the cart has priced.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $purchasable What is said so far.
	 * @param mixed $product     The product.
	 */
	public function purchasable( mixed $purchasable, mixed $product ): mixed {
		if ( ! $product instanceof WC_Product || Options::PRODUCT_TYPE_KIT !== $product->get_type() ) {
			return $purchasable;
		}
		return array_key_exists( 'price', $product->get_changes() ) || ( $this->builds )()->admits( $product->get_id() );
	}

	/**
	 * Whether a line read from the session may stay: a kit's line with a build this version reads.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $purchasable What the product says.
	 * @param mixed $key         The line's cart key.
	 * @param mixed $values      The line as the session stored it.
	 * @param mixed $product     The product.
	 */
	public function linePurchasable( mixed $purchasable, mixed $key, mixed $values, mixed $product ): mixed {
		unset( $key );
		if ( ! $product instanceof WC_Product || Options::PRODUCT_TYPE_KIT !== $product->get_type() ) {
			return $purchasable;
		}
		return null !== Build::read( is_array( $values ) ? ( $values[ Options::CART_BUILD ] ?? null ) : null );
	}

	/**
	 * A line as it is added or read from the session: a kit's product is given its price for this build, the
	 * assembly fee or nothing, which is also what marks it as a line's product.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $item The line.
	 */
	public function priced( mixed $item ): mixed {
		$product = is_array( $item ) ? ( $item['data'] ?? null ) : null;
		$build   = is_array( $item ) ? Build::read( $item[ Options::CART_BUILD ] ?? null ) : null;
		if ( $product instanceof WC_Product && null !== $build && Options::PRODUCT_TYPE_KIT === $product->get_type() ) {
			$decimals = wc_get_price_decimals();
			$product->set_price( wc_format_decimal( ( $this->builds )()->fee( $build ) / 10 ** $decimals, $decimals ) );
		}
		return $item;
	}

	/**
	 * A removed line is being put back: a kit's product is priced again.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $key  The line's cart key.
	 * @param mixed $cart The cart.
	 */
	public function restoring( mixed $key, mixed $cart ): void {
		if ( $cart instanceof WC_Cart && is_string( $key ) ) {
			$this->priced( $cart->get_cart_item( $key ) );
		}
	}

	/**
	 * A line was added: a kit's gets its parts. When they cannot all be added nothing of the build stays, and the
	 * add fails in the way of the route it came by: WooCommerce's cart turns an exception into its notice and a
	 * failed add, and the Store API answers with the error.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $key The new line's cart key.
	 *
	 * @throws RouteException When nothing of the build stays.
	 */
	public function added( mixed $key ): void {
		$refusal = is_string( $key ) ? ( $this->builds )()->attach( $key ) : null;
		if ( null === $refusal ) {
			return;
		}
		// The Store API's route answers with it as it is. To WooCommerce's own cart it is an exception like any
		// other, whose message becomes the notice.
		$refused = new RouteException( esc_html( $refusal->value ), esc_html( Words::refusal( $refusal ) ), 400 );
		throw $refused;
	}

	/**
	 * Whether a product is a kit.
	 *
	 * @param int $id A product's ID.
	 */
	private static function isKit( int $id ): bool {
		$product = $id > 0 ? wc_get_product( $id ) : null;
		return $product instanceof WC_Product && Options::PRODUCT_TYPE_KIT === $product->get_type();
	}

	/**
	 * The build the form posted. WooCommerce's add-to-cart form carries no nonce, and none is needed for what is
	 * read here: slot keys and IDs, each checked against the shop before anything is added.
	 */
	private static function posted(): mixed {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
		$sent = isset( $_POST[ self::REQUEST ] ) ? map_deep( wp_unslash( $_POST[ self::REQUEST ] ), 'sanitize_text_field' ) : null;
		return $sent;
	}
}
