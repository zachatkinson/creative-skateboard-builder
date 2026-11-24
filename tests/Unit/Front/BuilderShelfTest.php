<?php
/**
 * The step's shelf in the builder's view.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedPrices;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuilderView;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\Delivery;
use CreativeBranding\SkateboardBuilder\Front\PageRenderer;
use CreativeBranding\SkateboardBuilder\Front\RememberedWidth;
use CreativeBranding\SkateboardBuilder\Front\ShippingWords;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Port\TextFolding;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Service\StepShelf;
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
 * SPEC-CONFIGURATOR, Finding a part: the deck's width first (D-241), tools that grow with the step, 30 at a time
 * (D-244) and the sold-out fold; every control a field of the step's GET form (D-265, D-266).
 */
final class BuilderShelfTest extends TestCase {

	private const int KIT = 77;

	private const string BASE = 'https://shop.test/kit/';

	private InMemoryKits $kits;

	private InMemoryPartLooks $looks;

	protected function setUp(): void {
		$this->kits  = new InMemoryKits();
		$this->looks = new InMemoryPartLooks();
		$this->stock( Shop::parts() );
	}

	/**
	 * The kit with these parts, each a look of its own.
	 *
	 * @param array<string, list<Part>> $parts The parts by slot.
	 */
	private function stock( array $parts ): void {
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		foreach ( $parts as $list ) {
			foreach ( $list as $part ) {
				$this->looks->facts[ $part->id ] = new LookFact( $part->id, $part->id, 0, 'Part ' . $part->id, array(), array(), false, 0, 0 );
			}
		}
	}

	/**
	 * Wheels enough for every tool: 45, of three brands, the last sold out.
	 */
	private function manyWheels(): void {
		$parts = Shop::parts();
		$model = $parts[ SlotKey::WHEELS ][0];
		$list  = array();
		for ( $at = 0; $at < 45; $at++ ) {
			$list[] = new Part( 3000 + $at, array( 'bones', 'spitfire', 'ricta' )[ $at % 3 ], $model->specs, 4000, 44 !== $at );
		}
		$parts[ SlotKey::WHEELS ] = $list;
		$this->stock( $parts );
	}

	/**
	 * The view's step for a link.
	 *
	 * @return array<string, mixed>
	 */
	private function step( BuildLink $link, ?RememberedWidth $remembered = null ): array {
		$view = $this->shown( $link, $remembered )['view']['step'];
		self::assertIsArray( $view );
		return $view;
	}

	/**
	 * What the builder shows for a link.
	 *
	 * @return array{state: mixed, answer: array<string, mixed>, view: array<string, mixed>}
	 */
	private function shown( BuildLink $link, ?RememberedWidth $remembered = null, ?SavedBuilds $saved = null ): array {
		$folding = new class() implements TextFolding {
			public function fold( string $text ): string {
				return strtolower( $text );
			}
		};
		$settings = new InMemoryShopSettings();
		$cards    = new InMemoryPartCards();
		$shelf    = new StepShelf( $this->looks, $cards, new FakeRanking(), $folding, $settings );
		$answer   = ( new StateAnswer( $cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ) )
			->withShelf( $shelf );
		$shown    = ( new Builder( new BuildState( $this->kits, $settings, new FakeLogger() ), $answer, new BuilderView() ) )->withSaved( $remembered, $saved )->withShelf( $shelf )->show( self::KIT, $link, self::BASE );
		self::assertNotNull( $shown );
		return $shown;
	}

	/**
	 * The hidden fields of the step's form, by name.
	 *
	 * @param array<string, mixed> $step The view's step.
	 * @return array<string, string>
	 */
	private static function tail( array $step ): array {
		return array_column( $step['tail'], 'value', 'name' );
	}

	public function test_the_deck_step_asks_the_width_first_with_how_many_decks_are_in_stock_in_each(): void {
		$step = $this->step( new BuildLink() );
		self::assertSame( array(), $step['options'], 'no decks before a width' );
		self::assertFalse( $step['empty'], 'and nothing is said to be missing' );
		$width = $step['shelf']['width'];
		self::assertTrue( $width['shown'] );
		self::assertTrue( $width['asking'] );
		self::assertSame( 'What width do you ride?', $width['legend'] );
		self::assertSame( 'cbuild[_width]', $width['name'] );
		self::assertSame( array( '8in', '8.25in', '8.5in' ), array_column( $width['options'], 'value' ) );
		self::assertSame( array( '8″', '8.25″', '8.5″' ), array_column( $width['options'], 'text' ) );
		self::assertSame( array( '1', '1', '1' ), array_column( $width['options'], 'count' ) );
		self::assertSame( array( 'false', 'false', 'false' ), array_column( $width['options'], 'pressed' ) );
		self::assertFalse( $width['hasNone'] );
		self::assertSame( 'deck', self::tail( $step )['cbuild[_on]'] ?? null, 'the width stays on the step it was asked on' );
		self::assertFalse( $step['shelf']['search']['shown'] );
	}

