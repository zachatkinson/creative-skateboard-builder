<?php
/**
 * The kit product type.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\KitProduct;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * A kit is a product of the type `cbuild_kit` (spec: Data model). It is virtual, cannot be bought outside the
 * builder, keeps its fee out of WooCommerce's price fields, and holds four documents as product meta, read and
 * written through the product's own CRUD (roadmap, milestone 4: "The kit: saves and loads through CRUD").
 */
final class KitProductTest extends IntegrationTestCase {

	private static function kit(): KitProduct {
		return KitFactory::kit();
	}

	/**
	 * The kit as a later request reads it.
	 *
	 * @param int $id The kit's ID.
	 */
	private static function fresh( int $id ): \WC_Product {
		wp_cache_flush();
		$product = wc_get_product( $id );
		self::assertInstanceOf( \WC_Product::class, $product );
		return $product;
	}

	public function test_woocommerce_lists_the_type_and_builds_a_kit_for_it(): void {
		self::assertSame( 'cbuild_kit', Options::PRODUCT_TYPE_KIT );
		self::assertSame( 'Builder kit', wc_get_product_types()[ Options::PRODUCT_TYPE_KIT ] ?? null );

		$id      = self::kit()->get_id();
		$product = self::fresh( $id );
		self::assertInstanceOf( KitProduct::class, $product );
		self::assertSame( 'cbuild_kit', $product->get_type() );
		self::assertTrue( has_term( 'cbuild_kit', 'product_type', $id ), 'the type is the product\'s own term, as WooCommerce keeps any type' );
		self::assertSame( 'cbuild_kit', \WC_Product_Factory::get_product_type( $id ) );
	}

	public function test_a_kit_saves_and_loads_its_four_documents_through_crud(): void {
		$kit                        = self::kit();
		$slots                      = KitDocuments::slots();
		$slots['slots'][0]['label'] = 'Deck \\ 8" «large» \'wide\' <b>';
		$slots['slots'][0]['help']  = "Two lines\nand a tab\there";
		$kit->setDocument( Options::META_KIT_SLOTS, $slots );
		$kit->save();

		$read = self::fresh( $kit->get_id() );
		self::assertInstanceOf( KitProduct::class, $read );
		self::assertSame(
			array(
				Options::META_KIT_SLOTS    => $slots,
				Options::META_KIT_RULES    => KitDocuments::rules(),
				Options::META_KIT_PRESETS  => KitDocuments::presets(),
				Options::META_KIT_ASSEMBLY => KitDocuments::assembly(),
			),
			$read->documents(),
			'every character comes back, a backslash and a quote among them'
		);
		self::assertSame( $slots, $read->document( Options::META_KIT_SLOTS ) );

		// Each is JSON text in the product's meta, as the spec has it.
		foreach ( array( Options::META_KIT_SLOTS, Options::META_KIT_RULES, Options::META_KIT_PRESETS, Options::META_KIT_ASSEMBLY ) as $key ) {
			$stored = get_post_meta( $kit->get_id(), $key, true );
			self::assertIsString( $stored, $key );
			self::assertSame( $read->document( $key ), json_decode( $stored, true ), $key );
			self::assertCount( 1, get_post_meta( $kit->get_id(), $key, false ), $key . ' is stored once' );
		}
	}

	public function test_a_document_that_is_missing_or_is_not_json_reads_as_none(): void {
		$kit = new KitProduct();
		$kit->set_name( 'Empty kit' );
		$kit->save();
		self::assertNull( $kit->document( Options::META_KIT_SLOTS ) );

		update_post_meta( $kit->get_id(), Options::META_KIT_SLOTS, '{"schema_version":1,' );
		update_post_meta( $kit->get_id(), Options::META_KIT_RULES, array( 'schema_version' => 1 ) );
		update_post_meta( $kit->get_id(), Options::META_KIT_PRESETS, '"text"' );
		$read = self::fresh( $kit->get_id() );
		self::assertInstanceOf( KitProduct::class, $read );
		self::assertSame(
			array(
				Options::META_KIT_SLOTS    => null,
				Options::META_KIT_RULES    => null,
				Options::META_KIT_PRESETS  => null,
				Options::META_KIT_ASSEMBLY => null,
			),
			$read->documents()
		);
		self::assertNull( $read->document( '_price' ), 'only the kit\'s own four keys are documents' );
	}

	public function test_only_the_kits_four_keys_take_a_document(): void {
		$kit = self::kit();
		$kit->setDocument( '_price', array( 'schema_version' => 1 ) );
		$kit->setDocument( Options::META_SPECS, array( 'deck_width' => '8.2500' ) );
		$kit->save();
		self::assertSame( '', get_post_meta( $kit->get_id(), '_price', true ) );
		self::assertSame( '', get_post_meta( $kit->get_id(), Options::META_SPECS, true ) );
	}

	public function test_a_kit_is_virtual_and_cannot_be_bought_as_a_product(): void {
		$kit = self::fresh( self::kit()->get_id() );
		self::assertTrue( $kit->is_virtual() );
		self::assertFalse( $kit->needs_shipping() );
		self::assertTrue( $kit->exists() );
		self::assertFalse( $kit->is_purchasable(), 'a kit is bought through the builder alone' );
		self::assertFalse( WC()->cart->add_to_cart( $kit->get_id() ), 'and WooCommerce\'s own cart refuses it' );
		wc_clear_notices();
	}

	public function test_its_price_is_the_assembly_fee_and_woocommerces_price_fields_stay_empty(): void {
		$assembly        = KitDocuments::assembly();
		$assembly['fee'] = '15.50';
		$kit             = self::kit();
		$kit->setDocument( Options::META_KIT_ASSEMBLY, $assembly );
		// Whatever sets a price on it, an importer or the REST API, the fields stay empty.
		$kit->set_regular_price( '99' );
		$kit->set_sale_price( '89' );
		$kit->set_price( '89' );
		$kit->save();

		$read = self::fresh( $kit->get_id() );
		self::assertSame( '15.50', $read->get_price() );
		self::assertSame( '15.50', $read->get_regular_price() );
		self::assertSame( '', $read->get_sale_price() );
		self::assertFalse( $read->is_on_sale() );
		foreach ( array( '_price', '_regular_price', '_sale_price' ) as $field ) {
			self::assertSame( '', (string) get_post_meta( $kit->get_id(), $field, true ), $field );
		}
		self::assertSame( '', $read->get_price( 'edit' ), 'what is stored is no price' );

		$read->setDocument( Options::META_KIT_ASSEMBLY, array( 'schema_version' => 1 ) );
		self::assertSame( '', $read->get_price(), 'assembly settings that do not read give no fee' );
	}

	public function test_switched_off_a_kit_is_a_simple_product_with_no_price_which_woocommerce_will_not_sell(): void {
		$id = self::kit()->get_id();
		// The plugin switched off: nothing answers for the type any more.
		remove_all_filters( 'woocommerce_product_class' );
		remove_all_filters( 'product_type_selector' );

		$product = self::fresh( $id );
		self::assertInstanceOf( \WC_Product_Simple::class, $product );
		self::assertSame( '', $product->get_price() );
		self::assertFalse( $product->is_purchasable() );
		self::assertFalse( WC()->cart->add_to_cart( $id ) );
		wc_clear_notices();
	}
}
