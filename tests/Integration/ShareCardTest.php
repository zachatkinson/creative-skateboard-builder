<?php
/**
 * A build link's share card, printed by the plugin where no SEO plugin is active.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Front\BuilderPage;
use CreativeBranding\SkateboardBuilder\Front\BuilderPlace;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\SeoHooks;
use CreativeBranding\SkateboardBuilder\Front\ShareCard;
use CreativeBranding\SkateboardBuilder\Front\ShareHooks;
use CreativeBranding\SkateboardBuilder\Hooks\PictureHooks;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;
use WP_Locale_Switcher;

/**
 * Spec, SEO: "A build link's page carries Open Graph and X card tags with the build's composed picture, its name and
 * its total. `og:url` is the build link, so the card shows that build, while `rel=canonical` stays the clean page."
 * "With Yoast or another SEO plugin active, the tags go through its filters. With none, the plugin prints them
 * itself." Plan: the image "falls back to a file that exists, checked by a request in the test" (D-200).
 * `BuilderYoastTest` runs the card with Yoast SEO.
 */
final class ShareCardTest extends CartTestCase {

	/**
	 * The deck's layer image.
	 */
	private int $layer;

	public function set_up(): void {
		parent::set_up();
		$this->service( PictureHooks::class )->register();
		( new SeoHooks( $this->service( BuilderPlace::class ) ) )->register();
		$this->layer = LayerFiles::attachment( 420, 1626 );
		$deck        = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) $this->layer );
		$deck->save();
	}

	public function tear_down(): void {
		unset( $_GET[ BuildLink::PARAM ] );
		remove_all_actions( 'wp_head' );
		remove_all_filters( 'get_canonical_url' );
		remove_all_filters( 'wp_robots' );
		add_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );
		as_unschedule_all_actions( Options::JOB_COMPOSE_PICTURE, array(), Options::QUEUE_GROUP );
		as_unschedule_all_actions( Options::JOB_PRUNE_PICTURES, array(), Options::QUEUE_GROUP );
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		parent::tear_down();
	}

	/**
	 * The share card's hooks, as the plugin builds them, or with other SEO plugins' constants named.
	 *
	 * @param list<string> $others The constants that tell another SEO plugin is active.
	 */
	private function hooks( array $others = ShareHooks::OTHERS ): ShareHooks {
		return new ShareHooks(
			$this->service( BuilderPlace::class ),
			fn (): BuilderPage => $this->service( BuilderPage::class ),
			fn (): ShareCard => $this->service( ShareCard::class ),
			$others
		);
	}

	/**
	 * The `<meta>` tags the card's hooks print in the kit's page's head, by name, with a build in its address or none.
	 *
	 * @param array<string, mixed>|null $build The build, under `cbuild`.
	 * @param ShareHooks|null           $hooks The hooks, or the plugin's own.
	 * @return array<string, list<string>>
	 */
	private function head( ?array $build, ?ShareHooks $hooks = null ): array {
		$url = (string) get_permalink( $this->kit );
		$this->go_to( null === $build ? $url : add_query_arg( array( BuildLink::PARAM => $build ), $url ) );
		unset( $_GET[ BuildLink::PARAM ] );
		if ( null !== $build ) {
			$_GET[ BuildLink::PARAM ] = $build;
		}
		ob_start();
		( $hooks ?? $this->hooks() )->tags();
		$html = (string) ob_get_clean();
		preg_match_all( '#<meta (?:property|name)="([^"]+)" content="([^"]*)" />#', $html, $found, PREG_SET_ORDER );
		$tags = array();
		foreach ( $found as [ , $name, $content ] ) {
			$tags[ $name ][] = html_entity_decode( $content, ENT_QUOTES );
		}
		return $tags;
	}

	public function test_a_build_links_page_carries_its_name_total_build_link_and_the_decks_layer_until_the_picture_is_made(): void {
		$build = array_map( 'strval', $this->picks() ) + array( 'step' => 'review' );
		$tags  = $this->head( $build );
		self::assertSame( array( "8.5″ complete: 8.5″ and 54\u{00A0}mm" ), $tags['og:title'], 'the deck\'s width heads the name; the trucks and wheels by their sizes, as these have no brand' );
		self::assertSame( $tags['og:title'], $tags['twitter:title'] );
		self::assertSame( array( 'Total: $183.00' ), $tags['og:description'], 'the total: the parts at the shop\'s prices, a truck sold singly counted twice' );
		self::assertSame( array( 'website' ), $tags['og:type'] );

		$link = $tags['og:url'][0];
		self::assertSame( (string) get_permalink( $this->kit ), remove_query_arg( BuildLink::PARAM, $link ), 'the build link: the clean page with the build in its address' );
		self::assertNotSame( (string) get_permalink( $this->kit ), $link );
		wp_parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
		self::assertSame( array_map( 'strval', $this->picks() ), array_intersect_key( $query['cbuild'] ?? array(), $this->picks() ), 'the picks, so the card shows this build' );
		self::assertSame( array( (string) wp_get_attachment_url( $this->layer ) ), $tags['og:image'], 'the deck\'s layer, until the picture is made' );
		self::assertSame( array( array( '420' ), array( '1626' ), array( 'image/png' ) ), array( $tags['og:image:width'], $tags['og:image:height'], $tags['og:image:type'] ) );
		self::assertFileExists( (string) get_attached_file( $this->layer ), 'a file that is there' );
		self::assertSame( array( 'summary_large_image' ), $tags['twitter:card'] );
		self::assertCount( 1, self::waiting( Options::JOB_COMPOSE_PICTURE ), 'the page asked for the picture' );

		self::work();
		$tags = $this->head( $build );
		self::assertMatchesRegularExpression( '#/creative-skateboard-builder/pictures/[0-9a-f]{32}\.webp$#', $tags['og:image'][0] );
		self::assertFileExists( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . basename( $tags['og:image'][0] ), 'a file that is there' );
		self::assertSame( array( array( '1200' ), array( '630' ), array( 'image/webp' ) ), array( $tags['og:image:width'], $tags['og:image:height'], $tags['og:image:type'] ) );
		self::assertSame( $tags['og:image'], $tags['twitter:image'] );
	}

	public function test_the_clean_page_a_build_that_cannot_go_to_the_cart_and_another_seo_plugin_get_no_card_or_no_ask(): void {
		self::assertSame( array(), $this->head( null ), 'the clean page keeps what the theme and WordPress print' );

		$sold = wc_get_product( $this->parts['wheels'] );
		$sold->set_stock_status( 'outofstock' );
		$sold->save();
		$short = $this->head( array_map( 'strval', $this->picks() ) );
		self::assertArrayHasKey( 'og:url', $short, 'a build with a part sold out still has its card' );
		self::assertSame( array(), self::waiting( Options::JOB_COMPOSE_PICTURE ), 'but no picture is asked for until it could go to the cart' );

		define( 'CBUILD_TEST_OTHER_SEO', '1' );
		self::assertSame( array(), $this->head( array_map( 'strval', $this->picks() ), $this->hooks( array( 'CBUILD_TEST_OTHER_SEO' ) ) ), 'another SEO plugin prints its own tags, and the plugin no second set' );
		self::assertSame( 'other', $this->hooks( array( 'CBUILD_TEST_OTHER_SEO' ) )->seoPlugin() );
		self::assertNull( $this->hooks()->seoPlugin(), 'none in this site' );
	}

	public function test_the_cards_name_and_total_are_in_the_pages_language(): void {
		add_filter( 'get_available_languages', static fn( array $languages ): array => array_merge( $languages, array( 'fr_CA' ) ) );
		$french = array(
			'%1$s complete: %2$s' => 'Planche complète de %1$s : %2$s',
			'Total: %s'           => 'Total : %s',
		);
		add_filter(
			'gettext_creative-skateboard-builder',
			static fn( string $translation, string $text ): string => 'fr_CA' === determine_locale() ? ( $french[ $text ] ?? $translation ) : $translation,
			10,
			2
		);
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		self::assertTrue( switch_to_locale( 'fr_CA' ) );
		$tags = $this->head( array_map( 'strval', $this->picks() ) );
		restore_previous_locale();
		self::assertStringStartsWith( 'Planche complète de 8.5″ : ', $tags['og:title'][0], 'the name from the pattern in the page\'s language' );
		self::assertSame( array( 'Total : $183.00' ), $tags['og:description'] );
	}

	public function test_the_canonical_stays_the_clean_page_beside_the_card(): void {
		$this->head( array_map( 'strval', $this->picks() ) );
		self::assertSame( (string) get_permalink( $this->kit ), wp_get_canonical_url( $this->kit ) );
	}
}