	// Spec (configurator): The deck's width first, "Not sure of your width?", a guide by shoe size from Skate
	// Warehouse's deck buying guide, in the plugin's own words; `cbuild_width_guide` gives a shop's own rows.
	public function test_the_width_question_has_a_guide_by_shoe_size_that_a_shop_can_replace(): void {
		$guide = $this->step( new BuildLink() )['shelf']['width']['guide'];
		self::assertTrue( $guide['shown'] );
		self::assertSame( 'Not sure of your width?', $guide['summary'] );
		self::assertSame( array( 'Shoe size (US)', 'Deck width' ), array( $guide['shoe'], $guide['width'] ) );
		self::assertSame(
			array(
				// Each shoe size in one row (the auditor's M10 review, 15): US sizes go by halves.
				array( 'shoe' => '6.5 or smaller', 'width' => '7″ to 7.75″' ),
				array( 'shoe' => '7 to 8', 'width' => '7.75″ to 8″' ),
				array( 'shoe' => '8.5 to 9', 'width' => '8″ to 8.25″' ),
				array( 'shoe' => '9.5 and larger', 'width' => '8.25″ and wider' ),
			),
			$guide['rows']
		);
		self::assertTrue( $this->step( BuildLink::read( array( '_width' => '8.25in' ) ) )['shelf']['width']['guide']['shown'], 'still there once a width is picked' );

		$asked                                                = array();
		$GLOBALS['cbuild_test_filters']['cbuild_width_guide'] = static function ( array $rows, int $kit ) use ( &$asked ): array {
			$asked[] = array( count( $rows ), $kit );
			return array(
				array( 'shoe' => 'EU 38 to 41', 'width' => '8″' ),
				array( 'shoe' => '<b>EU 42</b> and up', 'width' => '8.5″' ),
				array( 'shoe' => 'no width' ),
				'not a row',
			);
		};
		try {
			$guide = $this->step( new BuildLink() )['shelf']['width']['guide'];
		} finally {
			unset( $GLOBALS['cbuild_test_filters']['cbuild_width_guide'] );
		}
		self::assertSame( array( array( 4, self::KIT ) ), $asked, 'given the plugin\'s rows and the kit' );
		self::assertSame(
			array(
				array( 'shoe' => 'EU 38 to 41', 'width' => '8″' ),
				array( 'shoe' => 'EU 42 and up', 'width' => '8.5″' ),
			),
			$guide['rows'],
			'plain text, and a row without both is left out'
		);

		$GLOBALS['cbuild_test_filters']['cbuild_width_guide'] = static fn(): array => array();
		try {
			self::assertFalse( $this->step( new BuildLink() )['shelf']['width']['guide']['shown'], 'no rows, no guide' );
		} finally {
			unset( $GLOBALS['cbuild_test_filters']['cbuild_width_guide'] );
		}
	}

