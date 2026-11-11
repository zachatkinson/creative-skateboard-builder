<?php
/**
 * The state route's answer.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeMoney;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCards;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Spec, How it fits: the options with their fit, how many were hidden and why, any change list and the total. Spec,
 * Performance: a slot shows 24 options at a time, and card data is loaded only for the options a step shows.
 */
final class StateAnswerTest extends TestCase {

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

	/**
	 * Picks as the answer lists them.
	 *
	 * @param array<string, int> $picks Slot key to part ID.
	 * @return list<array{slot: string, part: int}>
	 */
	private static function listed( array $picks ): array {
		$listed = array();
		foreach ( $picks as $slot => $part ) {
			$listed[] = compact( 'slot', 'part' );
		}
		return $listed;
	}

	private InMemoryKits $kits;

	private InMemoryShopSettings $settings;

	private FakeLogger $logger;

	private InMemoryPartCards $cards;

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'] );
	}

	protected function setUp(): void {
		$this->kits     = new InMemoryKits();
		$this->settings = new InMemoryShopSettings();
		$this->logger   = new FakeLogger();
		$this->cards    = new InMemoryPartCards();
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
	}

	/**
	 * The answer for a build of the usual kit.
	 *
	 * @param array<mixed>      $picks    The picks.
	 * @param array<mixed>|null $previous The picks before.
	 * @return array<string, mixed>
	 */
	private function answer( array $picks = array(), ?string $step = null, ?array $previous = null, ?bool $assembly = null ): array {
		$state = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, $picks, $previous, $step, $assembly );
		self::assertNotNull( $state );
		return ( new StateAnswer( $this->cards, new FakeMoney(), $this->kits ) )->of( $state );
	}

	/**
	 * A list's IDs.
	 *
	 * @param mixed $options Options, as the answer lists them.
	 * @return list<int>
	 */
	private static function ids( mixed $options ): array {
		self::assertIsArray( $options );
		return array_values( array_column( $options, 'id' ) );
	}

	/**
	 * A step of the answer, by its slot.
	 *
	 * @param array<string, mixed> $answer The answer.
	 * @return array<string, mixed>
	 */
	private static function step( array $answer, string $slot ): array {
		foreach ( $answer['steps'] as $step ) {
			if ( $slot === $step['slot'] ) {
				return $step;
			}
		}
		self::fail( "The answer has no step {$slot}." );
	}

	private static function money( int $amount, string $text ): array {
		return compact( 'amount', 'text' );
	}

	public function test_with_nothing_picked_the_first_step_lists_its_parts_each_with_its_card(): void {
		$answer = $this->answer();

		self::assertSame( array( 'kit', 'step', 'ready', 'texts', 'presets', 'steps', 'options', 'hidden', 'refused', 'problems', 'changes', 'total' ), array_keys( $answer ) );
		self::assertSame( self::KIT, $answer['kit'] );
		self::assertSame( SlotKey::DECK, $answer['step'] );
		self::assertFalse( $answer['ready'] );
		self::assertSame( array( 101, 102, 103 ), self::ids( $answer['options'] ) );
		self::assertSame( array(), $answer['hidden'] );
		self::assertSame(
			array(
				'id'          => 101,
				'brand'       => Builds::deck( '8.0', 101 )->brand,
				'fit'         => 'fits',
				'in_stock'    => true,
				'available'   => true,
				'quantity'    => 1,
				'picked'      => false,
				'recommended' => false,
				'messages'    => array(),
				'card'        => InMemoryPartCards::card( 101 ),
			),
			$answer['options'][0]
		);
		self::assertSame(
			array(
				'slot'        => SlotKey::DECK,
				'label'       => 'Deck',
				'help'        => '',
				'required'    => true,
				'units'       => 1,
				'picked'      => null,
				'recommended' => null,
				'offered'     => 3,
				'hidden'      => 0,
				'groups'      => array(),
			),
			$answer['steps'][0]
		);
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), array_column( $answer['steps'], 'slot' ) );
		self::assertNull( $answer['changes'] );
		self::assertSame( array(), $answer['refused'] );
		self::assertSame( 'slot_empty', $answer['problems'][0]['code'] );
		self::assertSame( 'Deck: nothing is chosen yet.', $answer['problems'][0]['message'] );
		self::assertNull( $answer['problems'][0]['part'] );
		self::assertSame(
			array(
				'lines'    => array(),
				'parts'    => self::money( 0, '$0.00' ),
				'saved'    => self::money( 0, '$0.00' ),
				'assembly' => false,
				'fee'      => self::money( 0, '$0.00' ),
				'total'    => self::money( 0, '$0.00' ),
			),
			$answer['total']
		);
		self::assertSame( array( array( 101, 102, 103 ) ), $this->cards->asked, 'One call, for the step on show and for no other.' );
	}

	/**
	 * A kit with words of the owner's own: help on the trucks, two presets, a lead time and a returns notice.
	 */
	private function worded(): void {
		$slots                     = KitDocuments::slots();
		$slots['slots'][1]['help'] = 'Sold in pairs.';
		$this->kits->add(
			self::KIT,
			KitDocuments::kit(
				$slots,
				null,
				array(
					'schema_version' => 1,
					'presets'        => array(
						array(
							'name'        => 'Street',
							'description' => 'For ledges and stairs.',
							'picks'       => self::STREET,
						),
						array(
							'name'  => 'Park',
							'picks' => self::PARK,
						),
					),
				),
				array(
					'schema_version' => 1,
					'default_on'     => false,
					'fee'            => '15',
					'lead_time'      => 'Two working days.',
					'returns_notice' => 'An assembled board goes back whole.',
				)
			),
			Shop::parts()
		);
	}

	public function test_the_owners_own_words_are_in_the_answer_as_typed(): void {
		$bare = $this->answer();
		self::assertSame(
			array(
				'lead_time'      => '',
				'returns_notice' => '',
			),
			$bare['texts']
		);
		self::assertSame( array(), $bare['presets'] );

		$this->worded();
		$answer = $this->answer();
		self::assertSame(
			array(
				'lead_time'      => 'Two working days.',
				'returns_notice' => 'An assembled board goes back whole.',
			),
			$answer['texts']
		);
		self::assertSame(
			array(
				array(
					'name'        => 'Street',
					'description' => 'For ledges and stairs.',
					'picks'       => self::listed( self::STREET ),
				),
				array(
					'name'        => 'Park',
					'description' => '',
					'picks'       => self::listed( self::PARK ),
				),
			),
			$answer['presets']
		);
		self::assertSame( 'Sold in pairs.', self::step( $answer, SlotKey::TRUCKS )['help'] );
	}

	public function test_a_plugins_answer_to_each_filter_is_what_the_answer_says(): void {
		$this->worded();
		$asked                           = array();
		$GLOBALS['cbuild_test_filters'] = array(
			'cbuild_slot_label'   => static function ( string $label, string $slot, int $kit ) use ( &$asked ): string {
				$asked[] = array( 'cbuild_slot_label', $label, $slot, $kit );
				return "[fr] {$label}";
			},
			'cbuild_preset_label' => static function ( string $name, int $position, int $kit ) use ( &$asked ): string {
				$asked[] = array( 'cbuild_preset_label', $name, $position, $kit );
				return "[fr] {$name}";
			},
			'cbuild_kit_text'     => static function ( string $text, string $field, int $kit, string $of ) use ( &$asked ): string {
				$asked[] = array( 'cbuild_kit_text', $text, $field, $kit, $of );
				return "[fr] {$field} {$of}";
			},
		);
		$answer = $this->answer(
			array(
				SlotKey::DECK   => 103,
				SlotKey::TRUCKS => 139,
				'sail'          => 5,
			),
			null,
			array(
				SlotKey::DECK   => 101,
				SlotKey::TRUCKS => 139,
			)
		);

		self::assertSame( array( '[fr] Deck', '[fr] Trucks', '[fr] Wheels', '[fr] Risers', '[fr] Bearings', '[fr] Hardware', '[fr] Grip' ), array_column( $answer['steps'], 'label' ) );
		self::assertSame( '[fr] slot_help trucks', self::step( $answer, SlotKey::TRUCKS )['help'] );
		self::assertSame( '[fr] slot_help deck', self::step( $answer, SlotKey::DECK )['help'] );
		self::assertSame( array( '[fr] Street', '[fr] Park' ), array_column( $answer['presets'], 'name' ) );
		self::assertSame( array( '[fr] preset_description 0', '[fr] preset_description 1' ), array_column( $answer['presets'], 'description' ) );
		self::assertSame(
			array(
				'lead_time'      => '[fr] lead_time ',
				'returns_notice' => '[fr] returns_notice ',
			),
			$answer['texts']
		);
		// The label a plugin gave is the one every sentence and the change list name the slot by.
		self::assertSame( '[fr] Trucks', $answer['changes']['changes'][0]['label'] );
		self::assertContains( '[fr] Trucks: the part chosen does not fit the rest of the build.', array_column( $answer['problems'], 'message' ) );
		self::assertSame( 'This kit has no slot called "sail".', $answer['refused'][0]['message'] );

		self::assertContains( array( 'cbuild_slot_label', 'Trucks', SlotKey::TRUCKS, self::KIT ), $asked );
		self::assertContains( array( 'cbuild_preset_label', 'Park', 1, self::KIT ), $asked );
		self::assertContains( array( 'cbuild_kit_text', 'Sold in pairs.', 'slot_help', self::KIT, SlotKey::TRUCKS ), $asked );
		self::assertContains( array( 'cbuild_kit_text', 'For ledges and stairs.', 'preset_description', self::KIT, '0' ), $asked );
		self::assertContains( array( 'cbuild_kit_text', 'Two working days.', 'lead_time', self::KIT, '' ), $asked );
		self::assertContains( array( 'cbuild_kit_text', 'An assembled board goes back whole.', 'returns_notice', self::KIT, '' ), $asked );
		self::assertCount( 7, array_filter( $asked, static fn( array $call ): bool => 'cbuild_slot_label' === $call[0] ), 'Each label is asked for once.' );
	}

	public function test_an_answer_to_a_filter_that_is_no_text_leaves_the_owners_words(): void {
		$this->worded();
		$GLOBALS['cbuild_test_filters'] = array(
			'cbuild_slot_label'   => static fn(): ?string => null,
			'cbuild_preset_label' => static fn(): array => array( 'Street' ),
			'cbuild_kit_text'     => static fn(): bool => false,
		);
		$answer                          = $this->answer();

		self::assertSame( 'Trucks', self::step( $answer, SlotKey::TRUCKS )['label'] );
		self::assertSame( 'Sold in pairs.', self::step( $answer, SlotKey::TRUCKS )['help'] );
		self::assertSame( 'Street', $answer['presets'][0]['name'] );
		self::assertSame( 'For ledges and stairs.', $answer['presets'][0]['description'] );
		self::assertSame( 'Two working days.', $answer['texts']['lead_time'] );
		self::assertSame( 'An assembled board goes back whole.', $answer['texts']['returns_notice'] );
	}

	public function test_a_later_step_lists_what_fits_hides_the_rest_with_why_and_marks_the_part_it_starts_on(): void {
		$answer = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );

		self::assertSame( SlotKey::TRUCKS, $answer['step'] );
		self::assertTrue( $answer['ready'] );
		self::assertSame( array( 149 ), self::ids( $answer['options'] ) );
		self::assertTrue( $answer['options'][0]['recommended'] );
		self::assertFalse( $answer['options'][0]['picked'] );
		self::assertSame( array( 139, 144, 169 ), self::ids( $answer['hidden'] ) );
		self::assertSame( 'block', $answer['hidden'][0]['fit'] );
		self::assertSame(
			array(
				array(
					'rule'     => 'r1',
					'code'     => 'deck_above_range',
					'severity' => 'block',
					'text'     => 'The trucks are made for decks from 7.8 in to 8.2 in wide. The deck is 8.5 in.',
				),
			),
			$answer['hidden'][0]['messages']
		);
		self::assertSame( InMemoryPartCards::card( 139 ), $answer['hidden'][0]['card'] );

		$trucks = self::step( $answer, SlotKey::TRUCKS );
		self::assertNull( $trucks['picked'] );
		self::assertSame( 149, $trucks['recommended'] );
		self::assertSame( 1, $trucks['offered'] );
		self::assertSame( 3, $trucks['hidden'] );
		self::assertSame(
			array(
				array(
					'rule'    => 'r1',
					'code'    => 'deck_above_range',
					'count'   => 2,
					'message' => '2 parts hidden: the trucks are made for another width of deck.',
				),
				array(
					'rule'    => 'r1',
					'code'    => 'deck_below_range',
					'count'   => 1,
					'message' => '1 part hidden: the trucks are made for another width of deck.',
				),
			),
			$trucks['groups']
		);
		self::assertSame( 103, self::step( $answer, SlotKey::DECK )['picked'] );
		// A step that is not on show still says what it counts, and lists nothing.
		self::assertSame( 302, self::step( $answer, SlotKey::WHEELS )['recommended'] );
		self::assertSame( 3, self::step( $answer, SlotKey::WHEELS )['offered'] );

		// The total counts the pick and every part a later step starts on, each named from its card.
		self::assertSame( array( 103, 149, 302, 501, 601, 701 ), array_column( $answer['total']['lines'], 'part' ) );
		self::assertSame(
			array(
				'slot'     => SlotKey::TRUCKS,
				'part'     => 149,
				'name'     => 'Part 149',
				'quantity' => 1,
				'price'    => self::money( 5400, '$54.00' ),
				'charged'  => self::money( 5400, '$54.00' ),
				'total'    => self::money( 5400, '$54.00' ),
			),
			$answer['total']['lines'][1]
		);
		self::assertSame( self::money( 18300, '$183.00' ), $answer['total']['parts'] );
		self::assertSame( self::money( 18300, '$183.00' ), $answer['total']['total'] );
		self::assertSame( array( array( 149, 139, 144, 169, 103, 302, 501, 601, 701 ) ), $this->cards->asked, 'One call: the options on show, then the parts the total counts, each once.' );
	}

	public function test_the_pick_is_marked_and_a_pick_the_build_blocks_is_among_the_hidden_with_its_reason(): void {
		$picked = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::DECK );
		self::assertSame( array( false, false, true ), array_column( $picked['options'], 'picked' ) );

		$blocked = $this->answer(
			array(
				SlotKey::DECK   => 103,
				SlotKey::TRUCKS => 139,
			),
			SlotKey::TRUCKS
		);
		self::assertFalse( $blocked['ready'] );
		self::assertSame( array( true, false, false ), array_column( $blocked['hidden'], 'picked' ) );
		self::assertSame( array( false ), array_column( $blocked['options'], 'recommended' ), 'A step with a pick starts on nothing else.' );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS ), array_column( $blocked['problems'], 'slot' ), 'Both parts of the pair that does not fit.' );
		$problem = $blocked['problems'][1];
		self::assertSame( 'part_blocked', $problem['code'] );
		self::assertSame( 139, $problem['part'] );
		self::assertSame( 'Trucks: the part chosen does not fit the rest of the build.', $problem['message'] );
		self::assertSame( 'deck_above_range', $problem['messages'][0]['code'] );
	}

	public function test_an_option_carries_every_finding_that_reads_it_notes_included(): void {
		// Trucks that give no largest wheel: the rule takes the usual one for their height, and says so.
		$specs                    = array(
			'baseplate'     => 'new_school',
			'axle_diameter' => '8',
			'set_size'      => 2,
		);
		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array( Shop::priced( Parts::indy( '139', $specs ), 5000 ), Shop::priced( Parts::indy( '149', $specs ), 5400 ) );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$answer = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );

		self::assertSame( array( 'limit_from_class' ), array_column( $answer['options'][0]['messages'], 'code' ) );
		self::assertSame( 'fits', $answer['options'][0]['fit'], 'A note is no warning.' );
		self::assertEqualsCanonicalizing( array( 'deck_above_range', 'limit_from_class' ), array_column( $answer['hidden'][0]['messages'], 'code' ) );
		self::assertEqualsCanonicalizing( array( 'block', 'fits' ), array_column( $answer['hidden'][0]['messages'], 'severity' ) );
	}

	public function test_a_pick_not_taken_is_named_by_its_slots_label_or_by_its_key_where_the_kit_has_no_such_slot(): void {
		$answer = $this->answer(
			array(
				SlotKey::DECK => 139,
				'sail'        => 5,
			)
		);
		self::assertSame(
			array(
				array(
					'code'     => 'part_not_in_slot',
					'slot'     => SlotKey::DECK,
					'part'     => 139,
					'message'  => 'Deck: the part chosen is not one this kit offers here.',
					'messages' => array(),
				),
				array(
					'code'     => 'slot_unknown',
					'slot'     => 'sail',
					'part'     => 5,
					'message'  => 'This kit has no slot called "sail".',
					'messages' => array(),
				),
			),
			$answer['refused']
		);
		self::assertSame( array( array( 101, 102, 103 ) ), $this->cards->asked, 'A part that was refused has no card asked for.' );
	}

	public function test_a_change_that_breaks_a_later_pick_comes_with_what_is_kept_what_goes_and_the_closest_fit(): void {
		$now    = array(
			SlotKey::DECK   => 103,
			SlotKey::TRUCKS => 139,
			SlotKey::WHEELS => 301,
		);
		$answer = $this->answer( $now, null, array( SlotKey::DECK => 101 ) + $now );

		self::assertSame( array( SlotKey::WHEELS ), $answer['changes']['kept'] );
		self::assertSame(
			array(
				array(
					'slot' => SlotKey::DECK,
					'part' => 103,
				),
				array(
					'slot' => SlotKey::TRUCKS,
					'part' => 149,
				),
				array(
					'slot' => SlotKey::WHEELS,
					'part' => 301,
				),
			),
			$answer['changes']['proposed']
		);
		self::assertCount( 1, $answer['changes']['changes'] );
		$change = $answer['changes']['changes'][0];
		self::assertSame( array( 'slot', 'label', 'was', 'name', 'messages', 'closest' ), array_keys( $change ) );
		self::assertSame( SlotKey::TRUCKS, $change['slot'] );
		self::assertSame( 'Trucks', $change['label'] );
		self::assertSame( 139, $change['was'] );
		self::assertSame( 'Part 139', $change['name'] );
		self::assertSame( 'The trucks are made for decks from 7.8 in to 8.2 in wide. The deck is 8.5 in.', $change['messages'][0]['text'] );
		self::assertSame( 149, $change['closest']['id'] );
		self::assertSame( InMemoryPartCards::card( 149 ), $change['closest']['card'] );
		self::assertFalse( $change['closest']['picked'] );
		self::assertFalse( $change['closest']['recommended'] );
		self::assertCount( 1, $this->cards->asked );
		self::assertContains( 139, $this->cards->asked[0] );
		self::assertContains( 149, $this->cards->asked[0] );
	}

	public function test_a_change_with_no_part_that_fits_has_no_closest_and_leaves_the_slot_out_of_the_proposal(): void {
		$parts                   = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array( Shop::priced( Builds::truck( '139' ), 5000 ) );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$now    = array(
			SlotKey::DECK   => 103,
			SlotKey::TRUCKS => 139,
		);
		$answer = $this->answer( $now, null, array( SlotKey::DECK => 101 ) + $now );

		self::assertNull( $answer['changes']['changes'][0]['closest'] );
		self::assertSame( array( SlotKey::DECK ), array_column( $answer['changes']['proposed'], 'slot' ) );
	}

	public function test_the_total_carries_the_fee_and_the_build_discount_as_amounts_and_as_the_shop_writes_them(): void {
		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '10' ) );
		$answer                = $this->answer( array( SlotKey::DECK => 103 ), null, null, true );

		self::assertTrue( $answer['total']['assembly'] );
		self::assertSame( self::money( 1500, '$15.00' ), $answer['total']['fee'] );
		self::assertSame( self::money( 1830, '$18.30' ), $answer['total']['saved'] );
		self::assertSame( self::money( 16470, '$164.70' ), $answer['total']['parts'] );
		self::assertSame( self::money( 17970, '$179.70' ), $answer['total']['total'] );
		self::assertSame( self::money( 7000, '$70.00' ), $answer['total']['lines'][0]['price'] );
		self::assertSame( self::money( 6300, '$63.00' ), $answer['total']['lines'][0]['charged'] );
		self::assertSame( self::money( 6300, '$63.00' ), $answer['total']['lines'][0]['total'] );
	}

	public function test_money_is_written_in_the_shops_own_decimals(): void {
		$this->kits->decimals = 0;
		$answer               = $this->answer( array( SlotKey::DECK => 103 ) );
		self::assertSame( self::money( 7000, '$7,000' ), $answer['total']['lines'][0]['price'] );
	}

	/**
	 * A shop with many decks: IDs 1001 up, all 8.5 in, the ones named sold out.
	 *
	 * @param list<int> $soldOut The decks the shop has none of.
	 */
	private function decks( int $many, array $soldOut = array() ): void {
		$parts = Shop::parts();
		$decks = array();
		for ( $id = 1001; $id <= 1000 + $many; $id++ ) {
			$decks[] = Shop::priced( Builds::deck( '8.5', $id ), 7000, ! in_array( $id, $soldOut, true ) );
		}
		$parts[ SlotKey::DECK ] = $decks;
		$this->kits->add( self::KIT, Shop::kit(), $parts );
	}

	public function test_a_step_lists_24_parts_and_asks_for_no_more_cards_than_it_lists(): void {
		$this->decks( 30 );
		$answer = $this->answer();

		self::assertSame( range( 1001, 1024 ), self::ids( $answer['options'] ) );
		self::assertSame( 30, $answer['steps'][0]['offered'], 'The step still says how many there are.' );
		self::assertSame( array( range( 1001, 1024 ) ), $this->cards->asked );
	}

	public function test_exactly_24_are_all_listed(): void {
		$this->decks( 24 );
		self::assertSame( range( 1001, 1024 ), self::ids( $this->answer()['options'] ) );
	}

	public function test_parts_the_shop_cannot_supply_come_after_those_it_can_each_in_their_own_order(): void {
		$this->decks( 30, array( 1001, 1003, 1030 ) );
		$answer = $this->answer();

		self::assertSame( array_merge( array( 1002 ), range( 1004, 1026 ) ), self::ids( $answer['options'] ) );

		$this->decks( 6, array( 1001, 1003 ) );
		$few = $this->answer();
		self::assertSame( array( 1002, 1004, 1005, 1006, 1001, 1003 ), self::ids( $few['options'] ) );
		self::assertSame( array( true, true, true, true, false, false ), array_column( $few['options'], 'in_stock' ) );
		self::assertSame( array( true, true, true, true, false, false ), array_column( $few['options'], 'available' ) );
	}

	public function test_the_pick_is_listed_wherever_it_stands_and_once(): void {
		$this->decks( 30 );
		$late = $this->answer( array( SlotKey::DECK => 1029 ), SlotKey::DECK );
		self::assertSame( array_merge( range( 1001, 1024 ), array( 1029 ) ), self::ids( $late['options'] ) );
		self::assertTrue( $late['options'][24]['picked'] );

		$early = $this->answer( array( SlotKey::DECK => 1003 ), SlotKey::DECK );
		self::assertSame( range( 1001, 1024 ), self::ids( $early['options'] ) );

		$edge = $this->answer( array( SlotKey::DECK => 1025 ), SlotKey::DECK );
		self::assertSame( range( 1001, 1025 ), self::ids( $edge['options'] ) );
	}

	public function test_the_hidden_list_stops_at_24_too_and_keeps_a_pick_that_is_hidden(): void {
		$parts  = Shop::parts();
		$trucks = array( Shop::priced( Builds::truck( '149' ), 5400 ) );
		for ( $id = 2001; $id <= 2030; $id++ ) {
			$narrow   = Builds::truck( '139' );
			$trucks[] = Shop::priced( new Part( $id, $narrow->brand, $narrow->specs, 0, true ), 5000 );
		}
		$parts[ SlotKey::TRUCKS ] = $trucks;
		$this->kits->add( self::KIT, Shop::kit(), $parts );

		$answer = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );
		self::assertSame( range( 2001, 2024 ), self::ids( $answer['hidden'] ) );
		self::assertSame( 30, self::step( $answer, SlotKey::TRUCKS )['hidden'] );

		$picked = $this->answer(
			array(
				SlotKey::DECK   => 103,
				SlotKey::TRUCKS => 2030,
			),
			SlotKey::TRUCKS
		);
		self::assertSame( array_merge( range( 2001, 2024 ), array( 2030 ) ), self::ids( $picked['hidden'] ) );
	}

	public function test_a_part_whose_product_has_gone_keeps_its_place_with_no_card_and_no_name(): void {
		$this->cards->missing = array( 103, 149 );
		$now                  = array(
			SlotKey::DECK   => 103,
			SlotKey::TRUCKS => 149,
		);
		$answer               = $this->answer( $now, SlotKey::DECK );

		self::assertNull( $answer['options'][2]['card'] );
		self::assertSame( 103, $answer['options'][2]['id'] );
		self::assertSame( '', $answer['total']['lines'][0]['name'] );
		self::assertSame( 'Part 302', $answer['total']['lines'][2]['name'] );

		$this->cards->missing = array( 139 );
		$changed              = $this->answer( array( SlotKey::TRUCKS => 139 ) + $now, null, array( SlotKey::DECK => 101 ) + array( SlotKey::TRUCKS => 139 ) + $now );
		self::assertSame( '', $changed['changes']['changes'][0]['name'] );
	}
}
