<?php
/**
 * "Build a complete with this deck" on a deck's own page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\BuilderUrl;
use CreativeBranding\SkateboardBuilder\Front\StartButton;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Product_Simple;

/**
 * Spec (configurator): Ways in that skip the hunt, "Build a complete with this deck" (D-253): a deck's own product page
 * gets a button after its add-to-cart form, opening the kit's builder with that deck picked. It appears when the deck
 * is offered by a kit's first slot; where more than one kit offers it, the first by menu order. `cbuild_start_button`
 * can choose the kit, move the button, change its words or turn it off.
 */
final class StartButtonTest extends IntegrationTestCase {

	private int $deck;

	private int $truck;

	private int $first;

	private int $second;

	public function set_up(): void {
		parent::set_up();
		$decks  = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$trucks = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );

		$this->deck  = $this->part( 'Deck 8.25', array( 'deck_width' => '8.2500' ), $decks );
		$this->truck = $this->part(
			'Truck 144',
			array(
				'axle_width'    => '8.2500',
				'deck_min'      => '8.0000',
				'deck_max'      => '8.3750',
				'baseplate'     => 'new_school',
				'max_wheel'     => '56.0000',
				'axle_diameter' => '8.0000',
				'set_size'      => '2',
			),
			$trucks
		);

		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array( 'categories' => array( $decks ) );
		$slots['slots'][1]['sources'] = array( 'categories' => array( $trucks ) );
		// Made first, but second by menu order.
		$this->second = KitFactory::kit( 'Street complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
		$this->first  = KitFactory::kit( 'Cruiser complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
		wp_update_post(
			array(
				'ID'         => $this->second,
				'menu_order' => 2,
			)
		);
		wp_update_post(
			array(
				'ID'         => $this->first,
				'menu_order' => 1,
			)
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'cbuild_start_button' );
		remove_all_actions( 'woocommerce_after_add_to_cart_form' );
		remove_all_actions( 'woocommerce_before_add_to_cart_form' );
		parent::tear_down();
	}

	/**
	 * A published part in a category, indexed.
	 *
	 * @param array<string, string> $specs Its specs.
	 */
	private function part( string $name, array $specs, int $category ): int {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( '50' );
		$product->update_meta_data( Options::META_SPECS, $specs );
		$product->save();
		wp_set_object_terms( $product->get_id(), array( $category ), 'product_cat' );
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		return $product->get_id();
	}

	/**
	 * What a hook prints on a product's page, once the button has been placed.
	 */
	private function shown( int $product, string $hook = 'woocommerce_after_add_to_cart_form' ): string {
		$this->go_to( (string) get_permalink( $product ) );
		$this->service( StartButton::class )->place();
		ob_start();
		do_action( $hook );
		return (string) ob_get_clean();
	}

	public function test_a_decks_page_opens_the_first_kit_by_menu_order_with_the_deck_picked(): void {
		$html = $this->shown( $this->deck );
		self::assertStringContainsString( '>Build a complete with this deck</a>', $html );
		$href = html_entity_decode( (string) preg_replace( '/\A.*href="([^"]*)".*\z/s', '$1', $html ) );
		self::assertStringStartsWith( BuilderUrl::of( $this->first ), $href, 'the first kit by menu order' );
		self::assertStringContainsString( 'cbuild[deck]=' . $this->deck, rawurldecode( $href ), 'with the deck picked' );
		// The auditor's M10 review, 3: a link into a builder state is not for search engines, as the rail's are not.
		self::assertStringContainsString( 'rel="nofollow"', $html );
	}

	// The auditor's M10 review, 4: a product's page costs no more queries for a slot of 50 decks than of 5.
	public function test_a_decks_page_asks_no_more_of_the_database_however_many_decks_the_slot_has(): void {
		$count = function (): int {
			$this->shown( $this->deck );
			wp_cache_flush();
			global $wpdb;
			$before = $wpdb->num_queries;
			$this->shown( $this->deck );
			return $wpdb->num_queries - $before;
		};
		$few = $count();
		$cat = wp_get_object_terms( $this->deck, 'product_cat', array( 'fields' => 'ids' ) );
		for ( $i = 0; $i < 45; ++$i ) {
			$this->part( 'Deck ' . $i, array( 'deck_width' => '8.0000' ), (int) $cat[0] );
		}
		self::assertSame( $few, $count() );
	}

	// #115: a kit's Show on its parts' pages off leaves it out when the button picks its kit. Another kit that offers
	// the deck still places it, the lowest menu order first; with every kit off there is none.
	public function test_a_kit_turned_off_is_skipped_and_the_next_kit_by_menu_order_places_the_button(): void {
		$off = static function ( int $kit ): void {
			$product = wc_get_product( $kit );
			self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\KitProduct::class, $product );
			$product->setDocument( Options::META_KIT_SETTINGS, array( 'schema_version' => 1, 'start_button' => false ) );
			$product->save();
		};
		$off( $this->first );
		$html = $this->shown( $this->deck );
		self::assertStringContainsString( '>Build a complete with this deck</a>', $html );
		self::assertStringStartsWith( BuilderUrl::of( $this->second ), html_entity_decode( (string) preg_replace( '/\A.*href="([^"]*)".*\z/s', '$1', $html ) ), 'the next kit by menu order' );

		remove_all_actions( 'woocommerce_after_add_to_cart_form' );
		$off( $this->second );
		self::assertSame( '', $this->shown( $this->deck ), 'no kit places it' );

		// The filter answers last: a shop may still place it for a kit that is off.
		remove_all_actions( 'woocommerce_after_add_to_cart_form' );
		$asked = null;
		add_filter(
			'cbuild_start_button',
			function ( $button ) use ( &$asked ) {
				$asked = $button;
				return array(
					'kit'      => $this->second,
					'part'     => $this->deck,
					'slot'     => 'deck',
					'text'     => 'Build a complete with this deck',
					'hook'     => 'woocommerce_after_add_to_cart_form',
					'priority' => 10,
				);
			}
		);
		$html = $this->shown( $this->deck );
		self::assertFalse( $asked, 'the filter is told no kit places it' );
		self::assertStringContainsString( html_entity_decode( BuilderUrl::of( $this->second ) ), html_entity_decode( $html ), 'and its answer is followed' );
	}

