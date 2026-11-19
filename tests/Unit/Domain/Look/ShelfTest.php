<?php
/**
 * Shelf.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SizePicker;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Look\Chip;
use CreativeBranding\SkateboardBuilder\Domain\Look\ChipGroup;
use CreativeBranding\SkateboardBuilder\Domain\Look\Listing;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Look\ShelfAsk;
use CreativeBranding\SkateboardBuilder\Domain\Look\ShelfLook;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelved;
use CreativeBranding\SkateboardBuilder\Domain\Look\Width;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * A step's shelf: its parts as looks, searched, chipped, ordered and paged (SPEC-CONFIGURATOR, Finding a part).
 */
final class ShelfTest extends TestCase {

	/**
	 * A wheel.
	 *
	 * @param int    $id       Its ID.
	 * @param string $diameter Its diameter, mm.
	 * @param int    $price    Its price.
	 * @param string $brand    Its brand.
	 * @param bool   $inStock  Whether it is in stock.
	 */
	private static function wheel( int $id, string $diameter, int $price = 5000, string $brand = 'spitfire', bool $inStock = true ): Part {
		return Parts::of( array( 'wheel_diameter' => $diameter, 'set_size' => 4 ), $id, $price, $brand, $inStock );
	}

	/**
	 * What the shelf knows of a part.
	 *
	 * @param int                   $product  Its product.
	 * @param int                   $photo    Its photo.
	 * @param list<string>          $words    Its folded words.
	 * @param array<string, string> $choices  Its choices you can't see.
	 * @param array<string, mixed>  $more     Featured, sales, created or name.
	 */
	private static function shelved( int $product, int $photo, array $words = array(), array $choices = array(), array $more = array() ): Shelved {
		$facets = array();
		foreach ( $choices as $name => $value ) {
			$facets[ $name ] = array( $value );
		}
		return new Shelved(
			$product,
			$photo,
			0,
			$choices,
			$facets,
			$words,
			array_values( $choices ),
			(bool) ( $more['featured'] ?? false ),
			(int) ( $more['sales'] ?? 0 ),
			(int) ( $more['created'] ?? 0 ),
			(string) ( $more['name'] ?? 'Product ' . $product )
		);
	}

	/**
	 * The wheels shelf.
	 *
	 * - Retna, product 10: Mint in 52 (99A) and 54 (101A) on photo 1, Black in 54 on photo 2.
	 * - Formula Four, product 20, in 56, $60 (photo 3), Bones.
	 * - Mini Cubes, product 30, in 53 and sold out (photo 4).
	 *
	 * @return array{list<Part>, array<int, Shelved>}
	 */
	private static function wheels(): array {
		$parts   = array(
			self::wheel( 301, '52' ),
			self::wheel( 302, '54' ),
			self::wheel( 303, '54' ),
			self::wheel( 304, '56', 6000, 'bones' ),
			self::wheel( 305, '53', 4000, 'bones', false ),
		);
		$shelved = array(
			301 => self::shelved( 10, 1, array( 'spitfire', 'retna', 'cruiser', '52' ), array( 'pa_colour' => 'mint', 'pa_hardness' => '99a' ) ),
			302 => self::shelved( 10, 1, array( 'spitfire', 'retna', 'cruiser', '54' ), array( 'pa_colour' => 'mint', 'pa_hardness' => '101a' ) ),
			303 => self::shelved( 10, 2, array( 'spitfire', 'retna', 'cruiser', '54' ), array( 'pa_colour' => 'black' ) ),
			304 => self::shelved( 20, 3, array( 'bones', 'formula', 'four', '56' ), array(), array( 'featured' => true ) ),
			305 => self::shelved( 30, 4, array( 'bones', 'mini', 'cubes', '53' ) ),
		);
		return array( $parts, $shelved );
	}

