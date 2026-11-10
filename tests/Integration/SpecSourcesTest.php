<?php
/**
 * The sources of a part's specs, on real products.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\AttributeSpecSource;
use CreativeBranding\SkateboardBuilder\Adapter\FieldSpecSource;
use CreativeBranding\SkateboardBuilder\Adapter\OptionShopSettings;
use CreativeBranding\SkateboardBuilder\Adapter\Products;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Roadmap, milestone 3: indexing from each source, which source wins, disagreements flagged. The chart's source
 * comes with the spec tables.
 */
final class SpecSourcesTest extends IntegrationTestCase {

	public function tear_down(): void {
		delete_option( Options::SETTINGS );
		delete_option( 'woocommerce_price_decimal_sep' );
		parent::tear_down();
	}

	/**
	 * A simple product with these fields.
	 *
	 * @param mixed $fields The meta's value.
	 */
	private static function simple( mixed $fields = null ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Part' );
		$product->set_regular_price( '10' );
		if ( null !== $fields ) {
			$product->update_meta_data( Options::META_SPECS, $fields );
		}
		$product->save();
		return $product;
	}

	/**
	 * The Width attribute as a taxonomy, with these terms.
	 *
	 * @param string ...$names The terms' names.
	 */
	private static function widthTaxonomy( string ...$names ): void {
		// The attribute's row is rolled back after each test; the taxonomy registered in this process is not.
		// WooCommerce refuses a slug whose taxonomy is registered, so it goes first.
		if ( 0 === wc_attribute_taxonomy_id_by_name( 'pa_width' ) ) {
			unregister_taxonomy( 'pa_width' );
			$created = wc_create_attribute( array( 'name' => 'Width', 'slug' => 'width' ) );
			self::assertIsInt( $created );
		}
		if ( ! taxonomy_exists( 'pa_width' ) ) {
			register_taxonomy( 'pa_width', array( 'product' ) );
		}
		foreach ( $names as $name ) {
			if ( ! term_exists( $name, 'pa_width' ) ) {
				wp_insert_term( $name, 'pa_width' );
			}
		}
	}

