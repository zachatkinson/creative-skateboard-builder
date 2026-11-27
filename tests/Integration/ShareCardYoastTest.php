<?php
/**
 * A build link's share card, through Yoast SEO's filters.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Front\BuilderPlace;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\SeoHooks;
use CreativeBranding\SkateboardBuilder\Front\ShareHooks;
use CreativeBranding\SkateboardBuilder\Hooks\PictureHooks;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Spec, SEO: "With Yoast or another SEO plugin active, the tags go through its filters." Yoast prints one set of
 * Open Graph tags, with the build's name, total, link and picture in place of the product's own, and the plugin
 * prints none of its own. Runs with Yoast SEO loaded, in `composer test:integration:yoast` (D-102, D-200).
 */
final class ShareCardYoastTest extends CartTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( '1' !== getenv( 'CBUILD_YOAST' ) ) {
			self::markTestSkipped( 'Runs with Yoast SEO loaded: composer test:integration:yoast.' );
		}
		add_filter( 'Yoast\\WP\\SEO\\should_index_indexables', '__return_true' );
		( new SeoHooks( $this->service( BuilderPlace::class ) ) )->register();
		$this->service( ShareHooks::class )->register();
		$this->service( PictureHooks::class )->register();
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 420, 1626 ) );
		$deck->set_image_id( LayerFiles::attachment( 400, 400, false, 'jpeg' ) );
		$deck->save();
		$kit = wc_get_product( $this->kit );
		$kit->set_image_id( LayerFiles::attachment( 300, 300, false, 'jpeg' ) );
		$kit->save();
	}

	public function tear_down(): void {
		unset( $_GET[ BuildLink::PARAM ] );
		as_unschedule_all_actions( Options::JOB_COMPOSE_PICTURE, array(), Options::QUEUE_GROUP );
		as_unschedule_all_actions( Options::JOB_PRUNE_PICTURES, array(), Options::QUEUE_GROUP );
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		parent::tear_down();
	}

	/**
	 * The kit's page's head as Yoast prints it, and its Open Graph and X tags by name.
	 *
	 * @param array<string, string>|null $build The build, under `cbuild`.
	 * @return array{string, array<string, list<string>>}
	 */
	private function head( ?array $build ): array {
		$url = (string) get_permalink( $this->kit );
		$this->go_to( null === $build ? $url : add_query_arg( array( BuildLink::PARAM => $build ), $url ) );
		unset( $_GET[ BuildLink::PARAM ] );
		if ( null !== $build ) {
			$_GET[ BuildLink::PARAM ] = $build;
		}
		YoastSEO()->classes->get( \Yoast\WP\SEO\Memoizers\Meta_Tags_Context_Memoizer::class )->clear();
		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();
		preg_match_all( '#<meta (?:property|name)="((?:og|twitter):[^"]+)" content="([^"]*)"#', $html, $found, PREG_SET_ORDER );
		$tags = array();
		foreach ( $found as [ , $name, $content ] ) {
			$tags[ $name ][] = html_entity_decode( $content, ENT_QUOTES );
		}
		return array( $html, $tags );
	}

	public function test_yoast_prints_the_builds_card_once_with_its_picture_alone(): void {
		self::assertTrue( function_exists( 'YoastSEO' ), 'Yoast SEO is loaded' );
		[ , $clean ] = $this->head( null );
		self::assertSame( array( (string) get_permalink( $this->kit ) ), $clean['og:url'], 'the clean page keeps Yoast\'s own' );

		$build         = array_map( 'strval', $this->picks() ) + array( 'step' => 'review' );
		[ $html, $tags ] = $this->head( $build );
		self::assertSame( array( '8.5″ complete: Deck, Trucks and Wheels' ), $tags['og:title'], 'joined as the plugin joins a list, as ShareCardTest says (D-460)' );
		self::assertSame( array( 'Total: $183.00' ), $tags['og:description'] );
		self::assertCount( 1, $tags['og:url'], 'one set of tags' );
		self::assertSame( (string) get_permalink( $this->kit ), remove_query_arg( BuildLink::PARAM, $tags['og:url'][0] ) );
		self::assertNotSame( (string) get_permalink( $this->kit ), $tags['og:url'][0], 'og:url is the build link (spec), where D-147 had the clean page' );
		// Cowork's recheck of 0.7.0, M6 (D-215): Yoast 28.8 urldecode()s what its filter returns
		// (src/presenters/open-graph/url-presenter.php:46), which printed the brackets bare. The build link is printed as
		// the plugin's own tags and Copy link write it, its brackets encoded.
		self::assertStringContainsString( 'cbuild%5Bdeck%5D=', $tags['og:url'][0] );
		self::assertStringNotContainsString( '[', $tags['og:url'][0] );
		self::assertStringNotContainsString( '%25', $tags['og:url'][0], 'and nothing encoded twice' );
		self::assertCount( 1, $tags['og:image'], 'the deck\'s layer alone, not the product\'s own photo beside it' );
		self::assertStringContainsString( 'cbuild-layer-', $tags['og:image'][0] );
		self::assertStringNotContainsString( 'rel="canonical"', $html, 'Yoast prints no canonical on a noindex page' );

		self::work();
		// A later request: the card of a request is worked out once, and this one has asked already.
		[ , $tags ] = $this->head( array( 'step' => 'deck' ) + $build );
		self::assertCount( 1, $tags['og:image'] );
		self::assertMatchesRegularExpression( '#/creative-skateboard-builder/pictures/[0-9a-f]{32}\.webp$#', $tags['og:image'][0] );
		self::assertSame( array( array( '1200' ), array( '630' ), array( 'image/webp' ) ), array( $tags['og:image:width'], $tags['og:image:height'], $tags['og:image:type'] ) );
		self::assertSame( array( 'summary_large_image' ), $tags['twitter:card'] );
	}
}
