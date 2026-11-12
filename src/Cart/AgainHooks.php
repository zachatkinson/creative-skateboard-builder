<?php
/**
 * A build ordered again.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildAgain;
use CreativeBranding\SkateboardBuilder\Service\BuildCart;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce's Order again, on an order with a build (D-137). WooCommerce fills the cart from the order's items
 * itself, one by one, and writes the lines without adding them (includes/class-wc-cart-session.php:632). So the
 * build is judged again here from the order's snapshot, as the shop is now, and its lines are given the keys that
 * make them one build. A build the shop would no longer sell, or one WooCommerce left a line out of, is not put
 * back at all, and the shopper is told once.
 *
 * @since 0.5.0
 */
final class AgainHooks implements Hookable {

	/**
	 * On a line while WooCommerce fills the cart, and on none after: the order's identifier of its build, or false
	 * for a line that is not to be put back.
	 */
	private const AGAIN = 'cbuild_again';

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Closure(): BuildCart $builds Builds the service, when an order is ordered again.
	 */
	public function __construct( private Closure $builds ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// includes/class-wc-cart-session.php:660.
		add_filter( 'woocommerce_order_again_cart_item_data', array( $this, 'data' ), 10, 2 );
		// includes/class-wc-cart-session.php:688. Before the cart's own check of a kit.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate' ), 5, 6 );
		// includes/class-wc-cart-session.php:731, with the cart by reference.
		add_action( 'woocommerce_ordered_again', array( $this, 'whole' ), 10, 3 );
	}

	/**
	 * An order item's cart item data. A kit's build is judged again and taken, or the line is marked to be left
	 * out; a part's line is marked with its build.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $data The data so far.
	 * @param mixed $item The order's item.
	 */
	public function data( mixed $data, mixed $item = null ): mixed {
		$id = $item instanceof WC_Order_Item_Product ? Cast::str( $item->get_meta( Options::ORDER_BUILD ) ) : '';
		if ( ! is_array( $data ) || ! $item instanceof WC_Order_Item_Product || '' === $id ) {
			return $data;
		}
		$data[ self::AGAIN ] = $id;
		if ( Options::ROLE_KIT !== $item->get_meta( Options::ORDER_ROLE ) ) {
			$data[ Options::CART_SLOT ] = Cast::str( $item->get_meta( Options::ORDER_SLOT ) );
			return $data;
		}
		$asked = BuildAgain::asked( $item->get_meta( Options::ORDER_SNAPSHOT ) );
		$build = null === $asked ? null : ( $this->builds )()->consider( $item->get_product_id(), $asked, $item->get_quantity() )->build;
		if ( null === $build ) {
			$data[ self::AGAIN ] = false;
			$this->tell();
			return $data;
		}
		$data[ Options::CART_BUILD ] = $build->toArray();
		return $data;
	}

	/**
	 * A line marked to be left out is left out, with nothing more said of it.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $passed    Whether it has passed so far.
	 * @param mixed $product   The product's ID.
	 * @param mixed $quantity  How many.
	 * @param mixed $variation The variation's ID.
	 * @param mixed $chosen    The variation's attributes.
	 * @param mixed $item      The cart item data.
	 */
	public function validate( mixed $passed, mixed $product = 0, mixed $quantity = 1, mixed $variation = 0, mixed $chosen = array(), mixed $item = array() ): mixed {
		unset( $product, $quantity, $variation, $chosen );
		return is_array( $item ) && false === ( $item[ self::AGAIN ] ?? null ) ? false : $passed;
	}

	/**
	 * WooCommerce has put back what it would: each whole build's lines are tied to one another, and every line of
	 * a build that is not whole is taken out.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $order The order's ID.
	 * @param mixed $items The order's items.
	 * @param mixed $cart  The cart's lines, which WooCommerce goes on with.
	 */
	public function whole( mixed $order, mixed $items, mixed &$cart ): void {
		unset( $order, $items );
		if ( ! is_array( $cart ) ) {
			return;
		}
		$kits = array();
		foreach ( $cart as $key => $line ) {
			$again = is_array( $line ) ? ( $line[ self::AGAIN ] ?? null ) : null;
			if ( is_string( $again ) && null !== Build::read( $line[ Options::CART_BUILD ] ?? null ) ) {
				$kits[ $again ] = (string) $key;
			}
		}
		$lines = array();
		foreach ( $cart as $key => $line ) {
			$again = is_array( $line ) ? ( $line[ self::AGAIN ] ?? null ) : null;
			if ( ! is_array( $line ) || ! is_string( $again ) ) {
				continue;
			}
			$build                  = Build::read( $line[ Options::CART_BUILD ] ?? null );
			$lines[ (string) $key ] = array(
				'build'  => $build,
				'parent' => null === $build ? ( $kits[ $again ] ?? '' ) : '',
				'slot'   => Cast::str( $line[ Options::CART_SLOT ] ?? '' ),
			);
		}
		$whole = BuildAgain::whole( $lines );
		foreach ( $whole['dropped'] as $key ) {
			unset( $cart[ $key ], $lines[ $key ] );
			$this->tell();
		}
		foreach ( $lines as $key => $line ) {
			$kept = Cast::map( $cart[ $key ] ?? null );
			unset( $kept[ self::AGAIN ] );
			if ( null === $line['build'] ) {
				$kept[ Options::CART_PARENT ] = $line['parent'];
			} else {
				$kept[ Options::CART_CHILDREN ] = $whole['children'][ $key ] ?? array();
			}
			$cart[ $key ] = $kept;
		}
	}

	/**
	 * Tell the shopper, once, that a build was not put back.
	 */
	private function tell(): void {
		$notice = esc_html__( 'A build from that order cannot be bought as it was, so none of it was added. Build it again to order it.', 'creative-skateboard-builder' );
		if ( ! wc_has_notice( $notice, 'error' ) ) {
			wc_add_notice( $notice, 'error' );
		}
	}
}
