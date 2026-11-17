<?php
/**
 * A build link's share card: Open Graph and X card tags.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec, SEO: "Shared builds unfurl as themselves." On a page holding a builder with a build in its address, the card
 * names the build, gives its total and shows its picture, and `og:url` is the build link, while `rel=canonical` stays
 * the clean page (SeoHooks). The plan: "With Yoast, the tags go through its filters. With no SEO plugin, the plugin
 * prints its own. With another known SEO plugin (Rank Math, SEOPress, All in One SEO), the plugin prints nothing
 * rather than a second set of tags." (D-200.)
 *
 * Checked in Yoast SEO 28.8: `wpseo_opengraph_title`, `wpseo_opengraph_desc` and `wpseo_opengraph_url`
 * (src/presenters/open-graph/title-presenter.php:41, description-presenter.php:41, url-presenter.php:46);
 * `wpseo_frontend_presentation` with the presentation whose `open_graph_images` the image presenter prints, width,
 * height and type with each (src/integrations/front-end-integration.php:478; src/presenters/open-graph/
 * image-presenter.php:44-61). Its image filters add to the product's own images rather than replace them
 * (src/generators/open-graph-image-generator.php:89-160), so the list is replaced on the presentation. Yoast prints
 * no X title, description or image where Open Graph has them (src/presentations/indexable-presentation.php:593-662),
 * and X reads Open Graph's. The others are known by what their main file defines while it loads: Rank Math 1.0.282's
 * `RANK_MATH_VERSION` (rank-math.php:248), SEOPress 10.4's `SEOPRESS_VERSION` (seopress.php:42) and All in One SEO
 * 5.0.5.1's `AIOSEO_FILE` (all_in_one_seo_pack.php:74). Each prints Open Graph on every product page by default.
 *
 * @since 0.7.0
 */
final class ShareHooks implements Hookable {

	/**
	 * What the SEO plugins the card is left to define while their main file loads.
	 *
	 * @since 0.7.0
	 */
	public const OTHERS = array( 'RANK_MATH_VERSION', 'SEOPRESS_VERSION', 'AIOSEO_FILE' );