	/**
	 * A step's listing.
	 *
	 * @param string              $slot        The slot.
	 * @param list<Part>          $parts       Its parts.
	 * @param array<int, Shelved> $shelved     What the shelf knows of them.
	 * @param ShelfAsk            $ask         What the shopper asks.
	 * @param Picks|null          $build       The build so far.
	 * @param int|null            $picked      The part picked, if any.
	 * @param int|null            $recommended The part recommended, if any.
	 */
	private static function list( string $slot, array $parts, array $shelved, ShelfAsk $ask = new ShelfAsk(), ?Picks $build = null, ?int $picked = null, ?int $recommended = null ): Listing {
		$engine  = FitEngine::standard();
		$build ??= Parts::picks( array( SlotKey::DECK => Builds::deck( '8.25' ), SlotKey::TRUCKS => Builds::truck( '144' ) ) );
		$offered = $engine->options( KitDocuments::kit(), $slot, $build, $parts )->offered;
		$find    = static function ( ?int $id ) use ( $offered ): ?Option {
			foreach ( $offered as $option ) {
				if ( $option->part->id === $id ) {
					return $option;
				}
			}
			return null;
		};
		return ( new Shelf( new SizePicker( $engine ) ) )->of( KitDocuments::kit(), $slot, $build, $offered, $find( $picked ), $find( $recommended ), $shelved, $ask );
	}

	/**
	 * The cards as look key => the part each shows.
	 *
	 * @param list<ShelfLook> $looks The cards.
	 * @return array<string, int>
	 */
	private static function cards( array $looks ): array {
		$cards = array();
		foreach ( $looks as $look ) {
			$cards[ $look->look->key ] = $look->option->part->id;
		}
		return $cards;
	}

