<?php
/**
 * StepShelf.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Look\ChipGroup;
use CreativeBranding\SkateboardBuilder\Domain\Look\Listing;
use CreativeBranding\SkateboardBuilder\Domain\Look\ShelfLook;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Port\TextFolding;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\ShelfQuery;
use CreativeBranding\SkateboardBuilder\Service\ShelfView;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Service\StepShelf;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeRanking;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCards;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLooks;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * The step's shelf from what the shop holds of each part (D-263, D-264): looks, the size attribute kept out of the
 * choices and chips, words folded for search, and Creative Search's ranking where the kit allows it.
 */
final class StepShelfTest extends TestCase {

	private const int KIT = 77;

	private InMemoryKits $kits;

	private InMemoryPartLooks $looks;

	private FakeRanking $ranking;

	/**
	 * The kit's parts, by slot.
	 *
	 * @var array<string, list<Part>>
	 */
	private array $parts = array();

	private InMemoryShopSettings $settings;

	protected function setUp(): void {
		$this->kits     = new InMemoryKits();
		$this->looks    = new InMemoryPartLooks();
		$this->ranking  = new FakeRanking();
		$this->settings = new InMemoryShopSettings();
		$parts          = Shop::parts();
		// Retna in Mint (52, 54) and Black (58), Spitfire's; Rétro Cubes, Bones', in 53.
		$parts[ SlotKey::WHEELS ] = array(
			self::wheel( 301, '52', 'spitfire' ),
			self::wheel( 302, '54', 'spitfire' ),
			self::wheel( 303, '56', 'spitfire' ),
			self::wheel( 304, '53', 'bones' ),
		);
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$this->parts = $parts;
		$retna = static fn( int $photo, string $colour, string $size ): LookFact => new LookFact(
			10,
			$photo,
			0,
			'Retna Cruiser',
			array(
				'pa_colour'   => array( $colour ),
				'pa_diameter' => array( $size ),
			),
			array(
				'pa_colour'   => array( $colour => ucfirst( $colour ) ),
				'pa_diameter' => array( $size => $size . ' mm' ),
			),
			false,
			0,
			0
		);
		$this->looks->facts = array(
			301 => $retna( 1, 'mint', '52' ),
			302 => $retna( 1, 'mint', '54' ),
			303 => $retna( 2, 'black', '56' ),
			304 => new LookFact( 20, 3, 0, 'Rétro Cubes', array( 'pa_colour' => array( 'white' ) ), array( 'pa_colour' => array( 'white' => 'Blanc cassé' ) ), true, 0, 0 ),
		);
		$map = AttributeMap::read(
			array(
				'attribute' => 'pa_diameter',
				'spec'      => 'wheel_diameter',
				'unit'      => '',
			)
		);
		self::assertNotNull( $map );
		$this->settings->mapping = array( $map );
	}

	private static function wheel( int $id, string $diameter, string $brand ): Part {
		$part = Builds::wheel( $diameter, $id );
		return new Part( $id, $brand, $part->specs, 3000, true );
	}

	private function shelf(): StepShelf {
		$folding = new class() implements TextFolding {
			public function fold( string $text ): string {
				return strtolower( strtr( $text, array( 'é' => 'e', 'É' => 'e', 'í' => 'i' ) ) );
			}
		};
		return new StepShelf( $this->looks, new InMemoryPartCards(), $this->ranking, $folding, $this->settings );
	}

	private function state( string $step, array $picks = array() ): State {
		$state = ( new BuildState( $this->kits, $this->settings, new FakeLogger() ) )->of( self::KIT, $picks, null, $step );
		self::assertNotNull( $state );
		return $state;
	}

	private function wheels( ShelfQuery $query = new ShelfQuery() ): Listing {
		$view = $this->shelf()->of( $this->state( SlotKey::WHEELS, array( 'deck' => 102, 'trucks' => 144 ) ), $query );
		self::assertNotNull( $view );
		return $view->listing;
	}

	/**
	 * The look keys listed.
	 *
	 * @param list<ShelfLook> $looks The cards.
	 * @return list<string>
	 */
	private static function keys( array $looks ): array {
		return array_map( static fn( ShelfLook $look ): string => $look->look->key, $looks );
	}