	/**
	 * A variable deck whose variations are these widths of the Width taxonomy.
	 *
	 * @param string ...$names The terms' names, a variation each.
	 * @return array{0: \WC_Product_Variable, 1: list<\WC_Product_Variation>}
	 */
	private static function variableDeck( string ...$names ): array {
		self::widthTaxonomy( ...$names );
		$slugs = array();
		foreach ( $names as $name ) {
			$slugs[] = get_term_by( 'name', $name, 'pa_width' )->slug;
		}
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( 'pa_width' ) );
		$attribute->set_name( 'pa_width' );
		$attribute->set_options( array_map( static fn( string $name ): int => (int) get_term_by( 'name', $name, 'pa_width' )->term_id, $names ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Deck' );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();
		$variations = array();
		foreach ( $slugs as $slug ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( array( 'pa_width' => $slug ) );
			$variation->set_regular_price( '80' );
			$variation->save();
			$variations[] = $variation;
		}
		return array( $parent, $variations );
	}

	private static function map( string $attribute = 'pa_width', string $spec = 'deck_width', string $unit = '' ): void {
		update_option( Options::SETTINGS, array( Options::SETTING_ATTRIBUTE_MAPPING => array( compact( 'attribute', 'spec', 'unit' ) ) ) );
	}

	public function test_a_parts_own_fields_are_read_and_what_is_not_a_value_is_reported(): void {
		$part = self::simple(
			array(
				'deck_width'   => '8.2500',
				'hole_pattern' => 'both',
				'deck_length'  => 'long',
				'wheelbase'    => '  ',
				'set_size'     => 4,
				'concave'      => 'steep',
				7              => '9',
			)
		);
		$read = $this->service( FieldSpecSource::class )->read( $part->get_id() );
		self::assertEquals(
			array(
				$part->get_id() => array(
					Reading::of( SpecKey::DeckWidth, \CreativeBranding\SkateboardBuilder\Domain\Measure\Measure::inches( 82500 ), Origin::Field ),
					new Unread( 'deck_length', 'long', Origin::Field, '', 'measure_malformed' ),
					Reading::of( SpecKey::HolePattern, 'both', Origin::Field ),
				),
			),
			$read,
			'in the order of the keys; a blank, a value that is not text and a key this version does not know are passed over'
		);
	}

	public function test_a_part_with_no_fields_a_deleted_one_and_what_is_not_a_product_have_nothing(): void {
		$none    = self::simple();
		$junk    = self::simple( 'not an array' );
		$deleted = self::simple( array( 'deck_width' => '8.0000' ) );
		$gone    = $deleted->get_id();
		$deleted->delete( true );
		$post = (int) self::factory()->post->create();

		$read = $this->service( FieldSpecSource::class )->read( $none->get_id(), $junk->get_id(), $gone, $post, 0 );
		self::assertSame( array( $none->get_id() => array(), $junk->get_id() => array(), $gone => array(), $post => array(), 0 => array() ), $read );
		self::assertSame( array(), $this->service( FieldSpecSource::class )->read() );
	}

	public function test_a_variation_left_blank_takes_its_parents_value_and_says_so(): void {
		[ $parent, $variations ] = self::variableDeck( '8.25', '8.5' );
		$parent->update_meta_data( Options::META_SPECS, array( 'deck_length' => '32.0000', 'hole_pattern' => 'new_school', 'wheelbase' => '14.2500' ) );
		$parent->save();
		$variations[0]->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.2500', 'deck_length' => '31.7500', 'wheelbase' => '' ) );
		$variations[0]->save();

		$read = $this->service( FieldSpecSource::class )->read( $variations[0]->get_id(), $variations[1]->get_id(), $parent->get_id() );
		$rows = static fn( array $readings ): array => array_map( static fn( Reading $reading ): IndexedSpec => $reading->row(), $readings );
		self::assertEquals(
			array(
				new IndexedSpec( SpecKey::DeckWidth, '8.2500', Origin::Field ),
				new IndexedSpec( SpecKey::DeckLength, '31.7500', Origin::Field ),
				new IndexedSpec( SpecKey::Wheelbase, '14.2500', Origin::Field, 'parent' ),
				new IndexedSpec( SpecKey::HolePattern, 'new_school', Origin::Field, 'parent' ),
			),
			$rows( $read[ $variations[0]->get_id() ] ),
			'its own width and length; the wheelbase it left blank and the pattern it never had are the parent\'s'
		);
		self::assertSame( array( 'parent', 'parent', 'parent' ), array_column( $rows( $read[ $variations[1]->get_id() ] ), 'ref' ) );
		self::assertSame( array( '', '', '' ), array_column( $rows( $read[ $parent->get_id() ] ), 'ref' ), 'the parent\'s own are its own' );
		self::assertSame( 'parent', FieldSpecSource::PARENT );
	}

	public function test_a_mapped_attribute_feeds_a_variation_from_its_own_value(): void {
		[ $parent, $variations ] = self::variableDeck( '8.25', '8 1/2"' );
		self::map();
		$read = $this->service( AttributeSpecSource::class )->read( $variations[0]->get_id(), $variations[1]->get_id(), $parent->get_id() );
		self::assertEquals( array( Reading::from( SpecKey::DeckWidth, '8.25', Origin::Attribute, 'pa_width' ) ), $read[ $variations[0]->get_id() ] );
		self::assertSame( '8.5000', $read[ $variations[1]->get_id() ][0]->text );
		self::assertEquals(
			array( new Unread( 'deck_width', '8 1/2", 8.25', Origin::Attribute, 'pa_width', 'measure_malformed' ) ),
			$read[ $parent->get_id() ],
			'the parent has both widths, so none that can be read'
		);
	}

	public function test_a_mapped_attribute_is_read_in_its_lines_unit_and_from_a_products_own_attribute(): void {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Diameter' );
		$attribute->set_options( array( '2.125' ) );
		$wheel = new \WC_Product_Simple();
		$wheel->set_name( 'Wheel' );
		$wheel->set_attributes( array( $attribute ) );
		$wheel->save();
		$plain = self::simple();

		self::map( 'Diameter', 'wheel_diameter', 'in' );
		$settings = $this->service( ShopSettings::class );
		self::assertSame( Unit::Inch, $settings->attributeMapping()[0]->unit );
		$read = $this->service( AttributeSpecSource::class )->read( $wheel->get_id(), $plain->get_id() );
		self::assertSame( '53.9750', $read[ $wheel->get_id() ][0]->text, '2.125 in, in the key\'s millimetres' );
		self::assertSame( 'Diameter', $read[ $wheel->get_id() ][0]->ref );
		self::assertSame( array(), $read[ $plain->get_id() ], 'a product without the attribute' );
	}

	public function test_a_term_is_read_with_the_shops_separator_or_a_point(): void {
		[ , $variations ] = self::variableDeck( '8,375', '8.625', 'wide' );
		self::map();
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$read = $this->service( AttributeSpecSource::class )->read( ...array_map( static fn( \WC_Product_Variation $v ): int => $v->get_id(), $variations ) );
		self::assertSame( '8.3750', $read[ $variations[0]->get_id() ][0]->text, 'typed with the shop\'s comma' );
		self::assertSame( '8.6250', $read[ $variations[1]->get_id() ][0]->text, 'typed with a point in a comma shop' );
		self::assertEquals( new Unread( 'deck_width', 'wide', Origin::Attribute, 'pa_width', 'measure_malformed' ), $read[ $variations[2]->get_id() ][0] );
	}

	public function test_with_no_mapping_the_attribute_source_reads_no_product(): void {
		$part = self::simple();
		// The option's own read, which WordPress then remembers.
		$this->service( ShopSettings::class )->attributeMapping();
		$queries = get_num_queries();
		self::assertSame( array( $part->get_id() => array() ), $this->service( AttributeSpecSource::class )->read( $part->get_id() ) );
		self::assertSame( $queries, get_num_queries() );
	}

	public function test_the_settings_read_what_was_saved_and_anything_else_as_the_default(): void {
		$settings = $this->service( ShopSettings::class );
		self::assertInstanceOf( OptionShopSettings::class, $settings );
		self::assertEquals( SourceOrder::standard(), $settings->sourceOrder() );
		self::assertSame( array(), $settings->attributeMapping() );

		update_option(
			Options::SETTINGS,
			array(
				'source_order'      => array( 'field', 'attribute', 'table' ),
				'attribute_mapping' => array(
					array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => '' ),
					'junk',
					array( 'attribute' => 'pa_size', 'spec' => 'concave' ),
					array( 'attribute' => 'pa_diameter', 'spec' => 'wheel_diameter', 'unit' => 'mm' ),
				),
			)
		);
		self::assertSame( array( 'field', 'attribute', 'table' ), $settings->sourceOrder()->names() );
		self::assertSame( array( 'pa_width', 'pa_diameter' ), array_column( $settings->attributeMapping(), 'attribute' ) );

		foreach ( array( 'junk', array( 'source_order' => 'table', 'attribute_mapping' => 'pa_width' ) ) as $stored ) {
			update_option( Options::SETTINGS, $stored );
			self::assertEquals( SourceOrder::standard(), $settings->sourceOrder() );
			self::assertSame( array(), $settings->attributeMapping() );
		}
		self::assertContains( Options::SETTINGS, Options::names(), 'uninstall removes it' );
		self::assertSame( array( 'attribute_mapping', 'source_order' ), Options::settingsKeys() );
		self::assertContains( '_cbuild_specs', Options::metaKeys() );
	}