	// Spec (configurator): The deck's width first, "A signed-in customer's width comes from their most recent saved
	// build. Nothing is stored on the device (D-242)."
	public function test_a_signed_in_customers_width_comes_from_their_most_recent_saved_build(): void {
		$store = new InMemorySavedBuilds();
		$saved = new SavedBuilds( $store, new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ), new CountingUuids(), new FakeClock(), new FakeLogger() );
		$at    = static fn( string $when ): DateTimeImmutable => new DateTimeImmutable( $when, new DateTimeZone( 'UTC' ) );
		$store->put( new SavedBuild( 'a', 7, self::KIT, array( SlotKey::DECK => 103 ), true, 'Old', $at( '2026-11-01' ), $at( '2026-11-01' ) ) );
		$store->put( new SavedBuild( 'b', 7, self::KIT, array( SlotKey::DECK => 102 ), true, 'New', $at( '2026-11-02' ), $at( '2026-11-10' ) ) );
		$store->put( new SavedBuild( 'c', 8, self::KIT, array( SlotKey::TRUCKS => 139 ), true, 'No deck', $at( '2026-11-02' ), $at( '2026-11-10' ) ) );
		$remembered = new RememberedWidth( $saved, $this->kits );
		try {
			$GLOBALS['cbuild_test_user'] = 7;
			$step                        = $this->step( new BuildLink(), $remembered );
			self::assertSame( array( 102 ), array_column( $step['options'], 'id' ), 'the decks in the width of the build saved last' );
			self::assertSame( array( 'false', 'true', 'false' ), array_column( $step['shelf']['width']['options'], 'pressed' ) );
			self::assertSame( '8.25″, 1 in stock', $step['shelf']['width']['options'][1]['said'] ?? null, 'heard as words (the auditor\'s M10 review, 13)' );
			self::assertSame( '8.25in', self::tail( $step )['cbuild[_width]'] ?? null, 'and it travels with the build' );
			self::assertSame( array( 103 ), array_column( $this->step( BuildLink::read( array( '_width' => '8.5in' ) ), $remembered )['options'], 'id' ), 'a width asked for comes first' );
			self::assertSame( array( 'true', 'false', 'false' ), array_column( $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'deck' ) ), $remembered )['shelf']['width']['options'], 'pressed' ), 'and the deck the build holds' );
			$GLOBALS['cbuild_test_user'] = 8;
			self::assertSame( array(), $this->step( new BuildLink(), $remembered )['options'], 'a saved build with no deck brings no width' );
			$GLOBALS['cbuild_test_user'] = 0;
			self::assertSame( array(), $this->step( new BuildLink(), $remembered )['options'], 'nothing for a visitor signed out' );
		} finally {
			unset( $GLOBALS['cbuild_test_user'] );
		}
	}

	// D-208: only this release's container asks the saved builds; a builder made without them still asks the width.
	public function test_a_builder_given_the_saved_builds_leaves_the_one_it_came_from_as_it_was(): void {
		$store = new InMemorySavedBuilds();
		$state = new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() );
		$saved = new SavedBuilds( $store, $state, new CountingUuids(), new FakeClock(), new FakeLogger() );
		$at    = new DateTimeImmutable( '2026-11-02', new DateTimeZone( 'UTC' ) );
		$store->put( new SavedBuild( 'b', 7, self::KIT, array( SlotKey::DECK => 102 ), true, 'Mine', $at, $at ) );
		$plain = new Builder( $state, new StateAnswer( new InMemoryPartCards(), new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ), new BuilderView() );
		$given = $plain->withSaved( new RememberedWidth( $saved, $this->kits ), $saved );
		try {
			$GLOBALS['cbuild_test_user'] = 7;
			$remembered                  = $given->show( self::KIT, new BuildLink(), self::BASE );
			$shown                       = $plain->show( self::KIT, new BuildLink(), self::BASE );
		} finally {
			unset( $GLOBALS['cbuild_test_user'] );
		}
		self::assertStringContainsString( '_width', $remembered['view']['url'] ?? '', 'the builder given them brings the width' );
		self::assertStringNotContainsString( '_width', $shown['view']['url'] ?? '', 'the one it came from does not' );
	}

	// Spec (configurator): A saved build that has changed (D-254), "The screen": "Your build has changed" before the
	// review, with each change, each new price, the totals, Use these and Choose myself.
	public function test_a_saved_build_that_changed_opens_on_what_changed_before_its_review(): void {
		$store  = new InMemorySavedBuilds();
		$money  = new FakeMoney();
		$saved  = new SavedBuilds( $store, new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ), new CountingUuids(), new FakeClock(), new FakeLogger(), $money );
		$picks  = array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149, SlotKey::WHEELS => 302, SlotKey::BEARINGS => 501, SlotKey::HARDWARE => 601, SlotKey::GRIP => 701 );
		$at     = new DateTimeImmutable( '2026-11-01', new DateTimeZone( 'UTC' ) );
		$prices = new SavedPrices( 'CAD', array( SlotKey::TRUCKS => 5000 ), 30000 );
		$store->put( new SavedBuild( 'a0000000-0000-4000-8000-000000000001', 7, self::KIT, $picks, false, '', $at, $at, $prices ) );
		$parts                    = Shop::parts();
		$parts[ SlotKey::WHEELS ] = array_map( static fn( Part $part ): Part => 302 === $part->id ? Shop::priced( $part, $part->price, false, 0 ) : $part, $parts[ SlotKey::WHEELS ] );
		$this->stock( $parts );
		$link = BuildLink::read( array_map( 'strval', $picks ) + array( 'step' => 'review', '_saved' => 'a0000000-0000-4000-8000-000000000001' ) );
		try {
			$GLOBALS['cbuild_test_user'] = 7;
			$shown                       = $this->shown( $link, null, $saved );
			$view                        = $shown['view'];
			self::assertTrue( $view['isChanges'] );
			// The route's answer to the same link says what changed, for a page or app that draws its own panel.
			$reopened = $shown['answer']['reopened'] ?? array();
			self::assertSame( array( SlotKey::WHEELS ), array_column( $reopened['changes'] ?? array(), 'slot' ) );
			self::assertSame( 'sold_out', $reopened['changes'][0]['reason'] ?? null );
			self::assertSame( 301, $reopened['changes'][0]['closest']['id'] ?? null );
			self::assertSame( SlotKey::WHEELS, $reopened['first'] ?? null );
			self::assertFalse( $view['isReview'] );
			$panel = $view['changes'];
			$use   = array_column( $panel['use']['fields'], 'value', 'name' );
			$self  = array_column( $panel['undo']['fields'], 'value', 'name' );
			self::assertSame( 'Your build has changed', $panel['heading'] );
			self::assertSame( array( SlotKey::WHEELS ), array_column( $panel['items'], 'key' ) );
			self::assertStringEndsWith( 'is sold out.', $panel['items'][0]['text'] );
			self::assertStringStartsWith( 'Closest fit: ', $panel['items'][0]['closest'] );
			self::assertCount( 1, $panel['prices'], 'the trucks cost another amount now' );
			self::assertStringContainsString( 'when you saved', $panel['prices'][0]['text'] );
			self::assertStringStartsWith( 'Total: $300.00 when you saved, ', $panel['totals'] );
			self::assertSame( '301', $use['cbuild[wheels]'] ?? null, 'Use these takes the closest wheels' );
			self::assertSame( 'review', $use['cbuild[step]'] ?? null, 'to the review' );
			self::assertArrayNotHasKey( 'cbuild[wheels]', $self, 'Choose myself leaves the wheels to choose' );
			self::assertSame( 'wheels', $self['cbuild[step]'] ?? null, 'on their step' );
			self::assertArrayNotHasKey( 'cbuild[_saved]', $use, 'checked once' );

			$GLOBALS['cbuild_test_user'] = 8;
			self::assertTrue( $this->shown( $link, null, $saved )['view']['isReview'], 'another customer\'s build opens as the link says' );
		} finally {
			unset( $GLOBALS['cbuild_test_user'] );
		}
	}

	public function test_only_the_deck_step_without_a_width_is_asking_it(): void {
		self::assertTrue( $this->step( BuildLink::read( array() ) )['shelf']['width']['asking'], 'the deck step, no width yet' );
		self::assertFalse( $this->step( BuildLink::read( array( '_width' => '8.25in' ) ) )['shelf']['width']['asking'], 'a width picked' );
		self::assertFalse( $this->step( BuildLink::read( array( 'deck' => '102', 'step' => 'trucks' ) ) )['shelf']['width']['asking'], 'a step with no width question, whose cards show' );
	}

	public function test_a_width_with_nothing_in_stock_is_dimmed_and_named(): void {
		$parts                  = Shop::parts();
		$parts[ SlotKey::DECK ] = array_map( static fn( Part $part ): Part => 102 === $part->id ? Shop::priced( $part, $part->price, false, 0 ) : $part, $parts[ SlotKey::DECK ] );
		$this->stock( $parts );
		$width = $this->step( new BuildLink() )['shelf']['width'];
		self::assertSame( array( false, true, false ), array_column( $width['options'], 'dimmed' ) );
		self::assertTrue( $width['hasNone'] );
		self::assertSame( 'Nothing in 8.25″ is in stock right now.', $width['none'] );
	}

	public function test_a_width_lists_its_decks_and_travels_with_the_build(): void {
		$step = $this->step( BuildLink::read( array( '_width' => '8.25in' ) ) );
		self::assertSame( array( 102 ), array_column( $step['options'], 'id' ) );
		$width = $step['shelf']['width'];
		self::assertFalse( $width['asking'] );
		self::assertSame( 'Width', $width['legend'] );
		self::assertSame( array( 'false', 'true', 'false' ), array_column( $width['options'], 'pressed' ) );
		self::assertSame( '8.25in', self::tail( $step )['cbuild[_width]'] ?? null, 'before the width buttons, so a button pressed comes after it' );
		self::assertSame( '8.25in', self::tail( $this->step( BuildLink::read( array( 'deck' => '102', 'step' => 'trucks', '_width' => '8.25in' ) ) ) )['cbuild[_width]'] ?? null, 'and every later step keeps it (D-242)' );
		self::assertFalse( $this->step( BuildLink::read( array( 'deck' => '102', 'step' => 'trucks', '_width' => '8.25in' ) ) )['shelf']['width']['shown'], 'the question is the deck step\'s' );
	}

	// The card the prototype draws: brand, the product's name and the look's, the full price, the change once the step
	// has a pick, and the deck's other widths (D-249, D-273).
	public function test_a_card_names_the_brand_the_product_and_its_look_with_the_full_price_and_the_other_widths(): void {
		$parts                  = Shop::parts();
		$parts[ SlotKey::DECK ] = array_map( static fn( Part $part ): Part => new Part( $part->id, 'harbour', $part->specs, $part->price, $part->inStock ), $parts[ SlotKey::DECK ] );
		$this->stock( $parts );
		$colour = array( 'pa_colour' => array( 'mint' => 'Mint', 'black' => 'Black' ) );
		foreach ( array( 101, 102, 103 ) as $id ) {
			$this->looks->facts[ $id ] = new LookFact( 10, 1, 0, 'Katie', array( 'pa_colour' => array( 'mint' ) ), $colour, false, 0, 0 );
		}
		$step = $this->step( BuildLink::read( array( '_width' => '8.25in' ) ) );
		self::assertCount( 1, $step['options'] );
		$card = $step['options'][0];
		self::assertSame(
			array( 'Harbour', true, 'Katie', '', false, '$102', '', false, 'Also in 8″ and 8.5″', true, 'Choose Katie', 'cbuild-77-102-brand cbuild-77-102-about cbuild-77-102-notes' ),
			array( $card['brand'], $card['hasBrand'], $card['name'], $card['look'], $card['hasLook'], $card['price'], $card['delta'], $card['hasDelta'], $card['also'], $card['hasAlso'], $card['choose'], $card['describedby'] ),
			'one look of one product needs no look name; no change before a pick'
		);

		$this->looks->facts[103] = new LookFact( 10, 2, 0, 'Katie', array( 'pa_colour' => array( 'black' ) ), $colour, false, 0, 0 );
		$picked = $this->step( BuildLink::read( array( 'deck' => '102', 'step' => 'deck', '_width' => '8.25in' ) ) )['options'][0];
		self::assertSame(
			array( 'Mint', true, 'Also in 8″', 'Included', true, 'Choose Katie, Mint' ),
			array( $picked['look'], $picked['hasLook'], $picked['also'], $picked['delta'], $picked['hasDelta'], $picked['choose'] ),
			'the black deck has its own photo, so its own card, and the mint one is named for what tells them apart'
		);
	}

	// Cowork's 1.0.0 review, F14 (#99): the compact deck card says "Also in…" or "Only in…". A look made in one width
	// says that width; a part of another step, or a deck listed with no look, says neither.
	public function test_a_deck_made_in_one_width_says_it_is_only_in_that_width(): void {
		$card = $this->step( BuildLink::read( array( '_width' => '8.25in' ) ) )['options'][0];
		self::assertSame( array( 'Only in 8.25″', true ), array( $card['also'], $card['hasAlso'] ) );

		$wheels = $this->step( BuildLink::read( array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels' ) ) )['options'];
		self::assertSame( array( false ), array_values( array_unique( array_column( $wheels, 'hasAlso' ) ) ), 'the deck step\'s alone' );
	}

	public function test_a_short_step_has_the_cards_alone(): void {
		$shelf = $this->step( BuildLink::read( array( '_width' => '8.25in' ) ) )['shelf'];
		self::assertFalse( $shelf['search']['shown'] );
		self::assertFalse( $shelf['chips']['shown'] );
		self::assertFalse( $shelf['sort']['shown'] );
	}

	public function test_a_long_step_has_search_chips_and_sort_as_fields_of_its_form(): void {
		$this->manyWheels();
		$step  = $this->step( BuildLink::read( array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels' ) ) );
		$shelf = $step['shelf'];

		self::assertTrue( $shelf['search']['shown'] );
		self::assertSame( 'Search wheels', $shelf['search']['label'] );
		// Spec (configurator): "a hint of the step's own words ("Brand or graphic, like Rascals")": the step's first
		// brand (the spec reviewer's M10 minor M2).
		self::assertSame( 'Brand or name, like Bones', $shelf['search']['hint'] );
		self::assertSame( 'cbuild[_q]', $shelf['search']['name'] );
		self::assertSame( '', $shelf['search']['value'] );

		self::assertTrue( $shelf['chips']['shown'] );
		$brand = $shelf['chips']['groups'][0];
		self::assertSame( 'Brand', $brand['legend'] );
		self::assertSame( array( 'Bones', 'Ricta', 'Spitfire' ), array_column( $brand['inputs'], 'text' ) );
		self::assertSame( 'cbuild[_chips][brand][]', $brand['inputs'][0]['name'] );
		self::assertSame( array( '15', '15', '15' ), array_column( $brand['inputs'], 'count' ), 'what each leaves, its sold-out looks with it' );
		// Heard as words, not "Bones 15" (the auditor's M10 review, 13).
		self::assertSame( 'Bones, 15', $brand['inputs'][0]['said'] );

		self::assertTrue( $shelf['sort']['shown'] );
		self::assertSame( 'cbuild[_sort]', $shelf['sort']['name'] );
		self::assertSame( array( 'Recommended', 'Price low to high', 'Price high to low', 'Newest' ), array_column( $shelf['sort']['options'], 'text' ) );
		self::assertSame( array( true, false, false, false ), array_column( $shelf['sort']['options'], 'selected' ) );

		self::assertCount( 30, $step['options'] );
		self::assertSame( 'Showing 30 of 44', $step['more']['count'] );
		self::assertTrue( $step['more']['shown'] );
		self::assertSame( array( 'cbuild[_shown]', '60', 'Show 14 more' ), array( $step['more']['name'], $step['more']['value'], $step['more']['text'] ) );
		self::assertSame( '', $step['more']['added'], 'the first page added nothing' );
		// Spec (configurator): Accessibility, "Show 30 more" says how many were added.
		$more = $this->step( BuildLink::read( array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels', '_on' => 'wheels', '_shown' => '60' ) ) );
		self::assertSame( '14 more shown, 44 of 44.', $more['more']['added'] );
		self::assertSame( 'wheels', self::tail( $step )['cbuild[_on]'] ?? null );
	}

	// Spec (configurator): One card per look, "Where nothing decides it (a wheel's diameter …), the shopper chooses after
	// picking the card. … The picked card is followed by a radio group of its own, 'Size for Retna Cruiser, Mint', with
	// the size … already chosen." Its radios are the step's own picks (D-279).
	public function test_the_picked_card_is_followed_by_its_sizes_as_picks_of_the_step(): void {
		$this->looks->facts[301] = new LookFact( 10, 1, 0, 'Retna Cruiser', array(), array(), false, 0, 0 );
		$this->looks->facts[302] = new LookFact( 10, 1, 0, 'Retna Cruiser', array(), array(), false, 0, 0 );
		$at      = array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels' );
		$unpicked = $this->step( BuildLink::read( $at ) );
		self::assertSame( array( false, false ), array_column( $unpicked['options'], 'hasSizes' ), 'no group before a pick' );

		$step   = $this->step( BuildLink::read( $at + array( 'wheels' => '302' ) ) );
		$picked = array_values( array_filter( $step['options'], static fn( array $card ): bool => (bool) $card['checked'] ) )[0];
		self::assertTrue( $picked['hasSizes'] );
		self::assertSame( 'Size for Retna Cruiser', $picked['sizeGroup']['legend'] );
		self::assertSame( array( "52\u{a0}mm", "54\u{a0}mm" ), array_column( $picked['sizeGroup']['inputs'], 'text' ) );
		self::assertSame( array( '301', '302' ), array_column( $picked['sizeGroup']['inputs'], 'value' ), 'each size is the part of it' );
		self::assertSame( array( false, true ), array_column( $picked['sizeGroup']['inputs'], 'checked' ), 'the size the build holds' );
		self::assertSame( 'cbuild[wheels]', $picked['sizeGroup']['inputs'][0]['name'], 'a pick of the step, as a card is' );
		self::assertSame( '', $picked['field'], 'the picked card\'s own radio stands aside, so one radio of the step is checked' );
		$other = array_values( array_filter( $step['options'], static fn( array $card ): bool => ! $card['checked'] ) )[0];
		self::assertFalse( $other['hasSizes'] );
		self::assertSame( 'cbuild[wheels]', $other['field'] );

		$deck = $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'deck' ) ) );
		self::assertSame( array( false ), array_unique( array_column( $deck['options'], 'hasSizes' ) ), 'the build decides a deck\'s width' );
	}

	// Spec (configurator): Phone, "The filters sit behind a Filters button, so the cards come first." The button says
	// how many chips are chosen, so the shopper knows the cards are narrowed while the chips are folded away.
	public function test_the_filters_button_says_how_many_chips_are_chosen(): void {
		$this->manyWheels();
		$at = array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels', '_on' => 'wheels' );
		self::assertSame( 'Filters', $this->step( BuildLink::read( $at ) )['shelf']['chips']['toggle'] );
		self::assertSame( 'Filters, 2 chosen', $this->step( BuildLink::read( $at + array( '_chips' => array( 'brand' => array( 'bones', 'ricta' ) ) ) ) )['shelf']['chips']['toggle'] );
	}

	/**
	 * #98 (F13): on a desktop the brand chips sit in one row, and "N more" opens the rest in place. How many fit is the
	 * browser's to measure, so the view gives the button's words for every count it may need, each through `_n()`,
	 * and the id of the chips it opens. Only the brand group has it.
	 */
	public function test_the_brand_chips_have_n_more_for_each_count_the_row_may_leave_out(): void {
		$this->manyWheels();
		$groups = $this->step( BuildLink::read( array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels' ) ) )['shelf']['chips']['groups'];
		self::assertSame( 'brand', $groups[0]['key'] );
		self::assertSame(
			array(
				'shown' => true,
				'id'    => 'cbuild-chips-wheels-brand',
				'texts' => array( '1 more', '2 more', '3 more' ),
				'fewer' => 'Show fewer',
			),
			$groups[0]['more']
		);
		foreach ( array_slice( $groups, 1 ) as $group ) {
			self::assertFalse( $group['more']['shown'], $group['key'] . ' wraps as it did' );
			self::assertSame( array(), $group['more']['texts'] );
		}
	}

	public function test_what_the_shopper_chose_is_said_back(): void {
		$this->manyWheels();
		$searched = $this->step( BuildLink::read( array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels', '_on' => 'wheels', '_q' => 'bones' ) ) );
		self::assertSame( 'bones', $searched['shelf']['search']['value'] );
		self::assertFalse( $searched['shelf']['chips']['shown'], 'one brand left is no choice: its group goes' );
		self::assertSame( 'Showing 15 of 15', $searched['more']['count'] );
		$step  = $this->step( BuildLink::read( array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels', '_on' => 'wheels', '_sort' => 'price-desc', '_chips' => array( 'brand' => array( 'bones' ) ), '_shown' => '60' ) ) );
		$shelf = $step['shelf'];
		self::assertSame( array( true, false, false ), array_column( $shelf['chips']['groups'][0]['inputs'], 'checked' ) );
		self::assertSame( array( false, false, true, false ), array_column( $shelf['sort']['options'], 'selected' ) );
		self::assertCount( 15, $step['options'] );
		self::assertSame( 'Showing 15 of 15', $step['more']['count'] );
		self::assertFalse( $step['more']['shown'] );
	}

	/**
	 * #83 (Cowork's 1.0.0 F4): a search or a chip that empties the list gives that reason alone, not the fit rule's; a
	 * list the rules empty keeps the fit sentence.
	 */
	public function test_a_search_that_empties_the_list_says_so_alone_and_the_fit_sentence_is_for_a_list_the_rules_empty(): void {
		$deck = $this->step( BuildLink::read( array( 'step' => 'deck', '_on' => 'deck', '_width' => '8.25in', '_q' => 'zzzz' ) ) );
		self::assertFalse( $deck['empty'], 'no fit sentence' );
		self::assertSame( 'No 8.25″ decks match zzzz.', $deck['found']['text'], 'in the width asked' );
		self::assertTrue( $deck['found']['clear']['shown'] );

		$trucks = $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'trucks', '_on' => 'trucks', '_q' => 'zzz' ) ) );
		self::assertFalse( $trucks['empty'] );
		self::assertSame( 'No trucks match zzz.', $trucks['found']['text'] );

		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array();
		$this->stock( $parts );
		$ruled = $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'trucks', '_on' => 'trucks' ) ) );
		self::assertTrue( $ruled['empty'] );
		self::assertSame( 'Nothing the shop has fits the parts before this step.', $ruled['emptyText'] );
	}

	// Spec (configurator): Search, "When nothing is found, the step says why and offers the way on".
	public function test_a_search_that_lists_nothing_says_why_and_offers_the_way_on(): void {
		$elsewhere = $this->step( BuildLink::read( array( 'step' => 'deck', '_on' => 'deck', '_width' => '8.25in', '_q' => 'Part 101' ) ) )['found'];
		self::assertTrue( $elsewhere['shown'] );
		self::assertSame( 'No 8.25″ decks match Part 101. Part 101 comes in 8″.', $elsewhere['text'] );
		self::assertSame( array( array( 'value' => '8in', 'text' => '8″', 'label' => 'Show 8″ decks' ) ), $elsewhere['widths'], 'a button to that width' );
		self::assertFalse( $elsewhere['clear']['shown'] );

		$hidden = $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'trucks', '_on' => 'trucks', '_q' => 'Part 169' ) ) )['found'];
		self::assertSame( '1 part matches, but it doesn’t fit your build.', $hidden['text'] );
		self::assertTrue( $hidden['why']['shown'], 'with a link to the hidden parts' );
		self::assertFalse( $hidden['clear']['shown'] );

		$none = $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'trucks', '_on' => 'trucks', '_q' => 'zzz' ) ) )['found'];
		self::assertSame( 'No trucks match zzz.', $none['text'] );
		self::assertSame( array( true, false, 'cbuild[_q]' ), array( $none['clear']['shown'], $none['why']['shown'], $none['clear']['name'] ), 'with Clear search' );

		self::assertFalse( $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'trucks', '_on' => 'trucks', '_q' => 'Part 139' ) ) )['found']['shown'], 'nothing to say where it lists a part' );
		self::assertFalse( $this->step( BuildLink::read( array( 'deck' => '101', 'step' => 'trucks', '_on' => 'trucks' ) ) )['found']['shown'], 'or without a search' );
	}

	public function test_sold_out_looks_fold_into_one_line_at_the_end(): void {
		$this->manyWheels();
		$step = $this->step( BuildLink::read( array( 'deck' => '101', 'trucks' => '139', 'step' => 'wheels', '_shown' => '60' ) ) );
		self::assertNotContains( 3044, array_column( $step['options'], 'id' ) );
		$fold = $step['shelf']['soldOut'];
		self::assertTrue( $fold['shown'] );
		self::assertSame( 'Show 1 sold out', $fold['summary'] );
		self::assertSame( array( 3044 ), array_column( $fold['options'], 'id' ) );
		self::assertTrue( $fold['options'][0]['soldOut'] );
		self::assertTrue( $fold['options'][0]['disabled'] );
	}

	public function test_a_new_width_moves_the_picked_deck_to_its_look_in_that_width_and_says_so(): void {
		// Decks 101 (8.0) and 103 (8.5) are one look, Hard Hat (#81).
		foreach ( array( 101, 103 ) as $part ) {
			$this->looks->facts[ $part ] = new LookFact( 900, 5, 0, 'Hard Hat', array(), array(), false, 0, 0 );
		}
		$shown = $this->shown(
			BuildLink::read(
				array(
					'deck'   => '101',
					'step'   => 'deck',
					'_width' => '8.5in',
				)
			)
		);
		self::assertStringContainsString( rawurlencode( 'cbuild[deck]' ) . '=103', Cast::str( $shown['view']['url'] ), 'the build holds Hard Hat at 8.5″' );
		self::assertStringStartsWith( 'Hard Hat is now 8.5″.', Cast::str( $shown['view']['status'] ) );
		$ticked = array_values( array_filter( $shown['view']['step']['options'], static fn( array $card ): bool => (bool) $card['checked'] ) );
		self::assertCount( 1, $ticked );
		self::assertSame( 103, $ticked[0]['id'], 'its card ticked at the new width' );
	}

	// The interim audit of milestone 11: a look with no name said " is now 8.5″." It moves all the same, and says nothing.
	public function test_a_new_width_moves_a_deck_with_no_name_and_says_nothing_of_it(): void {
		foreach ( array( 101, 103 ) as $part ) {
			$this->looks->facts[ $part ] = new LookFact( 900, 5, 0, '', array(), array(), false, 0, 0 );
		}
		$shown = $this->shown(
			BuildLink::read(
				array(
					'deck'   => '101',
					'step'   => 'deck',
					'_width' => '8.5in',
				)
			)
		);
		self::assertStringContainsString( rawurlencode( 'cbuild[deck]' ) . '=103', Cast::str( $shown['view']['url'] ), 'the build holds the deck at 8.5″' );
		self::assertStringNotContainsString( 'is now', Cast::str( $shown['view']['status'] ) );
	}

	public function test_a_new_width_the_picked_decks_look_is_not_made_in_keeps_the_pick_and_its_card_says_which_width_it_is(): void {
		$shown = $this->shown(
			BuildLink::read(
				array(
					'deck'   => '102',
					'step'   => 'deck',
					'_width' => '8.5in',
				)
			)
		);
		self::assertStringContainsString( rawurlencode( 'cbuild[deck]' ) . '=102', Cast::str( $shown['view']['url'] ), 'the 8.25″ deck is kept' );
		$ticked = array_values( array_filter( $shown['view']['step']['options'], static fn( array $card ): bool => (bool) $card['checked'] ) );
		self::assertCount( 1, $ticked );
		self::assertSame( 102, $ticked[0]['id'] );
		self::assertContains( 'Picked in 8.25″. Not made in 8.5″.', array_column( $ticked[0]['notes'], 'text' ) );
	}

	public function test_either_names_one_two_or_more_as_a_choice(): void {
		self::assertSame( '', Words::either( array() ) );
		self::assertSame( '8″', Words::either( array( '8″' ) ) );
		self::assertSame( '8″ or 9″', Words::either( array( '8″', '9″' ) ) );
		self::assertSame( '7.75″, 8.125″ or 9″', Words::either( array( '7.75″', '8.125″', '9″' ) ) );
	}
}
