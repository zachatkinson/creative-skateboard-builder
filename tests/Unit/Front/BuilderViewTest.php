<?php
/**
 * The builder's document, from an answer written out by hand.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Front\BuilderView;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, The builder; Rules, Markup: the view words the state route's answer, and decides nothing of fit, price or
 * step. Each case gives it the answer a build would have, cut down to what the case is about, and reads what the
 * template would show. The kit has a slot of a kind the plugin does not name, Stickers, after the usual seven.
 */
final class BuilderViewTest extends TestCase {

	private const int KIT = 77;

	private const string BASE = 'https://shop.test/kit/';

	private InMemoryKits $kits;

	protected function setUp(): void {
		$slots            = KitDocuments::slots();
		$slots['slots'][] = array(
			'key'      => 'stickers',
			'label'    => 'Stickers',
			'required' => false,
			'units'    => 1,
		);
		$this->kits = new InMemoryKits();
		$this->kits->add( self::KIT, KitDocuments::kit( $slots ), Shop::parts() + array( 'stickers' => array() ) );
	}

	/**
	 * The answer's steps, each with its label, and the trucks named as the shop names them.
	 *
	 * @param array<string, array<string, mixed>> $more What a step's entry holds besides, by slot.
	 * @return list<array<string, mixed>>
	 */
	private static function steps( array $more = array() ): array {
		$steps = array();
		foreach ( array( 'deck' => 'Deck', 'trucks' => 'Truck set', 'wheels' => 'Wheels', 'risers' => 'Risers', 'bearings' => 'Bearings', 'hardware' => 'Hardware', 'grip' => 'Grip', 'stickers' => 'Stickers' ) as $slot => $label ) {
			$steps[] = array(
				'slot'  => $slot,
				'label' => $label,
			) + ( $more[ $slot ] ?? array() );
		}
		return $steps;
	}

