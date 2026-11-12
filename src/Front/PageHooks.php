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
use WP_Post;

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
		add_action( 'template_redirect', array( $this, 'toBuilder' ), 10, 0 );
		// After an SEO plugin's own, such as Yoast SEO's at 15, whose title it keeps (D-149).
		add_filter( 'pre_get_document_title', array( $this, 'title' ), 20, 1 );
	}

	/**
	 * The page's title on a page with a build in its address: the builder's step before the page's own. The clean
	 * page keeps its own, for search.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $title The title an SEO plugin has written, or an empty string for WordPress's own.
	 * @return mixed
	 */
	public function title( mixed $title ): mixed {
		$sent = BuilderPage::sent();
		if ( PageTitle::asking() || ! is_array( $sent ) || array() === $sent || ! is_singular() ) {
			return $title;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $title;
		}
		$kit = Options::PRODUCT_TYPE_KIT === \WC_Product_Factory::get_product_type( $post->ID ) ? $post->ID : PageTitle::kit( parse_blocks( $post->post_content ) );
		if ( $kit < 1 ) {
			return $title;
		}
		$step = $this->page()->title( $kit, BuilderPage::requested(), (string) get_permalink( $post ) );
		if ( null === $step || '' === $step ) {
			return $title;
		}
		$around = PageTitle::around( PageTitle::base() );
		return esc_html( $around['before'] . $step . $around['after'] );
	}

	/**
	 * Send a visitor on a kit's page to the page its builder is on, with the build they came with (D-008, D-146).
	 *
	 * @since 0.6.0
	 */
	public function toBuilder(): void {
		$to = $this->elsewhere();
		// A 302: the shop may move its builder, and a browser that kept a 301 would not follow.
		if ( null !== $to && wp_safe_redirect( $to, 302, 'Creative Skateboard Builder' ) ) {
			exit;
		}
	}

	/**
	 * Where a visitor on a kit's page goes instead, or null where the builder is on the page they are on.
	 *
	 * @since 0.6.0
	 */
	public function elsewhere(): ?string {
		if ( ! is_singular( 'product' ) ) {
			return null;
		}
		$kit     = get_queried_object_id();
		$product = wc_get_product( $kit );
		if ( ! $product instanceof WC_Product || Options::PRODUCT_TYPE_KIT !== $product->get_type() ) {
			return null;
		}
		$url = BuilderUrl::of( $kit );
		if ( (string) get_permalink( $kit ) === $url ) {
			return null;
		}
		$sent = BuilderPage::sent();
		return is_array( $sent ) && array() !== $sent ? add_query_arg( array( BuildLink::PARAM => urlencode_deep( $sent ) ), $url ) : $url;
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
