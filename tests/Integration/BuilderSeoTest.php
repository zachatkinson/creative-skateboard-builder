<?php
/**
 * The builder's pages and search engines.
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
 * Spec, SEO and sharing: the clean builder page is indexable with its own canonical; a page carrying a build in its
 * address gets `noindex, follow` and a canonical to the clean page, so search engines index one page however many
 * builds are shared. Without an SEO plugin, WordPress prints both; `BuilderYoastTest` runs the same with Yoast SEO.
 */
final class BuilderSeoTest extends IntegrationTestCase {

	/**
	 * A page holding the Builder block.
	 *
	 * @var int
	 */
	private int $page;

	/**
	 * The kit.
	 *
	 * @var int
	 */
	private int $kit;

	public function set_up(): void {
		parent::set_up();
		$this->kit  = KitFactory::kit( 'Complete' )->get_id();
		$this->page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $this->kit . '} /-->',
			)
		);
		( new SeoHooks() )->register();
	}

	public function tear_down(): void {
		unset( $_GET[ BuildLink::PARAM ] );
		remove_all_filters( 'wp_robots' );
		remove_all_filters( 'get_canonical_url' );
		add_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );
		parent::tear_down();
	}

	/**
	 * Visits a page, with a build in its address or none.
	 *
	 * @param int                       $post  The page.
	 * @param array<string, string>|null $build The build, under `cbuild`.
	 */
	private function visit( int $post, ?array $build = null ): void {
		$url = (string) get_permalink( $post );
		$this->go_to( null === $build ? $url : add_query_arg( array( BuildLink::PARAM => $build ), $url ) );
		unset( $_GET[ BuildLink::PARAM ] );
		if ( null !== $build ) {
			$_GET[ BuildLink::PARAM ] = $build;
		}
	}

	/**
	 * What WordPress prints for robots.
	 */
	private static function robots(): string {
		ob_start();
		wp_robots();
		return (string) ob_get_clean();
	}

	/**
	 * What WordPress prints for the canonical.
	 */
	private static function canonical(): string {
		ob_start();
		rel_canonical();
		return (string) ob_get_clean();
	}

	public function test_the_clean_builder_page_is_indexable_with_its_own_canonical(): void {
		$this->visit( $this->page );
		self::assertStringNotContainsString( 'noindex', self::robots() );
		self::assertSame( '<link rel="canonical" href="' . get_permalink( $this->page ) . '" />' . "\n", self::canonical() );
	}

	public function test_a_build_in_the_address_is_noindex_follow_with_the_clean_page_as_canonical(): void {
		$this->visit(
			$this->page,
			array(
				'deck' => '12',
				'step' => 'trucks',
			)
		);
		self::assertStringContainsString( 'noindex, follow', self::robots() );
		self::assertSame( '<link rel="canonical" href="' . get_permalink( $this->page ) . '" />' . "\n", self::canonical() );
	}

	public function test_a_build_link_that_reads_as_nothing_is_still_a_state_of_the_builder(): void {
		$this->visit( $this->page, array( 'deck' => 'x' ) );
		self::assertStringContainsString( 'noindex, follow', self::robots(), 'Whatever it holds, the address is not the clean page.' );
	}

	public function test_a_kits_own_page_with_a_build_is_noindex_too(): void {
		$this->visit( $this->kit, array( 'deck' => '12' ) );
		self::assertStringContainsString( 'noindex, follow', self::robots() );
	}

	public function test_a_page_without_a_builder_is_left_alone_whatever_its_address(): void {
		$plain = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$this->visit( $plain, array( 'deck' => '12' ) );
		self::assertStringNotContainsString( 'noindex', self::robots() );
	}

	public function test_the_hooks_say_the_same_to_another_seo_plugins_canonical_through_wordpress(): void {
		$this->visit( $this->page, array( 'deck' => '12' ) );
		$dirty = add_query_arg( array( BuildLink::PARAM => array( 'deck' => '12' ) ), (string) get_permalink( $this->page ) );
		self::assertSame( get_permalink( $this->page ), apply_filters( 'get_canonical_url', $dirty, get_post( $this->page ) ), 'A canonical another filter wrote with the build in it is made clean.' );
	}
}