	public function test_a_steps_looks_come_from_the_shop_with_the_size_attribute_kept_out_of_the_choices_and_chips(): void {
		$listing = $this->wheels();
		self::assertSame( array( '10:1:0', '20:3:0', '10:2:0' ), self::keys( $listing->looks ), 'the recommendation\'s look, then Rétro Cubes, featured' );
		self::assertContains( $this->state( SlotKey::WHEELS, array( 'deck' => 102, 'trucks' => 144 ) )->steps[2]->recommended?->part->id, array( 301, 302 ) );
		self::assertSame( array(), $listing->looks[1]->look->choices(), 'Mint\'s two diameters are its sizes, not a choice' );
		self::assertSame( array( 'brand', 'wheel_diameter', 'pa_colour' ), array_map( static fn( ChipGroup $group ): string => $group->name, $listing->groups ) );
		self::assertSame( array( array( 301, 302, 303, 304 ) ), $this->looks->asked, 'the step\'s offered parts, in one call' );
	}

	public function test_search_folds_the_query_and_matches_brand_names_product_names_and_values(): void {
		self::assertSame( array( '10:1:0', '10:2:0' ), self::keys( $this->wheels( new ShelfQuery( query: 'SPIT' ) )->looks ), 'the brand\'s name, Spitfire' );
		self::assertSame( array( '20:3:0' ), self::keys( $this->wheels( new ShelfQuery( query: 'retro' ) )->looks ), 'Rétro, folded' );
		self::assertSame( array( '20:3:0' ), self::keys( $this->wheels( new ShelfQuery( query: 'casse' ) )->looks ), 'a value\'s name: Blanc cassé' );
		self::assertSame( array( '10:1:0' ), self::keys( $this->wheels( new ShelfQuery( query: '54' ) )->looks ), 'the size' );
		self::assertCount( 4, $this->ranking->asked, 'asked each time, and with no answer the builder searched on its own' );
	}

	public function test_creative_search_ranks_the_query_where_the_kit_allows_it(): void {
		$this->ranking->answer = array(
			'ids'       => array( 10 ),
			'corrected' => 'Mínt',
		);
		self::assertSame( array( '10:1:0' ), self::keys( $this->wheels( new ShelfQuery( query: '  mnit  ' ) )->looks ), 'Retna, narrowed by the corrected query, folded' );
		self::assertSame( array( array( 'mnit', array( 301, 302, 303, 304 ) ) ), $this->ranking->asked, 'the query trimmed, with the step\'s parts' );

		$this->ranking->asked = array();
		self::assertSame( array(), self::keys( $this->wheels( new ShelfQuery( query: 'mnit', ranked: false ) )->looks ), 'the builder\'s own search, with no typos' );
		self::assertSame( array(), $this->ranking->asked, 'and Creative Search is not asked' );

		$this->wheels( new ShelfQuery( query: str_repeat( 'a', 150 ) ) );
		self::assertSame( 100, strlen( $this->ranking->asked[0][0] ), 'a query is 100 characters at most' );
	}

