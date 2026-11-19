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
		$folding = new class() implements TextFolding {
			public function fold( string $text ): string {
				return strtolower( $text );
			}
		};
		$settings = new InMemoryShopSettings();
		$cards    = new InMemoryPartCards();
		$answer   = ( new StateAnswer( $cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ) )
			->withShelf( new StepShelf( $this->looks, $cards, new FakeRanking(), $folding, $settings ) );
		$shown    = ( new Builder( new BuildState( $this->kits, $settings, new FakeLogger() ), $answer, new BuilderView(), $remembered ) )->show( self::KIT, $link, self::BASE );
		self::assertNotNull( $shown );
		$step = $shown['view']['step'];
		self::assertIsArray( $step );
		return $step;
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
				array( 'shoe' => '7 or smaller', 'width' => '7″ to 7.75″' ),
				array( 'shoe' => '7 to 8.5', 'width' => '7.75″ to 8″' ),
				array( 'shoe' => '8.5 to 9', 'width' => '8″ to 8.25″' ),
				array( 'shoe' => '9 and larger', 'width' => '8.25″ and wider' ),
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
		self::assertSame( 'cbuild[_q]', $shelf['search']['name'] );
		self::assertSame( '', $shelf['search']['value'] );

		self::assertTrue( $shelf['chips']['shown'] );
		$brand = $shelf['chips']['groups'][0];
		self::assertSame( 'Brand', $brand['legend'] );
		self::assertSame( array( 'Bones', 'Ricta', 'Spitfire' ), array_column( $brand['inputs'], 'text' ) );
		self::assertSame( 'cbuild[_chips][brand][]', $brand['inputs'][0]['name'] );
		self::assertSame( array( '15', '15', '15' ), array_column( $brand['inputs'], 'count' ), 'what each leaves, its sold-out looks with it' );

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
		self::assertSame( '14 more parts shown, 44 of 44.', $more['more']['added'] );
		self::assertSame( 'wheels', self::tail( $step )['cbuild[_on]'] ?? null );
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

	public function test_either_names_one_two_or_more_as_a_choice(): void {
		self::assertSame( '', Words::either( array() ) );
		self::assertSame( '8″', Words::either( array( '8″' ) ) );
		self::assertSame( '8″ or 9″', Words::either( array( '8″', '9″' ) ) );
		self::assertSame( '7.75″, 8.125″ or 9″', Words::either( array( '7.75″', '8.125″', '9″' ) ) );
	}
}
