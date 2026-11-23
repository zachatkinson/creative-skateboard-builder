<?php
/**
 * The state route's answer: the parts of it StateAnswerTest leaves to one side.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedPrices;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\Delivery;
use CreativeBranding\SkateboardBuilder\Front\PageRenderer;
use CreativeBranding\SkateboardBuilder\Front\ShippingWords;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Port\TextFolding;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\Reopened;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Service\StepShelf;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\CountingUuids;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeMoney;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeRanking;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeShippingZones;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCards;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLooks;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySavedBuilds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * The answer's tile pictures, the cards it asks for, the sizes after a picked card, the shelf's chips, and a saved
 * build as it reopens.
 */
final class StateAnswerDetailTest extends TestCase {

	private const int KIT = 77;

	private const array STREET = array(
		SlotKey::DECK     => 101,
		SlotKey::TRUCKS   => 139,
		SlotKey::WHEELS   => 301,
		SlotKey::BEARINGS => 501,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

	private const array PARK = array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149, SlotKey::WHEELS => 302 ) + self::STREET;

	private InMemoryKits $kits;

	private InMemoryShopSettings $settings;

	private InMemoryPartCards $cards;

	private InMemoryPartLooks $looks;

	protected function setUp(): void {
		$this->kits     = new InMemoryKits();
		$this->settings = new InMemoryShopSettings();
		$this->cards    = new InMemoryPartCards();
		$this->looks    = new InMemoryPartLooks();
		$this->stock( Shop::parts() );
	}

	/**
	 * The usual kit with these parts, each a look of its own product unless one is set.
	 *
	 * @param array<string, list<Part>> $parts The parts by slot.
	 */
	private function stock( array $parts, bool $presets = false ): void {
		$this->kits->add(
			self::KIT,
			$presets ? KitDocuments::kit(
				KitDocuments::slots(),
				null,
				array(
					'schema_version' => 1,
					'presets'        => array(
						array(
							'name'  => 'Street',
							'picks' => self::STREET,
						),
						array(
							'name'  => 'Park',
							'picks' => self::PARK,
						),
					),
				)
			) : Shop::kit(),
			$parts
		);
		foreach ( $parts as $list ) {
			foreach ( $list as $part ) {
				$this->looks->facts[ $part->id ] ??= new LookFact( $part->id, $part->id, 0, 'Part ' . $part->id, array(), array(), false, 0, 0 );
			}
		}
	}

	/**
	 * The build's state.
	 *
	 * @param array<string, int> $picks The picks.
	 */
	private function state( array $picks = array(), ?string $step = null ): State {
		$state = ( new BuildState( $this->kits, $this->settings, new FakeLogger() ) )->of( self::KIT, $picks, null, $step );
		self::assertNotNull( $state );
		return $state;
	}

