<?php
/**
 * Parts' cards.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WooPartCards;
use CreativeBranding\SkateboardBuilder\Hooks\CardHooks;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * `Adapter\WooPartCards`: what a card shows of a part (spec: Vocabulary, Option), kept per part and read again when
 * the part changes (milestone 4's plan, card data).
 */
final class WooPartCardsTest extends IntegrationTestCase {

	public function tear_down(): void {
		foreach ( array( 'woocommerce_stock_format', 'woocommerce_calc_taxes', 'woocommerce_tax_display_shop', 'woocommerce_prices_include_tax' ) as $option ) {
			delete_option( $option );
		}
		parent::tear_down();
	}

	private function cards(): PartCards {
		return $this->service( PartCards::class );
	}

	/**
	 * A product at 50.00, with any setters called.
	 *
	 * @param array<string, mixed> $set Setter to value.
	 */
	private static function product( string $name, array $set = array() ): int {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '50' );
		foreach ( $set as $setter => $value ) {
			$product->$setter( $value );
		}
		return $product->save();
	}

	/**
	 * An amount as the shop writes it, in plain text.
	 */
	private static function price( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * A photo in the media library.
	 */
	private static function photo(): int {
		return self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * Change a price behind WooCommerce's back, as nothing in a shop should: no hook fires, and no post is saved.
	 */
	private static function quietly( int $id, string $price ): void {
		update_post_meta( $id, '_price', $price );
		update_post_meta( $id, '_regular_price', $price );
	}

	public function test_a_card_is_the_parts_name_page_price_stock_and_photo_as_the_shop_shows_them(): void {
		update_option( 'woocommerce_stock_format', '' );
		$photo = self::photo();
		$deck  = self::product(
			'Rascals "8.25" & deck',
			array(
				'set_sale_price'     => '44.50',
				'set_manage_stock'   => true,
				'set_stock_quantity' => 3,
				'set_image_id'       => $photo,
			)
		);
		$plain = self::product( 'Plain' );
		$gone  = self::product( 'Gone', array( 'set_stock_status' => 'outofstock' ) );

		$cards = $this->cards()->cards( array( $gone, $deck, 0, -4, 999999, $plain, $deck ) );
		self::assertSame( array( $gone, $deck, $plain ), array_keys( $cards ), 'in the order asked, each once, and nothing for what is no part' );

		$card = $cards[ $deck ];
		self::assertSame( array( 'name', 'url', 'price', 'stock', 'stock_class', 'image' ), array_keys( $card ) );
		self::assertSame( 'Rascals "8.25" & deck', $card['name'], 'plain text, where WordPress stores &amp;: the edge escapes it once' );
		self::assertSame( get_permalink( $deck ), $card['url'] );
		self::assertSame( self::price( 44.5 ), $card['price'], 'the price of one as the shop shows it' );
		self::assertSame( '$44.50', $card['price'], 'plain text: no markup, and the symbol itself' );
		self::assertSame( '3 in stock', $card['stock'] );
		self::assertSame( 'in-stock', $card['stock_class'] );
		self::assertNotNull( $card['image'] );
		self::assertSame( wp_get_attachment_image_src( $photo, 'woocommerce_thumbnail' )[0], $card['image']['src'] );
		self::assertGreaterThan( 0, $card['image']['width'] );
		self::assertGreaterThan( 0, $card['image']['height'] );
		self::assertSame( array( 'src', 'width', 'height', 'srcset', 'sizes' ), array_keys( $card['image'] ), 'no words of its own: the name stands beside it' );
		self::assertIsString( $card['image']['srcset'] );
		self::assertIsString( $card['image']['sizes'] );

		self::assertNull( $cards[ $plain ]['image'], 'a part with no photo' );
		self::assertSame( '', $cards[ $plain ]['stock'], 'the shop says nothing of a part it does not count' );
		self::assertSame( self::price( 50 ), $cards[ $plain ]['price'] );
		self::assertSame( 'Out of stock', $cards[ $gone ]['stock'] );
		self::assertSame( 'out-of-stock', $cards[ $gone ]['stock_class'] );

		self::assertSame( array(), $this->cards()->cards( array() ) );
		self::assertSame( array(), $this->cards()->cards( array( self::factory()->post->create() ) ), 'a post that is no product' );
	}

	public function test_stock_is_said_in_the_shops_own_display_format(): void {
		$deck = self::product( 'Counted', array( 'set_manage_stock' => true, 'set_stock_quantity' => 2 ) );
		update_option( 'woocommerce_stock_format', 'no_amount' );
		self::assertSame( 'In stock', $this->cards()->cards( array( $deck ) )[ $deck ]['stock'], 'a shop that hides its counts' );
		self::assertStringNotContainsString( '2', $this->cards()->cards( array( $deck ) )[ $deck ]['stock'] );
	}

	public function test_a_variation_has_its_own_name_and_price_and_its_parents_photo_where_it_has_none(): void {
		$photo  = self::photo();
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Team deck' );
		$parent->set_image_id( $photo );
		$parent->save();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_attributes( array( 'width' => '8.25' ) );
		$variation->set_regular_price( '61' );
		$id = $variation->save();

		$card = $this->cards()->cards( array( $id ) )[ $id ];
		self::assertStringStartsWith( 'Team deck', $card['name'] );
		self::assertSame( self::price( 61 ), $card['price'] );
		self::assertSame( wp_get_attachment_image_src( $photo, 'woocommerce_thumbnail' )[0], $card['image']['src'] ?? null, 'its parent\'s photo' );

		// The parent is renamed: its variation's card is read again, by the hook and by the parent's last change.
		$this->service( CardHooks::class )->register();
		$parent->set_name( 'Pro deck' );
		$parent->save();
		self::assertStringStartsWith( 'Pro deck', $this->cards()->cards( array( $id ) )[ $id ]['name'] );
	}

	public function test_a_card_is_kept_and_read_again_when_its_part_is_forgotten(): void {
		$deck = self::product( 'Kept' );
		self::assertSame( self::price( 50 ), $this->cards()->cards( array( $deck ) )[ $deck ]['price'] );

		self::quietly( $deck, '75' );
		self::assertSame( self::price( 50 ), $this->cards()->cards( array( $deck ) )[ $deck ]['price'], 'kept: the product is not read a second time' );
		$entry = wp_cache_get( 'card_' . $deck, Options::CACHE_GROUP );
		self::assertIsArray( $entry );
		self::assertSame( array( 'stamp', 'cards' ), array_keys( $entry ) );
		self::assertCount( 1, $entry['cards'] );
		self::assertSame( wp_json_encode( $entry ), wp_json_encode( json_decode( (string) wp_json_encode( $entry ), true ) ), 'scalars and arrays of them alone' );

		$this->cards()->forget( $deck, 0, -1 );
		self::assertSame( self::price( 75 ), $this->cards()->cards( array( $deck ) )[ $deck ]['price'] );
		$this->cards()->forget();
	}

	public function test_a_part_saved_since_is_read_again_whatever_fired(): void {
		global $wpdb;
		$deck = self::product( 'Saved since' );
		$this->cards()->cards( array( $deck ) );
		self::quietly( $deck, '75' );
		// Saved a minute later by something that fires none of WooCommerce's hooks.
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() + 60 ) ), array( 'ID' => $deck ) );
		clean_post_cache( $deck );
		self::assertSame( self::price( 75 ), $this->cards()->cards( array( $deck ) )[ $deck ]['price'] );
	}

	public function test_each_thing_that_moves_a_price_or_stock_forgets_the_card(): void {
		$this->service( CardHooks::class )->register();
		$deck  = self::product( 'Moves', array( 'set_manage_stock' => true, 'set_stock_quantity' => 5 ) );
		$price = fn(): string => $this->cards()->cards( array( $deck ) )[ $deck ]['price'];
		$stock = fn(): string => $this->cards()->cards( array( $deck ) )[ $deck ]['stock'];
		update_option( 'woocommerce_stock_format', '' );
		self::assertSame( '5 in stock', $stock() );

		// An order takes two: WooCommerce writes the count without saving the product.
		wc_update_product_stock( $deck, 2, 'decrease' );
		self::assertSame( '3 in stock', $stock(), 'woocommerce_product_set_stock' );

		// The product is saved with a new price.
		$product = wc_get_product( $deck );
		$product->set_regular_price( '60' );
		$product->save();
		self::assertSame( self::price( 60 ), $price(), 'woocommerce_update_product' );

		// A sale starts and ends on schedule: each is told with the IDs, after the prices are written.
		foreach ( array( 'wc_after_products_starting_sales' => '40', 'wc_after_products_ending_sales' => '60' ) as $hook => $now ) {
			$price();
			self::quietly( $deck, $now );
			self::assertNotSame( self::price( (float) $now ), $price(), 'kept until the hook' );
			do_action( $hook, array( $deck ) );
			self::assertSame( self::price( (float) $now ), $price(), $hook );
		}

		// The stock status alone.
		$stock();
		update_post_meta( $deck, '_stock_status', 'outofstock' );
		do_action( 'woocommerce_product_set_stock_status', $deck, 'outofstock', wc_get_product( $deck ) );
		self::assertSame( 'out-of-stock', $this->cards()->cards( array( $deck ) )[ $deck ]['stock_class'], 'woocommerce_product_set_stock_status' );

		// Deleted: nothing is kept of it.
		wp_delete_post( $deck, true );
		self::assertFalse( wp_cache_get( 'card_' . $deck, Options::CACHE_GROUP ) );
		self::assertSame( array(), $this->cards()->cards( array( $deck ) ) );
	}

	public function test_the_hooks_are_the_ones_woocommerce_fires(): void {
		$hooks = $this->service( CardHooks::class );
		$hooks->register();
		foreach ( array( 'woocommerce_update_product', 'woocommerce_update_product_variation', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'wc_after_products_starting_sales', 'wc_after_products_ending_sales', 'deleted_post' ) as $hook ) {
			self::assertSame( 10, has_action( $hook, array( $hooks, 'moved' ) ), $hook );
		}
		// What a hook hands over that names no product forgets nothing and fails nothing.
		$hooks->moved( null );
		$hooks->moved( 'draft' );
		$hooks->moved( array() );
		self::assertTrue( true );
	}

	public function test_a_price_context_has_a_card_of_its_own_and_an_entry_holds_a_few(): void {
		$deck  = self::product( 'In two currencies' );
		$price = fn(): string => $this->cards()->cards( array( $deck ) )[ $deck ]['price'];
		$shops = get_option( 'woocommerce_currency' );
		$cad   = $price();
		update_option( 'woocommerce_currency', 'EUR' );
		$eur = $price();
		self::assertNotSame( $cad, $eur, 'the same number with another currency\'s sign' );
		self::assertCount( 2, wp_cache_get( 'card_' . $deck, Options::CACHE_GROUP )['cards'] );

		// Both are kept: neither is read again.
		self::quietly( $deck, '75' );
		self::assertSame( $eur, $price() );
		update_option( 'woocommerce_currency', $shops );
		self::assertSame( $cad, $price() );

		// A fifth context pushes the oldest out.
		foreach ( array( 'GBP', 'JPY', 'AUD', 'NZD' ) as $currency ) {
			update_option( 'woocommerce_currency', $currency );
			$price();
		}
		self::assertCount( WooPartCards::CONTEXTS, wp_cache_get( 'card_' . $deck, Options::CACHE_GROUP )['cards'] );
		update_option( 'woocommerce_currency', $shops );
	}

	public function test_where_a_visitor_is_changes_the_price_and_no_address_is_kept(): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );
		$rate = \WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => 'CA', 'tax_rate_state' => 'ON', 'tax_rate' => '13.0000', 'tax_rate_name' => 'HST', 'tax_rate_priority' => 1, 'tax_rate_order' => 1, 'tax_rate_class' => '' ) );
		$deck = self::product( 'Taxed' );
		$at   = static function ( string $country, string $state, string $postcode ): void {
			add_filter( 'woocommerce_customer_taxable_address', static fn(): array => array( $country, $state, $postcode, 'Springfield' ), 99 );
		};

		$at( 'CA', 'ON', 'K1A 0B1' );
		$ontario = $this->cards()->cards( array( $deck ) )[ $deck ]['price'];
		self::assertSame( self::price( 56.5 ), $ontario );
		remove_all_filters( 'woocommerce_customer_taxable_address' );
		$at( 'CA', 'AB', 'T5J 0N3' );
		self::assertSame( self::price( 50 ), $this->cards()->cards( array( $deck ) )[ $deck ]['price'], 'no rate in Alberta: a card of its own' );

		$kept = (string) wp_json_encode( wp_cache_get( 'card_' . $deck, Options::CACHE_GROUP ) );
		foreach ( array( 'K1A', 'T5J', 'Springfield' ) as $address ) {
			self::assertStringNotContainsString( $address, $kept );
		}
		remove_all_filters( 'woocommerce_customer_taxable_address' );
		\WC_Tax::_delete_tax_rate( $rate );
	}

	public function test_an_entry_that_is_not_one_is_a_miss(): void {
		$deck = self::product( 'Broken entry' );
		$good = $this->cards()->cards( array( $deck ) );
		foreach ( array( 'text', array( 'stamp' => 'x' ), array( 'cards' => array() ) ) as $entry ) {
			wp_cache_set( 'card_' . $deck, $entry, Options::CACHE_GROUP, 60 );
			self::assertSame( $good, $this->cards()->cards( array( $deck ) ) );
		}
		// The right stamp and a card that lost a field, or whose photo is not one.
		$entry = wp_cache_get( 'card_' . $deck, Options::CACHE_GROUP );
		$key   = array_key_first( $entry['cards'] );
		foreach ( array( array( 'name' => 7 ), array( 'image' => 'photo.jpg' ), array( 'image' => array( 'src' => 'photo.jpg' ) ) ) as $broken ) {
			$bad                  = $entry;
			$bad['cards'][ $key ] = $broken + $entry['cards'][ $key ];
			wp_cache_set( 'card_' . $deck, $bad, Options::CACHE_GROUP, 60 );
			self::assertSame( $good, $this->cards()->cards( array( $deck ) ) );
		}
		$bad                  = $entry;
		$bad['cards'][ $key ] = 'a card';
		wp_cache_set( 'card_' . $deck, $bad, Options::CACHE_GROUP, 60 );
		self::assertSame( $good, $this->cards()->cards( array( $deck ) ) );
	}

	public function test_a_steps_cards_cost_a_few_queries_however_many_and_none_once_kept(): void {
		global $wpdb;
		$ids = array();
		for ( $n = 1; $n <= 24; $n++ ) {
			$ids[] = self::product( 'Part ' . $n );
		}
		wp_cache_flush_group( Options::CACHE_GROUP );
		foreach ( $ids as $id ) {
			clean_post_cache( $id );
		}
		$before = $wpdb->num_queries;
		self::assertCount( 24, $this->cards()->cards( $ids ) );
		$cold = $wpdb->num_queries - $before;
		self::assertLessThan( 24, $cold, 'not a query a part' );

		$before = $wpdb->num_queries;
		self::assertCount( 24, $this->cards()->cards( $ids ) );
		self::assertSame( 0, $wpdb->num_queries - $before, 'kept' );
	}
}
