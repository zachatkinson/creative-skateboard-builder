<?php
/**
 * The builder's pages and search engines.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Hookable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec, SEO and sharing: builder state is reached by forms, so crawlers that do not send forms never enter it; a page
 * that carries a build in its address all the same, a shared link among them, gets `noindex, follow` and a canonical
 * to the clean page, so search engines index one page however many builds are shared.
 *
 * - **Which pages.** A singular page that holds a builder: a kit's own page, or a post with the Builder block in its
 *   content. A page with no builder is left alone whatever its address says.
 * - **Which addresses.** Any with a `cbuild` query, whatever it holds: an address that reads as no build is still not
 *   the clean page.
 * - **WordPress and Yoast SEO both.** WordPress prints robots from `wp_robots` and the canonical from
 *   `get_canonical_url`, whose permalink has no query already. Yoast merges its robots into `wp_robots`, keeping a
 *   `noindex`; where `wp_robots` is not in the head it prints its own, so the same is said through
 *   `wpseo_robots_array`. Its canonical is the post's permalink, with no query, and needs nothing.
 *
 * @since 0.6.0
 */
final class SeoHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param BuilderPlace $place Which kit a page shows.
	 */
	public function __construct( private BuilderPlace $place ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.6.0
	 */
	public function register(): void {
		add_filter( 'wp_robots', array( $this, 'robots' ), 20, 1 );
		add_filter( 'get_canonical_url', array( $this, 'canonical' ), 20, 1 );
		add_filter( 'wpseo_robots_array', array( $this, 'yoastRobots' ), 20, 1 );
	}

	/**
	 * WordPress's robots for a page with a build in its address: `noindex`, and `follow` unless something said `nofollow` (#88).
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $robots The directives, as `wp_robots` holds them.
	 * @return mixed The directives.
	 */
	public function robots( mixed $robots ): mixed {
		if ( ! is_array( $robots ) || ! $this->isState() ) {
			return $robots;
		}
		unset( $robots['index'] );
		$robots['noindex'] = true;
		// Follow, but where the site or another source said nofollow: a site that discourages search engines keeps it (#88).
		if ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
		return $robots;
	}

	/**
	 * Yoast SEO's robots for a page with a build in its address: `noindex`, and `follow` unless something said `nofollow` (#88).
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $robots The directives, as Yoast holds them: `index` => `noindex`, and so on.
	 * @return mixed The directives.
	 */
	public function yoastRobots( mixed $robots ): mixed {
		if ( ! is_array( $robots ) || ! $this->isState() ) {
			return $robots;
		}
		$robots['index'] = 'noindex';
		// As for WordPress's robots: a nofollow from elsewhere stays (#88).
		if ( 'nofollow' !== ( $robots['follow'] ?? null ) ) {
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/**
	 * The canonical of a page with a build in its address: the page without it.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $url The canonical, as WordPress or another filter has it.
	 * @return mixed The canonical.
	 */
	public function canonical( mixed $url ): mixed {
		return is_string( $url ) && '' !== $url && $this->isState() ? remove_query_arg( BuildLink::PARAM, $url ) : $url;
	}

	/**
	 * Whether this request is a page holding a builder, with a build in its address.
	 */
	private function isState(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- whether a query is there; nothing is read from it.
		if ( ! isset( $_GET[ BuildLink::PARAM ] ) || ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && $this->place->kit( $post->ID, $post->post_type ) > 0;
	}
}
