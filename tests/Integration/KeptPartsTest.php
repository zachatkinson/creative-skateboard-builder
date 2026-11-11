<?php
/**
 * Each slot's kept list of parts.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Hooks\CardHooks;
use CreativeBranding\SkateboardBuilder\Hooks\IndexHooks;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\PartRows;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Product_Simple;

/**
 * D-116. The state route's budget holds because a request loads no product to learn a slot's parts: they are kept
 * for an hour at most, for the prices a visitor is shown, and forgotten the moment anything they are made of moves.
 * Spec, Performance: the budgets hold without a persistent object cache; Security: prices are the server's.
 */
final class KeptPartsTest extends IntegrationTestCase {

	private int $category;

	private int $kit;

	private int $deck;

	public function set_up(): void {
		parent::set_up();
		$this->service( CardHooks::class )->register();
		$this->service( IndexHooks::class )->register();
		$this->category               = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$this->deck                   = $this->part( 'Deck 8.25', '60' );
		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array( 'categories' => array( $this->category ) );
		$this->kit                    = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
	}

	/**
	 * A published deck in the slot's category, indexed.
	 */
	private function part( string $name, string $price ): int {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->update_meta_data(
			Options::META_SPECS,
			array(
				'deck_width'   => '8.25',
				'deck_length'  => '32',
				'hole_pattern' => 'new_school',
			)
		);
		$product->save();
		wp_set_object_terms( $product->get_id(), array( $this->category ), 'product_cat' );
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		return $product->get_id();
	}

	private function kits(): Kits {
		return $this->service( Kits::class );
	}

	/**
	 * The deck slot's parts as a build's state has them, by ID.
	 *
	 * @return array<int, Part>
	 */
	private function decks(): array {
		$state = $this->service( BuildState::class )->of( $this->kit, array() );
		self::assertNotNull( $state );
		$parts = array();
		foreach ( array( ...$state->steps[0]->options->offered, ...$state->steps[0]->options->hidden ) as $option ) {
			$parts[ $option->part->id ] = $option->part;
		}
		ksort( $parts );
		return $parts;
	}

	private static function mark(): string {
		return (string) get_option( Options::PARTS_MARK, '' );
	}

	public function test_a_slots_parts_are_kept_in_a_transient_for_an_hour_and_read_back(): void {
		self::assertNull( $this->kits()->kept( $this->kit, 'deck' ) );
		$rows = PartRows::of( $this->kits()->candidates( $this->kit, 'deck' ) );
		self::assertEquals( array( array( $this->deck, '', 6000, true, null, array( 'deck_width' => '8.2500', 'deck_length' => '32.0000', 'hole_pattern' => 'new_school' ) ) ), $rows );
		$this->kits()->keep( $this->kit, 'deck', $rows );
		self::assertSame( $rows, $this->kits()->kept( $this->kit, 'deck' ) );
		self::assertNull( $this->kits()->kept( $this->kit, 'trucks' ), 'each slot has its own' );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The test reads what the plugin stored.
		$names = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_cbuild_parts_' ) . '%' ) );
		self::assertCount( 1, $names );
		self::assertMatchesRegularExpression( '/^_transient_cbuild_parts_' . $this->kit . '_[0-9a-f]{16}$/', $names[0] );
		$timeout = (int) get_option( '_transient_timeout_' . substr( $names[0], strlen( '_transient_' ) ) );
		self::assertGreaterThan( time() + HOUR_IN_SECONDS - 60, $timeout );
		self::assertLessThanOrEqual( time() + HOUR_IN_SECONDS, $timeout );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The test reads what the plugin stored.
		self::assertContains( $wpdb->get_var( $wpdb->prepare( 'SELECT autoload FROM %i WHERE option_name = %s', $wpdb->options, $names[0] ) ), array( 'off', 'no' ), 'never loaded with every request' );
	}