	/**
	 * The view of an answer on a step.
	 *
	 * @param array<string, mixed> $answer The answer, without its steps.
	 * @param string               $step   The step shown.
	 * @param BuildLink|null       $link   The address, or the step's own.
	 * @return array<string, mixed>
	 */
	private function view( array $answer, string $step, ?BuildLink $link = null ): array {
		$link ??= new BuildLink( array(), null, $step );
		$state  = ( new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ) )->of( self::KIT, $link->picks, null, $step );
		self::assertNotNull( $state );
		return ( new BuilderView() )->of( $state->at( $step ), $answer + array( 'steps' => self::steps() ), $link, self::BASE, $state->kit->slots->keys(), null );
	}

	/**
	 * The view's step.
	 *
	 * @param array<string, mixed> $answer The answer.
	 * @param string               $step   The step shown.
	 * @param BuildLink|null       $link   The address.
	 * @return array<string, mixed>
	 */
	private function step( array $answer, string $step, ?BuildLink $link = null ): array {
		$view = $this->view( $answer, $step, $link )['step'];
		self::assertIsArray( $view );
		return $view;
	}

	// Spec: The layout, Footer. The footer's Back is the step's own, none on the first step, and the last step at the
	// review; the change panel has none.
	public function test_the_footers_back_goes_to_the_step_before_or_from_the_review_to_the_last(): void {
		$review = $this->view( array(), State::REVIEW )['bar']['back'];
		self::assertTrue( $review['shown'] );
		self::assertSame( 'Back: Stickers', $review['label'] );
		self::assertStringContainsString( 'cbuild%5Bstep%5D=stickers', $review['url'] );

		self::assertFalse( $this->view( array(), SlotKey::DECK )['bar']['back']['shown'], 'the first step has nothing before it' );
		self::assertTrue( $this->view( array(), SlotKey::TRUCKS )['bar']['back']['shown'] );
	}

	/**
	 * The footer's Next on a step, with the answer's steps saying which need a pick.
	 *
	 * @param string                      $step     The step shown.
	 * @param array<string, bool>         $required Whether each slot needs a pick, by slot.
	 * @param list<array<string, mixed>>  $lines    The total's lines: the picks the build counts.
	 * @return array<string, mixed>
	 */
	private function next( string $step, array $required, array $lines = array() ): array {
		$more = array();
		foreach ( $required as $slot => $needed ) {
			$more[ $slot ] = array( 'required' => $needed );
		}
		$answer = array(
			'steps' => self::steps( $more ),
			'total' => array( 'lines' => $lines ),
		);
		$next   = $this->view( $answer, $step )['bar']['next'];
		self::assertIsArray( $next );
		return $next;
	}

	// #100, F15: Next is ready once the step has a pick, or on a step that needs none, by the rule the server stops a
	// step on (Builder: a required slot with nothing counted). Until then it says what is missing, named by the step's
	// part.
	public function test_next_is_ready_once_the_step_has_a_pick_or_on_a_step_that_needs_none(): void {
		$empty = $this->next( SlotKey::DECK, array( 'deck' => true ) );
		self::assertFalse( $empty['ready'] );
		self::assertSame( 'Choose your deck first.', $empty['missing'] );

		$picked = $this->next(
			SlotKey::DECK,
			array( 'deck' => true ),
			array(
				array(
					'slot'  => 'deck',
					'part'  => 101,
					'total' => array( 'text' => '$60.00' ),
				),
			)
		);
		self::assertTrue( $picked['ready'], 'the deck picked' );

		self::assertTrue( $this->next( SlotKey::RISERS, array( 'risers' => false ) )['ready'], 'No risers is a pick of its own' );
		self::assertTrue( $this->next( State::REVIEW, array( 'deck' => true ) )['ready'], 'the review has Add to cart, which says itself whether it is ready' );
	}

	/**
	 * Each step's words for what is missing.
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function missing(): iterable {
		yield 'deck' => array( SlotKey::DECK, 'Choose your deck first.' );
		yield 'trucks' => array( SlotKey::TRUCKS, 'Choose your trucks first.' );
		yield 'wheels' => array( SlotKey::WHEELS, 'Choose your wheels first.' );
		yield 'bearings' => array( SlotKey::BEARINGS, 'Choose your bearings first.' );
		yield 'risers' => array( SlotKey::RISERS, 'Choose your risers first.' );
		yield 'hardware' => array( SlotKey::HARDWARE, 'Choose your hardware first.' );
		yield 'grip' => array( SlotKey::GRIP, 'Choose your grip tape first.' );
		yield 'a slot the plugin does not name' => array( 'stickers', 'Stickers: make a choice first.' );
	}

	#[DataProvider( 'missing' )]
	public function test_next_says_what_is_missing_in_each_steps_own_words( string $slot, string $words ): void {
		$next = $this->next( $slot, array( $slot => true ) );
		self::assertFalse( $next['ready'] );
		self::assertSame( $words, $next['missing'] );
	}

	// #100, F15: the footer's Next is marked aria-disabled, never disabled, so it stays in the tab order and a press
	// can say what is missing; the words are in a polite live region beside it, drawn by the server (Rules, Markup).
	public function test_the_footers_next_is_aria_disabled_with_a_live_region_beside_it(): void {
		$bar = (string) file_get_contents( dirname( __DIR__, 3 ) . '/templates/builder/bar.php' );
		self::assertSame( 1, preg_match( '/<button class="[^"]*cbuild-bar__next"[^\n]*STEP_FORM[^\n]*<\/button>/', $bar, $found ) );
		$next = $found[0];
		self::assertStringContainsString( 'data-wp-bind--aria-disabled="state.dimmed"', $next );
		self::assertStringNotContainsString( 'disabled="', str_replace( 'aria-disabled="', '', $next ) );
		self::assertStringContainsString( 'aria-describedby="cbuild-bar-missing"', $next );
		self::assertSame( 1, preg_match( '/<p class="cbuild-bar__missing" id="cbuild-bar-missing" data-wp-text="state.missing"><\/p>/', $bar ), 'Next\'s description, and no second status region: the builder has one' );
	}

	// Spec: A change that breaks later picks. The panel asks for Use these or Undo, so the footer has no Back.
	public function test_the_change_panel_has_its_items_no_prices_and_no_back_in_the_footer(): void {
		$answer = array(
			'changes' => array(
				'kept'     => array(),
				'changes'  => array(
					array(
						'slot'     => 'wheels',
						'label'    => 'Wheels',
						'name'     => 'Retna',
						'messages' => array(),
						'closest'  => array(),
					),
				),
				'proposed' => array(),
			),
			'steps'   => self::steps( array( 'trucks' => array( 'required' => true ) ) ),
		);
		$view   = $this->view( $answer, SlotKey::TRUCKS );
		self::assertTrue( $view['step']['back']['shown'], 'the step has a Back of its own' );
		self::assertFalse( $view['bar']['back']['shown'], 'the panel shows none' );
		// The trucks are required and empty, so the step's own Next waits; the panel has no Next to dim (#100).
		self::assertFalse( $view['step']['next']['ready'] );
		self::assertSame( array( true, '' ), array( $view['bar']['next']['ready'], $view['bar']['next']['missing'] ) );
		self::assertTrue( $view['changes']['hasItems'] );
		self::assertFalse( $view['changes']['hasPrices'], 'a change made now has no new prices' );
		self::assertFalse( $view['changes']['hasTotals'] );
	}

	/**
	 * A preset as the answer has it.
	 *
	 * @param list<array<string, string>> $problems What keeps it from the cart.
	 * @return array<string, mixed>
	 */
	private static function preset( array $problems ): array {
		return array(
			'name'     => 'Street',
			'picks'    => array( array( 'slot' => 'deck', 'part' => 101 ) ),
			'problems' => $problems,
			'total'    => array( 'text' => '$184.00' ),
		);
	}

	// Spec: Presets. A part refused is named once a slot, and a slot left without its part leaves the tile no total.
	public function test_a_preset_tile_names_each_slot_once_and_has_no_total_once_a_slot_is_left_empty(): void {
		$answer = array(
			'presets' => array(
				self::preset(
					array(
						array( 'code' => 'slot_empty', 'slot' => 'wheels' ),
						array( 'code' => 'part_sold_out', 'slot' => 'trucks', 'name' => 'Indy' ),
					)
				),
				self::preset(
					array(
						array( 'code' => 'part_sold_out', 'slot' => 'trucks', 'name' => 'Indy' ),
						array( 'code' => 'part_blocked', 'slot' => 'trucks', 'name' => 'Indy' ),
						array( 'code' => 'part_short', 'slot' => 'grip', 'name' => 'Mob' ),
					)
				),
			),
		);
		$tiles  = $this->view( $answer, SlotKey::DECK )['presets']['tiles'];
		self::assertFalse( $tiles[0]['hasTotal'], 'empty wheels leave the total short' );
		self::assertSame(
			array(
				array( 'text' => 'Truck set: Indy is sold out.' ),
				array( 'text' => 'Grip: the shop has fewer of Mob than a build takes.' ),
			),
			$tiles[1]['notes'],
			'a list, as the template iterates it and the store reads it from JSON, not a map by slot'
		);
		self::assertTrue( $tiles[1]['hasTotal'], 'a part sold out still counts' );
	}

	// D-408: a build the guide recommends shows its deck's card picture, which is the deck's layer image where it has no
	// photo (D-282). That picture is marked drawn, as the card's is, so a plugin that flags the layer image alone finds
	// it; a photo is not, and a part's classes are merged either way (D-309).
	public function test_a_guide_tile_showing_a_decks_layer_image_is_marked_drawn_and_a_photo_is_not(): void {
		$state = ( new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ) )->of( self::KIT, array(), null, SlotKey::DECK );
		self::assertNotNull( $state );
		$image = array(
			'src'    => 'https://shop.test/deck.png',
			'width'  => 76,
			'height' => 300,
			'srcset' => '',
			'sizes'  => '',
		);
		$build = static fn( \CreativeBranding\SkateboardBuilder\Domain\Guide\Tier $tier, int $deck, bool $drawn ): array => array(
			'tier'  => $tier,
			'picks' => array( SlotKey::DECK => $deck ),
			'total' => '$100.00',
			'image' => $image,
			'drawn' => $drawn,
		);
		$GLOBALS['cbuild_test_filters']['cbuild_part_classes'] = static fn( array $classes, int $part ): array => 31 === $part ? array( 'is-flagged' ) : $classes;
		try {
			$guide = BuilderView::guide(
				$state,
				array(),
				new BuildLink(),
				self::BASE,
				array(
					'builds' => array(
						$build( \CreativeBranding\SkateboardBuilder\Domain\Guide\Tier::Budget, 31, true ),
						$build( \CreativeBranding\SkateboardBuilder\Domain\Guide\Tier::Premium, 32, false ),
					),
				)
			);
		} finally {
			unset( $GLOBALS['cbuild_test_filters'] );
		}
		self::assertSame(
			array( 'cbuild-preset__photo cbuild-preset__photo--drawn is-flagged', 'cbuild-preset__photo' ),
			array_column( $guide['tiles'], 'photoClasses' )
		);
	}

	// The auditor's 1.1.0 should-fix 2: the answer lists every ready build, each saying whether it is shown, and the
	// page makes a tile for those shown alone, each opening its own place among the kit's.
	public function test_a_ready_build_the_answer_does_not_show_has_no_tile(): void {
		$answer = array(
			'presets' => array(
				array( 'at' => 0, 'shown' => false, 'name' => 'Park' ) + self::preset( array() ),
				array( 'at' => 1, 'shown' => true, 'name' => 'Street' ) + self::preset( array() ),
			),
		);
		$presets = $this->view( $answer, SlotKey::DECK )['presets'];
		self::assertSame( array( 'Start from Street' ), array_column( $presets['tiles'], 'button' ) );
		self::assertContains( array( 'name' => 'cbuild[preset]', 'value' => '1' ), $presets['tiles'][0]['fields'] );
		self::assertTrue( $presets['shown'] );

		$answer['presets'][1]['shown'] = false;
		self::assertFalse( $this->view( $answer, SlotKey::DECK )['presets']['shown'], 'none shown, no section' );
	}

	/**
	 * Filters as the answer has them.
	 *
	 * @param bool $brands Whether the parts have brands to filter by.
	 * @return array<string, mixed>
	 */
	private static function filters( bool $brands ): array {
		return array(
			'filters' => array(
				'brands' => $brands ? array( array( 'value' => 'indy', 'label' => 'Independent', 'count' => 2 ) ) : array(),
				'sizes'  => array( array( 'value' => '149', 'label' => '149', 'count' => 3 ) ),
			),
		);
	}

	// Spec: A step, "Long lists get filters (brand, size) above them". The deck's sizes come first.
	public function test_filters_list_the_brand_then_the_size_and_the_decks_width_first(): void {
		$groups = $this->step( self::filters( true ), SlotKey::TRUCKS )['filters']['groups'];
		self::assertSame( array( 'brand', 'size' ), array_column( $groups, 'key' ) );
		self::assertSame( array( 'cbuild[_brand]', 'cbuild[_size]' ), array_column( $groups, 'name' ) );
		self::assertSame( array( 'size', 'brand' ), array_column( $this->step( self::filters( true ), SlotKey::DECK )['filters']['groups'], 'key' ) );
		self::assertSame( array( 'size' ), array_column( $this->step( self::filters( false ), SlotKey::TRUCKS )['filters']['groups'], 'key' ), 'parts with no brand still filter by size' );
	}

	// D-150: a step's filter stays in its form.
	public function test_a_steps_brand_filter_travels_in_its_form(): void {
		$tail = $this->step( array(), SlotKey::TRUCKS, new BuildLink( array(), null, SlotKey::TRUCKS, brand: 'indy' ) )['tail'];
		self::assertSame( 'indy', array_column( $tail, 'value', 'name' )['cbuild[_brand]'] ?? null );
	}

	// Spec: Accessibility. After a Show more the status region says what it added, and nothing where it added none.
	public function test_a_show_more_that_added_nothing_says_nothing(): void {
		$shelf = array(
			'shelf' => array(
				'looks'    => array(),
				'matching' => 0,
			),
		);
		self::assertSame( '', $this->step( $shelf, SlotKey::WHEELS, ( new BuildLink( array(), null, SlotKey::WHEELS ) )->showing( 60 ) )['more']['added'] );
	}

	// Spec (configurator): The deck's width first. The width chosen is the step's first line, and travels with the
	// build where the address has none of its own, as the deck the build holds gives it.
	public function test_the_width_chosen_is_said_and_travels_in_the_form(): void {
		$width = static fn( string $value, string $text, bool $selected ): array => array(
			'value'    => $value,
			'text'     => $text,
			'in_stock' => 1,
			'selected' => $selected,
		);
		$step  = $this->step(
			array(
				'shelf' => array(
					'looks'  => array(),
					'widths' => array( $width( '8in', '8″', false ), $width( '8.25in', '8.25″', true ), $width( '8.5in', '8.5″', false ) ),
				),
			),
			SlotKey::DECK
		);
		self::assertSame( '8.25″', $step['shelf']['width']['chosen'] );
		self::assertSame( '8.25in', array_column( $step['tail'], 'value', 'name' )['cbuild[_width]'] ?? null );
	}

	/**
	 * The deck step's answer, 41 decks offered in two widths, one of them chosen or none.
	 *
	 * @param bool $chosen   Whether 8.25in is chosen.
	 * @param int  $matching How many decks the shelf finds.
	 * @return array<string, mixed>
	 */
	private function deckStep( bool $chosen, int $matching ): array {
		$width = static fn( string $value, bool $selected ): array => array(
			'value'    => $value,
			'text'     => $value,
			'in_stock' => 1,
			'selected' => $selected,
		);
		return $this->step(
			array(
				'shelf' => array(
					'looks'    => array(),
					'offered'  => 41,
					'matching' => $matching,
					'widths'   => array( $width( '8in', false ), $width( '8.25in', $chosen ) ),
				),
			),
			SlotKey::DECK
		);
	}

	// Cowork's 1.1.0 review, F26 (#124): a list that holds nothing has no Sort and no count; the step says why.
	public function test_a_list_that_holds_nothing_has_neither_sort_nor_count(): void {
		$listed = $this->deckStep( true, 41 );
		self::assertSame( array( true, 'Showing 0 of 41' ), array( $listed['shelf']['sort']['shown'], $listed['more']['count'] ) );
		$none = $this->deckStep( true, 0 );
		self::assertSame( array( false, '' ), array( $none['shelf']['sort']['shown'], $none['more']['count'] ), 'nothing listed' );
		self::assertSame( '', $this->step( array( 'shelf' => array( 'matching' => 0 ) ), SlotKey::WHEELS )['more']['count'], 'a step listing parts, none' );
	}

	// Cowork's 1.1.0 review, F24 (#122): the deck step says to start with a width until one is chosen, and Sort, which
	// sits beside the chips out of the step's fieldset, waits for the width as the cards do.
	public function test_the_deck_steps_lead_and_sort_wait_for_the_width(): void {
		$asked = $this->deckStep( false, 41 );
		self::assertSame( array( true, false ), array( $asked['hasLead'], $asked['shelf']['sort']['shown'] ), 'the width asked first' );
		$chosen = $this->deckStep( true, 41 );
		self::assertSame( array( false, true ), array( $chosen['hasLead'], $chosen['shelf']['sort']['shown'] ), 'the width chosen' );
	}

	// Spec (configurator): Tools that grow. Each chip is an input with an ID of its own.
	public function test_each_chip_has_an_id_of_its_step_its_group_and_its_place(): void {
		$chip   = static fn( string $value ): array => array(
			'value' => $value,
			'label' => ucfirst( $value ),
			'count' => 3,
		);
		$shelf  = array(
			'looks'   => array(),
			'offered' => 13,
			'chips'   => array(
				array(
					'name'  => 'brand',
					'label' => 'Brand',
					'chips' => array( $chip( 'bones' ), $chip( 'ricta' ) ),
				),
			),
		);
		$inputs = $this->step( array( 'shelf' => $shelf ), SlotKey::WHEELS )['shelf']['chips']['groups'][0]['inputs'];
		self::assertSame( array( 'cbuild-chip-wheels-brand-0', 'cbuild-chip-wheels-brand-1' ), array_column( $inputs, 'id' ) );
	}

	/**
	 * @return iterable<string, array{int, bool, bool}>
	 */
	public static function offered(): iterable {
		yield 'twelve looks have their cards alone' => array( 12, false, false );
		yield 'thirteen get Sort and the chips' => array( 13, true, false );
		yield 'forty have no search' => array( 40, true, false );
		yield 'forty-one get one' => array( 41, true, true );
	}

	// Spec (configurator): Tools that grow.
	#[DataProvider( 'offered' )]
	public function test_the_tools_grow_with_the_looks_a_step_offers( int $offered, bool $sorted, bool $searched ): void {
		$shelf = array(
			'looks'    => array(),
			'offered'  => $offered,
			'matching' => $offered,
			'chips'    => array(
				array(
					'name'  => 'brand',
					'label' => 'Brand',
					'chips' => array(
						array(
							'value' => 'bones',
							'label' => 'Bones',
							'count' => 3,
						),
					),
				),
			),
		);
		$tools = $this->step( array( 'shelf' => $shelf ), SlotKey::WHEELS )['shelf'];
		self::assertSame( array( $sorted, $sorted, $searched ), array( $tools['sort']['shown'], $tools['chips']['shown'], $tools['search']['shown'] ) );
	}

	/**
	 * @return iterable<string, array{string, string, string, string}>
	 */
	public static function slots(): iterable {
		yield 'deck' => array( 'deck', 'Choose your deck', 'Search decks', 'No decks match zzz.' );
		yield 'trucks' => array( 'trucks', 'Choose your trucks', 'Search trucks', 'No trucks match zzz.' );
		yield 'wheels' => array( 'wheels', 'Choose your wheels', 'Search wheels', 'No wheels match zzz.' );
		yield 'risers' => array( 'risers', 'Choose your risers', 'Search risers', 'No risers match zzz.' );
		yield 'bearings' => array( 'bearings', 'Choose your bearings', 'Search bearings', 'No bearings match zzz.' );
		yield 'hardware' => array( 'hardware', 'Choose your hardware', 'Search hardware', 'No hardware matches zzz.' );
		yield 'grip' => array( 'grip', 'Choose your grip tape', 'Search grip tape', 'No grip tape matches zzz.' );
		yield 'a slot of another kind' => array( 'stickers', 'Stickers', 'Search parts', 'No parts match zzz.' );
	}

	// D-269 and Spec: Search, "When nothing is found". Each step asks, searches and finds nothing in its own words.
	#[DataProvider( 'slots' )]
	public function test_each_step_asks_and_searches_in_its_own_words( string $slot, string $heading, string $search, string $none ): void {
		$view = $this->view(
			array(
				'shelf' => array(
					'looks' => array(),
					'query' => 'zzz',
				),
			),
			$slot
		);
		self::assertSame( $heading, $view['heading'] );
		self::assertSame( $search, $view['step']['shelf']['search']['label'] );
		self::assertSame( $none, $view['step']['found']['text'] );
	}

	// Spec: Search, "When nothing is found". Parts that match and don't fit are said only after a search.
	public function test_parts_that_match_but_do_not_fit_are_said_only_after_a_search(): void {
		$found = $this->step(
			array(
				'shelf' => array(
					'looks'           => array(),
					'hidden_matching' => 3,
				),
			),
			SlotKey::WHEELS
		)['found'];
		self::assertFalse( $found['shown'] );
		self::assertFalse( $found['why']['shown'] );
	}

	// Spec: A step. The deck step asking its width lists nothing and says nothing is missing; a deck step with no
	// shelf that lists nothing says so.
	public function test_a_deck_step_with_no_shelf_and_nothing_to_list_says_so(): void {
		self::assertTrue( $this->step( array(), SlotKey::DECK )['empty'] );
	}

	/**
	 * An option as the answer has it.
	 *
	 * @param int                         $id       The part's ID.
	 * @param string                      $fit      `fits`, `warn` or `block`.
	 * @param list<array<string, string>> $messages What the rules said.
	 * @return array<string, mixed>
	 */
	private static function option( int $id, string $fit, array $messages = array() ): array {
		return array(
			'id'        => $id,
			'card'      => array( 'name' => 'Part ' . $id ),
			'fit'       => $fit,
			'available' => true,
			'messages'  => $messages,
		);
	}

	/**
	 * The wheels step's cards, by ID, checked against the trucks.
	 *
	 * @param list<array<string, mixed>> $options The options.
	 * @return array<int, array<string, mixed>>
	 */
	private function cards( array $options ): array {
		$answer = array(
			'options' => $options,
			'steps'   => self::steps( array( 'wheels' => array( 'against' => array( 'trucks' ) ) ) ),
		);
		return array_column( $this->step( $answer, SlotKey::WHEELS )['options'], null, 'id' );
	}

	// #81: only the picked deck's card, and only against a width asked, says it is picked in another width.
	public function test_only_the_picked_decks_card_says_the_width_asked_is_not_its_own(): void {
		$sized  = static fn( int $id, string $value, string $text ): array => array(
			'part' => array( 'size' => compact( 'value', 'text' ) ) + self::option( $id, 'fits' ),
			'name' => 'Part ' . $id,
		);
		$width  = static fn( string $value, string $text, bool $selected ): array => array(
			'value'    => $value,
			'text'     => $text,
			'in_stock' => 1,
			'selected' => $selected,
		);
		$notes  = function ( array $widths ) use ( $sized ): array {
			$answer = array(
				'total' => array( 'lines' => array( array( 'slot' => 'deck', 'part' => 102 ) ) ),
				'shelf' => array(
					'looks'  => array( $sized( 102, '8.25in', '8.25″' ), $sized( 103, '8.5in', '8.5″' ) ),
					'widths' => $widths,
				),
			);
			$cards  = $this->step( $answer, SlotKey::DECK, new BuildLink( array( SlotKey::DECK => 102 ), null, SlotKey::DECK ) )['options'];
			return array_map( static fn( array $card ): array => array_column( $card['notes'], 'text' ), array_column( $cards, null, 'id' ) );
		};
		$asked  = $notes( array( $width( '8.25in', '8.25″', false ), $width( '8.5in', '8.5″', true ) ) );
		self::assertSame( array( 'Picked in 8.25″. Not made in 8.5″.' ), $asked[102] );
		self::assertSame( array(), $asked[103], 'a card in the width asked that is not picked says nothing' );
		self::assertSame( array( 102 => array(), 103 => array() ), $notes( array( $width( '8.25in', '8.25″', false ), $width( '8.5in', '8.5″', false ) ) ), 'no width asked, nothing to say' );
	}

	// Spec: A step, P9; Cowork's review, minors 5 and 14. A fitting card names what it was checked against by the
	// slot's label inside a sentence.
	public function test_a_fitting_card_names_the_parts_before_it_by_their_labels(): void {
		self::assertSame( 'Fits your truck set', $this->cards( array( self::option( 301, 'fits' ) ) )[301]['fit'] );
	}

	// Spec: A step. A note the owner's to fix is kept quiet on a card that fits, and said on one that warns.
	public function test_a_card_says_its_notes_but_keeps_an_assumed_spec_quiet_where_it_fits(): void {
		$assumed = array(
			'severity' => 'fits',
			'text'     => 'The usual pattern is taken.',
		);
		$warning = array(
			'severity' => 'warn',
			'text'     => 'The bolts are long.',
		);
		$cards   = $this->cards( array( self::option( 302, 'warn', array( $assumed ) ), self::option( 303, 'fits', array( $assumed, $warning ) ) ) );
		self::assertSame( array( 'The usual pattern is taken.' ), array_column( $cards[302]['notes'], 'text' ), 'it may be the reason' );
		self::assertSame( 'Fits, with a note', $cards[302]['fit'], 'a warning with no short label of its own' );
		self::assertSame( array( 'The bolts are long.' ), array_column( $cards[303]['notes'], 'text' ) );
	}

	// Spec: Risers, "Before the pick". A short label is a warning's; a card that does not fit says so.
	public function test_a_card_that_does_not_fit_says_so_whatever_short_label_it_has(): void {
		$message = array(
			'severity' => 'block',
			'text'     => 'Too big.',
			'short'    => 'Needs ⅛″ risers with your trucks',
		);
		self::assertSame( 'Does not fit', $this->cards( array( self::option( 304, 'block', array( $message ) ) ) )[304]['fit'] );
	}

	// D-279 and D-273: the picked card's sizes follow it as picks of the step, a sold-out size said and not offered,
	// and its brand is said first by its own ID.
	public function test_the_picked_cards_sizes_are_radios_of_their_own_and_a_sold_out_one_cannot_be_picked(): void {
		$sized  = static fn( int $part, string $text, bool $stock, bool $picked ): array => array(
			'part'     => $part,
			'text'     => $text,
			'in_stock' => $stock,
			'picked'   => $picked,
		);
		$answer = array(
			'total' => array( 'lines' => array( array( 'slot' => 'wheels', 'part' => 301 ) ) ),
			'shelf' => array(
				'looks' => array(
					array(
						'part'  => self::option( 301, 'fits' ),
						'name'  => 'Retna',
						'brand' => 'Spitfire',
						'sized' => array( $sized( 301, '52 mm', true, true ), $sized( 305, '54 mm', true, false ), $sized( 306, '56 mm', false, false ) ),
					),
				),
			),
		);
		$card   = $this->step( $answer, SlotKey::WHEELS, new BuildLink( array( SlotKey::WHEELS => 301 ), null, SlotKey::WHEELS ) )['options'][0];
		self::assertSame( 'cbuild-77-301-brand', $card['brandId'] );
		$inputs = $card['sizeGroup']['inputs'];
		self::assertSame( array( 'cbuild-77-301-size-301', 'cbuild-77-301-size-305', 'cbuild-77-301-size-306' ), array_column( $inputs, 'id' ) );
		self::assertSame( array( false, false, true ), array_column( $inputs, 'disabled' ) );
		self::assertSame( array( '52 mm', '54 mm', '56 mm, sold out' ), array_column( $inputs, 'label' ) );
	}

	/**
	 * A saved build's changes as the answer says them.
	 *
	 * @param list<array<string, mixed>> $changes What changed.
	 * @return array<string, mixed>
	 */
	private function reopened( array $changes ): array {
		$state = ( new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ) )->of( self::KIT, array(), null, State::REVIEW );
		self::assertNotNull( $state );
		$view = array(
			'isStep'    => true,
			'isReview'  => true,
			'isChanges' => false,
		);
		return ( new BuilderView() )->reopened(
			$view,
			array(
				'changes' => $changes,
				'prices'  => array(),
				'then'    => null,
				'now'     => '$100.00',
				'picks'   => array( SlotKey::DECK => 101 ),
				'first'   => SlotKey::WHEELS,
			),
			$state,
			null
		);
	}

	// Spec: A saved build that has changed (D-254). Each changed part is named with why, one with no name as a part.
	public function test_a_reopened_build_says_why_each_part_changed_and_its_total_now(): void {
		$change = static fn( string $slot, string $name, string $reason, ?array $closest ): array => array(
			'slot'    => $slot,
			'label'   => ucfirst( $slot ),
			'was'     => 1,
			'name'    => $name,
			'reason'  => $reason,
			'closest' => $closest,
		);
		$view   = $this->reopened(
			array(
				$change( 'wheels', '', 'gone', null ),
				$change(
					'trucks',
					'Indy',
					'blocked',
					array(
						'id'   => 139,
						'name' => 'Thunder',
					)
				),
			)
		);
		self::assertSame( array( 'Wheels: a part is no longer in the shop.', 'Trucks: Indy no longer fits.' ), array_column( $view['changes']['items'], 'text' ) );
		self::assertSame( array( false, true, false, true ), array( $view['changes']['hasKept'], $view['changes']['hasItems'], $view['changes']['hasPrices'], $view['changes']['hasTotals'] ) );
		self::assertSame( 'Total now: $100.00', $view['changes']['totals'] );
		self::assertSame( array( false, false, true ), array( $view['isStep'], $view['isReview'], $view['isChanges'] ) );

		self::assertFalse( $this->reopened( array() )['changes']['hasItems'], 'a build changed only in price has no items' );
	}
}
