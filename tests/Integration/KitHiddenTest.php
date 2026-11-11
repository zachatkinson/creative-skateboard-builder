<?php
/**
 * The kit stays out of search.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * A kit is the builder, not a product to list (spec: SEO and sharing): it is kept out of the catalogue, search
 * results, feeds and WordPress's sitemap, and prints no product markup, since its own price is only the assembly
 * fee. Each test has an ordinary product beside the kit, which must still be there. Yoast's sitemap has its own
 * test, run with Yoast installed (KitYoastTest).
 */
final class KitHiddenTest extends IntegrationTestCase {

	/**
	 * The kit's ID.
	 */
	private int $kit;

	/**
	 * An ordinary product's ID.
	 */
	private int $part;

	public function set_up(): void {
		parent::set_up();
		$this->kit = KitFactory::kit( 'Complete skateboard' )->get_id();
		$part      = new \WC_Product_Simple();
		$part->set_name( 'Complete truck set' );
		$part->set_status( 'publish' );
		$part->set_regular_price( '60' );
		$part->save();
		$this->part = $part->get_id();
		// Both in one category: the test site's theme gives products no archive of their own, and WooCommerce queries a
		// category's page as it does the shop's (WooCommerce 11.2.2, includes/class-wc-query.php:421).
		$category = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'completes',
			)
		);
		wp_set_object_terms( $this->kit, $category, 'product_cat' );
		wp_set_object_terms( $this->part, $category, 'product_cat' );
	}

	/**
	 * The IDs a front-end request's main query finds.
	 *
	 * @param string $path The address.
	 * @return list<int>
	 */
	private function found( string $path ): array {
		$this->go_to( home_url( $path ) );
		global $wp_query;
		return array_map( 'intval', wp_list_pluck( $wp_query->posts, 'ID' ) );
	}

	public function test_a_kit_is_hidden_whatever_visibility_it_is_given(): void {
		$kit = wc_get_product( $this->kit );
		self::assertInstanceOf( \WC_Product::class, $kit );
		self::assertSame( 'hidden', $kit->get_catalog_visibility() );
		self::assertFalse( $kit->is_visible() );
		foreach ( array( 'exclude-from-catalog', 'exclude-from-search' ) as $term ) {
			self::assertTrue( has_term( $term, 'product_visibility', $this->kit ), $term );
		}

		// The product screen's Catalogue visibility box, an import or the REST API setting it visible.
		$kit->set_catalog_visibility( 'visible' );
		$kit->save();
		wp_cache_flush();
		foreach ( array( 'exclude-from-catalog', 'exclude-from-search' ) as $term ) {
			self::assertTrue( has_term( $term, 'product_visibility', $this->kit ), $term . ' after a save as visible' );
		}
		self::assertFalse( has_term( 'exclude-from-catalog', 'product_visibility', $this->part ) );
	}

	// The auditor's blocker 1, milestone 4: WooCommerce writes the visibility terms on an update only when visibility is
	// among the product's changes, and the product screen sends the visibility it read, so nothing changed.
	public function test_a_product_made_a_kit_on_the_product_screen_is_hidden_by_that_save(): void {
		$simple = new \WC_Product_Simple();
		$simple->set_name( 'About to be a kit' );
		$simple->set_status( 'publish' );
		$simple->set_regular_price( '10' );
		$simple->save();
		$id = $simple->get_id();
		self::assertFalse( has_term( 'exclude-from-catalog', 'product_visibility', $id ) );

		// As WC_Meta_Box_Product_Data::save() does (WooCommerce 11.2.2,
		// includes/admin/meta-boxes/class-wc-meta-box-product-data.php:337): the type's class on the existing post, the
		// screen's fields set, WooCommerce's action, and a save.
		$this->service( \CreativeBranding\SkateboardBuilder\Hooks\KitHooks::class )->register();
		$class = \WC_Product_Factory::get_product_classname( $id, Options::PRODUCT_TYPE_KIT );
		$kit   = new $class( $id );
		self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\KitProduct::class, $kit );
		$kit->set_props( array( 'catalog_visibility' => 'visible' ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as its screen does it.
		do_action( 'woocommerce_admin_process_product_object', $kit );
		$kit->save();

		wp_cache_flush();
		foreach ( array( 'exclude-from-catalog', 'exclude-from-search' ) as $term ) {
			self::assertTrue( has_term( $term, 'product_visibility', $id ), $term );
		}
		self::assertNotContains(
			$id,
			wc_get_products(
				array(
					'visibility' => 'catalog',
					'return'     => 'ids',
					'limit'      => -1,
				)
			)
		);
	}

	public function test_the_catalogue_and_woocommerces_product_queries_leave_it_out(): void {
		$shop = $this->found( '/?product_cat=completes' );
		self::assertContains( $this->part, $shop );
		self::assertNotContains( $this->kit, $shop );

		$listed = wc_get_products(
			array(
				'visibility' => 'catalog',
				'return'     => 'ids',
				'limit'      => -1,
			)
		);
		self::assertContains( $this->part, $listed );
		self::assertNotContains( $this->kit, $listed );
	}

	public function test_a_search_of_the_products_or_of_the_whole_site_leaves_it_out(): void {
		$products = $this->found( '/?s=Complete&post_type=product' );
		self::assertContains( $this->part, $products );
		self::assertNotContains( $this->kit, $products );

		$site = $this->found( '/?s=Complete' );
		self::assertContains( $this->part, $site, 'WordPress searches products too' );
		self::assertNotContains( $this->kit, $site );
	}

	public function test_the_products_feed_leaves_it_out(): void {
		$feed = $this->found( '/?product_cat=completes&feed=rss2' );
		self::assertContains( $this->part, $feed );
		self::assertNotContains( $this->kit, $feed );
	}

	public function test_wordpresss_sitemap_leaves_it_out(): void {
		$provider = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
		self::assertInstanceOf( \WP_Sitemaps_Posts::class, $provider );
		$urls = wp_list_pluck( $provider->get_url_list( 1, 'product' ), 'loc' );
		self::assertContains( get_permalink( $this->part ), $urls );
		self::assertNotContains( get_permalink( $this->kit ), $urls );
		self::assertSame( 1, $provider->get_max_num_pages( 'product' ) );

		// Another post type's sitemap is asked as before.
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		self::assertContains( get_permalink( $page ), wp_list_pluck( $provider->get_url_list( 1, 'page' ), 'loc' ) );
	}

	public function test_a_kit_prints_no_product_markup_and_a_part_still_does(): void {
		$data = new \WC_Structured_Data();
		$data->generate_product_data( wc_get_product( $this->kit ) );
		self::assertSame( array(), $data->get_data(), 'the kit' );

		$data->generate_product_data( wc_get_product( $this->part ) );
		$types = array_column( $data->get_data(), '@type' );
		self::assertSame( array( 'Product' ), $types, 'an ordinary product' );
	}
}