	public function test_the_indexer_writes_real_parts_to_the_table_and_flags_where_they_disagree(): void {
		[ , $variations ] = self::variableDeck( '8.25', '8.5' );
		$variations[0]->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.3750', 'deck_length' => '32.0000' ) );
		$variations[0]->save();
		self::map();
		$first  = $variations[0]->get_id();
		$second = $variations[1]->get_id();

		$indexer = $this->service( SpecIndexer::class );
		$sourced = $indexer->index( $first, $second );
		$index   = $this->service( SpecIndex::class );
		self::assertEquals(
			array(
				new IndexedSpec( SpecKey::DeckWidth, '8.3750', Origin::Field ),
				new IndexedSpec( SpecKey::DeckLength, '32.0000', Origin::Field ),
			),
			$index->rowsOf( $first ),
			'the field before the attribute'
		);
		self::assertEquals( array( new IndexedSpec( SpecKey::DeckWidth, '8.5000', Origin::Attribute, 'pa_width' ) ), $index->rowsOf( $second ) );
		self::assertCount( 1, $sourced[ $first ]->resolution->disagreements );
		self::assertSame( '8.2500', $sourced[ $first ]->resolution->disagreements[0]->others[0]->text );
		self::assertSame( array(), $sourced[ $second ]->resolution->disagreements );

		$variations[1]->delete( true );
		$indexer->index( $first, $second );
		self::assertSame( array(), $index->rowsOf( $second ), 'a deleted part leaves the index' );
		self::assertCount( 2, $index->rowsOf( $first ) );
	}

	public function test_a_batch_is_loaded_in_a_few_queries_not_one_per_part(): void {
		$ids = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$ids[] = self::simple( array( 'deck_width' => '8.0000' ) )->get_id();
		}
		wp_cache_flush();
		$before = get_num_queries();
		$loaded = $this->service( Products::class )->load( ...$ids );
		self::assertCount( 20, $loaded );
		self::assertEqualsCanonicalizing( $ids, array_keys( $loaded ) );
		self::assertLessThan( 20, get_num_queries() - $before, 'fewer queries than parts: the posts, their meta and their terms come a batch at a time' );
		self::assertSame( array(), $this->service( Products::class )->parentsOf( $loaded ) );
	}
}
