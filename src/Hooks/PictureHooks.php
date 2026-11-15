<?php
/**
 * Asks for a build's composed pictures and runs the job that draws them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit is added to the cart from the page, the Store API or Edit build, and the job runs wherever Action Scheduler
 * does, so these fire on any request (D-197). The add is heard after the cart's own hook, which throws for a build
 * that does not stay: only a build in the cart is asked for. Checked in WooCommerce 11.3.0:
 * `woocommerce_add_to_cart` with the line's key, the product and the line's data (includes/class-wc-cart.php:1491;
 * :1483 in 11.2.1).
 *
 * @since 0.7.0
 */
final class PictureHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param Closure(): ComposedPictures $pictures Builds the pictures' service, when a kit is added or the job runs.
	 */
	public function __construct( private Closure $pictures ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.7.0
	 */
	public function register(): void {
		add_action( 'woocommerce_add_to_cart', array( $this, 'added' ), 20, 6 );
		add_action( Options::JOB_COMPOSE_PICTURE, array( $this, 'compose' ), 10, 2 );
		add_action( Options::JOB_PRUNE_PICTURES, array( $this, 'prune' ), 10, 0 );
	}

	/**
	 * A line was added to the cart: a kit's asks for its build's pictures.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $key       The line's key.
	 * @param mixed $product   The product's ID.
	 * @param mixed $quantity  How many.
	 * @param mixed $variation The variation's ID.
	 * @param mixed $chosen    The variation's attributes.
	 * @param mixed $data      The line's data, the build under `cbuild` for a kit's.
	 */
	public function added( mixed $key, mixed $product, mixed $quantity, mixed $variation, mixed $chosen, mixed $data ): void {
		$build = Build::read( is_array( $data ) ? ( $data[ Options::CART_BUILD ] ?? null ) : null );
		if ( null !== $build ) {
			( $this->pictures )()->ask( $build->kit, $build->picks );
		}
	}

	/**
	 * The job that draws a build's pictures.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $kit   The kit product's ID.
	 * @param mixed $picks The part's ID for each slot filled.
	 *
	 * @throws \CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney When the build cannot be priced in the
	 *                                                                     shop's currency settings.
	 */
	public function compose( mixed $kit, mixed $picks ): void {
		if ( is_int( $kit ) && is_array( $picks ) ) {
			( $this->pictures )()->compose( $kit, $picks );
		}
	}

	/**
	 * The daily job that prunes the pictures.
	 *
	 * @since 0.7.0
	 */
	public function prune(): void {
		( $this->pictures )()->prune();
	}
}