	public function test_forgetting_changes_the_mark_and_nothing_kept_under_the_old_one_is_read(): void {
		$this->kits()->kept( $this->kit, 'deck' );
		$this->kits()->keep( $this->kit, 'deck', array() );
		self::assertSame( array(), $this->kits()->kept( $this->kit, 'deck' ) );
		$before = self::mark();
		$this->kits()->forget();
		self::assertNotSame( $before, self::mark() );
		self::assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', self::mark() );
		self::assertNull( $this->kits()->kept( $this->kit, 'deck' ) );
		$again = self::mark();
		$this->kits()->forget();
		self::assertNotSame( $again, self::mark(), 'a new mark each time' );
	}

	public function test_parts_read_before_something_moved_are_not_kept_after_it(): void {
		$kits = $this->kits();
		$rows = PartRows::of( $kits->candidates( $this->kit, 'deck' ) );
		// Nothing is kept by one who never asked what was there.
		$kits->keep( $this->kit, 'deck', $rows );
		self::assertNull( $kits->kept( $this->kit, 'deck' ) );
		// Asked, then something moved while the parts were being read.
		$kits->forget();
		$kits->keep( $this->kit, 'deck', $rows );
		self::assertNull( $kits->kept( $this->kit, 'deck' ) );
		$kits->keep( $this->kit, 'deck', $rows );
		self::assertSame( $rows, $kits->kept( $this->kit, 'deck' ), 'read again after the move, they are' );
	}

	public function test_the_second_answer_loads_no_part_and_is_the_first(): void {
		$asked = 0;
		add_filter(
			'cbuild_part_available',
			static function ( $available ) use ( &$asked ) {
				++$asked;
				return $available;
			}
		);
		$first = $this->decks();
		self::assertSame( 1, $asked );
		self::assertSame( array( $this->deck ), array_keys( $first ) );
		self::assertEquals( $first, $this->decks() );
		self::assertSame( 1, $asked, 'the part was read once' );
	}

	public function test_visitors_shown_other_prices_have_lists_of_their_own(): void {
		$this->decks();
		self::assertNotNull( $this->kits()->kept( $this->kit, 'deck' ) );
		$dealer = static fn( array $context ): array => array( ...$context, 'dealer' );
		add_filter( 'cbuild_price_context', $dealer );
		self::assertNull( $this->kits()->kept( $this->kit, 'deck' ), 'nothing kept for a dealer yet' );
		add_filter( 'woocommerce_product_get_price', static fn(): string => '45' );
		self::assertSame( 4500, $this->decks()[ $this->deck ]->price );
		remove_all_filters( 'woocommerce_product_get_price' );
		remove_filter( 'cbuild_price_context', $dealer );
		self::assertSame( 6000, $this->decks()[ $this->deck ]->price, 'and the shop\'s own price is still kept for everyone else' );
	}

