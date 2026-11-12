<?php
/**
 * Where the builder renders.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use Closure;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The builder's two ways onto a page (spec: The builder, ways in; Security, performance and compatibility): the kit's
 * own product page, where WooCommerce asks a product's type for its add-to-cart area, and the Builder block, which a
 * shop places on any page. Both call one renderer and hold nothing else.
 *
 * @since 0.6.0
 */
final class PageHooks implements Hookable {

	/**
	 * The block's name.
	 *
	 * @since 0.6.0
	 */
	public const string BLOCK = 'creative-skateboard-builder/builder';

	/**
	 * The renderer, once made.
	 *
	 * @var BuilderPage|null
	 */
	private ?BuilderPage $built = null;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param Closure(): BuilderPage $page Makes the renderer, the first time a page asks for a builder: a request that
	 *                                     shows none builds none of its services.
	 */
	public function __construct( private Closure $page ) {}

	/**
	 * The renderer, made once.
	 */
	private function page(): BuilderPage {
		$this->built ??= ( $this->page )();
		return $this->built;
	}

	/**
	 * Attach hooks.
	 *
	 * @since 0.6.0
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'block' ), 10, 0 );
		// WooCommerce 11.3.0: includes/wc-template-functions.php:2174, and the Add to Cart form block at
		// src/Blocks/BlockTypes/AddToCartForm.php:227. Each fires `woocommerce_<type>_add_to_cart`.
		add_action( 'woocommerce_' . Options::PRODUCT_TYPE_KIT . '_add_to_cart', array( $this, 'kitPage' ), 10, 0 );
	}

	/**
	 * Register the block from what the build wrote. A site with no built assets (a checkout of the source) has no
	 * block, and says nothing.
	 *
	 * @since 0.6.0
	 */
	public function block(): void {
		$built = CBUILD_PATH . 'build/builder/';
		if ( ! is_readable( $built . 'block.json' ) ) {
			return;
		}
		// The render is a callback, not the metadata's render file: the file would need the renderer from a service
		// locator, which the plugin does not have (docs/STANDARDS.md: PHP).
		register_block_type( $built, array( 'render_callback' => array( $this, 'renderBlock' ) ) );
	}

	/**
	 * The kit's own page: the builder in place of the add-to-cart form.
	 *
	 * @since 0.6.0
	 */
	public function kitPage(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$kit = $product->get_id();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template's every value is set by the HTML API's processing of its directives, which escapes each; the failure sentence is escaped where it is made.
		echo $this->page()->render( $kit, BuilderPage::requested(), (string) get_permalink( $kit ) );
	}

	/**
	 * The block's markup.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $attributes The block's attributes.
	 */
	public function renderBlock( mixed $attributes ): string {
		$kit = Cast::int( Cast::map( $attributes )['kit'] ?? null );
		if ( $kit < 1 ) {
			return '';
		}
		$base = is_singular() ? (string) get_permalink( get_queried_object_id() ) : (string) get_permalink( $kit );
		return $this->page()->render( $kit, BuilderPage::requested(), $base, false );
	}
}
