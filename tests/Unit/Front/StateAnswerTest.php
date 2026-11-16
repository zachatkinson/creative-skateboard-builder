<?php
/**
 * The state route's answer.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Front\PageRenderer;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\Delivery;
use CreativeBranding\SkateboardBuilder\Front\ShippingWords;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeMoney;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeShippingZones;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCards;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Spec, How it fits: the options with their fit, how many were hidden and why, any change list and the total. Spec,
 * Performance: a slot shows 24 options at a time, and card data is loaded only for the options a step shows.
 */
final class StateAnswerTest extends TestCase {

	private InMemoryLayerImages $layers;

	private InMemoryLayerRenditions $renditions;

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
		$this->layers     = new InMemoryLayerImages();
		$this->renditions = new InMemoryLayerRenditions();
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
	private function answer( array $picks = array(), ?string $step = null, ?array $previous = null, ?bool $assembly = null, ?BuildLink $link = null ): array {
		$state = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, $picks, $previous, $step, $assembly );
		self::assertNotNull( $state );
		return ( new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new LayerArtwork( $this->layers, $this->renditions ), new PageRenderer() ) )->of( $state, $link );
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

		self::assertSame( array( 'kit', 'step', 'ready', 'texts', 'presets', 'steps', 'options', 'hidden', 'filters', 'matching', 'more', 'refused', 'problems', 'changes', 'total', 'picture', 'delivery', 'notes' ), array_keys( $answer ) );
		self::assertSame( self::KIT, $answer['kit'] );
		self::assertSame( SlotKey::DECK, $answer['step'] );
		self::assertFalse( $answer['ready'] );
		self::assertSame( array( 101, 102, 103 ), self::ids( $answer['options'] ) );
		self::assertSame( array(), $answer['hidden'] );
		self::assertSame(
			array(
				'id'          => 101,
				'brand'       => Builds::deck( '8.0', 101 )->brand,
				'size'        => array(
					'value' => '8in',
					'text'  => '8″',
				),
				'delta'       => array(
					'kind'   => 'more',
					'amount' => 6000,
					'text'   => '+$60.00',
				),
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
				'against'     => array(),
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
			array_map( static fn( array $preset ): array => array_intersect_key( $preset, array_flip( array( 'name', 'description', 'picks' ) ) ), $answer['presets'] ),
			'its total and readiness are tested on their own'
		);
		self::assertSame( 'Sold in pairs.', self::step( $answer, SlotKey::TRUCKS )['help'] );
	}

	// Spec, Presets: each tile shows its total, and one holding a sold-out part says so.
	public function test_each_preset_says_its_total_and_names_what_keeps_it_from_the_cart(): void {
		$this->worded();
		$parts                    = Shop::parts();
		$parts[ SlotKey::WHEELS ] = array_map( static fn( Part $part ): Part => 302 === $part->id ? Shop::priced( $part, $part->price, false, 0 ) : $part, $parts[ SlotKey::WHEELS ] );
		$this->kits->add( self::KIT, $this->kits->kit( self::KIT ), $parts );

		$answer = $this->answer();
		$street = $answer['presets'][0];
		self::assertSame( $this->answer( self::STREET )['total']['total'], $street['total'], "the total of the build it starts, at the kit's own assembly setting" );
		self::assertTrue( $street['ready'] );
		self::assertSame( array(), $street['problems'] );
		self::assertSame( InMemoryPartCards::card( 101 )['image'], $street['image'], "its picture, for now its deck's photo" );
		self::assertSame( InMemoryPartCards::card( 103 )['image'], $answer['presets'][1]['image'] );

		$park = $answer['presets'][1];
		self::assertFalse( $park['ready'] );
		self::assertSame( array( array( 'part_sold_out', 'wheels', 302 ) ), array_map( static fn( array $problem ): array => array( $problem['code'], $problem['slot'], $problem['part'] ), $park['problems'] ) );
		self::assertSame( 'Part 302', $park['problems'][0]['name'], 'the part named, for its tile' );
		self::assertNotSame( '', $park['problems'][0]['message'] );
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

	// The order's snapshot and the delivery line ask for the owner's words the same way (milestone 5).
	public function test_a_kits_text_may_be_asked_for_by_itself(): void {
		self::assertSame( 'Ships in a week', StateAnswer::text( 'Ships in a week', 'lead_time', 77 ) );
		$GLOBALS['cbuild_test_filters']['cbuild_kit_text'] = static fn( string $text, string $field, int $kit, string $of ): string => "{$text}|{$field}|{$kit}|{$of}";
		self::assertSame( 'Ships in a week|lead_time|77|', StateAnswer::text( 'Ships in a week', 'lead_time', 77 ) );
		$GLOBALS['cbuild_test_filters']['cbuild_kit_text'] = static fn(): int => 7;
		self::assertSame( 'Ships in a week', StateAnswer::text( 'Ships in a week', 'lead_time', 77 ) );
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
					'text'     => 'The trucks are made for decks from 7.8″ to 8.2″ wide. The deck is 8.5″.',
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
		self::assertSame( 'The trucks are made for decks from 7.8″ to 8.2″ wide. The deck is 8.5″.', $change['messages'][0]['text'] );
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
	 * A shop with many decks: IDs 1001 up, all 8.5″, the ones named sold out.
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

	/**
	 * An option's price change, by its part.
	 *
	 * @param array<string, mixed> $answer The answer.
	 * @return array<int, array{kind: string, amount: int, text: string}>
	 */
	private static function deltas( array $answer ): array {
		return array_column( $answer['options'], 'delta', 'id' );
	}

	public function test_a_card_says_what_its_part_adds_to_the_total_or_takes_off_it(): void {
		$picked = self::deltas( $this->answer( array( SlotKey::DECK => 102 ), SlotKey::DECK ) );
		self::assertSame(
			array(
				'kind'   => 'less',
				'amount' => -500,
				'text'   => '−$5.00',
			),
			$picked[101]
		);
		self::assertSame(
			array(
				'kind'   => 'included',
				'amount' => 0,
				'text'   => 'Included',
			),
			$picked[102]
		);
		self::assertSame(
			array(
				'kind'   => 'more',
				'amount' => 500,
				'text'   => '+$5.00',
			),
			$picked[103]
		);
	}

	public function test_a_part_at_the_price_of_the_one_counted_is_the_same_price_and_only_the_one_counted_is_included(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::DECK ][] = Shop::priced( Builds::deck( '8.25', 104 ), 6500 );
		$parts[ SlotKey::DECK ][] = Shop::priced( Builds::deck( '8.25', 105 ), 0 );
		$this->kits->add( self::KIT, Shop::kit(), $parts );

		$picked = self::deltas( $this->answer( array( SlotKey::DECK => 102 ), SlotKey::DECK ) );
		self::assertSame( 'included', $picked[102]['kind'] );
		self::assertSame(
			array(
				'kind'   => 'free',
				'amount' => 0,
				'text'   => 'Same price',
			),
			$picked[104]
		);

		$blank = self::deltas( $this->answer() );
		self::assertSame( 'free', $blank[105]['kind'], 'With nothing counted, a part that costs nothing is free, and none is included.' );
		self::assertSame( 'more', $blank[102]['kind'] );
	}

	public function test_the_change_is_in_what_the_build_charges_for_as_many_as_it_takes(): void {
		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '10' ) );
		// The trucks step starts on its recommendation, which the total counts: each other pair is priced against it.
		$answer  = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );
		$counted = self::step( $answer, SlotKey::TRUCKS )['recommended'];
		self::assertSame( 149, $counted );
		self::assertSame( 'included', self::deltas( $answer )[149]['kind'] );
		// The trucks that do not fit say theirs too. 169 is 56.00 against 54.00, and 139 is 50.00, each less 10 %.
		$hidden = array_column( $answer['hidden'], 'delta', 'id' );
		self::assertSame(
			array(
				'kind'   => 'more',
				'amount' => 180,
				'text'   => '+$1.80',
			),
			$hidden[169]
		);
		self::assertSame(
			array(
				'kind'   => 'less',
				'amount' => -360,
				'text'   => '−$3.60',
			),
			$hidden[139]
		);
	}

	public function test_a_part_with_no_set_size_has_no_price_in_a_build_and_says_nothing(): void {
		$parts                      = Shop::parts();
		$loose                      = Parts::indy( '149', array( 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8' ) );
		$parts[ SlotKey::TRUCKS ][] = new Part( 150, $loose->brand, $loose->specs, 5400, true );
		$this->kits->add( self::KIT, Shop::kit(), $parts );

		$answer = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );
		$all    = array_column( array_merge( $answer['options'], $answer['hidden'] ), null, 'id' );
		self::assertNull( $all[150]['quantity'] );
		self::assertSame(
			array(
				'kind'   => 'none',
				'amount' => 0,
				'text'   => '',
			),
			$all[150]['delta']
		);
	}

	// D-200: the share card names a part's size as the answer does, from outside it.
	public function test_a_parts_size_is_read_the_same_from_outside_the_answer(): void {
		self::assertSame(
			array(
				'value' => '54mm',
				'text'  => '54 mm',
			),
			StateAnswer::size( SlotKey::WHEELS, Shop::parts()[ SlotKey::WHEELS ][1] )
		);
	}

	public function test_a_part_is_sized_as_its_slots_parts_are_and_bolts_and_risers_in_fractions(): void {
		$picks = array(
			SlotKey::DECK   => 103,
			SlotKey::TRUCKS => 149,
			SlotKey::WHEELS => 303,
		);
		$size  = fn( string $step ): mixed => $this->answer( $picks, $step )['options'][0]['size'];
		$fits  = array( SlotKey::WHEELS => 302 ) + $picks;

		self::assertSame( '54mm', $this->answer( $picks, SlotKey::WHEELS )['options'][1]['size']['value'] );
		self::assertSame( '54 mm', $this->answer( $picks, SlotKey::WHEELS )['options'][1]['size']['text'] );
		self::assertSame(
			array(
				'value' => '0.125in',
				'text'  => '⅛″',
			),
			$size( SlotKey::RISERS )
		);
		self::assertSame(
			array(
				'value' => '1in',
				'text'  => '1″',
			),
			$this->answer( $fits, SlotKey::HARDWARE )['options'][0]['size']
		);
		self::assertNull( $size( SlotKey::BEARINGS ), 'Bearings have no size a shopper chooses by.' );
		self::assertNull( $size( SlotKey::GRIP ) );
	}

	/**
	 * A shop whose decks are of two brands, with one of no brand and one whose brand the shop has since deleted.
	 */
	private function branded(): void {
		$parts                  = Shop::parts();
		$deck                   = static fn( string $width, int $id, string $brand ): Part => new Part( $id, $brand, Builds::deck( $width )->specs, 6000, true );
		$parts[ SlotKey::DECK ] = array(
			$deck( '8.5', 111, 'zero' ),
			$deck( '8.0', 112, 'baker' ),
			$deck( '8.25', 113, 'baker' ),
			$deck( '8.25', 114, '' ),
			$deck( '8.0', 115, 'gone' ),
		);
		$this->kits->add( self::KIT, Shop::kit(), $parts );
	}

	public function test_the_filters_list_the_brands_by_name_and_the_sizes_in_order_each_with_its_count(): void {
		$this->branded();
		$answer = $this->answer();

		self::assertSame(
			array(
				array(
					'value'    => 'baker',
					'label'    => 'Baker',
					'count'    => 2,
					'selected' => false,
				),
				array(
					'value'    => 'gone',
					'label'    => 'gone',
					'count'    => 1,
					'selected' => false,
				),
				array(
					'value'    => 'zero',
					'label'    => 'Zero',
					'count'    => 1,
					'selected' => false,
				),
			),
			$answer['filters']['brands']
		);
		self::assertSame( array( '8in', '8.25in', '8.5in' ), array_column( $answer['filters']['sizes'], 'value' ) );
		self::assertSame( array( array( 'zero', 'baker', 'gone' ) ), $this->cards->brandsAsked, 'Each brand once, and the part with none asks for none.' );
		self::assertSame( array( 0, 1, 2 ), array_keys( $answer['filters']['brands'] ) );
		self::assertSame( array( 0, 1, 2 ), array_keys( $answer['filters']['sizes'] ) );
		self::assertSame( array( '8″', '8.25″', '8.5″' ), array_column( $answer['filters']['sizes'], 'label' ) );
		self::assertSame( array( 2, 2, 1 ), array_column( $answer['filters']['sizes'], 'count' ) );
		self::assertSame( 5, $answer['matching'] );
		self::assertNull( $answer['more'] );
	}

	public function test_a_filter_lists_the_parts_that_match_and_the_filters_still_offer_the_rest(): void {
		$this->branded();

		$baker = $this->answer( array(), null, null, null, new BuildLink( brand: 'baker' ) );
		self::assertSame( array( 112, 113 ), self::ids( $baker['options'] ) );
		self::assertSame( 2, $baker['matching'] );
		self::assertSame( array( true, false, false ), array_column( $baker['filters']['brands'], 'selected' ) );
		self::assertSame( array( 2, 1, 1 ), array_column( $baker['filters']['brands'], 'count' ), 'The counts are of every part the step offers.' );
		self::assertSame( 5, $baker['steps'][0]['offered'] );

		$wide = $this->answer( array(), null, null, null, new BuildLink( size: '8.25in' ) );
		self::assertSame( array( 113, 114 ), self::ids( $wide['options'] ) );
		self::assertSame( array( false, true, false ), array_column( $wide['filters']['sizes'], 'selected' ) );

		$both = $this->answer( array(), null, null, null, new BuildLink( brand: 'baker', size: '8.25in' ) );
		self::assertSame( array( 113 ), self::ids( $both['options'] ) );

		$none = $this->answer( array(), null, null, null, new BuildLink( brand: 'zero', size: '8in' ) );
		self::assertSame( array(), $none['options'] );
		self::assertSame( 0, $none['matching'] );
	}

	public function test_the_part_the_step_counts_is_listed_though_a_filter_leaves_it_out(): void {
		$this->branded();
		$answer = $this->answer( array( SlotKey::DECK => 111 ), SlotKey::DECK, null, null, new BuildLink( brand: 'baker' ) );
		self::assertSame( array( 112, 113, 111 ), self::ids( $answer['options'] ) );
		self::assertSame( 2, $answer['matching'] );
	}

	public function test_a_list_of_one_brand_or_one_size_is_no_filter(): void {
		$answer = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );
		self::assertSame(
			array(
				'brands' => array(),
				'sizes'  => array(),
			),
			$answer['filters'],
			'One truck fits, so there is one brand and one size.'
		);
		self::assertSame( array( array( 'independent' ) ), $this->cards->brandsAsked );
		$wheels = $this->answer( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ), SlotKey::WHEELS );
		self::assertSame( array(), $wheels['filters']['brands'] );
		self::assertNotSame( array(), $wheels['filters']['sizes'] );

		$bearings = $this->answer( self::STREET, SlotKey::BEARINGS );
		self::assertSame(
			array(
				'brands' => array(),
				'sizes'  => array(),
			),
			$bearings['filters']
		);
		$this->cards = new InMemoryPartCards();
		$this->answer();
		self::assertSame( array(), $this->cards->brandsAsked, 'No brand, no question.' );
	}

	public function test_show_more_lists_24_more_each_time_and_says_whether_more_remain(): void {
		$this->decks( 60 );

		$first = $this->answer();
		self::assertCount( 24, $first['options'] );
		self::assertSame( 60, $first['matching'] );
		self::assertSame( 48, $first['more'], 'The number the address carries for the next page.' );

		$second = $this->answer( array(), null, null, null, new BuildLink( shown: 48 ) );
		self::assertSame( range( 1001, 1048 ), self::ids( $second['options'] ) );
		self::assertSame( 72, $second['more'] );

		$last = $this->answer( array(), null, null, null, new BuildLink( shown: 72 ) );
		self::assertCount( 60, $last['options'] );
		self::assertNull( $last['more'] );

		$this->decks( 48 );
		self::assertNull( $this->answer( array(), null, null, null, new BuildLink( shown: 48 ) )['more'], 'Exactly a page leaves none.' );
		self::assertSame( 48, $this->answer()['more'] );
	}

	public function test_the_most_an_address_may_ask_for_is_the_last_page(): void {
		$this->decks( BuildLink::MOST + 30 );
		$near = $this->answer( array(), null, null, null, new BuildLink( shown: BuildLink::MOST - 10 ) );
		self::assertSame( BuildLink::MOST, $near['more'] );
		$most = $this->answer( array(), null, null, null, new BuildLink( shown: BuildLink::MOST ) );
		self::assertCount( BuildLink::MOST, $most['options'] );
		self::assertNull( $most['more'] );
	}

	public function test_the_picture_shows_what_is_in_the_box_each_part_labelled_and_says_the_build_in_a_sentence(): void {
		$blank = $this->answer();
		self::assertSame(
			array(
				'items'       => array(),
				'description' => 'Nothing is chosen yet.',
				'width'       => 0,
				'height'      => 0,
				'layers'      => array(),
			),
			$blank['picture']
		);

		$answer = $this->answer(
			array(
				SlotKey::DECK   => 103,
				SlotKey::TRUCKS => 149,
			),
			SlotKey::WHEELS
		);
		self::assertSame(
			array(
				'slot'  => SlotKey::DECK,
				'part'  => 103,
				'label' => 'Deck: 8.5″',
				'size'  => '8.5″',
				'name'  => 'Part 103',
				'image' => InMemoryPartCards::card( 103 )['image'],
				'drawn' => false,
			),
			$answer['picture']['items'][0]
		);
		self::assertSame( 'Trucks: Independent, 8.5″', $answer['picture']['items'][1]['label'] );
		self::assertSame( '', $answer['picture']['items'][3]['size'], 'Bearings have no size of their own.' );
		self::assertSame( array_column( $answer['total']['lines'], 'part' ), array_column( $answer['picture']['items'], 'part' ), 'The parts the total counts, the recommendations among them.' );
		self::assertSame( 'Bearings', $answer['picture']['items'][3]['label'], 'A part with no brand and no size is labelled with its slot.' );
		self::assertStringStartsWith( 'The build so far: Deck: Part 103; Trucks: Part 149; ', $answer['picture']['description'] );
		self::assertStringEndsWith( '; Grip: Part 701.', $answer['picture']['description'] );
	}

	public function test_the_board_draws_the_parts_with_a_layer_image_and_the_box_keeps_the_rest(): void {
		$this->layers->attachments = array(
			103 => 13,
			149 => 49,
		);
		$this->layers->images      = array(
			13 => new ImageFacts( 'image/png', 432, 1626, true ),
			49 => new ImageFacts( 'image/png', 432, 160, true ),
		);
		$answer = $this->answer(
			array(
				SlotKey::DECK   => 103,
				SlotKey::TRUCKS => 149,
			),
			SlotKey::WHEELS
		);
		$picture = $answer['picture'];
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::TRUCKS ), array_column( $picture['layers'], 'slot' ), 'the wheels the step recommends have no layer image' );
		self::assertSame( array( 103, 149, 149 ), array_column( $picture['layers'], 'part' ) );
		self::assertSame( array( 2159000, 8128000 ), array( $picture['width'], $picture['height'] ), 'an 8.5 in deck, 32 in long' );
		self::assertSame( 'https://example.com/layer-13.png', $picture['layers'][0]['image']['src'], 'the master until the renditions are made' );
		self::assertSame(
			array( SlotKey::DECK => true, SlotKey::TRUCKS => true ),
			array_slice( array_column( $picture['items'], 'drawn', 'slot' ), 0, 2, true )
		);
		self::assertSame( array(), array_filter( array_slice( array_column( $picture['items'], 'drawn' ), 2 ) ), 'the rest keep their labelled photos' );
		self::assertCount( count( $answer['total']['lines'] ), $picture['items'], 'every part the total counts is still in the box' );
	}

	public function test_a_part_whose_product_has_gone_is_in_the_box_with_no_name_and_out_of_the_sentence(): void {
		$this->cards->missing = array( 103 );
		$answer               = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::DECK );
		self::assertSame( '', $answer['picture']['items'][0]['name'] );
		self::assertNull( $answer['picture']['items'][0]['image'] );
		self::assertStringStartsWith( 'The build so far: Trucks: Part 149', $answer['picture']['description'] );
	}

	public function test_delivery_says_what_the_two_filters_answer_and_nothing_where_none_does(): void {
		self::assertSame(
			array(
				'arrival' => '',
				'line'    => '',
			),
			$this->answer()['delivery']
		);

		$seen                           = array();
		$GLOBALS['cbuild_test_filters'] = array(
			'cbuild_arrival_estimate' => static function ( string $said, array $context ) use ( &$seen ): string {
				$seen = $context;
				return 'Arrives by Thu, Nov 19, <b>assembled</b>';
			},
			'cbuild_delivery_line'    => static fn(): string => 'Spend $41.00 more for free shipping to Ontario.',
		);
		$answer                         = $this->answer( array( SlotKey::DECK => 103 ), null, null, true );
		self::assertSame(
			array(
				'arrival' => 'Arrives by Thu, Nov 19, assembled',
				'line'    => 'Spend $41.00 more for free shipping to Ontario.',
			),
			$answer['delivery']
		);
		self::assertSame( self::KIT, $seen['kit'] );
		self::assertTrue( $seen['assembly'] );
		self::assertSame( $answer['total']['total']['amount'], $seen['total'] );
		self::assertSame( 2, $seen['decimals'] );
	}

	public function test_other_plugins_lines_under_the_total_are_plain_text_and_six_at_most(): void {
		self::assertSame( array(), $this->answer()['notes'] );

		$seen                           = array();
		$GLOBALS['cbuild_test_filters'] = array(
			'cbuild_summary_notes' => static function ( array $notes, array $context ) use ( &$seen ): array {
				$seen = $context;
				return array( 'This build earns <strong>180</strong> points.', '', 7, ' ', 'Two.', 'Three.', 'Four.', 'Five.', 'Six.', 'Seven.' );
			},
		);
		$answer                         = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::DECK );
		self::assertSame( array( 'This build earns 180 points.', 'Two.', 'Three.', 'Four.', 'Five.', 'Six.' ), $answer['notes'] );
		self::assertSame( self::KIT, $seen['kit'] );
		self::assertSame( array_column( $answer['total']['lines'], 'part', 'slot' ), $seen['parts'] );
		self::assertSame( 103, $seen['parts'][ SlotKey::DECK ] );
		self::assertFalse( $seen['assembly'] );
		self::assertSame( $answer['total']['total']['amount'], $seen['total'] );
		self::assertSame( 2, $seen['decimals'] );

		$GLOBALS['cbuild_test_filters'] = array( 'cbuild_summary_notes' => static fn(): string => 'Not a list.' );
		self::assertSame( array(), $this->answer()['notes'] );
	}

	public function test_the_review_is_a_step_that_lists_no_options_and_keeps_the_build(): void {
		$answer = $this->answer( self::STREET, BuildLink::REVIEW );
		self::assertSame( 'review', $answer['step'] );
		self::assertSame( array(), $answer['options'] );
		self::assertSame( array(), $answer['hidden'] );
		self::assertSame( 0, $answer['matching'] );
		self::assertNull( $answer['more'] );
		self::assertSame(
			array(
				'brands' => array(),
				'sizes'  => array(),
			),
			$answer['filters']
		);
		self::assertTrue( $answer['ready'] );
		self::assertCount( 6, $answer['total']['lines'] );
		self::assertCount( 6, $answer['picture']['items'] );
	}
}