	public function test_variations_that_share_a_photo_are_one_card_and_a_sold_out_look_folds_at_the_end(): void {
		[ $parts, $shelved ] = self::wheels();
		$listing             = self::list( SlotKey::WHEELS, $parts, $shelved );
		self::assertSame( array( '20:3:0' => 304, '10:1:0' => 301, '10:2:0' => 303 ), self::cards( $listing->looks ), 'the featured product first, then by name; Mint in its smallest size' );
		self::assertSame( array( '30:4:0' => 305 ), self::cards( $listing->soldOut ) );
		$parts[]      = self::wheel( 306, '55', 4000, 'bones', false );
		$shelved[306] = self::shelved( 35, 5, array(), array(), array( 'featured' => true ) );
		self::assertSame( array( '35:5:0', '30:4:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved )->soldOut ) ), 'the fold in the same order: featured first' );
		self::assertSame( array( '52mm', '54mm' ), array_map( Shelf::named( ... ), $listing->looks[1]->sizes ) );
		self::assertSame( 4, $listing->offered, 'four looks, the sold out among them' );
		self::assertSame( 3, $listing->matching );
		self::assertNull( $listing->more );
		self::assertSame( array(), $listing->widths, 'no question but on decks' );
	}

	public function test_a_card_shows_the_pick_then_the_chip_then_the_recommendation_size_where_the_shopper_chooses(): void {
		[ $parts, $shelved ] = self::wheels();
		self::assertSame( 302, self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk(), null, 302 )->looks )['10:1:0'], 'the pick' );
		self::assertSame( 302, self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( null, array( 'wheel_diameter' => array( '54mm' ) ) ) )->looks )['10:1:0'], 'one size chip' );
		self::assertSame( 301, self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( null, array( 'wheel_diameter' => array( '52mm', '54mm' ) ) ) )->looks )['10:1:0'], 'two size chips want no one size' );
		self::assertSame( 302, self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk(), null, null, 303 )->looks )['10:1:0'], 'the recommendation\'s 54' );
		self::assertSame( array( '20:3:0' => 304 ), self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( null, array( 'wheel_diameter' => array( '56mm' ) ) ) )->looks ), 'a size only a later look comes in' );
		self::assertSame( array(), self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( null, array( 'wheel_diameter' => array( '60mm' ) ) ), null, null, 303 )->looks, 'a size no look comes in leaves none' );
	}

	public function test_the_recommendation_comes_first_then_featured_then_sales_then_name(): void {
		[ $parts, $shelved ] = self::wheels();
		$shelved[303]        = self::shelved( 10, 2, array(), array( 'pa_colour' => 'black' ), array( 'sales' => 9 ) );
		self::assertSame( array( '10:1:0', '20:3:0', '10:2:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk(), null, null, 301 )->looks ) ) );
		self::assertSame( array( '20:3:0', '10:2:0', '10:1:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved )->looks ) ), 'Black\'s sales put it before Mint' );

		$named = array(
			311 => self::shelved( 51, 1, array(), array(), array( 'name' => 'Wheel 10' ) ),
			312 => self::shelved( 52, 2, array(), array(), array( 'name' => 'wheel 9' ) ),
		);
		self::assertSame( array( 312, 311 ), array_values( self::cards( self::list( SlotKey::WHEELS, array( self::wheel( 311, '54' ), self::wheel( 312, '54' ) ), $named )->looks ) ), 'by name, 9 before 10, whatever the case' );
	}

	public function test_sort_by_the_price_of_the_part_each_card_shows_or_by_newest(): void {
		[ $parts, $shelved ] = self::wheels();
		$parts[2]            = self::wheel( 303, '54', 4500 );
		$shelved[304]        = self::shelved( 20, 3, array(), array(), array( 'created' => 200 ) );
		$shelved[301]        = self::shelved( 10, 1, array(), array( 'pa_colour' => 'mint' ), array( 'created' => 100 ) );
		$order               = static fn( string $sort ): array => array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( sort: $sort ) )->looks ) );
		self::assertSame( array( '10:2:0', '10:1:0', '20:3:0' ), $order( 'price-asc' ) );
		self::assertSame( array( '20:3:0', '10:1:0', '10:2:0' ), $order( 'price-desc' ) );
		self::assertSame( array( '20:3:0', '10:1:0', '10:2:0' ), $order( 'newest' ), 'a product made later first; the same product by its look' );
		self::assertSame( $order( 'recommended' ), $order( 'cheapest' ), 'anything else is the default' );
	}

	public function test_chips_narrow_and_count_and_the_size_is_a_group_only_where_the_shopper_chooses_it(): void {
		[ $parts, $shelved ] = self::wheels();
		$listing             = self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( null, array( 'brand' => array( 'spitfire' ) ) ) );
		self::assertSame( array( 'brand', 'wheel_diameter' ), array_map( static fn( ChipGroup $group ): string => $group->name, $listing->groups ) );
		self::assertSame( array( '10:1:0', '10:2:0' ), array_keys( self::cards( $listing->looks ) ) );
		self::assertSame( 2, $listing->matching );
		self::assertSame( 4, $listing->offered, 'the tools count every look, so they do not vanish as the shopper narrows' );
		$sizes = $listing->groups[1]->chips;
		self::assertSame( array( '52mm', '53mm', '54mm', '56mm' ), array_column( $sizes, 'value' ), 'sizes named as the route\'s size filter names them' );
		self::assertSame( array( 1, 0, 2, 0 ), array_column( $sizes, 'count' ) );
		self::assertSame( array( '20:3:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( null, array( 'brand' => array( 'bones' ) ) ) )->looks ) ), 'Retna\'s looks come first and are left off' );

		$decks = array( Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), 101, 6000, 'baker' ), Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), 102, 6000, 'girl' ) );
		$names = array_map( static fn( ChipGroup $group ): string => $group->name, self::list( SlotKey::DECK, $decks, array( 101 => self::shelved( 1, 1 ), 102 => self::shelved( 2, 2 ) ), new ShelfAsk( Measure::inches( 82500 ) ), Picks::none() )->groups );
		self::assertSame( array( 'brand' ), $names, 'the build decides a deck\'s width' );
		$few   = array( ...$decks, ...array_map( static fn( int $id ): Part => Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), $id ), array( 103, 104, 105 ) ) );
		$known = array();
		foreach ( array( 101, 102, 103, 104, 105 ) as $id ) {
			$known[ $id ] = self::shelved( $id, $id );
		}
		$names = array_map( static fn( ChipGroup $group ): string => $group->name, self::list( SlotKey::DECK, $few, $known, new ShelfAsk( Measure::inches( 82500 ) ), Picks::none() )->groups );
		self::assertSame( array( 'brand' ), $names, 'brand is always offered, though two looks in five carry one' );
	}

	public function test_search_matches_word_starts_or_keeps_creative_searchs_order_narrowed_to_looks(): void {
		[ $parts, $shelved ] = self::wheels();
		self::assertSame( array( '10:1:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( query: 'retna mi' ) )->looks ) ) );
		self::assertSame( array(), self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( query: 'rtena' ) )->looks, 'no typos without Creative Search' );
		self::assertSame( array( '10:1:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( query: '52' ) )->looks ) ), 'a word of any of the look\'s parts' );
		self::assertSame( array( '10:1:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( query: '99', ranked: array( 10 ) ) )->looks ) ), 'its own words, of any of its parts' );
		self::assertSame( array( '20:3:0', '10:1:0', '10:2:0' ), array_keys( self::cards( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( query: 'x', ranked: array( 20, 10 ) ) )->looks ) ), 'Creative Search\'s order, not the step\'s' );

		$ranked = self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( query: 'rtena blak', ranked: array( 10, 20 ), corrected: 'retna black' ) );
		self::assertSame( array( '10:2:0', '20:3:0' ), array_keys( self::cards( $ranked->looks ) ), 'its order; Retna narrowed to Black by the corrected query, and all of Formula Four' );
		self::assertSame( 2, $ranked->matching );
		$sorted = self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( query: 'x', ranked: array( 10, 20 ), sort: 'price-desc' ) );
		self::assertSame( array( '20:3:0', '10:1:0', '10:2:0' ), array_keys( self::cards( $sorted->looks ) ), 'a sort chosen still sorts' );
	}

	public function test_a_page_of_looks_and_where_the_next_starts(): void {
		$parts   = array();
		$shelved = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$parts[]            = self::wheel( 310 + $i, '54' );
			$shelved[ 310 + $i ] = self::shelved( 40 + $i, 10 + $i, array(), array(), array( 'name' => 'Wheel ' . $i ) );
		}
		$page = self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( from: 2, shown: 2 ) );
		self::assertSame( array( 312, 313 ), array_values( self::cards( $page->looks ) ) );
		self::assertSame( 4, $page->more );
		self::assertSame( 5, $page->matching );
		self::assertNull( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( from: 3, shown: 2 ) )->more, 'the last page' );
		self::assertSame( array(), self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( from: 9 ) )->looks, 'past the end' );
	}

	/**
	 * Spec (configurator): 30 at a time, "The cap stays at 500 looks a step: search and filters narrow past it." The
	 * route's `from` reaches 2,000 (D-265), but no page goes past the 500th look, so a reload shows what was paged.
	 */
	public function test_no_page_goes_past_the_five_hundredth_look(): void {
		$parts   = array();
		$shelved = array();
		for ( $i = 0; $i < 520; $i++ ) {
			$parts[]               = self::wheel( 10000 + $i, '54' );
			$shelved[ 10000 + $i ] = self::shelved( 20000 + $i, 30000 + $i, array(), array(), array( 'name' => sprintf( 'Wheel %04d', $i ) ) );
		}
		$last = self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( from: 480, shown: 30 ) );
		self::assertCount( 20, $last->looks, 'the page stops at the 500th' );
		self::assertNull( $last->more, 'and offers no more' );
		self::assertSame( 520, $last->matching, 'the count says how many match, for search and filters to narrow' );
		self::assertSame( 480, self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( from: 450, shown: 30 ) )->more );
		self::assertNull( self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( from: 470, shown: 30 ) )->more, 'a page ending on the 500th' );
		self::assertSame( array(), self::list( SlotKey::WHEELS, $parts, $shelved, new ShelfAsk( from: 500 ) )->looks, 'past the cap' );
	}

	public function test_the_deck_step_asks_the_width_first_then_lists_the_decks_in_it(): void {
		$deck    = static fn( string $width, int $id, bool $inStock = true ): Part => Parts::of( array( 'deck_width' => $width, 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), $id, 6000, 'baker', $inStock );
		$parts   = array( $deck( '9', 105, false ), $deck( '8', 101 ), $deck( '8.25', 102 ), $deck( '8.25', 103, false ), $deck( '8.5', 104 ) );
		// A deck with no width is hidden by the engine, so it is in no width.
		$parts[] = Parts::of( array( 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), 106 );
		$shelved = array(
			101 => self::shelved( 1, 1 ),
			102 => self::shelved( 1, 1 ),
			103 => self::shelved( 2, 2 ),
			104 => self::shelved( 2, 2 ),
			105 => self::shelved( 3, 3 ),
			106 => self::shelved( 1, 1 ),
		);
		$asking  = self::list( SlotKey::DECK, $parts, $shelved, new ShelfAsk(), Picks::none() );
		self::assertSame( array(), $asking->looks, 'no deck before the width' );
		self::assertSame(
			array( array( '8in', 1 ), array( '8.25in', 1 ), array( '8.5in', 1 ), array( '9in', 0 ) ),
			array_map( static fn( Width $width ): array => array( Shelf::named( $width->width ), $width->inStock ), $asking->widths ),
			'each width, with the looks in stock in it'
		);
		$listed = self::list( SlotKey::DECK, $parts, $shelved, new ShelfAsk( Measure::inches( 82500 ) ), Picks::none() );
		self::assertSame( array( '1:1:0' => 102 ), self::cards( $listed->looks ) );
		self::assertSame( array( '2:2:0' => 103 ), self::cards( $listed->soldOut ), 'sold out in the width asked' );
		self::assertCount( 4, $listed->widths, 'the question stays, for Change' );
	}

	/**
	 * Spec (configurator): "Each chip says how many looks it leaves." On the deck step that is in the width asked: a
	 * deck in another width is not on the step, so a brand's chip does not count it (found on the long step's page).
	 */
	public function test_a_deck_chip_counts_only_the_decks_in_the_width_asked(): void {
		$deck    = static fn( string $width, int $id, string $brand, bool $inStock = true ): Part => Parts::of( array( 'deck_width' => $width, 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), $id, 6000, $brand, $inStock );
		$parts   = array( $deck( '8.25', 101, 'baker' ), $deck( '8.25', 102, 'baker', false ), $deck( '8', 103, 'baker' ), $deck( '8.25', 104, 'zero' ), $deck( '8', 105, 'alien' ) );
		$shelved = array();
		foreach ( array( 101, 102, 103, 104, 105 ) as $id ) {
			$shelved[ $id ] = self::shelved( $id, $id );
		}
		$listing = self::list( SlotKey::DECK, $parts, $shelved, new ShelfAsk( Measure::inches( 82500 ) ), Picks::none() );
		self::assertSame(
			array( 'baker' => 2, 'zero' => 1 ),
			array_column( array_map( static fn( Chip $chip ): array => array( $chip->value, $chip->count ), $listing->groups[0]->chips ), 1, 0 ),
			'the sold-out 8.25″ deck is left, under its fold; the 8″ decks are not, and a brand only in 8″ has no chip'
		);
		// The build link keeps the width on every step, and a later step's chips are not the deck's.
		$wheels = array( Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4 ), 201, 3000, 'bones' ), Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4 ), 202, 3000, 'spitfire' ) );
		$later  = self::list( SlotKey::WHEELS, $wheels, array( 201 => self::shelved( 201, 201 ), 202 => self::shelved( 202, 202 ) ), new ShelfAsk( Measure::inches( 82500 ) ) );
		self::assertSame( array( 'bones', 'spitfire' ), array_map( static fn( Chip $chip ): string => $chip->value, $later->groups[0]->chips ) );
	}

	// Spec (configurator): Search, "When nothing is found": "No 8.25″ decks match Rascals. Rascals comes in 8.5″."
	public function test_a_deck_search_with_nothing_in_the_width_asked_names_the_widths_it_comes_in(): void {
		$deck    = static fn( string $width, int $id, bool $inStock = true ): Part => Parts::of( array( 'deck_width' => $width, 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), $id, 6000, 'baker', $inStock );
		$parts   = array( $deck( '8', 101 ), $deck( '8.25', 102 ), $deck( '8.5', 104 ), $deck( '9', 105, false ) );
		$shelved = array(
			101 => self::shelved( 1, 1, array( 'rascals' ) ),
			102 => self::shelved( 2, 2, array( 'other' ) ),
			104 => self::shelved( 3, 3, array( 'rascals' ) ),
			105 => self::shelved( 4, 4, array( 'rascals' ) ),
		);
		$named   = static fn( Listing $listing ): array => array_map( static fn( Width $width ): string => Shelf::named( $width->width ), $listing->elsewhere );
		$none    = self::list( SlotKey::DECK, $parts, $shelved, new ShelfAsk( Measure::inches( 82500 ), query: 'rascals' ), Picks::none() );
		self::assertSame( array(), $none->looks );
		self::assertSame( array( '8in', '8.5in' ), $named( $none ), 'the widths it is in stock in, narrowest first' );
		self::assertSame( array(), $named( self::list( SlotKey::DECK, $parts, $shelved, new ShelfAsk( Measure::inches( 82500 ), query: 'other' ), Picks::none() ) ), 'none where the width asked has it' );
		self::assertSame( array(), $named( self::list( SlotKey::DECK, $parts, $shelved, new ShelfAsk( Measure::inches( 82500 ) ), Picks::none() ) ), 'none without a search' );
		self::assertSame( array(), $named( self::list( SlotKey::DECK, $parts, $shelved, new ShelfAsk( Measure::inches( 82500 ), query: 'nothing' ), Picks::none() ) ), 'none where it is in no width' );
	}

	public function test_trucks_show_the_size_that_fits_the_deck(): void {
		$trucks  = array( Builds::truck( '139' ), Builds::truck( '149' ) );
		$shelved = array(
			139 => self::shelved( 5, 5 ),
			149 => self::shelved( 5, 5 ),
		);
		$listing = self::list( SlotKey::TRUCKS, $trucks, $shelved, new ShelfAsk(), Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) );
		self::assertSame( array( '5:5:0' => 149 ), self::cards( $listing->looks ) );
		self::assertSame( array( '8.5in' ), array_map( Shelf::named( ... ), $listing->looks[0]->sizes ), 'the 139 does not fit the deck, so it is hidden and no size of the card' );
	}

	public function test_a_slot_with_no_size_has_brand_chips_alone_and_cards_with_no_sizes(): void {
		$tools   = array( new Option( Parts::of( array(), 801, 900, 'shorty' ), array(), 1 ), new Option( Parts::of( array(), 802, 900, 'mob' ), array(), 1 ) );
		$shelved = array(
			801 => self::shelved( 81, 1 ),
			802 => self::shelved( 82, 2 ),
		);
		$slots            = KitDocuments::slots();
		$slots['slots'][] = array(
			'key'      => 'tool',
			'label'    => 'Skate tool',
			'required' => false,
			'units'    => 1,
		);
		$listing          = ( new Shelf( new SizePicker( FitEngine::standard() ) ) )->of( KitDocuments::kit( $slots ), 'tool', Picks::none(), $tools, null, null, $shelved, new ShelfAsk() );
		self::assertSame( array( 'brand' ), array_map( static fn( ChipGroup $group ): string => $group->name, $listing->groups ) );
		self::assertSame( array( array(), array() ), array_map( static fn( ShelfLook $look ): array => $look->sizes, $listing->looks ) );
	}

	public function test_a_part_the_shelf_knows_nothing_of_is_left_off(): void {
		[ $parts, $shelved ] = self::wheels();
		unset( $shelved[304] );
		self::assertArrayNotHasKey( '20:3:0', self::cards( self::list( SlotKey::WHEELS, $parts, $shelved )->looks ) );
	}
}
