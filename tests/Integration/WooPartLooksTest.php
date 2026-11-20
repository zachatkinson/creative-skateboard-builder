<?php
/**
 * Parts' looks.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WooPartCards;
use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Port\PartLooks;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * `Adapter\WooPartLooks`: what groups a step's parts into looks and orders them (D-240, D-243), read from the products
 * and kept with each part.
 */
final class WooPartLooksTest extends IntegrationTestCase {

	private function looks(): PartLooks {
		return $this->service( PartLooks::class );
	}

	/**
	 * A global attribute with these terms, by name; the slugs back.
	 *
	 * @param string $slug  The attribute's slug, without `pa_`.
	 * @param string ...$names The terms' names.
	 * @return list<int> The terms' IDs.
	 */
	private static function attribute( string $slug, string ...$names ): array {
		$taxonomy = 'pa_' . $slug;
		// The attribute's row is rolled back after each test; the taxonomy registered in this process is not.
		if ( 0 === wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
			unregister_taxonomy( $taxonomy );
			self::assertIsInt( wc_create_attribute( array( 'name' => ucfirst( $slug ), 'slug' => $slug ) ) );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, array( 'product' ) );
		}
		$ids = array();
		foreach ( $names as $name ) {
			$term  = term_exists( $name, $taxonomy ) ?: wp_insert_term( $name, $taxonomy );
			$ids[] = (int) $term['term_id'];
		}
		return $ids;
	}

	/**
	 * A product attribute on the taxonomy.
	 *
	 * @param list<int> $terms The terms.
	 */
	private static function held( string $taxonomy, array $terms, bool $variation ): WC_Product_Attribute {
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $terms );
		$attribute->set_visible( true );
		$attribute->set_variation( $variation );
		return $attribute;
	}

	/**
	 * A photo in the media library.
	 */
	private static function photo(): int {
		return self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	public function test_a_product_is_its_own_look_with_its_photo_layer_attributes_and_what_orders_it(): void {
		$terms = self::attribute( 'hardness', '99A', '101A' );
		$photo = self::photo();
		$layer = self::photo();
		$wheel = new WC_Product_Simple();
		$wheel->set_name( 'Formula Four &amp; Classic' );
		$wheel->set_regular_price( '60' );
		$wheel->set_image_id( $photo );
		$wheel->set_featured( true );
		$wheel->set_total_sales( 41 );
		$wheel->set_date_created( '2026-03-04T05:06:07+00:00' );
		$wheel->set_attributes( array( self::held( 'pa_hardness', array( $terms[0] ), false ) ) );
		$wheel->update_meta_data( Options::META_LAYER, $layer );
		$id = $wheel->save();

		$looks = $this->looks()->looks( array( $id, 999999 ) );
		self::assertSame( array( $id ), array_keys( $looks ), 'an ID that is no product has none' );
		self::assertEquals(
			new LookFact( $id, $photo, $layer, 'Formula Four & Classic', array( 'pa_hardness' => array( '99a' ) ), array( 'pa_hardness' => array( '99a' => '99A' ) ), true, 41, 1772600767 ),
			$looks[ $id ]
		);
	}

	public function test_a_variation_is_of_its_parent_with_its_own_photo_and_layer_or_its_parents(): void {
		$colours  = self::attribute( 'colour', 'Mint', 'Black' );
		$sizes    = self::attribute( 'diameter', '52', '54' );
		$hardness = self::attribute( 'hardness', '99A' );
		$photo    = self::photo();
		$own      = self::photo();
		$layer    = self::photo();

		$parent = new WC_Product_Variable();
		$parent->set_name( 'Retna Cruiser' );
		$parent->set_image_id( $photo );
		$parent->update_meta_data( Options::META_LAYER, $layer );
		$parent->set_attributes(
			array(
				self::held( 'pa_colour', $colours, true ),
				self::held( 'pa_diameter', $sizes, true ),
				self::held( 'pa_hardness', $hardness, false ),
			)
		);
		$parent->save();
		$made = array();
		foreach ( array( array( 'mint', '52', $own ), array( 'black', '54', 0 ) ) as list( $colour, $size, $image ) ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes(
				array(
					'pa_colour'   => $colour,
					'pa_diameter' => $size,
				)
			);
			$variation->set_regular_price( '50' );
			$variation->set_image_id( $image );
			$made[] = $variation->save();
		}

		$looks = $this->looks()->looks( $made );
		self::assertSame( $made, array_keys( $looks ), 'in the order asked' );
		self::assertSame( array( $parent->get_id(), $parent->get_id() ), array_column( $looks, 'product' ) );
		self::assertSame( array( $own, $photo ), array_column( $looks, 'photo' ), 'its own photo, or its parent\'s' );
		self::assertSame( array( $layer, $layer ), array_column( $looks, 'layer' ), 'its parent\'s layer image' );
		self::assertSame( 'Retna Cruiser', $looks[ $made[0] ]->name, 'the product\'s name, not the variation\'s' );
		self::assertSame(
			array(
				'pa_colour'   => array( 'mint' ),
				'pa_diameter' => array( '52' ),
				'pa_hardness' => array( '99a' ),
			),
			$looks[ $made[0] ]->attributes,
			'its own values, then the parent\'s it does not vary'
		);
		self::assertSame( 'Mint', $looks[ $made[0] ]->labels['pa_colour']['mint'] );
		self::assertFalse( $looks[ $made[0] ]->featured );
	}

	public function test_an_attributes_name_is_the_shops(): void {
		self::attribute( 'finish', 'Gloss' );
		self::assertSame( array( 'pa_finish' => 'Finish' ), $this->looks()->attributes( array( 'pa_nothing', 'pa_finish', 'brand' ) ) );
		self::assertSame( array(), $this->looks()->attributes( array() ) );
	}

	public function test_a_look_is_kept_and_read_again_when_its_part_or_parent_is_saved(): void {
		$wheel = new WC_Product_Simple();
		$wheel->set_name( 'Before' );
		$id = $wheel->save();
		self::assertSame( 'Before', $this->looks()->looks( array( $id ) )[ $id ]->name );

		$queries = $this->queries( fn() => $this->looks()->looks( array( $id ) ) );
		self::assertLessThanOrEqual( 2, $queries, 'kept: the post and nothing more' );

		global $wpdb;
		// Renamed and saved a minute later by something that fires none of WooCommerce's hooks.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title'        => 'After',
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() + 60 ),
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );
		self::assertSame( 'After', $this->looks()->looks( array( $id ) )[ $id ]->name, 'the stamp moved' );

		// Renamed again within the same minute: what forgets its card forgets its look.
		$wpdb->update( $wpdb->posts, array( 'post_title' => 'Again' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		self::assertSame( 'After', $this->looks()->looks( array( $id ) )[ $id ]->name, 'kept while the stamp holds' );
		$cards = $this->service( PartCards::class );
		$cards->forget( $id );
		self::assertInstanceOf( WooPartCards::class, $cards );
		$cards->settle();
		self::assertSame( 'Again', $this->looks()->looks( array( $id ) )[ $id ]->name );
	}

	/**
	 * How many queries a piece of work runs.
	 *
	 * @param callable(): mixed $work The work.
	 */
	private function queries( callable $work ): int {
		global $wpdb;
		$before = $wpdb->num_queries;
		$work();
		return $wpdb->num_queries - $before;
	}
}
