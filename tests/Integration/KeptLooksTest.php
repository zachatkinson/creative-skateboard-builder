<?php
/**
 * Each part's look and card, kept beside its facts in the parts table.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WooPartCards;
use CreativeBranding\SkateboardBuilder\Hooks\CardHooks;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Port\PartFact;
use CreativeBranding\SkateboardBuilder\Port\PartFacts;
use CreativeBranding\SkateboardBuilder\Port\PartLooks;
use CreativeBranding\SkateboardBuilder\Repository\PartFactsRepository;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * D-289: the state route's budget, on a site with no persistent object cache. A request reads its parts' looks and
 * cards with their facts, and loads no product for them; what tells of a move forgets them, and what does not keeps
 * them for an hour, as the object cache does.
 */
final class KeptLooksTest extends IntegrationTestCase {

	/**
	 * The products WooCommerce read since the test started.
	 *
	 * @var list<int>
	 */
	private array $read = array();

	public function set_up(): void {
		parent::set_up();
		$this->service( CardHooks::class )->register();
		add_action(
			'woocommerce_product_read',
			function ( int $id ): void {
				$this->read[] = $id;
			}
		);
	}

	/**
	 * A row for a part, as a request writes one.
	 *
	 * @param int  $id      The part.
	 * @param int  $price   Its price, in minor units.
	 * @param bool $inStock Whether it can be bought.
	 */
	private static function fact( int $id, int $price = 6000, bool $inStock = true ): PartFact {
		return new PartFact( $id, 0, true, 'katie', $price, $inStock, null, array(), array( 'deck_width' => '8.2500' ), 1_800_000_000 );
	}

	public function test_a_kept_look_and_card_are_read_for_an_hour_and_no_longer(): void {
		$clock = new FakeClock();
		$facts = new PartFactsRepository( $clock );
		$facts->replace( 'ctx', array( 41 ), array( self::fact( 41 ) ) );
		$facts->keep( 'ctx', 'look', array( 41 => array( 'stamp' => 's', 'look' => array( 'product' => 41 ) ) ) );
		$facts->keep( 'ctx', 'card', array( 41 => array( 'stamp' => 's', 'card' => array( 'name' => 'Katie' ) ) ) );

		$clock->advance( HOUR_IN_SECONDS - 1 );
		self::assertSame(
			array(
				41 => array(
					'look' => array(
						'stamp' => 's',
						'look'  => array( 'product' => 41 ),
					),
					'card' => array(
						'stamp' => 's',
						'card'  => array( 'name' => 'Katie' ),
					),
				),
			),
			$facts->kept( 'ctx', 41 )
		);
		self::assertSame( array(), $facts->kept( 'other', 41 ), 'in its own price context' );
		$clock->advance( 1 );
		self::assertSame( array( 41 => array( 'look' => null, 'card' => null ) ), $facts->kept( 'ctx', 41 ), 'an hour on, read from the shop again' );
	}

	public function test_forgetting_a_part_keeps_its_look_and_card_and_its_rewrite_carries_them_where_its_price_and_stock_are_the_same(): void {
		$facts = new PartFactsRepository( new FakeClock() );
		$facts->replace( 'ctx', array( 41, 42 ), array( self::fact( 41 ), self::fact( 42 ) ) );
		foreach ( array( 'look', 'card' ) as $column ) {
			$facts->keep(
				'ctx',
				$column,
				array(
					41 => array( $column => array( 'kept' => 41 ) ),
					42 => array( $column => array( 'kept' => 42 ) ),
				)
			);
		}

		$facts->forget( 41, 42 );
		self::assertSame( array( 0, 0 ), array_values( array_map( static fn( PartFact $fact ): int => $fact->readAt, $facts->of( 'ctx', 41, 42 ) ) ), 'read from the shop again' );
		self::assertNotNull( $facts->kept( 'ctx', 41 )[41]['look'], 'its look kept' );

		// Read again: 41 as it was, 42 repriced.
		$facts->replace( 'ctx', array( 41, 42 ), array( self::fact( 41 ), self::fact( 42, 6500 ) ) );
		$kept = $facts->kept( 'ctx', 41, 42 );
		self::assertSame( array( 'look' => array( 'kept' => 41 ) ), $kept[41]['look'] );
		self::assertSame( array( 'card' => array( 'kept' => 41 ) ), $kept[41]['card'], 'its card, which shows the same price and stock' );
		self::assertSame( array( 'look' => array( 'kept' => 42 ) ), $kept[42]['look'], 'a look shows no price' );
		self::assertNull( $kept[42]['card'], 'a card that shows the old price is not' );

		$facts->clear( 41 );
		self::assertSame( array( 'look' => null, 'card' => null ), $facts->kept( 'ctx', 41 )[41], 'a move forgets them' );
		self::assertNotNull( $facts->kept( 'ctx', 42 )[42]['look'], 'and no other part\'s' );
	}

