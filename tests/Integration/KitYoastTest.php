<?php
/**
 * A kit and Yoast SEO's sitemap.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * Yoast SEO builds its own sitemap from its own query, so what keeps a kit out of WordPress's (KitHiddenTest) does
 * not reach it. This runs with Yoast SEO itself loaded, in `composer test:integration:yoast` (D-102); without it
 * there is nothing to ask, and the test says so.
 */
final class KitYoastTest extends IntegrationTestCase {

	public function test_yoasts_product_sitemap_leaves_a_kit_out(): void {
		if ( '1' !== getenv( 'CBUILD_YOAST' ) ) {
			self::markTestSkipped( 'Runs with Yoast SEO loaded: composer test:integration:yoast.' );
		}
		self::assertTrue( class_exists( \WPSEO_Post_Type_Sitemap_Provider::class ), 'Yoast SEO is loaded' );

		$kit     = KitFactory::kit( 'Complete skateboard' );
		$product = new \WC_Product_Simple();
		$product->set_name( 'A deck' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '50' );
		$product->save();

		$links = ( new \WPSEO_Post_Type_Sitemap_Provider() )->get_sitemap_links( 'product', 100, 1 );
		$urls  = array_column( $links, 'loc' );
		self::assertContains( get_permalink( $product->get_id() ), $urls, 'an ordinary product is still listed' );
		self::assertNotContains( get_permalink( $kit->get_id() ), $urls );
	}
}
