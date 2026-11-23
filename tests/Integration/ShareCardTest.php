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
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Rest\StateRoute;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
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
		self::assertSame( array( '8.5″ complete: Deck, Trucks, and Wheels' ), $tags['og:title'], 'the deck\'s width heads the name; each part by its product\'s name, as these have no brand (M1), joined as WordPress joins a list' );
		self::assertSame( $tags['og:title'], $tags['twitter:title'] );
		self::assertSame( array( 'Total: $183.00' ), $tags['og:description'], 'the total: the parts at the shop\'s prices, a truck sold singly counted twice' );
		self::assertSame( array( 'website' ), $tags['og:type'] );

		$link = $tags['og:url'][0];
		self::assertSame( (string) get_permalink( $this->kit ), remove_query_arg( BuildLink::PARAM, $link ), 'the build link: the clean page with the build in its address' );
		self::assertNotSame( (string) get_permalink( $this->kit ), $link );
		wp_parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
		self::assertSame( array_map( 'strval', $this->picks() ), array_intersect_key( $query['cbuild'] ?? array(), $this->picks() ), 'the picks, so the card shows this build' );
		// Cowork's M7 review, M6: the brackets encoded, so a strict parser reads the same link.
		self::assertStringContainsString( BuildLink::PARAM . '%5Bdeck%5D=', $link );
		self::assertStringNotContainsString( '[', $link );
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
		// Cowork's M7 review, M2 (D-212): a card would call the build complete and total what remains.
		self::assertSame( array(), $short, 'a build with a part sold out keeps the kit\'s own card, as the clean page' );
		self::assertSame( array(), self::waiting( Options::JOB_COMPOSE_PICTURE ), 'and no picture is asked for until it could go to the cart' );

		define( 'CBUILD_TEST_OTHER_SEO', '1' );
		self::assertSame( array(), $this->head( array_map( 'strval', $this->picks() ), $this->hooks( array( 'CBUILD_TEST_OTHER_SEO' ) ) ), 'another SEO plugin prints its own tags, and the plugin no second set' );
		self::assertSame( 'other', $this->hooks( array( 'CBUILD_TEST_OTHER_SEO' ) )->seoPlugin() );
		self::assertNull( $this->hooks()->seoPlugin(), 'none in this site' );
	}

	public function test_a_build_links_page_is_kept_out_of_page_caches_while_its_card_falls_back_and_not_after(): void {
		// Cowork's M7 review, F5 (D-212). The constant cannot be taken back in a process, and an earlier test's page
		// may have defined it, so the answer and the headers say what this page did.
		$build = array_map( 'strval', $this->picks() ) + array( 'step' => 'review' );
		$sent  = 0;
		add_filter(
			'nocache_headers',
			static function ( array $headers ) use ( &$sent ): array {
				++$sent;
				return $headers;
			}
		);
		$this->head( $build );
		self::work();
		$made = $this->hooks();
		$this->head( $build, $made );
		self::assertFalse( $made->keptUncached(), 'with its picture, the page caches as any other' );
		self::assertSame( 0, $sent );

		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		$falls = $this->hooks();
		$tags  = $this->head( $build, $falls );
		self::assertSame( array( (string) wp_get_attachment_url( $this->layer ) ), $tags['og:image'], 'the card falls back' );
		self::assertTrue( $falls->keptUncached() );
		self::assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
		self::assertSame( headers_sent() ? 0 : 1, $sent, 'the no-cache headers, where they can still be sent' );
	}

	public function test_a_build_with_nothing_to_draw_is_cached_as_any_page_and_asks_for_no_picture(): void {
		// The audit of the fixes: no part has a layer image, so no picture will ever be made. Waiting for one would keep a
		// shared link out of page caches for good, and queue a job on every view.
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->delete_meta_data( Options::META_LAYER );
		$deck->save();
		$sent = 0;
		add_filter(
			'nocache_headers',
			static function ( array $headers ) use ( &$sent ): array {
				++$sent;
				return $headers;
			}
		);
		$never = $this->hooks();
		$this->head( array_map( 'strval', $this->picks() ) + array( 'step' => 'review' ), $never );
		self::assertFalse( $never->keptUncached() );
		self::assertSame( 0, $sent );
		self::assertFalse( as_has_scheduled_action( Options::JOB_COMPOSE_PICTURE, null, Options::QUEUE_GROUP ) );
	}

	public function test_a_part_with_no_brand_is_named_by_its_product_on_the_card(): void {
		// Cowork's M7 review, M1 (D-212): a bare size read like a second deck width.
		$tags = $this->head( array_map( 'strval', $this->picks() ) );
		$name = $tags['og:title'][0];
		self::assertStringStartsWith( '8.5″ complete: ', $name );
		foreach ( array( 'deck', 'trucks', 'wheels' ) as $slot ) {
			self::assertStringContainsString( wc_get_product( $this->parts[ $slot ] )->get_name(), $name, $slot );
		}
	}

	public function test_a_branded_deck_is_named_by_its_brand_and_branded_trucks_by_brand_and_size(): void {
		// Cowork's M7 review, M1 (D-212): PICTURE.md and the code agree.
		foreach ( array( 'deck' => 'Baker', 'trucks' => 'Independent' ) as $slot => $brand ) {
			$term = wp_insert_term( $brand, 'product_brand' );
			self::assertIsArray( $term );
			wp_set_object_terms( $this->parts[ $slot ], array( (int) $term['term_id'] ), 'product_brand' );
		}
		$this->service( Kits::class )->forget( ...array_values( $this->parts ) );
		$tags = $this->head( array_map( 'strval', $this->picks() ) );
		self::assertSame( array( '8.5″ complete: Baker, Independent 8.5″, and Wheels' ), $tags['og:title'] );
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

	/**
	 * Spec, Share links: they carry "never a customer, an order or a saved build's ID". A saved build opened from My
	 * builds has `cbuild_saved` in its address; the card's `og:url` and the canonical leave it out.
	 */
	public function test_a_saved_builds_id_is_in_neither_og_url_nor_the_canonical(): void {
		$build = array_map( 'strval', $this->picks() );
		$url   = add_query_arg(
			array(
				BuildLink::PARAM                                               => $build,
				\CreativeBranding\SkateboardBuilder\Front\MyBuildsView::PARAM => '00000000-0000-4000-8000-000000000001',
			),
			(string) get_permalink( $this->kit )
		);
		$this->go_to( $url );
		$_GET[ BuildLink::PARAM ] = $build;
		$_GET[ \CreativeBranding\SkateboardBuilder\Front\MyBuildsView::PARAM ] = '00000000-0000-4000-8000-000000000001';
		ob_start();
		$this->hooks()->tags();
		$html = (string) ob_get_clean();
		self::assertMatchesRegularExpression( '#<meta property="og:url" content="[^"]+"#', $html );
		self::assertStringNotContainsString( 'cbuild_saved', $html );
		self::assertStringNotContainsString( '00000000-0000-4000-8000-000000000001', $html );
		self::assertStringNotContainsString( 'cbuild_saved', (string) wp_get_canonical_url( $this->kit ) );
		unset( $_GET[ \CreativeBranding\SkateboardBuilder\Front\MyBuildsView::PARAM ] );
	}

	// The audit of milestone 7, should-fix: the crawler's first fetch of a shared link was what asked for the picture,
	// so that fetch always got the stand-in. The review's answer, where the link is offered, asks for it.
	public function test_the_reviews_answer_asks_for_the_picture_so_a_shared_links_first_unfurl_can_be_it(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		rest_get_server();
		$this->service( StateRoute::class )->register();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );
		$ask = function ( string $step ): void {
			$request = new \WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
			// As the store asks it: with the build link it moves to.
			$request->set_query_params( array( BuildLink::PARAM => array_map( 'strval', $this->picks() ) + array( 'step' => $step ) ) );
			$response = rest_get_server()->dispatch( $request );
			self::assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		};
		$ask( 'wheels' );
		self::assertSame( array(), self::waiting( Options::JOB_COMPOSE_PICTURE ), 'a step before the review asks nothing' );
		$ask( 'review' );
		self::assertCount( 1, self::waiting( Options::JOB_COMPOSE_PICTURE ), 'the review offers the link, and asks for its picture' );

		self::work();
		$tags = $this->head( array_map( 'strval', $this->picks() ) + array( 'step' => 'review' ) );
		self::assertMatchesRegularExpression( '#/pictures/[0-9a-f]{32}\.webp$#', $tags['og:image'][0], 'the first fetch of the link is the picture' );
		unset( $_SERVER['REMOTE_ADDR'] );
	}

	// The audit of milestone 7, should-fix: until the picture was made, the card showed the deck's layer, a narrow
	// cut-out that platforms draw on black. The deck's photo, whole and opaque, stands in first.
	public function test_until_the_picture_is_made_the_decks_photo_stands_in_before_its_layer(): void {
		$photo = LayerFiles::attachment( 800, 800, false );
		$deck  = wc_get_product( $this->parts['deck'] );
		$deck->set_image_id( $photo );
		$deck->save();
		$tags = $this->head( array_map( 'strval', $this->picks() ) );
		$file = (string) wp_get_attachment_url( $photo );
		self::assertStringStartsWith( substr( $file, 0, -strlen( '.png' ) ), $tags['og:image'][0], 'the deck\'s photo, at the size the builder shows it' );
		self::assertGreaterThanOrEqual( array( '300' ), $tags['og:image:width'], 'at least the 300 pixels a large card needs' );
	}

	// Cowork's review of 1.0.0, F19 (#105, #106; D-309): a deck another plugin blurs on the share card is drawn blurred
	// in it, and until that picture is made the card shows no photo of the deck, which a link preview would show anyone.
	public function test_a_flagged_decks_share_card_is_the_blurred_picture_and_never_its_photo_or_layer_meanwhile(): void {
		$photo = LayerFiles::attachment( 800, 800, false );
		$deck  = wc_get_product( $this->parts['deck'] );
		$deck->set_image_id( $photo );
		$deck->save();
		$build = array_map( 'strval', $this->picks() ) + array( 'step' => 'review' );
		$asked = array();
		$flag  = static function ( string $answer, int $part, string $slot, int $kit, string $use ) use ( &$asked ): string {
			$asked[] = $use;
			return SlotKey::DECK === $slot && 'share' === $use ? 'blurred' : $answer;
		};
		add_filter( 'cbuild_composed_layer', $flag, 10, 5 );
		try {
			$tags = $this->head( $build );
			self::assertContains( 'share', $asked );
			self::assertArrayNotHasKey( 'og:image', $tags, 'no photo of the flagged deck, nor its layer, while the picture is made' );
			self::assertArrayNotHasKey( 'twitter:image', $tags );
			self::assertSame( array( 'summary' ), $tags['twitter:card'], 'a card with no image is the small one' );
			self::assertCount( 1, self::waiting( Options::JOB_COMPOSE_PICTURE ), 'the blurred picture is asked for' );

			self::work();
			$tags = $this->head( $build );
			self::assertMatchesRegularExpression( '#/creative-skateboard-builder/pictures/[0-9a-f]{32}\.webp$#', $tags['og:image'][0] ?? '', 'the blurred picture, once made' );
			$blurred = $tags['og:image'][0];
		} finally {
			remove_filter( 'cbuild_composed_layer', $flag, 10 );
		}
		self::assertNotSame( $blurred, $this->head( $build )['og:image'][0] ?? '', 'the flag lifted, another picture: the sharp one' );
	}
}