	// #115: a kit saved before 1.2.0 has no start_button, and places the button as 1.1.0 did; on, as the tab saves it,
	// the same.
	public function test_a_kit_with_no_setting_or_with_it_on_places_the_button_as_before(): void {
		$product = wc_get_product( $this->first );
		self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\KitProduct::class, $product );
		self::assertNull( $product->documents()[ Options::META_KIT_SETTINGS ], 'saved before 1.2.0' );
		$before = $this->shown( $this->deck );
		self::assertStringStartsWith( BuilderUrl::of( $this->first ), html_entity_decode( (string) preg_replace( '/\A.*href="([^"]*)".*\z/s', '$1', $before ) ) );
		remove_all_actions( 'woocommerce_after_add_to_cart_form' );
		$product->setDocument( Options::META_KIT_SETTINGS, array( 'schema_version' => 1, 'start_button' => true ) );
		$product->save();
		self::assertSame( $before, $this->shown( $this->deck ) );
	}

	// #113 (D-348): the button opens the builder on the page the kit's Build tab chose, while that page is published
	// and holds the kit's Builder block; otherwise the kit's own page.
	public function test_the_button_opens_the_builder_on_the_kits_builder_page(): void {
		$page    = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $this->first . '} /-->',
			)
		);
		$product = wc_get_product( $this->first );
		self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\KitProduct::class, $product );
		$product->setDocument( Options::META_KIT_SETTINGS, array( 'schema_version' => 1, 'builder_page' => $page ) );
		$product->save();
		$href = static fn( string $html ): string => html_entity_decode( (string) preg_replace( '/\A.*href="([^"]*)".*\z/s', '$1', $html ) );
		$link = $href( $this->shown( $this->deck ) );
		self::assertStringStartsWith( (string) get_permalink( $page ), $link );
		self::assertStringContainsString( 'cbuild[deck]=' . $this->deck, rawurldecode( $link ), 'with the deck picked' );

		remove_all_actions( 'woocommerce_after_add_to_cart_form' );
		wp_update_post(
			array(
				'ID'          => $page,
				'post_status' => 'draft',
			)
		);
		self::assertStringStartsWith( (string) get_permalink( $this->first ), $href( $this->shown( $this->deck ) ), 'a page taken down: the kit\'s own' );
	}

	public function test_a_part_no_kits_first_slot_offers_gets_no_button(): void {
		self::assertSame( '', $this->shown( $this->truck ) );
	}

	public function test_a_shop_chooses_the_kit_moves_the_button_words_it_or_turns_it_off(): void {
		add_filter(
			'cbuild_start_button',
			fn( $button ) => array(
				'kit'      => $this->second,
				'text'     => 'Start a board',
				'hook'     => 'woocommerce_before_add_to_cart_form',
				'priority' => 5,
			) + (array) $button
		);
		$moved = $this->shown( $this->deck, 'woocommerce_before_add_to_cart_form' );
		self::assertStringContainsString( '>Start a board</a>', $moved );
		self::assertStringContainsString( BuilderUrl::of( $this->second ), html_entity_decode( $moved ) );

		remove_all_filters( 'cbuild_start_button' );
		remove_all_actions( 'woocommerce_before_add_to_cart_form' );
		add_filter( 'cbuild_start_button', '__return_false' );
		self::assertSame( '', $this->shown( $this->deck ), 'turned off' );
	}

	// The auditor's M10 review, 11: the slot the filter names is the one picked, where the kit has it.
	public function test_a_shop_can_name_another_slot_for_the_part(): void {
		add_filter( 'cbuild_start_button', fn( $button ) => is_array( $button ) ? array( 'slot' => 'trucks', 'part' => $this->truck ) + $button : $button );
		self::assertStringContainsString( 'cbuild[trucks]=' . $this->truck, rawurldecode( html_entity_decode( $this->shown( $this->deck ) ) ) );
		remove_all_filters( 'cbuild_start_button' );
		add_filter( 'cbuild_start_button', static fn( $button ) => is_array( $button ) ? array( 'slot' => 'spacers' ) + $button : $button );
		self::assertStringContainsString( 'cbuild[deck]=' . $this->deck, rawurldecode( html_entity_decode( $this->shown( $this->deck ) ) ), 'a slot the kit does not have is its first' );
	}

	// The spec reviewer's M10 minor M3: "with that deck picked, its width with it". On a variable deck's page the
	// button carries each in-stock variation's own link, which the variation form's choice puts in its place.
	public function test_a_variable_decks_button_carries_each_variations_link_for_the_one_chosen(): void {
		$decks  = (int) wp_get_object_terms( $this->deck, 'product_cat', array( 'fields' => 'ids' ) )[0];
		$width  = new \WC_Product_Attribute();
		$width->set_name( 'width' );
		$width->set_options( array( '8.0', '8.5', '9.0' ) );
		$width->set_variation( true );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Katie' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $width ) );
		$parent->save();
		wp_set_object_terms( $parent->get_id(), array( $decks ), 'product_cat' );
		$ids = array();
		foreach ( array( '8.0' => '8.0000', '8.5' => '8.5000', '9.0' => '9.0000' ) as $option => $spec ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( array( 'width' => $option ) );
			$variation->set_regular_price( '60' );
			$variation->set_stock_status( '9.0' === $option ? 'outofstock' : 'instock' );
			$variation->update_meta_data( Options::META_SPECS, array( 'deck_width' => $spec ) );
			$ids[ $option ] = $variation->save();
			$this->service( SpecIndexer::class )->index( $ids[ $option ] );
		}

		$html = $this->shown( $parent->get_id() );
		self::assertSame( 1, preg_match( '/data-cbuild-starts="([^"]*)"/', $html, $found ) );
		$starts = json_decode( html_entity_decode( $found[1] ), true );
		self::assertIsArray( $starts );
		self::assertSame( array( (string) $ids['8.0'], (string) $ids['8.5'] ), array_map( 'strval', array_keys( $starts ) ), 'each variation the kit offers in stock' );
		self::assertStringContainsString( 'cbuild[deck]=' . $ids['8.5'], rawurldecode( (string) $starts[ $ids['8.5'] ] ) );
		self::assertStringContainsString( 'data-cbuild-start="', $html, 'the button\'s own link, for a choice cleared' );
		// The script that follows the form's choice, after WooCommerce's own.
		do_action( 'wp_enqueue_scripts' );
		self::assertStringContainsString( 'found_variation reset_data', implode( '', (array) wp_scripts()->get_data( 'wc-add-to-cart-variation', 'after' ) ) );

		remove_all_actions( 'woocommerce_after_add_to_cart_form' );
		self::assertStringNotContainsString( 'data-cbuild-starts', $this->shown( $this->deck ), 'a simple deck has its one link' );
	}
}
