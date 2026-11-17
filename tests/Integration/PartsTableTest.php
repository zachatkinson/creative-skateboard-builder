<?php
/**
 * The parts table.
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
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Adapter\WooPartCards;
use CreativeBranding\SkateboardBuilder\Port\PartFact;
use CreativeBranding\SkateboardBuilder\Port\PartFacts;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Product_Simple;

/**
 * D-116. The state route's budget holds because a request loads no product to learn a slot's parts: what the shop
 * says of each is kept in a table, a row to a part and a price context, and a product that moves has its own rows
 * forgotten and no other's. Spec, Performance: the budgets hold without a persistent object cache; Security:
 * prices are the server's.
 */
final class PartsTableTest extends IntegrationTestCase {

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

	/**
	 * The rows kept of these products and their variations, for the prices this visitor is shown.
	 *
	 * @return array<int, PartFact>
	 */
	private function rows( int ...$ids ): array {
		// As the request's end does: what moved is deleted once, then.
		$kits = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class );
		self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\WooKits::class, $kits );
		$kits->settle();
		return $this->service( PartFacts::class )->of( WooPartCards::context(), ...$ids );
	}

	/**
	 * How many times a part was read from the shop: `cbuild_part_available` is asked once of each that is.
	 *
	 * @var list<int>
	 */
	private array $read = array();

	private function watch(): void {
		add_filter(
			'cbuild_part_available',
			function ( $available, $id ) {
				$this->read[] = $id;
				return $available;
			},
			10,
			2
		);
	}

	// Issue #28: the first request after a part was indexed read it from the shop, 470 ms at 2,000 parts. The job
	// that indexed it writes its row, for the prices a visitor nothing is known of is shown.
	public function test_the_job_that_indexes_a_part_writes_its_row_and_the_next_visitor_loads_no_product(): void {
		$loose = new WC_Product_Simple();
		$loose->set_name( 'No slot has this' );
		$loose->set_status( 'publish' );
		$loose->set_regular_price( '5' );
		$loose->save();
		self::assertSame( array(), $this->rows( $this->deck, $loose->get_id() ) );

		$this->service( IndexJobs::class )->indexPart( $this->deck );
		$this->service( IndexJobs::class )->indexPart( $loose->get_id() );
		$rows = $this->rows( $this->deck, $loose->get_id() );
		self::assertSame( array( $this->deck ), array_keys( $rows ), 'a product no kit could offer is given no row' );
		self::assertSame( 6000, $rows[ $this->deck ]->price );

		$this->watch();
		self::assertSame( array( $this->deck ), array_keys( $this->decks() ) );
		self::assertSame( array(), $this->read, 'the visitor reads rows' );

		// Indexed again after its price moved: the row is the new one, not one a request has to mend.
		$product = wc_get_product( $this->deck );
		$product->set_regular_price( '65' );
		$product->save();
		$this->service( IndexJobs::class )->indexPart( $this->deck );
		self::assertSame( 6500, $this->rows( $this->deck )[ $this->deck ]->price );
	}

	public function test_a_part_is_a_row_once_a_builds_state_has_asked_for_it(): void {
		self::assertSame( array(), $this->rows( $this->deck ) );
		$this->decks();
		$rows = $this->rows( $this->deck );
		self::assertSame( array( $this->deck ), array_keys( $rows ) );
		$row = $rows[ $this->deck ];
		self::assertSame( array( 0, true, '', 6000, true, null, array() ), array( $row->of, $row->sellable, $row->brand, $row->price, $row->inStock, $row->stock, $row->attributes ) );
		self::assertEqualsWithDelta( time(), $row->readAt, 5 );
	}

	public function test_one_part_that_moves_is_read_again_and_no_other(): void {
		$other = $this->part( 'Deck 8.5', '65' );
		$this->watch();
		$this->decks();
		self::assertSame( array( $this->deck, $other ), $this->read );
		$this->read = array();
		$product    = wc_get_product( $other );
		$product->set_regular_price( '70' );
		$product->save();
		$parts = $this->decks();
		self::assertSame( array( $other ), $this->read, 'the one that moved' );
		self::assertSame( array( 6000, 7000 ), array( $parts[ $this->deck ]->price, $parts[ $other ]->price ) );
	}

	public function test_a_variable_product_stands_for_its_variations_and_a_new_or_a_lost_one_is_noticed(): void {
		register_taxonomy( 'pa_width', 'product' );
		$narrow    = self::factory()->term->create(
			array(
				'taxonomy' => 'pa_width',
				'slug'     => 'w-8',
			)
		);
		$wide      = self::factory()->term->create(
			array(
				'taxonomy' => 'pa_width',
				'slug'     => 'w-85',
			)
		);
		$parent    = new \WC_Product_Variable();
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( 1 );
		$attribute->set_name( 'pa_width' );
		$attribute->set_options( array( $narrow, $wide ) );
		$attribute->set_variation( true );
		$parent->set_name( 'Deck, two widths' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();
		wp_set_object_terms( $parent->get_id(), array( $this->category ), 'product_cat' );
		wp_set_object_terms( $parent->get_id(), array( $narrow, $wide ), 'pa_width' );
		$variation = static function ( string $slug ) use ( $parent ): int {
			$one = new \WC_Product_Variation();
			$one->set_parent_id( $parent->get_id() );
			$one->set_attributes( array( 'pa_width' => $slug ) );
			$one->set_regular_price( '80' );
			$one->set_status( 'publish' );
			$one->save();
			return $one->get_id();
		};
		$first = $variation( 'w-8' );
		self::assertSame( array( $this->deck, $first ), array_keys( $this->decks() ), 'the variation, and never the parent' );
		$rows = $this->rows( $parent->get_id() );
		self::assertFalse( $rows[ $parent->get_id() ]->sellable );
		self::assertSame( array( $parent->get_id(), array( 'pa_width' => 'w-8' ) ), array( $rows[ $first ]->of, $rows[ $first ]->attributes ) );

		$second = $variation( 'w-85' );
		self::assertSame( array( $this->deck, $first, $second ), array_keys( $this->decks() ), 'a new variation' );

		// A slot that asks for one width takes that variation from the rows it kept.
		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array(
			'categories' => array( $this->category ),
			'attributes' => array(
				array(
					'taxonomy' => 'pa_width',
					'terms'    => array( $wide ),
				),
			),
		);
		$kit                          = new \CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $this->kit );
		$kit->setDocument( Options::META_KIT_SLOTS, $slots );
		$kit->save();
		$this->watch();
		self::assertSame( array( $second ), array_keys( $this->decks() ) );
		self::assertNotContains( $second, $this->read, 'read from its row' );

		wp_delete_post( $second, true );
		self::assertSame( array(), array_keys( $this->decks() ), 'a variation deleted' );
		self::assertArrayNotHasKey( $second, $this->rows( $parent->get_id() ), 'and its row with it' );
	}

	public function test_a_row_is_believed_for_an_hour_and_up_to_an_hour_more_by_its_id(): void {
		global $wpdb;
		$this->watch();
		$this->decks();
		// Past WooCommerce, so that nothing says the price moved.
		update_post_meta( $this->deck, '_price', '99' );
		update_post_meta( $this->deck, '_regular_price', '99' );
		clean_post_cache( $this->deck );
		$read  = $this->rows( $this->deck )[ $this->deck ]->readAt;
		$until = HOUR_IN_SECONDS + ( $this->deck % 60 ) * MINUTE_IN_SECONDS;
		$aged  = static function ( int $age ) use ( $wpdb, $read ): void {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The test ages a row, as time would.
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET read_at = %d', \CreativeBranding\SkateboardBuilder\Support\Tables::parts(), $read - $age ) );
		};
		$aged( $until - 5 );
		self::assertSame( 6000, $this->decks()[ $this->deck ]->price, 'seconds before its time' );
		$aged( $until + 5 );
		self::assertSame( 9900, $this->decks()[ $this->deck ]->price, 'past its time' );
		self::assertSame( array( $this->deck, $this->deck ), $this->read );
	}

	public function test_a_plugin_says_which_parts_changed_or_that_all_did(): void {
		$other = $this->part( 'Deck 8.5', '65' );
		$this->decks();
		$this->watch();
		do_action( 'cbuild_parts_changed', array( $other ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own prefix.
		$this->decks();
		self::assertSame( array( $other ), $this->read );
		do_action( 'cbuild_parts_changed' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own prefix.
		self::assertSame( array(), $this->rows( $this->deck, $other ) );
		$this->decks();
		self::assertSame( array( $other, $this->deck, $other ), $this->read );
	}

	public function test_a_brand_renamed_is_read_again_and_a_category_renamed_forgets_nothing(): void {
		$brand = self::factory()->term->create( array( 'taxonomy' => 'product_brand', 'slug' => 'indy' ) );
		wp_set_object_terms( $this->deck, array( $brand ), 'product_brand' );
		self::assertSame( 'indy', $this->decks()[ $this->deck ]->brand );
		wp_update_term( $brand, 'product_brand', array( 'slug' => 'independent' ) );
		self::assertSame( 'independent', $this->decks()[ $this->deck ]->brand );
		$this->watch();
		wp_update_term( $this->category, 'product_cat', array( 'name' => 'Decks' ) );
		$this->decks();
		self::assertSame( array(), $this->read );
	}

	public function test_the_second_answer_loads_no_part_and_is_the_first(): void {
		$this->watch();
		$first = $this->decks();
		self::assertSame( array( $this->deck ), $this->read );
		self::assertSame( array( $this->deck ), array_keys( $first ) );
		self::assertEquals( $first, $this->decks() );
		self::assertSame( array( $this->deck ), $this->read, 'the part was read once' );
	}

	/**
	 * Work done together (My builds) reads a slot's parts once, sees a part that moves inside it, and holds nothing
	 * past it, where the shop is asked again each time.
	 */
	public function test_work_done_together_reads_a_slots_parts_once_and_holds_nothing_after(): void {
		$queries = static function ( callable $work ): int {
			$before = get_num_queries();
			$work();
			return get_num_queries() - $before;
		};
		$this->decks();
		$alone = $queries( fn() => $this->decks() );
		self::assertGreaterThan( 0, $alone, 'outside, the shop is asked which products the slot holds each time' );
		$inside = $this->kits()->together(
			function () use ( $queries ): array {
				$first  = $queries( fn() => $this->decks() );
				$second = $queries( fn() => $this->decks() );
				$this->part( 'Deck 8.5', '62' );
				$moved = $this->decks();
				return array( $first, $second, count( $moved ) );
			}
		);
		self::assertSame( 0, $inside[1], 'the second build of the slot asks nothing of its parts' );
		self::assertGreaterThan( 0, $inside[0] );
		self::assertSame( 2, $inside[2], 'a part that moved inside is seen' );
		self::assertSame( $alone, $queries( fn() => $this->decks() ), 'nothing held after' );
		$kits = $this->kits();
		self::assertSame( 'answer', $kits->together( static fn(): string => $kits->together( static fn(): string => 'answer' ) ), 'work inside work' );
	}

	public function test_visitors_shown_other_prices_have_rows_of_their_own(): void {
		$this->decks();
		self::assertCount( 1, $this->rows( $this->deck ) );
		$dealer = static fn( array $context ): array => array( ...$context, 'dealer' );
		add_filter( 'cbuild_price_context', $dealer );
		self::assertSame( array(), $this->rows( $this->deck ), 'nothing kept for a dealer yet' );
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
		yield 'the kit is saved with other sources, which are asked of the shop each time' => array(
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
		self::assertCount( 1, $this->rows( $this->deck ), 'kept before the move' );
		$move( $this );
		$holds( $this->decks(), $this );
	}

	public function test_a_sale_starting_or_ending_forgets_the_parts_it_names(): void {
		foreach ( array( 'wc_after_products_starting_sales', 'wc_after_products_ending_sales' ) as $hook ) {
			$this->decks();
			self::assertCount( 1, $this->rows( $this->deck ) );
			do_action( $hook, array( $this->deck ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce's own.
			self::assertSame( array(), $this->rows( $this->deck ), $hook );
		}
	}

	public function test_a_post_that_is_no_part_moves_nothing(): void {
		$this->decks();
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		wp_publish_post( $page );
		wp_trash_post( $page );
		self::assertCount( 1, $this->rows( $this->deck ) );
	}

	// The auditor's should-fix 6, milestone 4: a row was looked for and deleted on every post's and every taxonomy's
	// write, a dozen times in one product's save.
	public function test_a_products_save_deletes_its_rows_once_and_another_posts_terms_cost_nothing(): void {
		global $wpdb;
		$this->decks();
		$kits = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class );
		self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\WooKits::class, $kits );
		$kits->settle();
		remove_action( 'shutdown', array( $kits, 'settle' ) );
		$asked = array();
		$count = static function ( string $query ) use ( &$asked ): string {
			if ( str_contains( $query, \CreativeBranding\SkateboardBuilder\Support\Tables::parts() ) ) {
				$asked[] = $query;
			}
			return $query;
		};
		add_filter( 'query', $count );

		// A page, its terms set, and a post deleted for good.
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_set_object_terms( $page, array( 'a', 'b' ), 'post_tag' );
		wp_delete_post( $page, true );
		self::assertSame( array(), $asked, 'no post but a product touches the table' );
		self::assertFalse( has_action( 'shutdown', array( $kits, 'settle' ) ), 'and nothing waits for the request\'s end' );

		// A product saved with a price, a category, a tag and its stock: told of many times, deleted once.
		$deck = wc_get_product( $this->deck );
		self::assertInstanceOf( \WC_Product::class, $deck );
		$deck->set_regular_price( '75' );
		$deck->set_tag_ids( array( self::factory()->term->create( array( 'taxonomy' => 'product_tag' ) ) ) );
		$deck->set_manage_stock( true );
		$deck->set_stock_quantity( 4 );
		$deck->save();
		self::assertSame( array(), $asked, 'nothing while the request runs' );
		self::assertSame( 10, has_action( 'shutdown', array( $kits, 'settle' ) ) );
		$kits->settle();
		remove_filter( 'query', $count );
		self::assertCount( 1, array_filter( $asked, static fn( string $query ): bool => str_starts_with( $query, 'DELETE' ) ), implode( "\n", $asked ) );
		self::assertLessThanOrEqual( 2, count( $asked ), implode( "\n", $asked ) );
		self::assertSame( 7500, $this->decks()[ $this->deck ]->price );
	}
}