	/**
	 * What moves in a shop, and what a build's state must then say of the deck slot.
	 *
	 * @return iterable<string, array{callable(self): void, callable(array<int, Part>, self): void}>
	 */
	public static function moves(): iterable {
		$gone = static function ( array $parts, self $test ): void {
			self::assertArrayNotHasKey( $test->deck, $parts );
		};
		yield 'a price is saved' => array(
			static function ( self $test ): void {
				$product = wc_get_product( $test->deck );
				$product->set_regular_price( '75' );
				$product->save();
			},
			static function ( array $parts, self $test ): void {
				self::assertSame( 7500, $parts[ $test->deck ]->price );
			},
		);
		yield 'stock is counted down, as an order does' => array(
			static function ( self $test ): void {
				$product = wc_get_product( $test->deck );
				$product->set_manage_stock( true );
				$product->set_stock_quantity( 5 );
				$product->save();
				$test->decks();
				wc_update_product_stock( $test->deck, 4, 'decrease' );
			},
			static function ( array $parts, self $test ): void {
				self::assertSame( 1, $parts[ $test->deck ]->stock );
			},
		);
		yield 'the part sells out' => array(
			static function ( self $test ): void {
				wc_update_product_stock_status( $test->deck, 'outofstock' );
			},
			static function ( array $parts, self $test ): void {
				self::assertFalse( $parts[ $test->deck ]->inStock );
			},
		);
		yield 'a new part joins the category' => array(
			static function ( self $test ): void {
				$test->added = $test->part( 'Deck 8.5', '65' );
			},
			static function ( array $parts, self $test ): void {
				self::assertSame( array( $test->deck, $test->added ), array_keys( $parts ) );
			},
		);
		yield 'the part leaves the category, with no product saved' => array(
			static function ( self $test ): void {
				wp_set_object_terms( $test->deck, array(), 'product_cat' );
			},
			$gone,
		);
		yield 'the category is deleted' => array(
			static function ( self $test ): void {
				wp_delete_term( $test->category, 'product_cat' );
			},
			$gone,
		);
		yield 'the part goes to the bin' => array(
			static function ( self $test ): void {
				wp_trash_post( $test->deck );
			},
			$gone,
		);
		yield 'the part is unpublished without WooCommerce' => array(
			static function ( self $test ): void {
				wp_update_post(
					array(
						'ID'          => $test->deck,
						'post_status' => 'draft',
					)
				);
			},
			$gone,
		);
		yield 'the part is deleted' => array(
			static function ( self $test ): void {
				wp_delete_post( $test->deck, true );
			},
			$gone,
		);
		yield 'the index is written by its job' => array(
			static function ( self $test ): void {
				// Written past WooCommerce, so that nothing but the job says the specs moved.
				update_post_meta( $test->deck, Options::META_SPECS, array( 'deck_width' => '8.5' ) );
				do_action( Options::JOB_INDEX_PART, $test->deck, 0 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			},
			static function ( array $parts, self $test ): void {
				self::assertSame( array( 'deck_width' => '8.5000' ), $parts[ $test->deck ]->specs->toIndex() );
			},
		);
		yield 'the kit is saved with other sources' => array(
			static function ( self $test ): void {
				$slots                        = KitDocuments::slots();
				$slots['slots'][0]['sources'] = array( 'categories' => array( self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) ) ) );
				$kit                          = new \CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $test->kit );
				$kit->setDocument( Options::META_KIT_SLOTS, $slots );
				$kit->save();
			},
			$gone,
		);
	}

	/**
	 * A part a move added.
	 */
	public int $added = 0;

	/**
	 * @dataProvider moves
	 *
	 * @param callable(self): void                   $move  What moves.
	 * @param callable(array<int, Part>, self): void $holds What must then be so.
	 */
	public function test_what_is_kept_is_forgotten_when_the_shop_moves( callable $move, callable $holds ): void {
		$before = $this->decks();
		self::assertSame( 6000, $before[ $this->deck ]->price );
		self::assertNotNull( $this->kits()->kept( $this->kit, 'deck' ), 'kept before the move' );
		$move( $this );
		$holds( $this->decks(), $this );
	}

	public function test_a_sale_starting_or_ending_and_a_term_edited_forget_the_lists(): void {
		$brand = self::factory()->term->create( array( 'taxonomy' => 'product_brand' ) );
		foreach ( array(
			'wc_after_products_starting_sales' => static fn() => do_action( 'wc_after_products_starting_sales', array( 1 ) ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'wc_after_products_ending_sales'   => static fn() => do_action( 'wc_after_products_ending_sales', array( 1 ) ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'edited_term'                      => static fn() => wp_update_term( $brand, 'product_brand', array( 'slug' => 'renamed' ) ),
			'a reindex batch'                  => static fn() => do_action( Options::JOB_REINDEX ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		) as $what => $happens ) {
			$before = self::mark();
			$happens();
			self::assertNotSame( $before, self::mark(), $what );
		}
	}

	public function test_a_post_that_is_no_part_moves_nothing(): void {
		$this->kits()->forget();
		$before = self::mark();
		$page   = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft' ) );
		wp_publish_post( $page );
		wp_trash_post( $page );
		self::assertSame( $before, self::mark() );
		// A product saved to the status it has is no move of its status either; its save says so by its own hook.
		remove_all_actions( 'woocommerce_update_product' );
		remove_all_actions( 'set_object_terms' );
		wp_update_post(
			array(
				'ID'         => $this->deck,
				'post_title' => 'Deck 8.25, again',
			)
		);
		self::assertSame( $before, self::mark() );
	}
}