	/**
	 * The cards worked out in this request, by page and build: an array, or false for none.
	 *
	 * @var array<string, array{title: string, description: string, url: string, image: array{src: string, width: int, height: int, type: string}|null, waiting: bool}|false>
	 */
	private array $cards = array();

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param BuilderPlace           $place Which kit a page shows.
	 * @param Closure(): BuilderPage $page  Builds the page's builder, on a page with a build in its address.
	 * @param Closure(): ShareCard   $card  Builds the card, on such a page.
	 * @param list<string>           $others The constants that tell another SEO plugin is active; tests name their own.
	 */
	public function __construct( private BuilderPlace $place, private Closure $page, private Closure $card, private array $others = self::OTHERS ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.7.0
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'uncached' ), 10, 0 );
		add_action( 'wp_head', array( $this, 'tags' ), 5, 0 );
		add_filter( 'wpseo_opengraph_title', array( $this, 'title' ), 20, 1 );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'description' ), 20, 1 );
		add_filter( 'wpseo_opengraph_url', array( $this, 'url' ), 20, 1 );
		add_filter( 'wpseo_frontend_presentation', array( $this, 'presentation' ), 20, 1 );
	}

	/**
	 * Keep a build link's page out of page caches while its card falls back from its composed picture, which is not
	 * made yet: the page caches named in the review (WP Super Cache, W3 Total Cache, LiteSpeed Cache, WP Rocket)
	 * honour `DONOTCACHEPAGE`, and proxies the no-cache headers. Once the picture is there, the page caches as any
	 * other (Cowork's M7 review, F5; D-212). Run before the headers are sent, so whichever plugin prints the tags.
	 *
	 * @since 0.7.0
	 */
	public function uncached(): void {
		$this->keptUncached();
	}

	/**
	 * What `uncached()` does, with whether it kept the page out of caches: the constant, once defined, says nothing of
	 * a later page in the same process.
	 *
	 * @since 0.7.0
	 *
	 * @return bool Whether the page was kept out of caches.
	 */
	public function keptUncached(): bool {
		// Another SEO plugin prints its own card, which this one does not change.
		if ( 'other' === $this->seoPlugin() || true !== ( $this->card()['waiting'] ?? null ) ) {
			return false;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The page caches' own constant, which they read by this name.
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
		return true;
	}

	/**
	 * The plugin's own tags, where no SEO plugin prints any.
	 *
	 * @since 0.7.0
	 */
	public function tags(): void {
		if ( null !== $this->seoPlugin() ) {
			return;
		}
		$card = $this->card();
		if ( null === $card ) {
			return;
		}
		$tags = array(
			array( 'property', 'og:type', 'website' ),
			array( 'property', 'og:site_name', wp_strip_all_tags( get_bloginfo( 'name' ) ) ),
			array( 'property', 'og:title', $card['title'] ),
			array( 'property', 'og:description', $card['description'] ),
			array( 'property', 'og:url', $card['url'] ),
		);
		if ( null !== $card['image'] ) {
			$tags[] = array( 'property', 'og:image', $card['image']['src'] );
			$tags[] = array( 'property', 'og:image:width', $card['image']['width'] > 0 ? (string) $card['image']['width'] : '' );
			$tags[] = array( 'property', 'og:image:height', $card['image']['height'] > 0 ? (string) $card['image']['height'] : '' );
			$tags[] = array( 'property', 'og:image:type', $card['image']['type'] );
		}
		$tags[] = array( 'name', 'twitter:card', null === $card['image'] ? 'summary' : 'summary_large_image' );
		$tags[] = array( 'name', 'twitter:title', $card['title'] );
		$tags[] = array( 'name', 'twitter:description', $card['description'] );
		$tags[] = array( 'name', 'twitter:image', $card['image']['src'] ?? '' );
		foreach ( $tags as [ $attribute, $name, $content ] ) {
			if ( '' === $content ) {
				continue;
			}
			$escaped = str_starts_with( $name, 'og:url' ) || str_ends_with( $name, ':image' ) ? esc_url( $content ) : esc_attr( $content );
			printf( '<meta %1$s="%2$s" content="%3$s" />' . "\n", esc_attr( $attribute ), esc_attr( $name ), $escaped ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped just above, as a URL or an attribute.
		}
	}

	/**
	 * Yoast's Open Graph title.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $title Yoast's.
	 * @return mixed
	 */
	public function title( mixed $title ): mixed {
		return $this->card()['title'] ?? $title;
	}

	/**
	 * Yoast's Open Graph description.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $description Yoast's.
	 * @return mixed
	 */
	public function description( mixed $description ): mixed {
		$card = $this->card();
		return null === $card || '' === $card['description'] ? $description : $card['description'];
	}

	/**
	 * Yoast's `og:url`: the build link.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $url Yoast's, the clean page.
	 * @return mixed
	 */
	public function url( mixed $url ): mixed {
		return $this->card()['url'] ?? $url;
	}

	/**
	 * Yoast's presentation, with the card's image as its one Open Graph image.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $presentation Yoast's presentation of the page.
	 * @return mixed
	 */
	public function presentation( mixed $presentation ): mixed {
		$image = $this->card()['image'] ?? null;
		// Yoast's presentation says it has the property through its magic __isset(), before it is worked out.
		if ( null === $image || ! is_object( $presentation ) || ! isset( $presentation->open_graph_images ) ) {
			return $presentation;
		}
		$presentation->open_graph_images = array(
			$image['src'] => array(
				'url'    => $image['src'],
				'width'  => $image['width'],
				'height' => $image['height'],
				'type'   => $image['type'],
			),
		);
		return $presentation;
	}

	/**
	 * The SEO plugin that prints the page's Open Graph tags: `yoast`, `other` for one the plugin leaves the tags to,
	 * or null for none.
	 *
	 * @since 0.7.0
	 */
	public function seoPlugin(): ?string {
		if ( function_exists( 'YoastSEO' ) ) {
			return 'yoast';
		}
		foreach ( $this->others as $other ) {
			if ( defined( $other ) ) {
				return 'other';
			}
		}
		return null;
	}

	/**
	 * This request's card, or null where the page holds no builder with a build in its address.
	 *
	 * @return array{title: string, description: string, url: string, image: array{src: string, width: int, height: int, type: string}|null, waiting: bool}|null
	 */
	private function card(): ?array {
		$sent = BuilderPage::sent();
		$post = is_singular() && is_array( $sent ) && array() !== $sent ? get_queried_object() : null;
		$kit  = $post instanceof WP_Post ? $this->place->kit( $post->ID, $post->post_type ) : 0;
		if ( ! $post instanceof WP_Post || $kit < 1 ) {
			return null;
		}
		$key = $post->ID . "\n" . wp_json_encode( $sent );
		if ( ! array_key_exists( $key, $this->cards ) ) {
			$this->cards[ $key ] = ( $this->page )()->card( $kit, BuilderPage::requested(), (string) get_permalink( $post ), ( $this->card )() ) ?? false;
		}
		return false === $this->cards[ $key ] ? null : $this->cards[ $key ];
	}
}
