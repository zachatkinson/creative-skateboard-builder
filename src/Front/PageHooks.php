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
	 * The block template kits take in a block theme: a slug no product can be read as, since a product's own
	 * template is `single-product-<slug>` (spec: Where it renders).
	 *
	 * @since 1.0.0
	 */
	public const string TEMPLATE = 'cbuild-kit';

	/**
	 * A kit's own page (D-174). The server leaves out a classic template's gallery, and these widen its summary
	 * column; a block template's gallery column is hidden and the builder's widened. Each rule outweighs the theme's
	 * own column rule, and a block column's width is an inline style, which only `!important` outweighs. They are
	 * the theme's and WooCommerce's classes, so they go with the kit's page alone, not in the builder's stylesheet.
	 */
	private const KIT_PAGE = 'body.cbuild-kit-page div.product div.summary.entry-summary{float:none;clear:both;inline-size:auto;max-inline-size:none;margin-inline:0}'
		. '.cbuild-kit-page .wp-block-columns:has(.cbuild)>.wp-block-column:has(.wp-block-woocommerce-product-image-gallery){display:none}'
		. '.cbuild-kit-page .wp-block-columns:has(.cbuild)>.wp-block-column:has(.cbuild){flex-basis:100%!important}'
		// Kadence Shop Kit's product template: a Kadence Blocks row, by its blocks' classes and never the row's own ID,
		// which is the template's (Cowork's recheck, finding 2; D-184). The template centres the buy box with auto
		// margins, which shrink a grid item to its content, so they go too (Cowork's check of 0.6.0, item 2).
		. '.cbuild-kit-page .kt-row-column-wrap:has(.cbuild)>.wp-block-kadence-column:has(.wp-block-kadence-wootemplate-blocks-gallery){display:none}'
		. '.cbuild-kit-page .kt-row-column-wrap:has(.cbuild)>.wp-block-kadence-column:has(.cbuild){grid-column:1/-1;flex-basis:100%;max-inline-size:none;margin-inline:0}';

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
	 * @param BuilderPlace           $place Which kit a page shows.
	 */
	public function __construct( private Closure $page, private BuilderPlace $place ) {}

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
		add_action( 'init', array( $this, 'template' ), 10, 0 );
		// WordPress 7.1.5, wp-includes/template.php:62 (`{$type}_template_hierarchy`), read by the block template's
		// resolver, wp-includes/block-template.php:153.
		add_filter( 'single_template_hierarchy', array( $this, 'hierarchy' ), 10, 1 );
		// WooCommerce 11.3.0: includes/wc-template-functions.php:2174, and the Add to Cart form block at
		// src/Blocks/BlockTypes/AddToCartForm.php:227. Each fires `woocommerce_<type>_add_to_cart`.
		add_action( 'woocommerce_' . Options::PRODUCT_TYPE_KIT . '_add_to_cart', array( $this, 'kitPage' ), 10, 0 );
		add_action( 'template_redirect', array( $this, 'toBuilder' ), 10, 0 );
		// Once the query is known and before the template: the kit's own page makes room for its builder.
		add_action( 'wp', array( $this, 'kitLayout' ), 10, 0 );
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
		$kit = $this->place->kit( $post->ID, $post->post_type );
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
	 * A kit's own page, where its builder is: the builder takes the content's width (Cowork's review, should-fix 2,
	 * D-174). A kit has no photo of its own, so a classic template's gallery and sale badge go, and the price under
	 * its title, which is the assembly fee and read as the board's (should-fix 3). The body class lets the page's
	 * styles widen the summary column, or a block template's column, and the builder's stylesheet goes in the head
	 * with them, so that nothing moves as the builder arrives.
	 *
	 * @since 0.6.0
	 */
	public function kitLayout(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$kit = $this->place->kit( get_queried_object_id(), 'product' );
		if ( $kit < 1 || null !== $this->elsewhere() ) {
			return;
		}
		// WooCommerce 11.3.0, includes/wc-template-hooks.php:40, :127 and :153.
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		// A classic theme's product page: the builder takes the page's width before the summary, and prints the
		// product's name as the page's h1 in place of the summary's title; the summary's form goes with it (spec:
		// Where it renders; D-238). WooCommerce 11.3.0, includes/wc-template-hooks.php:126 and :131.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		add_action( 'woocommerce_before_single_product_summary', array( $this, 'kitWide' ), 5, 0 );
		add_filter(
			'body_class',
			static function ( mixed $classes ): mixed {
				return is_array( $classes ) ? array( ...$classes, 'cbuild-kit-page' ) : $classes;
			}
		);
		// A block template prints the price through the Product Price block, which asks the product the same.
		add_filter(
			'woocommerce_get_price_html',
			static function ( mixed $html, mixed $product = null ) use ( $kit ): mixed {
				return $product instanceof WC_Product && $kit === $product->get_id() ? '' : $html;
			},
			10,
			2
		);
		wp_enqueue_style( BuilderPage::STYLE );
		wp_add_inline_style( BuilderPage::STYLE, self::KIT_PAGE );
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
		$kit = $this->place->kit( get_queried_object_id(), 'product' );
		if ( $kit < 1 ) {
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
	 * A classic theme's kit page: the builder full width before the summary, with the product's name as its h1.
	 *
	 * @since 1.0.0
	 */
	public function kitWide(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$kit = $product->get_id();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- as kitPage(): the HTML API's processing escapes every value.
		echo $this->page()->render( $kit, BuilderPage::requested(), (string) get_permalink( $kit ), true, true );
	}

	/**
	 * Register the template kits take in a block theme: the theme's header, the builder at the page's width with the
	 * product's name as the h1, the product's description, specs and reviews, and the theme's footer. The shop can
	 * edit it in the Site Editor (spec: Where it renders).
	 *
	 * @since 1.0.0
	 */
	public function template(): void {
		// WordPress 7.1.5, wp-includes/block-template.php:506.
		register_block_template(
			'creative-skateboard-builder//' . self::TEMPLATE,
			array(
				'title'       => __( 'Skateboard kit', 'creative-skateboard-builder' ),
				'description' => __( 'A kit product: its builder at the page\'s width, then its description, specs and reviews.', 'creative-skateboard-builder' ),
				'post_types'  => array( 'product' ),
				'content'     => self::markup(),
			)
		);
	}

	/**
	 * The kit template's block markup, from the plugin's own file.
	 */
	private static function markup(): string {
		ob_start();
		require CBUILD_PATH . 'templates/block/kit.php';
		return (string) ob_get_clean();
	}

	/**
	 * Put the kit template first for a kit's own page, in a block theme. A kit whose builder is on another page
	 * keeps the theme's own template, as the visitor is sent on.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $templates The template slugs, most specific first.
	 * @return mixed
	 */
	public function hierarchy( mixed $templates ): mixed {
		if ( ! is_array( $templates ) || ! wp_is_block_theme() || ! is_singular( 'product' ) ) {
			return $templates;
		}
		$kit = $this->place->kit( get_queried_object_id(), 'product' );
		if ( $kit < 1 || null !== $this->elsewhere() ) {
			return $templates;
		}
		return array( self::TEMPLATE, ...$templates );
	}

	/**
	 * The block's markup.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $attributes The block's attributes.
	 */
	public function renderBlock( mixed $attributes ): string {
		$attributes = Cast::map( $attributes );
		$kit        = Cast::int( $attributes['kit'] ?? null );
		// From 1.0.0, a block with no kit on a kit's own page, as the kit template has it, is that kit's builder.
		if ( $kit < 1 && is_singular( 'product' ) ) {
			$kit = $this->place->kit( get_queried_object_id(), 'product' );
		}
		if ( $kit < 1 ) {
			return '';
		}
		$base = is_singular() ? (string) get_permalink( get_queried_object_id() ) : (string) get_permalink( $kit );
		return $this->page()->render( $kit, BuilderPage::requested(), $base, false, Cast::bool( $attributes['title'] ?? null ) );
	}
}
