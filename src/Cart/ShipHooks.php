<?php
/**
 * A build's shipping.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildPackages;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a build's parts in one shipping package (spec: Pricing, stock, assembly and returns, Shipping). The kit is
 * virtual and is in no package; each part ships as the product it is, with its own weight and class. WooCommerce
 * puts a cart in one package, so this does something only where a shop's own code has split it.
 *
 * @since 0.5.0
 */
final class ShipHooks implements Hookable {

	/**
	 * After a shop's own code has split the cart.
	 */
	private const LATE = 100;

	/**
	 * Attach hooks.
	 *
	 * @since 0.5.0
	 */
	public function register(): void {
		// includes/class-wc-cart.php:1733.
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'packages' ), self::LATE, 1 );
	}

	/**
	 * The cart's shipping packages: each build that is to ship together has its parts in one.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $packages The packages.
	 */
	public function packages( mixed $packages ): mixed {
		if ( ! is_array( $packages ) || count( $packages ) < 2 ) {
			return $packages;
		}
		$builds = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$build = Build::read( $item[ Options::CART_BUILD ] ?? null );
			if ( null === $build ) {
				continue;
			}
			/**
			 * Whether a build's parts are kept in one shipping package where the shop's packages are split.
			 *
			 * @since 0.5.0
			 *
			 * @param bool $together Whether they are kept together: by default, where the shop assembles the build.
			 * @param int  $kit      The kit product's ID.
			 * @param bool $assembly Whether the shop assembles the build.
			 */
			if ( true === apply_filters( 'cbuild_build_in_one_package', $build->assembly, $build->kit, $build->assembly ) ) {
				$builds[] = Cast::strings( $item[ Options::CART_CHILDREN ] ?? null );
			}
		}
		return BuildPackages::together( $packages, $builds );
	}
}
