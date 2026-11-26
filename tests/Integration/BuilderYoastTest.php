<?php
/**
 * The builder's pages and Yoast SEO.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\SeoHooks;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * Yoast SEO prints its own robots and canonical, and merges its robots over WordPress's at the last priority but ten
 * (`src/integrations/front-end/wp-robots-integration.php`), so what `BuilderSeoTest` proves without it has to hold
 * through Yoast's own filter too. Yoast prints no canonical on a noindex page (spec: SEO and sharing, "With Yoast or another SEO plugin active, the tags go
 * through its filters"). Runs with Yoast SEO loaded, in `composer test:integration:yoast` (D-102).
 */
final class BuilderYoastTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( '1' !== getenv( 'CBUILD_YOAST' ) ) {
			self::markTestSkipped( 'Runs with Yoast SEO loaded: composer test:integration:yoast.' );
		}
		// Yoast saves no indexable outside production (`src/helpers/indexable-helper.php:210`), and then reads one with
		// no ID, which PHP 8.5 deprecates. A shop runs in production; the test site says it does.
		add_filter( 'Yoast\\WP\\SEO\\should_index_indexables', '__return_true' );
		( new SeoHooks( $this->service( \CreativeBranding\SkateboardBuilder\Front\BuilderPlace::class ) ) )->register();
	}

	public function tear_down(): void {
		unset( $_GET[ BuildLink::PARAM ] );
		parent::tear_down();
	}

	/**
	 * What the page's head says, with Yoast's presenters in it.
	 *
	 * @param int                        $post  The page.
	 * @param array<string, string>|null $build The build, under `cbuild`.
	 */
	private function head( int $post, ?array $build = null ): string {
		$url = (string) get_permalink( $post );
		$this->go_to( null === $build ? $url : add_query_arg( array( BuildLink::PARAM => $build ), $url ) );
		unset( $_GET[ BuildLink::PARAM ] );
		if ( null !== $build ) {
			$_GET[ BuildLink::PARAM ] = $build;
		}
		// Yoast keeps one page's tags for the whole request (`src/memoizers/meta-tags-context-memoizer.php:167`), and a
		// test visits several pages in one; a shop's visitor makes one request a page.
		YoastSEO()->classes->get( \Yoast\WP\SEO\Memoizers\Meta_Tags_Context_Memoizer::class )->clear();
		ob_start();
		do_action( 'wp_head' );
		return (string) ob_get_clean();
	}

	public function test_yoast_says_noindex_follow_with_no_canonical_and_the_build_link_as_og_url_for_a_build(): void {
		self::assertTrue( function_exists( 'YoastSEO' ), 'Yoast SEO is loaded' );
		$kit  = KitFactory::kit( 'Complete' )->get_id();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $kit . '} /-->',
			)
		);

		$clean = $this->head( $page );
		self::assertDoesNotMatchRegularExpression( "/<meta name='robots' content='[^']*noindex/", $clean );
		self::assertStringContainsString( '<link rel="canonical" href="' . get_permalink( $page ) . '"', $clean );

		$built = $this->head(
			$page,
			array(
				'deck' => '12',
				'step' => 'trucks',
			)
		);
		self::assertMatchesRegularExpression( "/<meta name='robots' content='noindex, follow/", $built );
		// Yoast prints no canonical on a noindex page (`src/presenters/canonical-presenter.php:38`), so that the two
		// never disagree. A complete build's og:url is the build link (D-200, ShareCardYoastTest); this one has a deck
		// alone, which since 0.8.0 is no complete build (D-213), so it keeps the kit's own card and its clean og:url
		// (D-212).
		self::assertStringNotContainsString( 'rel="canonical"', $built );
		self::assertStringContainsString( '<meta property="og:url" content="' . esc_url( (string) get_permalink( $page ) ) . '"', $built );
	}

	/**
	 * #88: a build link keeps a `nofollow` another source gave Yoast.
	 */
	public function test_yoast_keeps_a_nofollow_another_source_set_on_a_build_link(): void {
		$kit  = KitFactory::kit( 'Complete' )->get_id();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $kit . '} /-->',
			)
		);
		add_filter(
			'wpseo_robots_array',
			static function ( array $robots ): array {
				$robots['follow'] = 'nofollow';
				return $robots;
			}
		);
		$built = $this->head( $page, array( 'deck' => '12' ) );
		self::assertMatchesRegularExpression( "/<meta name='robots' content='noindex, nofollow/", $built );
	}

	public function test_yoasts_title_keeps_its_own_with_the_builders_step_before_it_once(): void {
		$kit  = KitFactory::kit( 'Complete' )->get_id();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Build a complete',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $kit . '} /-->',
			)
		);
		$clean = $this->head( $page );
		self::assertMatchesRegularExpression( '#<title>Build a complete - Test Blog</title>#', $clean, 'Yoast\'s own, for the clean page' );
		$built = $this->head( $page, array( 'step' => 'deck' ) );
		self::assertMatchesRegularExpression( '#<title>Step 1 of \d+: Deck - Build a complete - Test Blog</title>#', $built );
		// A theme with title-tag support asks WordPress, whose filter Yoast answers by asking WordPress again.
		self::assertMatchesRegularExpression( '#^Step 1 of \d+: Deck - Build a complete - Test Blog$#', wp_get_document_title() );
	}

	/**
	 * The `@type` of every node in the page's JSON-LD: Yoast's graph in the head, and WooCommerce's in the footer,
	 * which it makes on a product's page at `woocommerce_single_product_summary` (WooCommerce 11.3.1,
	 * `includes/class-wc-structured-data.php:42`) and prints at `wp_footer` (:48). The test makes the product's
	 * markup as that action would, with a structured data object of its own.
	 *
	 * @param int      $post    The page.
	 * @param int|null $product The product the page shows, or null for a page that shows none.
	 * @return list<string>
	 */
	private function types( int $post, ?int $product = null ): array {
		$html = $this->head( $post );
		$data = new \WC_Structured_Data();
		if ( null !== $product ) {
			$data->generate_product_data( wc_get_product( $product ) );
		}
		ob_start();
		echo apply_filters( 'the_content', (string) get_post_field( 'post_content', $post ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- read, not printed.
		$data->output_structured_data();
		$html .= (string) ob_get_clean();

		$types = array();
		preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $scripts );
		self::assertNotEmpty( $scripts[1], 'the page prints JSON-LD' );
		foreach ( $scripts[1] as $json ) {
			$graph = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
			array_walk_recursive(
				$graph,
				static function ( mixed $value, mixed $key ) use ( &$types ): void {
					if ( '@type' === $key && is_string( $value ) ) {
						$types[] = $value;
					}
				}
			);
		}
		return $types;
	}

	/**
	 * #141: with Yoast SEO and WooCommerce's structured data both, a kit's page and the Builder block's page print no
	 * Product, and a part's page keeps WooCommerce's. Yoast SEO 28.8 makes no Product piece of its own (its pieces are
	 * `src/generators/schema/`), so the kit's comes only from WooCommerce, which `KitHooks::markup()` empties (D-100).
	 */
	public function test_with_yoast_a_kits_page_and_the_builder_page_print_no_product_and_a_parts_page_keeps_it(): void {
		$kit  = KitFactory::kit( 'Complete' )->get_id();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $kit . '} /-->',
			)
		);
		$part = new \WC_Product_Simple();
		$part->set_name( 'A deck' );
		$part->set_status( 'publish' );
		$part->set_regular_price( '50' );
		$part->save();

		$kitTypes = $this->types( $kit, $kit );
		self::assertContains( 'WebPage', $kitTypes, "Yoast's graph is on the kit's page" );
		self::assertNotContains( 'Product', $kitTypes, "the kit's page" );
		$pageTypes = $this->types( $page );
		self::assertContains( 'WebPage', $pageTypes, "Yoast's graph is on the Builder page" );
		self::assertNotContains( 'Product', $pageTypes, 'the Builder page' );
		self::assertContains( 'Product', $this->types( $part->get_id(), $part->get_id() ), "a part's page keeps its markup" );
	}

	public function test_yoasts_own_robots_tag_says_noindex_where_wordpress_prints_none(): void {
		// A theme or plugin that takes wp_robots out of the head leaves the robots tag to Yoast's own presenter
		// (`src/integrations/front-end-integration.php:432`).
		remove_action( 'wp_head', 'wp_robots', 1 );
		$kit  = KitFactory::kit( 'Complete' )->get_id();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $kit . '} /-->',
			)
		);
		$built = $this->head( $page, array( 'deck' => '12' ) );
		add_action( 'wp_head', 'wp_robots', 1 );

		self::assertMatchesRegularExpression( '/<meta name="robots" content="noindex, follow/', $built );
	}
}