	// Spec (configurator): Settings (D-255), "Search in the steps": own, "the builder's own always".
	public function test_a_kit_whose_search_is_its_own_never_asks_creative_search(): void {
		$own = Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, array( 'schema_version' => 1, 'search' => 'own' ) );
		$this->kits->add( self::KIT, $own, $this->parts );
		$this->ranking->answer = array(
			'ids'       => array( 10 ),
			'corrected' => null,
		);
		$this->wheels( new ShelfQuery( query: 'mnit' ) );
		self::assertSame( array(), $this->ranking->asked );
	}

	public function test_the_width_asked_lists_the_decks_in_it_and_anything_else_asks_the_question(): void {
		$shelf = $this->shelf();
		foreach ( array( 101, 102, 103 ) as $at => $id ) {
			$this->looks->facts[ $id ] = new LookFact( 1, 10 + $at, 0, 'Deck', array(), array(), false, 0, 0 );
		}
		$listing = $shelf->of( $this->state( SlotKey::DECK ), new ShelfQuery( '8.25in' ) )?->listing;
		self::assertSame( array( 102 ), array_map( static fn( ShelfLook $look ): int => $look->option->part->id, $listing->looks ?? array() ) );
		self::assertCount( 3, $listing->widths ?? array() );
		foreach ( array( '', 'wide', '8.25', '8.25ft', '1234in', '8.123456in' ) as $width ) {
			self::assertSame( array(), $shelf->of( $this->state( SlotKey::DECK ), new ShelfQuery( $width ) )?->listing->looks, $width );
		}
		self::assertSame( array( 103 ), array_map( static fn( ShelfLook $look ): int => $look->option->part->id, $shelf->of( $this->state( SlotKey::DECK ), new ShelfQuery( '8.5in' ) )?->listing->looks ?? array() ) );
	}

	public function test_a_deck_the_build_holds_brings_its_width_and_skips_the_question(): void {
		foreach ( array( 101, 102, 103 ) as $at => $id ) {
			$this->looks->facts[ $id ] = new LookFact( 1, 10 + $at, 0, 'Deck', array(), array(), false, 0, 0 );
		}
		$view = $this->shelf()->of( $this->state( SlotKey::DECK, array( 'deck' => 102 ) ), new ShelfQuery() );
		self::assertSame( array( 102 ), array_map( static fn( ShelfLook $look ): int => $look->option->part->id, $view->listing->looks ?? array() ) );
		self::assertSame( '8.25in', $view?->width );
		$asked = $this->shelf()->of( $this->state( SlotKey::DECK, array( 'deck' => 102 ) ), new ShelfQuery( '8.5in' ) );
		self::assertSame( '8.5in', $asked?->width, 'a width asked comes first' );
	}

	public function test_the_review_lists_no_parts_and_a_page_starts_where_it_is_asked(): void {
		self::assertNull( $this->shelf()->of( $this->state( State::REVIEW ), new ShelfQuery() ) );
		$page = $this->wheels( new ShelfQuery( from: -4, shown: 0 ) );
		self::assertSame( array( '10:1:0' ), self::keys( $page->looks ), 'from 0, one at least' );
		self::assertSame( 1, $page->more );
	}

	public function test_the_view_names_the_brands_the_values_and_the_sizes(): void {
		$view = $this->shelf()->of( $this->state( SlotKey::WHEELS, array( 'deck' => 102, 'trucks' => 144 ) ), new ShelfQuery() );
		self::assertNotNull( $view );
		self::assertSame( 'Spitfire', $view->labels['brand']['spitfire'] );
		self::assertSame( 'Blanc cassé', $view->labels['pa_colour']['white'] );
		self::assertSame( array(), $view->groups, 'the shop names no attribute' );
		$this->looks->names = array(
			'pa_colour'   => 'Colour',
			'pa_diameter' => 'Diameter',
			'brand'       => 'Brand',
		);
		self::assertSame( array( 'pa_colour' => 'Colour' ), $this->shelf()->of( $this->state( SlotKey::WHEELS, array( 'deck' => 102, 'trucks' => 144 ) ), new ShelfQuery() )?->groups, 'the chip groups\' attributes alone' );
		self::assertArrayNotHasKey( 'pa_diameter', $view->labels, 'the size is said from its measure' );
		self::assertSame( array( '52mm', '54mm', '56mm', '53mm' ), array_keys( $view->sizes ) );
		self::assertSame( '54.0000', $view->sizes['54mm']->decimal() );
		self::assertSame( array( '', 'recommended', '' ), array( $view->query, $view->sort, $view->width ) );
		$read = $this->shelf()->of( $this->state( SlotKey::WHEELS, array( 'deck' => 102, 'trucks' => 144 ) ), new ShelfQuery( '8.250in', query: '  mint ', sort: 'cheapest' ) );
		self::assertSame( array( 'mint', 'recommended', '8.25in' ), array( $read?->query, $read?->sort, $read?->width ), 'as read: trimmed, the default for an unknown sort, the width named again' );
		$read = $this->shelf()->of( $this->state( SlotKey::WHEELS, array( 'deck' => 102, 'trucks' => 144 ) ), new ShelfQuery( sort: 'newest' ) );
		self::assertSame( 'newest', $read?->sort );
	}

	public function test_a_part_the_shop_holds_no_look_for_is_left_off(): void {
		unset( $this->looks->facts[304] );
		self::assertSame( array( '10:1:0', '10:2:0' ), self::keys( $this->wheels()->looks ) );
		$this->looks->facts[304] = new LookFact( 20, 3, 0, 'Rétro Cubes', array(), array(), true, 0, 0 );
		unset( $this->looks->facts[301] );
		self::assertSame( array( '20:3:0', '10:1:0', '10:2:0' ), self::keys( $this->wheels()->looks ), 'and the parts after it are listed: 302 and 303 of Retna' );
	}

	/**
	 * The shelf with a folding that keeps case, so a brand's name and its slug read apart.
	 */
	private function caseKept(): StepShelf {
		$folding = new class() implements TextFolding {
			public function fold( string $text ): string {
				return $text;
			}
		};
		return new StepShelf( $this->looks, new InMemoryPartCards(), $this->ranking, $folding, $this->settings );
	}

	// D-263: a brand is searched by the shop's name for it, and the shop is asked each brand's name once.
	public function test_a_brand_is_searched_by_its_name_and_each_brands_name_is_asked_once(): void {
		$cards = new InMemoryPartCards();
		$shelf = new StepShelf( $this->looks, $cards, $this->ranking, new class() implements TextFolding {
			public function fold( string $text ): string {
				return $text;
			}
		}, $this->settings );
		$view  = $shelf->of( $this->state( SlotKey::WHEELS, array( 'deck' => 102, 'trucks' => 144 ) ), new ShelfQuery( query: 'Spit' ) );
		self::assertSame( array( array( 'spitfire', 'bones' ) ), $cards->brandsAsked, 'a list, each brand once' );
		self::assertSame( array( '10:1:0', '10:2:0' ), self::keys( $view->listing->looks ?? array() ), 'Spitfire, not its slug' );
	}

	// SPEC-CONFIGURATOR, Search: a query is 100 characters at most, not 100 bytes.
	public function test_a_query_is_cut_at_100_characters(): void {
		$this->wheels( new ShelfQuery( query: str_repeat( 'é', 150 ) ) );
		self::assertSame( str_repeat( 'é', 100 ), $this->ranking->asked[0][0] );
	}

	// Spec: Search, when nothing is found (D-277): "2 trucks match, but they don't fit", by brand or by name, and only
	// when nothing is listed.
	public function test_the_parts_that_dont_fit_are_counted_by_brand_when_nothing_is_listed_and_only_then(): void {
		foreach ( array( 139, 144, 149, 169 ) as $id ) {
			$this->looks->facts[ $id ] = new LookFact( 40, $id, 0, 'Stage 11', array(), array(), false, 0, 0 );
		}
		$trucks = fn( string $query ): ?ShelfView => $this->caseKept()->of( $this->state( SlotKey::TRUCKS, array( 'deck' => 103 ) ), new ShelfQuery( query: $query ) );
		self::assertSame( 0, $trucks( 'Stage' )?->hidden, 'the 149 is listed' );
		unset( $this->looks->facts[149] );
		self::assertSame( 3, $trucks( 'Indep' )?->hidden, 'Independent, the brand\'s name' );
		self::assertSame( 3, $trucks( 'Stage' )?->hidden, 'the product\'s name' );
	}

	// D-264: a query left to its defaults starts at the first page, and a view that says nothing hides nothing.
	public function test_a_query_starts_at_the_first_page_and_a_view_hides_none_unless_told(): void {
		$query = new ShelfQuery();
		self::assertSame( array( '', array(), '', 'recommended', 0, true ), array( $query->width, $query->chips, $query->query, $query->sort, $query->from, $query->ranked ) );
		$view = new ShelfView( $this->wheels(), array(), array(), '', 'recommended', '' );
		self::assertSame( array( array(), array(), 0 ), array( $view->groups, $view->names, $view->hidden ) );
	}
}