	/**
	 * An answer with nothing given it by the container.
	 */
	private function bare(): StateAnswer {
		return new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() );
	}

	/**
	 * The step's shelf.
	 */
	private function shelved(): StepShelf {
		$folding = new class() implements TextFolding {
			public function fold( string $text ): string {
				return strtolower( $text );
			}
		};
		return new StepShelf( $this->looks, $this->cards, new FakeRanking(), $folding, $this->settings );
	}

	/**
	 * The answer's shelf for a build.
	 *
	 * @param array<string, int> $picks The picks.
	 * @return array<string, mixed>
	 */
	private function shelf( array $picks, string $step, ?BuildLink $link = null ): array {
		$shelf = $this->bare()->withShelf( $this->shelved() )->of( $this->state( $picks, $step ), $link )['shelf'];
		self::assertIsArray( $shelf );
		return $shelf;
	}

	/**
	 * The look a part's card is, among the shelf's looks and those sold out.
	 *
	 * @param array<string, mixed> $shelf The shelf.
	 * @return array<string, mixed>
	 */
	private static function card( array $shelf, int $part ): array {
		foreach ( array( ...$shelf['looks'], ...$shelf['sold_out'] ) as $look ) {
			if ( $part === $look['part']['id'] ) {
				return $look;
			}
		}
		self::fail( "No card is of part {$part}." );
	}

	public function test_an_answer_given_its_shelf_or_its_pictures_is_a_copy_and_the_one_it_was_given_on_is_unchanged(): void {
		$this->stock( Shop::parts(), true );
		$bare  = $this->bare();
		$state = $this->state();

		$shelved = $bare->withShelf( $this->shelved() );
		self::assertNotSame( $bare, $shelved );
		self::assertNull( $bare->of( $state )['shelf'], 'the answer withShelf() was called on has none' );
		self::assertIsArray( $shelved->of( $state )['shelf'] );

		$pictured = $bare->withPictures( static fn(): string => 'https://example.com/pictures/tile.webp' );
		self::assertNotSame( $bare, $pictured );
		self::assertSame( 'https://example.com/pictures/tile.webp', $pictured->of( $state )['presets'][0]['image']['src'] ?? null );
		self::assertSame( InMemoryPartCards::card( 101 )['image'], $bare->of( $state )['presets'][0]['image'], 'the answer withPictures() was called on shows the first part\'s photo' );
	}

	// D-252: the tiles show at the start alone; on a build begun the tile keeps its first part's photo, which the step
	// on show may not list, so the answer asks for its card.
	public function test_a_tile_off_the_first_step_shows_its_first_parts_photo_though_the_step_lists_another_slot(): void {
		$this->stock( Shop::parts(), true );
		$answer = $this->bare()->of( $this->state( array(), SlotKey::GRIP ) );

		self::assertSame( InMemoryPartCards::card( 101 )['image'], $answer['presets'][0]['image'], 'Street\'s deck' );
		self::assertSame( InMemoryPartCards::card( 103 )['image'], $answer['presets'][1]['image'], 'Park\'s deck' );
		self::assertSame( array( array( 701, 101, 103 ) ), $this->cards->asked, 'the grip on show, then each preset\'s deck' );

		$this->cards->missing = array( 101 );
		self::assertNull( $this->bare()->of( $this->state( array(), SlotKey::GRIP ) )['presets'][0]['image'], 'a deck whose product has gone has no photo' );
	}

	// D-252: "The tiles show at the start alone": a pick on the first step begins the build, and no picture is asked.
	public function test_a_pick_on_the_first_step_begins_the_build_and_asks_for_no_tile_picture(): void {
		$this->stock( Shop::parts(), true );
		$asked  = array();
		$answer = $this->bare()->withPictures(
			static function ( int $kit, array $picks ) use ( &$asked ): string {
				$asked[] = $picks;
				return 'https://example.com/pictures/tile.webp';
			}
		);

		$answer->of( $this->state( array(), SlotKey::DECK ) );
		self::assertCount( 2, $asked, 'at the start, each tile asks' );

		$asked  = array();
		$picked = $answer->of( $this->state( array( SlotKey::DECK => 101 ), SlotKey::DECK ) );
		self::assertSame( SlotKey::DECK, $picked['step'] );
		self::assertSame( array(), $asked, 'a deck picked, still on the deck step' );
		self::assertSame( InMemoryPartCards::card( 101 )['image'], $picked['presets'][0]['image'] );
	}

	// Spec, Presets: each tile shows its total. A state that carries no build for a preset shows it at nothing, and
	// with nothing keeping it from the cart.
	public function test_a_preset_the_state_did_not_build_is_shown_at_no_total_and_ready(): void {
		$this->stock( Shop::parts(), true );
		$built = $this->state();
		$state = new State( $built->id, $built->kit, $built->steps, $built->step, $built->refused, $built->validation, $built->changes, $built->quote, $built->discount );

		$presets = $this->bare()->of( $state )['presets'];
		self::assertSame(
			array(
				'amount' => 0,
				'text'   => '$0.00',
			),
			$presets[0]['total']
		);
		self::assertTrue( $presets[0]['ready'] );
		self::assertSame( array(), $presets[0]['problems'] );
	}

	// Spec, Presets: what keeps a preset from the cart is named for its tile. A deck the shop no longer has leaves its
	// slot empty, and an empty slot has no part to name.
	public function test_a_preset_whose_part_has_gone_names_the_part_and_not_the_slot_it_leaves_empty(): void {
		$parts                  = Shop::parts();
		$parts[ SlotKey::DECK ] = array_values( array_filter( $parts[ SlotKey::DECK ], static fn( Part $part ): bool => 103 !== $part->id ) );
		$this->stock( $parts, true );
		$problems = $this->bare()->of( $this->state() )['presets'][1]['problems'];

		$named = array_column( $problems, 'name', 'code' );
		self::assertSame( '', $named['slot_empty'] ?? null, 'the deck slot, left empty' );
		self::assertContains( null, array_column( $problems, 'part' ) );
	}

	// Spec, Performance: card data is loaded only for what the answer names, in one call, each part once.
	public function test_the_cards_are_asked_for_once_each_in_one_list(): void {
		$this->bare()->of( $this->state( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ), SlotKey::DECK ) );
		self::assertSame( array( array( 101, 102, 103, 149 ) ), $this->cards->asked, 'the deck listed and counted is asked for once, and the list is numbered from nothing' );
	}

	// Spec, Performance; D-264: the shelf lists more cards than the step's options, and its sold-out fold its own.
	public function test_every_card_on_the_shelf_and_in_its_sold_out_fold_has_its_part_card(): void {
		$parts = Shop::parts();
		$model = $parts[ SlotKey::WHEELS ][0];
		$list  = array();
		for ( $at = 0; $at < 45; $at++ ) {
			$list[] = new Part( 3000 + $at, '', $model->specs, 4000, 44 !== $at );
		}
		$parts[ SlotKey::WHEELS ] = $list;
		$this->stock( $parts );
		$shelf = $this->shelf( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139 ), SlotKey::WHEELS );

		self::assertCount( 30, $shelf['looks'] );
		self::assertSame( InMemoryPartCards::listed( 3029 ), self::card( $shelf, 3029 )['part']['card'], 'past the 24 options the step lists' );
		self::assertSame( array( 3044 ), array_column( array_column( $shelf['sold_out'], 'part' ), 'id' ) );
		self::assertSame( InMemoryPartCards::listed( 3044 ), $shelf['sold_out'][0]['part']['card'], 'sold out, so after the 24' );
	}

	// Spec, The total: a part whose product has gone keeps its place in the box with no photo.
	public function test_a_part_whose_product_has_gone_is_in_the_box_with_no_photo(): void {
		$this->cards->missing = array( 103 );
		$item                 = $this->bare()->of( $this->state( array( SlotKey::DECK => 103 ), SlotKey::DECK ) )['picture']['items'][0];
		self::assertSame( 103, $item['part'] );
		self::assertFalse( $item['photo'] );
		self::assertTrue( InMemoryPartCards::card( 103 )['photo'], 'its card would have said it had one' );
	}

	public function test_each_slot_names_its_size_and_one_of_the_owners_own_says_size(): void {
		self::assertSame(
			array( 'Width', 'Axle width', 'Diameter', 'Thickness', 'Length', 'Size', 'Size' ),
			array_map( StateAnswer::sizeName( ... ), array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS, SlotKey::HARDWARE, SlotKey::BEARINGS, 'sail' ) )
		);
	}

	/**
	 * Wheels of one look in several sizes, in the order given: each ID with its diameter, whether the shop has it, and
	 * its colour.
	 *
	 * @param list<array{int, string, bool, string}> $wheels The wheels.
	 */
	private function wheels( array $wheels ): void {
		$parts = Shop::parts();
		$list  = array();
		foreach ( $wheels as [ $id, $diameter, $stocked, $colour ] ) {
			$list[]                    = Shop::priced( Builds::wheel( $diameter, $id ), 3000, $stocked );
			$this->looks->facts[ $id ] = new LookFact( 10, 1, 0, 'Retna', array( 'pa_colour' => array( $colour ) ), array( 'pa_colour' => array( $colour => ucfirst( $colour ) ) ), false, 0, 0 );
		}
		$parts[ SlotKey::WHEELS ] = $list;
		$this->stock( $parts );
	}

	/**
	 * The sizes after the picked wheels' card: each radio's part, size, stock and whether it is the pick.
	 *
	 * @return list<array{int, string, bool, bool}>
	 */
	private function sizes( int $picked ): array {
		$shelf = $this->shelf( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149, SlotKey::WHEELS => $picked ), SlotKey::WHEELS );
		$sizes = array();
		foreach ( self::card( $shelf, $picked )['sized'] ?? array() as $one ) {
			self::assertSame( array( 'part', 'value', 'text', 'in_stock', 'picked' ), array_keys( $one ) );
			$sizes[] = array( $one['part'], $one['value'], $one['in_stock'], $one['picked'] );
		}
		return $sizes;
	}

	// Spec (configurator): One card per look, "The picked card is followed by a radio group of its own … with the size
	// … already chosen" (D-279): one radio a size, smallest first, of the parts with the pick's own choices; for each
	// size the part the build holds, else the first the shop has, else the first.
	public function test_the_sizes_after_the_picked_card_are_one_part_a_size_smallest_first(): void {
		$this->wheels(
			array(
				array( 306, '56', false, 'mint' ),
				array( 307, '56', true, 'mint' ),
				array( 301, '52', true, 'mint' ),
				array( 302, '54', true, 'mint' ),
				array( 305, '52', true, 'mint' ),
				array( 308, '54', true, 'mint' ),
				array( 309, '50', true, 'black' ),
			)
		);

		self::assertSame(
			array(
				array( 301, '52mm', true, false ),
				array( 302, '54mm', true, true ),
				array( 307, '56mm', true, false ),
			),
			$this->sizes( 302 ),
			'52: the first in stock; 54: the pick, before another; 56: the one in stock, after one sold out; no 50, which is black'
		);
		self::assertSame(
			array(
				array( 301, '52mm', true, false ),
				array( 308, '54mm', true, true ),
				array( 307, '56mm', true, false ),
			),
			$this->sizes( 308 ),
			'the pick, after another of its size'
		);
		self::assertSame(
			array( 'value' => '52mm', 'text' => "52\u{a0}mm" ),
			array_intersect_key( self::card( $this->shelf( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149, SlotKey::WHEELS => 302 ), SlotKey::WHEELS ), 302 )['sized'][0] ?? array(), array_flip( array( 'value', 'text' ) ) )
		);
	}

	public function test_a_picked_part_sold_out_is_its_sizes_radio_though_another_of_its_size_is_in_stock(): void {
		$this->wheels(
			array(
				array( 302, '54', false, 'mint' ),
				array( 308, '54', true, 'mint' ),
				array( 301, '52', true, 'mint' ),
			)
		);
		self::assertSame(
			array(
				array( 301, '52mm', true, false ),
				array( 302, '54mm', false, true ),
			),
			$this->sizes( 302 )
		);
	}

	// D-279: where the build decides the size, as a deck's width, or the slot has none a shopper picks, as bearings,
	// the picked card has no sizes.
	public function test_a_picked_card_whose_size_the_build_decides_or_that_has_none_has_no_sizes(): void {
		foreach ( array( 101, 102, 103 ) as $id ) {
			$this->looks->facts[ $id ] = new LookFact( 10, 1, 0, 'Katie', array(), array(), false, 0, 0 );
		}
		$deck = $this->shelf( array( SlotKey::DECK => 102 ), SlotKey::DECK );
		self::assertArrayNotHasKey( 'sized', self::card( $deck, 102 ), 'three widths of one look' );

		$bearings = $this->shelf( self::STREET, SlotKey::BEARINGS );
		self::assertArrayNotHasKey( 'sized', self::card( $bearings, 501 ) );
	}

	// Spec (configurator): Finding a part, the chips: a group of the shop's own attribute is called by the attribute's
	// name, and one whose name the shop has not given by its taxonomy.
	public function test_a_chip_group_of_the_shops_own_attribute_is_called_by_its_name(): void {
		foreach ( array( 301 => 'mint', 302 => 'black', 303 => 'red' ) as $id => $colour ) {
			$this->looks->facts[ $id ] = new LookFact( $id, 1, 0, 'Wheel ' . $id, array( 'pa_colour' => array( $colour ) ), array( 'pa_colour' => array( $colour => ucfirst( $colour ) ) ), false, 0, 0 );
		}
		$picks  = array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 );
		$groups = static fn( array $shelf ): array => array_column( $shelf['chips'], 'label', 'name' );

		self::assertSame( 'pa_colour', $groups( $this->shelf( $picks, SlotKey::WHEELS ) )['pa_colour'] ?? null, 'no name given' );
		$this->looks->names = array( 'pa_colour' => 'Colour' );
		$named              = $this->shelf( $picks, SlotKey::WHEELS );
		self::assertSame( array( 'wheel_diameter' => 'Diameter', 'pa_colour' => 'Colour' ), $groups( $named ) );
		self::assertSame( array( 'Black', 'Mint', 'Red' ), array_column( $named['chips'][1]['chips'], 'label' ) );
	}

	/**
	 * A complete build saved with the deck at $60.00 and the trucks at $50.00, as it reopens, its wheels sold out or not.
	 */
	private function reopened( bool $soldOut ): Reopened {
		$store = new InMemorySavedBuilds();
		$saved = new SavedBuilds( $store, new BuildState( $this->kits, $this->settings, new FakeLogger() ), new CountingUuids(), new FakeClock(), new FakeLogger(), new FakeMoney() );
		$at    = new DateTimeImmutable( '2026-11-01', new DateTimeZone( 'UTC' ) );
		$store->put( new SavedBuild( 'a0000000-0000-4000-8000-000000000001', 7, self::KIT, self::PARK, false, '', $at, $at, new SavedPrices( 'CAD', array( SlotKey::DECK => 6000, SlotKey::TRUCKS => 5000 ), 30000 ) ) );
		if ( $soldOut ) {
			$parts                    = Shop::parts();
			$parts[ SlotKey::WHEELS ] = array_map( static fn( Part $part ): Part => 302 === $part->id ? Shop::priced( $part, $part->price, false, 0 ) : $part, $parts[ SlotKey::WHEELS ] );
			$this->stock( $parts );
		}
		$reopened = $saved->reopened( 7, 'a0000000-0000-4000-8000-000000000001' );
		self::assertNotNull( $reopened );
		return $reopened;
	}

	// Spec (configurator): A saved build that has changed (D-254), "The screen": each change with its reason and the
	// closest part, each new price then and now, and the totals, each part named from its card.
	public function test_a_saved_build_reopens_with_each_change_and_each_new_price_named(): void {
		$reopened = $this->reopened( true );
		$said     = $this->bare()->reopened( $reopened );

		self::assertSame(
			array(
				array(
					'slot'    => SlotKey::WHEELS,
					'label'   => 'Wheels',
					'was'     => 302,
					'name'    => 'Part 302',
					'reason'  => 'sold_out',
					'closest' => array(
						'id'   => 301,
						'name' => 'Part 301',
					),
				),
			),
			$said['changes']
		);
		self::assertSame(
			array(
				array(
					'slot'  => SlotKey::DECK,
					'label' => 'Deck',
					'part'  => 103,
					'name'  => 'Part 103',
					'then'  => '$60.00',
					'now'   => '$70.00',
				),
				array(
					'slot'  => SlotKey::TRUCKS,
					'label' => 'Trucks',
					'part'  => 149,
					'name'  => 'Part 149',
					'then'  => '$50.00',
					'now'   => '$54.00',
				),
			),
			$said['prices']
		);
		self::assertSame( '$300.00', $said['then'] );
		self::assertSame( ( new FakeMoney() )->text( $reopened->state->quote->total, 2 ), $said['now'] );
		self::assertSame( $reopened->check->picks, $said['picks'] );
		self::assertSame( SlotKey::WHEELS, $said['first'], 'it opens on the first change' );
		self::assertSame( array( array( 103, 149, 302, 301 ) ), $this->cards->asked );
	}

	// D-254: a build whose prices alone changed opens on the first part whose price did.
	public function test_a_saved_build_whose_prices_alone_changed_opens_on_the_first_of_them(): void {
		$said = $this->bare()->reopened( $this->reopened( false ) );
		self::assertSame( array(), $said['changes'] );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS ), array_column( $said['prices'], 'slot' ) );
		self::assertSame( SlotKey::DECK, $said['first'] );
	}

	public function test_a_reopened_build_asks_for_each_card_once_and_names_a_slot_the_kit_lacks_by_its_key(): void {
		$reopened = $this->reopened( true );
		$prices   = array(
			array(
				'slot' => 'sail',
				'part' => 302,
				'then' => 100,
				'now'  => 200,
			),
			array(
				'slot' => SlotKey::TRUCKS,
				'part' => 149,
				'then' => 5000,
				'now'  => 5400,
			),
		);
		$said     = $this->bare()->reopened( new Reopened( $reopened->build, $reopened->check, $reopened->state, $prices, null ) );

		self::assertSame( array( array( 302, 149, 301 ) ), $this->cards->asked, 'each once, in a list numbered from nothing' );
		self::assertSame( 'sail', $said['prices'][0]['label'] );
		self::assertNull( $said['then'], 'no total saved' );
	}
}
