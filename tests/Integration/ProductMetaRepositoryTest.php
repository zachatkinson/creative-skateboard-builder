<?php
/**
 * Products' meta, read in one query into WooCommerce's cache.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\ProductMeta;
use CreativeBranding\SkateboardBuilder\Repository\ProductMetaRepository;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * Milestone 7's plan, Performance: "the state answer slowed by reading attachments". The board's parts' layer images
 * are read together: their meta in one query, the images in two, however many parts the board draws (D-202).
 */
final class ProductMetaRepositoryTest extends IntegrationTestCase {

	/**
	 * A product with a meta value, saved.
	 */
	private static function product( string $layer = '' ): int {
		$product = new WC_Product_Simple();
		$product->set_name( 'Part' );
		$product->update_meta_data( 'cbuild_test_note', 'kept' );
		if ( '' !== $layer ) {
			$product->update_meta_data( Options::META_LAYER, $layer );
		}
		$product->save();
		return $product->get_id();
	}

	public function test_the_container_gives_the_repository(): void {
		self::assertInstanceOf( ProductMetaRepository::class, $this->service( ProductMeta::class ) );
	}

	public function test_several_products_meta_is_read_in_one_query_and_then_read_through_woocommerce_from_its_cache(): void {
		$ids = array( self::product(), self::product(), self::product() );
		wp_cache_flush();

		$queries = get_num_queries();
		$this->service( ProductMeta::class )->prime( ...$ids );
		self::assertSame( $queries + 1, get_num_queries(), 'one query for the three' );
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			$before  = get_num_queries();
			self::assertSame( 'kept', $product->get_meta( 'cbuild_test_note', true ) );
			self::assertSame( $before, get_num_queries(), 'WooCommerce reads it from its own cache' );
		}

		// Saved through WooCommerce, the product reads what it saved: the primed rows carry their meta IDs.
		$product = wc_get_product( $ids[0] );
		$product->update_meta_data( 'cbuild_test_note', 'changed' );
		$product->save();
		self::assertSame( 'changed', wc_get_product( $ids[0] )->get_meta( 'cbuild_test_note', true ) );
		self::assertCount( 1, get_post_meta( $ids[0], 'cbuild_test_note' ), 'updated in place, not added beside' );

		$queries = get_num_queries();
		$this->service( ProductMeta::class )->prime( ...$ids );
		self::assertSame( $queries, get_num_queries(), 'what WooCommerce already holds is not read again' );
	}

	// #116, D-351: a page of saved builds knows its parts, not their products; their meta comes in the same query.
	public function test_parts_are_read_with_their_variations_products_in_one_query(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Trucks' );
		$parent->update_meta_data( 'cbuild_test_note', 'parent' );
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->update_meta_data( 'cbuild_test_note', 'variation' );
		$variation->save();
		$deck = self::product();
		wp_cache_flush();

		$queries = get_num_queries();
		$this->service( ProductMeta::class )->primeWithParents( $variation->get_id(), $deck );
		self::assertSame( $queries + 1, get_num_queries(), 'one query for the parts and the variation\'s product' );
		foreach ( array( $parent->get_id() => 'parent', $variation->get_id() => 'variation', $deck => 'kept' ) as $id => $note ) {
			$product = wc_get_product( $id );
			$before  = get_num_queries();
			self::assertSame( $note, $product->get_meta( 'cbuild_test_note', true ) );
			self::assertSame( $before, get_num_queries(), 'WooCommerce reads it from its own cache' );
		}

		$queries = get_num_queries();
		$this->service( ProductMeta::class )->primeWithParents( $variation->get_id(), $deck );
		self::assertSame( $queries, get_num_queries(), 'what WooCommerce already holds is not read again' );
	}

	public function test_a_boards_layer_images_are_read_in_a_few_queries_however_many_parts_it_draws(): void {
		$images = array( LayerFiles::attachment( 40, 160 ), LayerFiles::attachment( 40, 20 ), LayerFiles::attachment( 20, 30 ) );
		$parts  = array( self::product( (string) $images[0] ), self::product( (string) $images[1] ), self::product( (string) $images[2] ), self::product() );
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Wheels' );
		$parent->update_meta_data( Options::META_LAYER, (string) $images[2] );
		$parent->save();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->save();
		wp_cache_flush();
		// As the builder has them by then: the products loaded for their cards, their meta not yet read.
		foreach ( array( ...$parts, $variation->get_id(), $parent->get_id() ) as $id ) {
			wc_get_product( $id );
		}

		$queries = get_num_queries();
		$found   = $this->service( LayerImages::class )->attachments( ...$parts, ...array( $variation->get_id() ) );
		self::assertSame( array( $parts[0] => $images[0], $parts[1] => $images[1], $parts[2] => $images[2], $variation->get_id() => $images[2] ), $found, 'a variation with none takes its parent\'s, and a part with none is left out' );
		self::assertSame( 4, get_num_queries() - $queries, 'the parts\' meta, their parent\'s, and the images\' posts and meta, each read together' );
	}
}