	public function test_a_request_with_nothing_in_the_object_cache_reads_looks_and_cards_from_the_table_and_loads_no_product(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Katie 8.25' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '60' );
		$product->save();
		$id    = $product->get_id();
		$cards = $this->service( PartCards::class );
		self::assertInstanceOf( WooPartCards::class, $cards );
		$kits = $this->service( Kits::class );
		self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\WooKits::class, $kits );
		// As the save's request ends.
		$cards->settle();
		$kits->settle();
		// A row, as the state route writes one before it asks for cards.
		$this->service( PartFacts::class )->replace( WooPartCards::context(), array( $id ), array( self::fact( $id ) ) );
		$looks = $this->service( PartLooks::class );
		$card  = $cards->cards( array( $id ) );
		$look  = $looks->looks( array( $id ) );
		self::assertNotSame( array(), $card );
		self::assertNotSame( array(), $look );

		// The next request, on a site whose object cache lasts one request.
		wp_cache_flush();
		$this->read = array();
		self::assertEquals( $card, $cards->cards( array( $id ) ) );
		self::assertEquals( $look, $looks->looks( array( $id ) ) );
		self::assertSame( array(), $this->read, 'no product loaded' );

		// A reindex forgets the part's facts and keeps its look and card.
		$kits->forget( $id );
		$kits->settle();
		wp_cache_flush();
		self::assertEquals( $look, $looks->looks( array( $id ) ) );
		self::assertSame( array(), $this->read, 'still none' );

		// A sale tells of a move: the card shows a new price, so its look and card are forgotten with its facts.
		do_action( 'wc_after_products_starting_sales', array( $id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own.
		$cards->settle();
		self::assertSame( array( $id => array( 'look' => null, 'card' => null ) ), $this->service( PartFacts::class )->kept( WooPartCards::context(), $id ) );
		wp_cache_flush();
		$cards->cards( array( $id ) );
		self::assertContains( $id, $this->read, 'read from the shop again' );
	}

	// The auditor's 1.0.0 minor 5: variations with no photo and no layer image of their own show their parent's layer
	// image, and the parent is read once for all of them.
	public function test_variations_that_fall_back_to_their_parents_layer_image_read_it_once(): void {
		$layer  = \CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles::attachment( 420, 1626 );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Katie' );
		$parent->set_status( 'publish' );
		$parent->update_meta_data( \CreativeBranding\SkateboardBuilder\Support\Options::META_LAYER, (string) $layer );
		$parent->save();
		$ids = array();
		foreach ( array( '8.0', '8.25', '8.5' ) as $width ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_regular_price( '60' );
			$variation->set_attributes( array( 'width' => $width ) );
			$ids[] = $variation->save();
		}
		$cards = $this->service( PartCards::class );
		self::assertInstanceOf( WooPartCards::class, $cards );
		$cards->settle();
		$this->service( Kits::class )->settle();
		wp_cache_flush();
		$this->read = array();

		$found = $cards->cards( $ids );

		self::assertCount( 3, $found );
		foreach ( $found as $card ) {
			self::assertFalse( $card['photo'] );
			self::assertNotNull( $card['image'], 'the parent\'s layer image' );
		}
		self::assertSame( 1, count( array_keys( $this->read, $parent->get_id(), true ) ), 'the parent read once' );
	}
}
