<?php
/**
 * The shop's kits, and the parts that may fill their slots.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WooKits;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * `Adapter\WooKits` reads kit products (D-099) and turns a slot's sources into parts (D-101): the products a
 * shopper could buy now, each with its specs from the index, its price as the shop shows it and its stock.
 */
final class WooKitsTest extends IntegrationTestCase {

	private function kits(): Kits {
		return $this->service( Kits::class );
	}

	/**
	 * A published simple product, indexed.
	 *
	 * @param array<string, string>       $specs Its own specs.
	 * @param array<string, list<int>>    $terms Term IDs by taxonomy.
	 * @param array<string, mixed>        $props Other properties, by setter.
	 */
	private function part( string $name, array $specs = array(), array $terms = array(), array $props = array() ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( '50' );
		foreach ( $props as $setter => $value ) {
			$product->$setter( $value );
		}
		if ( array() !== $specs ) {
			$product->update_meta_data( Options::META_SPECS, $specs );
		}
		$product->save();
		foreach ( $terms as $taxonomy => $ids ) {
			wp_set_object_terms( $product->get_id(), $ids, $taxonomy );
		}
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		return $product->get_id();
	}

	private static function term( string $taxonomy, string $name ): int {
		return self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy,
				'name'     => $name,
			)
		);
	}

	/**
	 * A kit whose deck slot takes these sources.
	 *
	 * @param array<string, mixed> $sources The deck slot's sources.
	 */
	private static function kitWith( array $sources ): int {
		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = $sources;
		return KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
	}

	/**
	 * The IDs of a slot's parts, sorted.
	 *
	 * @return list<int>
	 */
	private function ids( int $kit, string $slot = 'deck' ): array {
		$ids = array_map( static fn( Part $part ): int => $part->id, $this->kits()->candidates( $kit, $slot ) );
		sort( $ids );
		return $ids;
	}

	public function test_the_shops_kits_are_bound_to_woocommerce(): void {
		self::assertInstanceOf( WooKits::class, $this->kits() );
	}

	public function test_every_kit_whose_documents_read_is_listed_by_id_with_its_name(): void {
		$ready = KitFactory::kit( 'Complete skateboard' );
		$draft = KitFactory::kit( 'Cruiser' );
		$draft->set_status( 'draft' );
		$draft->save();
		// Refused by the schema: a property it does not know. Refused by the domain: a preset for a slot the kit lacks.
		$assembly            = KitDocuments::assembly();
		$assembly['surplus'] = true;
		$unknown             = KitFactory::kit( 'Unknown field', array( Options::META_KIT_ASSEMBLY => $assembly ) );
		$presets               = KitDocuments::presets();
		$presets['presets'][0] = array(
			'name'  => 'Street',
			'picks' => array(
				'deck'     => 1,
				'trucks'   => 2,
				'wheels'   => 3,
				'bearings' => 4,
				'hardware' => 5,
				'grip'     => 6,
			),
		);
		$whole                 = KitFactory::kit( 'With a preset', array( Options::META_KIT_PRESETS => $presets ) );
		$presets['presets'][0]['picks']['sails'] = 9;
		$lost                = KitFactory::kit( 'Lost slot', array( Options::META_KIT_PRESETS => $presets ) );
		$empty               = KitFactory::kit( 'No slots', array( Options::META_KIT_SLOTS => null ) );
		$binned              = KitFactory::kit( 'Binned' );
		wp_trash_post( $binned->get_id() );
		$this->part( 'Not a kit' );

		$all = $this->kits()->all();
		self::assertSame( array( $ready->get_id(), $draft->get_id(), $whole->get_id() ), array_keys( $all ), 'a draft is a kit being made ready; one in the bin is not' );
		self::assertSame( 'Complete skateboard', $all[ $ready->get_id() ]['name'] );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'risers', 'bearings', 'hardware', 'grip' ), array_map( static fn( $slot ): string => $slot->key, $all[ $ready->get_id() ]['kit']->slots->all() ) );

		$ids = $this->kits()->ids();
		sort( $ids );
		self::assertSame( array( $ready->get_id(), $draft->get_id(), $unknown->get_id(), $whole->get_id(), $lost->get_id(), $empty->get_id() ), $ids, 'every kit product, whatever its documents hold' );
	}

	public function test_a_slot_takes_the_parts_in_its_categories(): void {
		$decks  = self::term( 'product_cat', 'Decks' );
		$shaped = self::term( 'product_cat', 'Shaped decks' );
		$trucks = self::term( 'product_cat', 'Trucks' );
		$one    = $this->part( 'Deck one', array( 'deck_width' => '8.2500' ), array( 'product_cat' => array( $decks ) ) );
		$two    = $this->part( 'Deck two', array(), array( 'product_cat' => array( $shaped ) ) );
		$this->part( 'A truck', array(), array( 'product_cat' => array( $trucks ) ) );
		$this->part( 'In no category' );
		$kit = self::kitWith( array( 'categories' => array( $decks, $shaped ) ) );

		self::assertSame( array( $one, $two ), $this->ids( $kit ) );
		self::assertSame( array(), $this->ids( $kit, 'trucks' ), 'a slot with no sources takes nothing' );
		self::assertSame( array(), $this->ids( $kit, 'sails' ), 'nor does a slot the kit lacks' );
		self::assertSame( array(), $this->ids( $one ), 'nor a product that is no kit' );
		self::assertSame( array(), $this->ids( 999999 ) );

		$part = $this->kits()->candidates( $kit, 'deck' )[0];
		self::assertSame( $one, $part->id );
		self::assertSame( '8.2500', $part->specs->measure( SpecKey::DeckWidth )?->decimal(), 'its specs are the index\'s' );
	}

	public function test_brands_and_attributes_narrow_the_categories_and_a_named_product_is_added(): void {
		$decks  = self::term( 'product_cat', 'Decks' );
		$brand  = self::term( 'product_brand', 'Rascals' );
		$other  = self::term( 'product_brand', 'Others' );
		register_taxonomy( 'pa_shape', 'product' );
		$popsicle = self::term( 'pa_shape', 'Popsicle' );
		$cruiser  = self::term( 'pa_shape', 'Cruiser' );
		$match    = $this->part( 'Ours, popsicle', array(), array( 'product_cat' => array( $decks ), 'product_brand' => array( $brand ), 'pa_shape' => array( $popsicle ) ) );
		$shape    = $this->part( 'Ours, cruiser', array(), array( 'product_cat' => array( $decks ), 'product_brand' => array( $brand ), 'pa_shape' => array( $cruiser ) ) );
		$theirs   = $this->part( 'Theirs, popsicle', array(), array( 'product_cat' => array( $decks ), 'product_brand' => array( $other ), 'pa_shape' => array( $popsicle ) ) );
		$named    = $this->part( 'Named, in nothing' );

		self::assertSame( array( $match, $shape ), $this->ids( self::kitWith( array( 'categories' => array( $decks ), 'brands' => array( $brand ) ) ) ), 'in the category and of the brand' );
		self::assertSame( array( $match, $shape, $theirs ), $this->ids( self::kitWith( array( 'categories' => array( $decks ) ) ) ) );
		self::assertSame(
			array( $match ),
			$this->ids(
				self::kitWith(
					array(
						'categories' => array( $decks ),
						'brands'     => array( $brand ),
						'attributes' => array(
							array(
								'taxonomy' => 'pa_shape',
								'terms'    => array( $popsicle ),
							),
						),
					)
				)
			)
		);
		self::assertSame( array( $match, $shape, $named ), $this->ids( self::kitWith( array( 'categories' => array( $decks ), 'brands' => array( $brand ), 'products' => array( $named, $match ) ) ) ), 'a named product is added, and one found twice is one part' );
		self::assertSame( array( $named ), $this->ids( self::kitWith( array( 'products' => array( $named, 999999 ) ) ) ) );
		self::assertSame( array( $match, $shape ), $this->ids( self::kitWith( array( 'brands' => array( $brand ) ) ) ), 'a brand alone is every part of the brand' );
		self::assertSame( 'rascals', $this->kits()->candidates( self::kitWith( array( 'products' => array( $match ) ) ), 'deck' )[0]->brand );
		self::assertSame( '', $this->kits()->candidates( self::kitWith( array( 'products' => array( $named ) ) ), 'deck' )[0]->brand );
	}

	public function test_only_what_a_shopper_could_be_shown_is_a_part(): void {
		$decks   = self::term( 'product_cat', 'Decks' );
		$in      = array( 'product_cat' => array( $decks ) );
		$shown   = $this->part( 'Shown', array(), $in );
		$search  = $this->part( 'In search alone', array(), $in, array( 'set_catalog_visibility' => 'search' ) );
		$draft   = $this->part( 'Draft', array(), $in, array( 'set_status' => 'draft' ) );
		$private = $this->part( 'Private', array(), $in, array( 'set_status' => 'private' ) );
		$hidden  = $this->part( 'Hidden', array(), $in, array( 'set_catalog_visibility' => 'hidden' ) );
		$free    = $this->part( 'No price', array(), $in, array( 'set_regular_price' => '' ) );
		$locked  = $this->part( 'Behind a password', array(), $in );
		wp_update_post(
			array(
				'ID'            => $locked,
				'post_password' => 'secret',
			)
		);
		$another = KitFactory::kit( 'A kit is no part' );
		wp_set_object_terms( $another->get_id(), array( $decks ), 'product_cat' );

		$kit = self::kitWith(
			array(
				'categories' => array( $decks ),
				'products'   => array( $draft, $private, $hidden, $free, $locked, $another->get_id() ),
			)
		);
		self::assertSame( array( $shown, $search ), $this->ids( $kit ), 'published, not hidden, with a price, and open to anyone: named or found' );
	}

	public function test_a_variable_product_gives_its_variations_and_each_is_narrowed_by_its_own_value(): void {
		$trucks = self::term( 'product_cat', 'Trucks' );
		register_taxonomy( 'pa_size', array( 'product', 'product_variation' ) );
		$small = self::factory()->term->create(
			array(
				'taxonomy' => 'pa_size',
				'name'     => '139',
				'slug'     => '139',
			)
		);
		$large = self::factory()->term->create(
			array(
				'taxonomy' => 'pa_size',
				'name'     => '149',
				'slug'     => '149',
			)
		);

		$parent    = new \WC_Product_Variable();
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( 1 );
		$attribute->set_name( 'pa_size' );
		$attribute->set_options( array( $small, $large ) );
		$attribute->set_variation( true );
		$parent->set_name( 'Stage 11' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();
		wp_set_object_terms( $parent->get_id(), array( $trucks ), 'product_cat' );
		wp_set_object_terms( $parent->get_id(), array( $small, $large ), 'pa_size' );
		$make = function ( string $size, string $price, bool $enabled = true ) use ( $parent ): int {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( array( 'pa_size' => $size ) );
			$variation->set_regular_price( $price );
			$variation->set_status( $enabled ? 'publish' : 'private' );
			$variation->update_meta_data( Options::META_SPECS, array( 'axle_width' => '149' === $size ? '8.5000' : '8.0000' ) );
			$variation->save();
			$this->service( SpecIndexer::class )->index( $variation->get_id() );
			return $variation->get_id();
		};
		$v139 = $make( '139', '30' );
		$v149 = $make( '149', '32' );
		$any  = $make( '', '31' );
		$make( '149', '33', false );
		$make( '149', '' );
		\WC_Product_Variable::sync( $parent->get_id() );

		$slots                        = KitDocuments::slots();
		$slots['slots'][1]['sources'] = array( 'categories' => array( $trucks ) );
		$kit                          = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
		self::assertSame( array( $v139, $v149, $any ), $this->ids( $kit, 'trucks' ), 'each variation that is enabled and priced, and never the parent' );

		$slots['slots'][1]['sources']['attributes'] = array(
			array(
				'taxonomy' => 'pa_size',
				'terms'    => array( $large ),
			),
		);
		$narrowed                                   = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
		self::assertSame( array( $v149, $any ), $this->ids( $narrowed, 'trucks' ), 'the 149, and the one that is any size' );

		$slots['slots'][1]['sources'] = array( 'products' => array( $v139, $parent->get_id() ) );
		$named                        = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
		self::assertSame( array( $v139, $v149, $any ), $this->ids( $named, 'trucks' ), 'a variation named, and a parent named for all of its own' );

		$parts = $this->kits()->candidates( $kit, 'trucks' );
		$found = array_values( array_filter( $parts, static fn( Part $part ): bool => $part->id === $v149 ) )[0];
		self::assertSame( '8.5000', $found->specs->measure( SpecKey::AxleWidth )?->decimal() );
		self::assertSame( 3200, $found->price );
	}

	public function test_a_part_carries_its_price_in_minor_units_and_its_stock(): void {
		$counted = $this->part(
			'Counted',
			array(),
			array(),
			array(
				'set_regular_price'  => '54.95',
				'set_sale_price'     => '49.50',
				'set_manage_stock'   => true,
				'set_stock_quantity' => 3,
			)
		);
		$gone    = $this->part( 'Sold out', array(), array(), array( 'set_stock_status' => 'outofstock' ) );
		$plain   = $this->part( 'Not counted' );
		$kit     = self::kitWith( array( 'products' => array( $counted, $gone, $plain ) ) );
		$parts   = array();
		foreach ( $this->kits()->candidates( $kit, 'deck' ) as $part ) {
			$parts[ $part->id ] = array( $part->price, $part->inStock, $part->stock );
		}
		self::assertSame(
			array(
				$counted => array( 4950, true, 3 ),
				$gone    => array( 5000, false, null ),
				$plain   => array( 5000, true, null ),
			),
			$parts,
			'a part that is sold out is still a part: the builder shows it as gone'
		);
	}

	/**
	 * Issue #17: the check's kit rules were tested on kits made from documents, and found nothing in a shop.
	 */
	public function test_the_catalogue_check_reads_a_real_kit_and_its_real_parts(): void {
		$decks  = self::term( 'product_cat', 'Decks' );
		$trucks = self::term( 'product_cat', 'Trucks' );
		$deck   = $this->part( 'A deck with no width', array(), array( 'product_cat' => array( $decks ) ) );
		$this->part( 'Trucks, sold out', array( 'axle_width' => '8.0000' ), array( 'product_cat' => array( $trucks ) ), array( 'set_stock_status' => 'outofstock' ) );

		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array( 'categories' => array( $decks ) );
		$slots['slots'][1]['sources'] = array( 'categories' => array( $trucks ) );
		$presets                      = KitDocuments::presets();
		$presets['presets'][0]        = array(
			'name'  => 'Street',
			'picks' => array(
				'deck'     => $deck,
				'trucks'   => 999999,
				'wheels'   => 999999,
				'bearings' => 999999,
				'hardware' => 999999,
				'grip'     => 999999,
			),
		);
		$kit                          = KitFactory::kit(
			'Complete',
			array(
				Options::META_KIT_SLOTS   => $slots,
				Options::META_KIT_PRESETS => $presets,
			)
		)->get_id();

		$found = array();
		foreach ( $this->service( CatalogueCheck::class )->run() as $finding ) {
			self::assertInstanceOf( CheckFinding::class, $finding );
			if ( $kit === $finding->kit ) {
				$found[] = $finding->code . ':' . $finding->slot . ':' . $finding->part . ':' . ( $finding->values['problem'] ?? $finding->spec );
			}
		}
		self::assertContains( CatalogueCheck::SPEC_MISSING . ':deck:' . $deck . ':deck_width', $found );
		self::assertContains( CatalogueCheck::SLOT_NO_STOCK . ':trucks:0:', $found );
		self::assertContains( CatalogueCheck::SLOT_EMPTY . ':wheels:0:', $found );
		self::assertContains( CatalogueCheck::PRESET . ':trucks:999999:part_not_in_slot', $found );
		self::assertNotContains( CatalogueCheck::SLOT_EMPTY . ':deck:0:', $found );
		self::assertNotContains( CatalogueCheck::PRESET . ':deck:' . $deck . ':part_not_in_slot', $found, 'the preset\'s deck is the slot\'s' );
	}
}
