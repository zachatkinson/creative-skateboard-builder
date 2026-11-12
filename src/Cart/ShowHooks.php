<?php
/**
 * A build shown in the carts.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Closure;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a build grouped in all three carts (spec: Cart, checkout and orders, Showing it). Nothing here keeps a
 * build whole: `LineHooks` does that on the server, whatever a cart shows.
 *
 * - The Cart and Checkout blocks read each line's role, build, parent, slot and name from the Store API, under the
 *   plugin's namespace, and the plugin's small script gives them to WooCommerce's line filters.
 * - The Mini-Cart runs no line filter unless WooCommerce's checkout script is on the page (D-010), so a part's
 *   line says what it is part of in its item data, with a class the styles indent it by.
 * - A classic cart or checkout is drawn by PHP, through WooCommerce's four line filters.
 *
 * @since 0.5.0
 */
final class ShowHooks implements Hookable {

	/**
	 * The plugin's namespace in the Store API's `extensions`, and for WooCommerce's line filters.
	 */
	public const SPACE = 'creative-skateboard-builder';

	/**
	 * The handle of the carts' styles and of the Cart and Checkout blocks' script, and where the script is built.
	 */
	public const HANDLE = 'cbuild-cart';
	private const BUILT = 'build/cart/lines';

	/**
	 * A build's parts, indented under its kit's line, on whichever side the language starts. The Cart and Checkout
	 * blocks and a classic cart give a part's row the class; the Mini-Cart runs no line filter, so there the class
	 * is on the part's item data. The indent is a clear border, which adds to whatever padding the theme gives the
	 * cell where a padding of the plugin's would replace it. They go in the page: the Mini-Cart is in many a shop's header, and a file would
	 * be one more request on every page (spec: Performance, nothing loads on pages without a builder).
	 */
	private const STYLES = '.wc-block-cart-items__row.cbuild-part>:first-child,.wc-block-cart-items__row:has(.cbuild-part)>:first-child,.woocommerce-cart-form__cart-item.cbuild-part>.product-name,.woocommerce-checkout-review-order-table .cbuild-part>.product-name,.woocommerce-mini-cart-item.cbuild-part{border-inline-start:1.5em solid transparent}';

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Closure(): Kits $kits The shop's kits, for a slot's label.
	 */
	public function __construct( private Closure $kits ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// includes/wc-template-functions.php:4591 and src/StoreApi/Schemas/V1/CartItemSchema.php:174.
		add_filter( 'woocommerce_get_item_data', array( $this, 'data' ), 10, 2 );
		// templates/cart/cart.php:65, :68, :166 and :73; templates/checkout/review-order.php:46 and :55.
		add_filter( 'woocommerce_cart_item_name', array( $this, 'name' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_class', array( $this, 'row' ), 10, 2 );
		// The classic mini-cart widget: templates/cart/mini-cart.php:56 and :26.
		add_filter( 'woocommerce_mini_cart_item_class', array( $this, 'row' ), 10, 2 );
		add_action( 'woocommerce_before_mini_cart', array( $this, 'widget' ), 10, 0 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'quantity' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'remove' ), 10, 2 );
		// WooCommerce fires this as it loads, which may be before or after the plugin's hooks are attached.
		if ( did_action( 'woocommerce_blocks_loaded' ) > 0 ) {
			$this->extend();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'extend' ), 10, 0 );
		}
		// WooCommerce 11.3.0, src/StoreApi/Schemas/V1/CartItemSchema.php:132: a Store API cart item's parent line. A
		// filter nobody applies costs nothing on 11.2.
		add_filter( 'woocommerce_store_api_cart_item_parent_item_key', array( $this, 'parent' ), 10, 2 );
		// Only where the cart holds a build: the styles go in the page with a cart, and the script comes with the
		// Cart or Checkout block alone, whose own script it needs (D-010, D-132).
		add_action( 'wp_enqueue_scripts', array( $this, 'classic' ), 10, 0 );
		add_filter( 'render_block_woocommerce/mini-cart', array( $this, 'styled' ), 10, 1 );
		add_filter( 'render_block_woocommerce/cart', array( $this, 'scripted' ), 10, 1 );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'scripted' ), 10, 1 );
	}

	/**
	 * A classic cart or checkout page: the styles.
	 *
	 * @since 0.5.0
	 */
	public function classic(): void {
		if ( is_cart() || is_checkout() ) {
			$this->assets( false );
		}
	}

	/**
	 * The classic mini-cart widget is on the page: the styles, printed with the footer's.
	 *
	 * @since 0.5.0
	 */
	public function widget(): void {
		$this->assets( false );
	}

	/**
	 * The Mini-Cart is on the page: the styles.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $html The block, which is given back as it came.
	 */
	public function styled( mixed $html ): mixed {
		$this->assets( false );
		return $html;
	}

	/**
	 * The Cart or Checkout block is on the page: the styles, and the script that answers its line filters.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $html The block, which is given back as it came.
	 */
	public function scripted( mixed $html ): mixed {
		$this->assets( true );
		return $html;
	}

	/**
	 * Whether the cart holds a build as the page is drawn. A build gets into a cart from the builder's page alone,
	 * so a page drawn for a cart with none needs nothing of the plugin, and gets nothing (spec: Performance,
	 * nothing loads on pages without a builder).
	 */
	private function holds(): bool {
		$cart = WC()->cart;
		foreach ( null === $cart ? array() : $cart->get_cart() as $item ) {
			if ( null !== Build::read( $item[ Options::CART_BUILD ] ?? null ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Put the styles in the page, once, and load the script where it is wanted. A checkout that was never built
	 * has no script; the server keeps a build whole without it, and the zip is checked for it.
	 *
	 * @param bool $script Whether the script is wanted too.
	 */
	private function assets( bool $script ): void {
		if ( ! $this->holds() ) {
			return;
		}
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			// A handle with no file: WordPress prints what is added to it and asks for nothing.
			wp_register_style( self::HANDLE, false, array(), CBUILD_VERSION );
			wp_add_inline_style( self::HANDLE, self::STYLES );
		}
		wp_enqueue_style( self::HANDLE );
		$built = $script && is_readable( CBUILD_PATH . self::BUILT . '.asset.php' ) ? require CBUILD_PATH . self::BUILT . '.asset.php' : null;
		if ( is_array( $built ) && is_string( $built['version'] ?? null ) ) {
			wp_enqueue_script( self::HANDLE, CBUILD_URL . self::BUILT . '.js', array( 'wc-blocks-checkout' ), $built['version'], array( 'in_footer' => true ) );
		}
	}

	/**
	 * A part's line says what it is part of, where a cart prints a line's data.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $data The line's data so far.
	 * @param mixed $item The cart item.
	 */
	public function data( mixed $data, mixed $item = null ): mixed {
		$told = is_array( $data ) && is_array( $item ) ? $this->told( $item ) : null;
		if ( null === $told || Options::ROLE_PART !== $told['role'] || ! is_array( $data ) ) {
			return $data;
		}
		/* translators: 1: a kit's name, such as Build a complete; 2: a slot's label, such as Deck. */
		$value  = sprintf( __( '%1$s (%2$s)', 'creative-skateboard-builder' ), $told['kit'], $told['label'] );
		$data[] = array(
			'key'     => __( 'Part of', 'creative-skateboard-builder' ),
			'value'   => $value,
			'display' => '<span class="cbuild-part">' . esc_html( $value ) . '</span>',
			'raw_key' => Options::CART_SLOT,
		);
		return $data;
	}

	/**
	 * A part's name in a classic cart: its slot's label, then the name.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $name The line's name, which may be a link.
	 * @param mixed $item The cart item.
	 */
	public function name( mixed $name, mixed $item = null ): mixed {
		$told = is_string( $name ) && is_array( $item ) ? $this->told( $item ) : null;
		if ( null === $told || Options::ROLE_PART !== $told['role'] || ! is_string( $name ) ) {
			return $name;
		}
		return self::named( esc_html( $told['label'] ), $name );
	}

	/**
	 * A Store API cart item's parent line, from WooCommerce 11.3.0: a part's is its kit's line. Any other line is
	 * given back as it came. WooCommerce itself drops a key that names no line in the cart.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $key  The parent's cart key so far.
	 * @param mixed $item The cart item.
	 */
	public function parent( mixed $key, mixed $item = null ): mixed {
		$kit = is_array( $item ) ? ( $item[ Options::CART_PARENT ] ?? null ) : null;
		return is_string( $kit ) && '' !== $kit ? $kit : $key;
	}

	/**
	 * A line's row in a classic cart and in the classic mini-cart widget: a class for a kit's line and one for a
	 * part's.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $classes The row's classes.
	 * @param mixed $item    The cart item.
	 */
	public function row( mixed $classes, mixed $item = null ): mixed {
		$told = is_string( $classes ) && is_array( $item ) ? $this->told( $item ) : null;
		return null === $told || ! is_string( $classes ) ? $classes : $classes . ' cbuild-' . $told['role'];
	}

	/**
	 * A part's quantity in a classic cart: the number, with no field to change it by.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $field The quantity field.
	 * @param mixed $key   The line's cart key.
	 * @param mixed $item  The cart item.
	 */
	public function quantity( mixed $field, mixed $key = '', mixed $item = null ): mixed {
		unset( $key );
		$told = is_array( $item ) ? $this->told( $item ) : null;
		if ( null === $told || Options::ROLE_PART !== $told['role'] ) {
			return $field;
		}
		return '<span class="cbuild-quantity">' . esc_html( number_format_i18n( Cast::int( $item['quantity'] ?? 0 ) ) ) . '</span>';
	}

	/**
	 * A part's remove link in a classic cart: none. The kit's line removes the build.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $link The link.
	 * @param mixed $key  The line's cart key.
	 */
	public function remove( mixed $link, mixed $key = '' ): mixed {
		$item = is_string( $key ) ? WC()->cart->get_cart_item( $key ) : array();
		$told = array() === $item ? null : $this->told( $item );
		return null !== $told && Options::ROLE_PART === $told['role'] ? '' : $link;
	}

	/**
	 * Give the Store API's cart items the build's keys, under the plugin's namespace.
	 *
	 * @since 0.5.0
	 */
	public function extend(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'cart-item',
				'namespace'       => self::SPACE,
				'data_callback'   => array( $this, 'extension' ),
				'schema_callback' => array( self::class, 'schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * What the Store API says of a line under the plugin's namespace.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $item The cart item.
	 * @return array{role: string, build: string, parent: string, slot: string, name: string}
	 */
	public function extension( mixed $item = null ): array {
		$told    = is_array( $item ) ? $this->told( $item ) : null;
		$product = is_array( $item ) ? ( $item['data'] ?? null ) : null;
		if ( null === $told ) {
			return array(
				'role'   => '',
				'build'  => '',
				'parent' => '',
				'slot'   => '',
				'name'   => '',
			);
		}
		return array(
			'role'   => $told['role'],
			'build'  => $told['build'],
			'parent' => $told['parent'],
			'slot'   => $told['slot'],
			'name'   => Options::ROLE_PART === $told['role'] && $product instanceof WC_Product ? wp_kses_post( self::named( esc_html( $told['label'] ), $product->get_title() ) ) : '',
		);
	}

	/**
	 * The schema of what `extension()` gives.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array{description: string, type: string, context: list<string>, readonly: bool}>
	 */
	public static function schema(): array {
		$schema = array();
		foreach ( array(
			'role'   => __( 'The line\'s part in a build: kit, part, or empty for a line of no build.', 'creative-skateboard-builder' ),
			'build'  => __( 'The build\'s identifier.', 'creative-skateboard-builder' ),
			'parent' => __( 'For a part, its kit\'s cart item key.', 'creative-skateboard-builder' ),
			'slot'   => __( 'For a part, the key of the slot it fills.', 'creative-skateboard-builder' ),
			'name'   => __( 'For a part, its name with its slot\'s label.', 'creative-skateboard-builder' ),
		) as $key => $description ) {
			$schema[ $key ] = array(
				'description' => $description,
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		}
		return $schema;
	}

	/**
	 * A part's name with its slot's label.
	 *
	 * @param string $label The slot's label.
	 * @param string $name  The part's name, which may be a link.
	 */
	private static function named( string $label, string $name ): string {
		/* translators: 1: a slot's label, such as Deck; 2: the product's name, which may be a link. */
		return sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $label, $name );
	}

	/**
	 * What a cart item is of a build, or null for a line of none.
	 *
	 * @param array<array-key, mixed> $item The cart item.
	 * @return array{role: string, build: string, parent: string, slot: string, label: string, kit: string}|null
	 */
	private function told( array $item ): ?array {
		$build = Build::read( $item[ Options::CART_BUILD ] ?? null );
		if ( null !== $build ) {
			return array(
				'role'   => Options::ROLE_KIT,
				'build'  => $build->id,
				'parent' => '',
				'slot'   => '',
				'label'  => '',
				'kit'    => '',
			);
		}
		$parent = $item[ Options::CART_PARENT ] ?? null;
		$key    = $item[ Options::CART_SLOT ] ?? null;
		$line   = is_string( $parent ) ? WC()->cart->get_cart_item( $parent ) : array();
		$build  = Build::read( $line[ Options::CART_BUILD ] ?? null );
		if ( null === $build || ! is_string( $parent ) || ! is_string( $key ) ) {
			return null;
		}
		$slot    = ( $this->kits )()->kit( $build->kit )?->slots->get( $key );
		$product = $line['data'] ?? null;
		return array(
			'role'   => Options::ROLE_PART,
			'build'  => $build->id,
			'parent' => $parent,
			'slot'   => $key,
			'label'  => null === $slot ? $key : StateAnswer::label( $slot->label, $slot->key, $build->kit ),
			'kit'    => $product instanceof WC_Product ? $product->get_name() : '',
		);
	}
}
