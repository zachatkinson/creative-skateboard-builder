<?php
/**
 * What a build's lines cost in WooCommerce's cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildCart;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Cart;
use WC_Coupon;
use WC_Product;
use WeakMap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prices a build's lines before the cart's totals (spec: Pricing, stock, assembly and returns): the kit's line at
 * the assembly fee, each part at the shop's own price less the build discount. Says which of a build's lines a
 * coupon reaches, and counts a build once in the cart's count (D-009). Every figure is the server's: the price is
 * read from the line's product, and the discount from the shop's settings.
 *
 * @since 0.5.0
 */
final class PriceHooks implements Hookable {

	/**
	 * Each part's line's product this request has discounted: the price it had, and the price it was given. The
	 * totals are worked out many times in a request on the same objects, and the discount is taken once.
	 *
	 * @var WeakMap<WC_Product, array{string, string}>
	 */
	private WeakMap $given;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Closure(): BuildCart $builds Builds the service, when the cart holds a build.
	 */
	public function __construct( private Closure $builds ) {
		$this->given = new WeakMap();
	}

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// includes/class-wc-cart.php:1605. Late, so a price another plugin sets on the line is the one discounted.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'price' ), 999, 1 );
		// includes/class-wc-coupon.php:1023 and :1060.
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'coupon' ), 10, 4 );
		// templates/cart/cart.php:140, templates/cart/mini-cart.php:53.
		add_filter( 'woocommerce_cart_item_price', array( $this, 'shown' ), 10, 2 );
		// includes/class-wc-cart.php:738.
		add_filter( 'woocommerce_cart_contents_count', array( $this, 'count' ), 10, 1 );
	}

	/**
	 * Price each line of each build, before the totals are worked out.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $cart The cart.
	 */
	public function price( mixed $cart ): void {
		$items = $cart instanceof WC_Cart ? $cart->get_cart() : array();
		foreach ( $items as $item ) {
			$product = $item['data'] ?? null;
			$kit     = $items[ $item[ Options::CART_PARENT ] ?? '' ] ?? null;
			if ( ! $product instanceof WC_Product || ! isset( $kit[ Options::CART_BUILD ] ) ) {
				continue;
			}
			$decimals = wc_get_price_decimals();
			$now      = (string) $product->get_price();
			$was      = $this->given[ $product ] ?? null;
			// The price the shop asks: what the line's product says, unless that is what this class gave it.
			$asked   = null !== $was && $was[1] === $now ? $was[0] : $now;
			$minor   = (int) round( (float) $asked * 10 ** $decimals );
			$charged = ( $this->builds )()->charged( $minor );
			$product->set_price( $charged === $minor ? $asked : wc_format_decimal( $charged / 10 ** $decimals, $decimals ) );
			if ( $charged === $minor ) {
				unset( $this->given[ $product ] );
			} else {
				$this->given[ $product ] = array( $asked, (string) $product->get_price() );
			}
		}
	}

	/**
	 * Whether a coupon takes a part of a build. A part's line answers with its kit's line: a coupon that takes
	 * the kit takes its parts, one that leaves the kit out leaves them out, and a fixed product coupon lands
	 * where the shop's setting says.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $valid   What WooCommerce says of the product itself.
	 * @param mixed $product The product.
	 * @param mixed $coupon  The coupon.
	 * @param mixed $values  The cart item, or an order's item.
	 */
	public function coupon( mixed $valid, mixed $product = null, mixed $coupon = null, mixed $values = null ): mixed {
		unset( $product );
		$parent = is_array( $values ) ? ( $values[ Options::CART_PARENT ] ?? null ) : null;
		$line   = is_string( $parent ) ? WC()->cart->get_cart_item( $parent ) : array();
		$kit    = $line['data'] ?? null;
		if ( ! $coupon instanceof WC_Coupon || ! $kit instanceof WC_Product || null === Build::read( $line[ Options::CART_BUILD ] ?? null ) ) {
			return $valid;
		}
		$left = in_array( $kit->get_id(), $coupon->get_excluded_product_ids(), true )
			|| array() !== array_intersect( $kit->get_category_ids(), $coupon->get_excluded_product_categories() );
		return ( $this->builds )()->couponTakes( $coupon->is_type( 'fixed_product' ), true === $valid, $coupon->is_valid_for_product( $kit, $line ), $left );
	}

	/**
	 * A line's price as the classic cart and the Mini-Cart's template show it: a part with the build discount has
	 * its own price struck through before it.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $price The price's markup.
	 * @param mixed $item  The cart item.
	 */
	public function shown( mixed $price, mixed $item = null ): mixed {
		$product = is_array( $item ) ? ( $item['data'] ?? null ) : null;
		$was     = $product instanceof WC_Product ? ( $this->given[ $product ] ?? null ) : null;
		if ( ! $product instanceof WC_Product || null === $was || ! is_string( $price ) ) {
			return $price;
		}
		$with = WC()->cart->display_prices_including_tax();
		$own  = array( 'price' => $was[0] );
		// Each as the cart words a price, so the pair reads as WooCommerce's own sale price does.
		return wc_format_sale_price(
			wc_price( (float) ( $with ? wc_get_price_including_tax( $product, $own ) : wc_get_price_excluding_tax( $product, $own ) ) ),
			wc_price( (float) ( $with ? wc_get_price_including_tax( $product ) : wc_get_price_excluding_tax( $product ) ) )
		);
	}

	/**
	 * The cart's count: a build counts as its kit's line does, and its parts add nothing (D-009).
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $count The sum of every line's quantity.
	 */
	public function count( mixed $count ): mixed {
		if ( ! is_numeric( $count ) ) {
			return $count;
		}
		$items = WC()->cart->get_cart();
		foreach ( $items as $item ) {
			if ( isset( $items[ $item[ Options::CART_PARENT ] ?? '' ][ Options::CART_BUILD ] ) ) {
				$count -= $item['quantity'];
			}
		}
		return $count;
	}
}
