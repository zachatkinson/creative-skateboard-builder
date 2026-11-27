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
use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\Delivery;
use CreativeBranding\SkateboardBuilder\Front\ShippingWords;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Port\PartRanks;
use CreativeBranding\SkateboardBuilder\Service\BuildSwaps;
use CreativeBranding\SkateboardBuilder\Port\TextFolding;
use CreativeBranding\SkateboardBuilder\Service\Phases;
use CreativeBranding\SkateboardBuilder\Service\ShelfQuery;
use CreativeBranding\SkateboardBuilder\Service\StepShelf;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeRanking;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLooks;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
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

	/**
	 * A complete of the shop's parts that fits: an 8.5 deck on 149s with 54 mm wheels.
	 */
	private const array COMPLETE = array(
		SlotKey::DECK     => 103,
		SlotKey::TRUCKS   => 149,
		SlotKey::WHEELS   => 302,
		SlotKey::BEARINGS => 501,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

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
	private function answer( array $picks = array(), ?string $step = null, ?array $previous = null, ?bool $assembly = null, ?BuildLink $link = null, bool $artwork = true ): array {
		$state = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, $picks, $previous, $step, $assembly );
		self::assertNotNull( $state );
		$answer = new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() );
		$drawn = $answer->withArtwork( new LayerArtwork( $this->layers, $this->renditions ) );
		// Without artwork, the answer withArtwork() was called on, as 0.6.0's container's is: a copy took the artwork.
		return ( $artwork ? $drawn : $answer )->of( $state, $link );
	}

	/**
	 * The answer's shelf for a build of the usual kit, with each part a look of its own product unless set.
	 *
	 * @param array<mixed>         $picks The picks.
	 * @param array<int, LookFact> $looks Looks by part, beside the usual.
	 * @return array<string, mixed>|null
	 */
	private function shelf( array $picks = array(), ?string $step = null, ?BuildLink $link = null, array $looks = array(), ?ShelfQuery $query = null ): ?array {
		$state = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, $picks, null, $step );
		self::assertNotNull( $state );
		$facts = new InMemoryPartLooks();
		foreach ( Shop::parts() as $parts ) {
			foreach ( $parts as $part ) {
				$facts->facts[ $part->id ] = $looks[ $part->id ] ?? new LookFact( $part->id, $part->id, 0, 'Part ' . $part->id, array(), array(), false, 0, 0 );
			}
		}
		$folding = new class() implements TextFolding {
			public function fold( string $text ): string {
				return strtolower( $text );
			}
		};
		$answer = ( new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ) )
			->withShelf( new StepShelf( $facts, $this->cards, new FakeRanking(), $folding, $this->settings ) )
			->of( $state, $link, $query );
		self::assertArrayHasKey( 'shelf', $answer );
		$shelf = $answer['shelf'];
		self::assertTrue( null === $shelf || is_array( $shelf ) );
		return $shelf;
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

	/**
	 * The auditor's second pass on M12, should-fix 2: `withPhases()` times a copy, and the answer it was called on
	 * times nothing, as an earlier release's container builds it (#125, D-365).
	 */
	public function test_with_phases_times_a_copy_and_leaves_the_answer_it_was_called_on_untimed(): void {
		$state = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, array(), null, SlotKey::DECK, null );
		self::assertNotNull( $state );
		$phases = new Phases( new FakeClock() );
		$plain  = new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() );
		$timed  = $plain->withPhases( $phases );
		$plain->of( $state );
		self::assertSame( array(), $phases->took(), 'the answer withPhases() was called on times nothing' );
		$timed->of( $state );
		self::assertArrayHasKey( 'shelf', $phases->took(), 'its copy times its phases' );
	}

	public function test_with_nothing_picked_the_first_step_lists_its_parts_each_with_its_card(): void {
		$answer = $this->answer();

		self::assertSame( array( 'kit', 'step', 'ready', 'texts', 'presets', 'steps', 'options', 'hidden', 'filters', 'matching', 'more', 'refused', 'problems', 'changes', 'swaps', 'total', 'picture', 'delivery', 'notes', 'shelf', 'bearings_included', 'currency' ), array_keys( $answer ) );
		// How the shop writes a price, for a page that formats a total itself (milestone 11, sent from 1.0.0).
		self::assertSame(
			array(
				'code'               => 'CAD',
				'symbol'             => '$',
				'decimals'           => 2,
				'decimal_separator'  => '.',
				'thousand_separator' => ',',
				'format'             => '%1$s%2$s',
			),
			$answer['currency']
		);
		self::assertFalse( $answer['bearings_included'] );
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
					'change' => '',
				),
				'fit'         => 'fits',
				'in_stock'    => true,
				'available'   => true,
				'quantity'    => 1,
				'picked'      => false,
				'recommended' => false,
				'messages'    => array(),
				'card'        => InMemoryPartCards::listed( 101 ),
				'board'       => null,
				// From 1.3.0 (#137): no ready build picks it.
				'presets'     => array(),
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
				'estimate' => null,
			),
			$answer['total']
		);
		self::assertSame( array( array( 101, 102, 103 ) ), $this->cards->asked, 'One call, for the step on show and for no other.' );
	}

	/**
	 * A kit with words of the owner's own: help on the trucks, two presets, a lead time and a returns notice.
	 */
	private function worded( ?int $image = null ): void {
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
						) + ( null === $image ? array() : array( 'image' => $image ) ),
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

	// #137 (D-392): each option names the ready builds shown that pick it, by their places among the kit's, and its card
	// has the rating the shop shows, said in words, or none.
	public function test_an_option_names_the_ready_builds_that_pick_it_and_its_card_has_its_rating(): void {
		$this->worded();
		$this->cards->rated[301] = array(
			'average' => 4.5,
			'count'   => 12,
		);
		$wheels                  = array();
		foreach ( $this->answer( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139 ), SlotKey::WHEELS )['options'] as $option ) {
			$wheels[ $option['id'] ] = $option;
		}
		self::assertSame( array( 0 ), $wheels[301]['presets'], 'Street picks it' );
		self::assertSame( array( 1 ), $wheels[302]['presets'], 'Park picks it' );
		self::assertSame(
			array(
				'average' => 4.5,
				'count'   => 12,
				'text'    => '4.5 out of 5, 12 reviews',
			),
			$wheels[301]['card']['rating']
		);
		self::assertNull( $wheels[302]['card']['rating'], 'no reviews, or the shop shows no ratings' );

		$bearings = $this->answer( self::STREET, SlotKey::BEARINGS )['options'];
		self::assertSame( array( array( 0, 1 ) ), array_values( array_column( array_filter( $bearings, static fn( array $option ): bool => 501 === $option['id'] ), 'presets' ) ), 'both pick it, in the kit\'s order' );

		// No ready build shown, none named: the kit's Show ready builds off (#104).
		$this->kits->add(
			self::KIT,
			Kit::fromDocuments(
				KitDocuments::slots(),
				KitDocuments::rules(),
				array(
					'schema_version' => 1,
					'presets'        => array(
						array(
							'name'  => 'Street',
							'picks' => self::STREET,
						),
					),
				),
				KitDocuments::assembly(),
				null,
				array(
					'schema_version' => 1,
					'show_presets'   => false,
				)
			),
			Shop::parts()
		);
		$decks = $this->answer( array(), SlotKey::DECK )['options'];
		self::assertContains( 101, array_column( $decks, 'id' ) );
		self::assertSame( array( array() ), array_values( array_column( array_filter( $decks, static fn( array $option ): bool => 101 === $option['id'] ), 'presets' ) ) );
	}

	// Spec, Presets: each tile shows its total, and one holding a sold-out part says so.
	// D-252: "Without one, the tile shows the build's composed picture (milestone 7), not the first part's photo."
	public function test_a_presets_tile_shows_its_rider_photo_else_the_builds_picture_once_made(): void {
		$this->worded( 81 );
		$photo                   = array(
			'src'    => 'https://example.com/rider.jpg',
			'width'  => 300,
			'height' => 400,
			'srcset' => '',
			'sizes'  => '',
		);
		$this->cards->photos[81] = $photo;
		$asked                   = array();
		$state                   = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, array(), null, null, null );
		self::assertNotNull( $state );
		$answer = ( new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ) )
			->withPictures(
				static function ( int $kit, array $picks ) use ( &$asked ): ?string {
					$asked[] = $picks;
					return self::PARK === $picks ? 'https://example.com/pictures/park.webp' : null;
				}
			);
		$presets = $answer->of( $state )['presets'];
		self::assertSame( $photo, $presets[0]['image'], 'the rider photo the preset names' );
		self::assertSame(
			array(
				'src'    => 'https://example.com/pictures/park.webp',
				'width'  => 480,
				'height' => 480,
				'srcset' => '',
				'sizes'  => '',
			),
			$presets[1]['image'],
			'the build\'s picture'
		);
		self::assertSame( array( self::PARK ), $asked, 'asked for the preset with no photo' );
		// F41 (#164): the build's picture is drawn from the parts' layers, so it is marked drawn, as a card's layer image
		// is; a rider photo is not.
		self::assertSame( array( false, true ), array_column( $presets, 'drawn' ) );

		unset( $this->cards->photos[81] );
		$asked = array();
		$presets = $answer->of( $state )['presets'];
		self::assertSame( InMemoryPartCards::card( 101 )['large'], $presets[0]['image'], 'a photo gone, the picture not made: the deck\'s photo' );
		self::assertFalse( $presets[0]['drawn'] );
		self::assertSame( array( self::STREET, self::PARK ), $asked );
		// A deck with no photo: its card picture is its layer image (D-282), marked drawn.
		$this->cards->layered[101] = $photo;
		self::assertSame( array( $photo, true ), array( $answer->of( $state )['presets'][0]['image'], $answer->of( $state )['presets'][0]['drawn'] ) );
		$this->cards->layered = array();

		$asked = array();
		$on    = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, array( SlotKey::DECK => 101 ), null, null, null );
		self::assertNotNull( $on );
		$answer->of( $on );
		self::assertSame( array(), $asked, 'a build begun shows no presets, and asks for none' );
	}

	// Spec, Presets: each tile says its total. #104 (R1): one a part keeps from the cart, Park with its wheels sold out,
	// is listed in the answer and not shown, and the Catalogue check lists it for the owner. Spec, Presets: "A preset or
	// link that holds one names it", so what keeps Park from the cart carries the wheels' name from their card.
	public function test_each_preset_says_its_total_and_one_a_part_keeps_from_the_cart_is_not_shown(): void {
		$this->worded();
		$parts                    = Shop::parts();
		$parts[ SlotKey::WHEELS ] = array_map( static fn( Part $part ): Part => 302 === $part->id ? Shop::priced( $part, $part->price, false, 0 ) : $part, $parts[ SlotKey::WHEELS ] );
		$this->kits->add( self::KIT, $this->kits->kit( self::KIT ), $parts );

		$answer = $this->answer();
		$street = $answer['presets'][0];
		self::assertSame( $this->answer( self::STREET )['total']['total'], $street['total'], "the total of the build it starts, at the kit's own assembly setting" );
		self::assertTrue( $street['ready'] );
		self::assertSame( array(), $street['problems'] );
		self::assertSame( InMemoryPartCards::card( 101 )['large'], $street['image'], "its picture, for now its deck's photo" );
		self::assertCount( 2, $answer['presets'], 'Park is listed, at its place' );
		self::assertSame( array( true, false ), array_column( $answer['presets'], 'shown' ), 'and not shown' );
		self::assertFalse( $answer['presets'][1]['ready'] );
		self::assertSame(
			array(
				array(
					'code'     => 'part_sold_out',
					'slot'     => SlotKey::WHEELS,
					'part'     => 302,
					'message'  => 'Wheels: the part chosen is sold out.',
					'messages' => array(),
					'name'     => 'Part 302',
				),
			),
			$answer['presets'][1]['problems'],
			'what keeps it from the cart, the part named as its card names it'
		);
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

	public function test_a_later_step_lists_what_fits_hides_the_rest_with_why_and_marks_the_part_it_recommends(): void {
		$answer = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );

		self::assertSame( SlotKey::TRUCKS, $answer['step'] );
		self::assertFalse( $answer['ready'], 'the trucks and the rest are still to pick (D-213)' );
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
					'short'    => '',
				),
			),
			$answer['hidden'][0]['messages']
		);
		self::assertSame( InMemoryPartCards::listed( 139 ), $answer['hidden'][0]['card'] );

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
		// A step that is not on show still says what it recommends, and lists nothing.
		self::assertSame( 302, self::step( $answer, SlotKey::WHEELS )['recommended'] );
		self::assertSame( 3, self::step( $answer, SlotKey::WHEELS )['offered'] );

		// The total counts the pick, named from its card; the estimate adds each recommendation (D-213).
		self::assertSame(
			array(
				array(
					'slot'     => SlotKey::DECK,
					'part'     => 103,
					'name'     => 'Part 103',
					'quantity' => 1,
					'price'    => self::money( 7000, '$70.00' ),
					'charged'  => self::money( 7000, '$70.00' ),
					'total'    => self::money( 7000, '$70.00' ),
				),
			),
			$answer['total']['lines']
		);
		self::assertSame( self::money( 7000, '$70.00' ), $answer['total']['parts'] );
		self::assertSame( self::money( 7000, '$70.00' ), $answer['total']['total'] );
		self::assertSame( self::money( 18300, '$183.00' ), $answer['total']['estimate'] );
		self::assertSame( array( array( 149, 139, 144, 169, 103 ) ), $this->cards->asked, 'One call: the options on show, then the parts the total counts, each once.' );
	}

	public function test_the_estimate_is_the_board_with_each_recommendation_and_is_gone_once_every_slot_is_picked(): void {
		self::assertNull( $this->answer()['total']['estimate'], 'nothing to recommend before the deck' );
		self::assertNull( $this->answer( self::COMPLETE )['total']['estimate'], 'everything picked: the total is the whole board' );

		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '10' ) );
		$deck                  = $this->answer( array( SlotKey::DECK => 103 ), null, null, true );
		$whole                 = $this->answer( self::COMPLETE, null, null, true );
		self::assertSame( $whole['total']['total'], $deck['total']['estimate'], 'with the fee and the discount, as the total' );
		self::assertSame( self::money( 17970, '$179.70' ), $deck['total']['estimate'] );
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
		$pair = array_values( array_filter( $blocked['problems'], static fn( array $problem ): bool => 'part_blocked' === $problem['code'] ) );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS ), array_column( $pair, 'slot' ), 'Both parts of the pair that does not fit.' );
		$problem = $pair[1];
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
		self::assertSame( InMemoryPartCards::listed( 149 ), $change['closest']['card'] );
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
		$answer                = $this->answer( self::COMPLETE, null, null, true );

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
		$now                  = self::COMPLETE;
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
				'change' => '$5.00 less',
			),
			$picked[101]
		);
		self::assertSame(
			array(
				'kind'   => 'included',
				'amount' => 0,
				'text'   => 'Included',
				'change' => 'Included',
			),
			$picked[102]
		);
		self::assertSame(
			array(
				'kind'   => 'more',
				'amount' => 500,
				'text'   => '+$5.00',
				'change' => '+$5.00 over your pick',
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
				'change' => 'Same price',
			),
			$picked[104]
		);

		$blank = self::deltas( $this->answer() );
		self::assertSame( 'free', $blank[105]['kind'], 'With nothing counted, a part that costs nothing is free, and none is included.' );
		self::assertSame( 'more', $blank[102]['kind'] );
	}

	public function test_a_recommended_part_is_priced_as_what_it_adds_since_the_total_does_not_count_it(): void {
		$trucks = self::deltas( $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS ) );
		self::assertSame(
			array(
				'kind'   => 'more',
				'amount' => 5400,
				'text'   => '+$54.00',
				'change' => '',
			),
			$trucks[149]
		);
	}

	public function test_the_change_is_in_what_the_build_charges_for_as_many_as_it_takes(): void {
		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '10' ) );
		// The trucks picked are what the total counts: each other pair is priced against them.
		$answer = $this->answer( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ), SlotKey::TRUCKS );
		self::assertSame( 'included', self::deltas( $answer )[149]['kind'] );
		// The trucks that do not fit say theirs too. 169 is 56.00 against 54.00, and 139 is 50.00, each less 10 %.
		$hidden = array_column( $answer['hidden'], 'delta', 'id' );
		self::assertSame(
			array(
				'kind'   => 'more',
				'amount' => 180,
				'text'   => '+$1.80',
				'change' => '+$1.80 over your pick',
			),
			$hidden[169]
		);
		self::assertSame(
			array(
				'kind'   => 'less',
				'amount' => -360,
				'text'   => '−$3.60',
				'change' => '$3.60 less',
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
				'change' => '',
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
				'top'         => array(),
			),
			$blank['picture']
		);

		$answer = $this->answer( self::COMPLETE, SlotKey::WHEELS );
		self::assertSame(
			array(
				'slot'  => SlotKey::DECK,
				'part'  => 103,
				'label' => 'Deck: 8.5″',
				'size'  => '8.5″',
				'name'  => 'Part 103',
				'image' => InMemoryPartCards::card( 103 )['image'],
				'photo' => true,
				'large' => InMemoryPartCards::card( 103 )['large'],
				'drawn' => false,
			),
			$answer['picture']['items'][0]
		);
		self::assertSame( 'Trucks: Independent, 8.5″', $answer['picture']['items'][1]['label'] );
		self::assertSame( '', $answer['picture']['items'][3]['size'], 'Bearings have no size of their own.' );
		self::assertSame( array_column( $answer['total']['lines'], 'part' ), array_column( $answer['picture']['items'], 'part' ), 'The parts the total counts.' );
		self::assertSame( 'Bearings', $answer['picture']['items'][3]['label'], 'A part with no brand and no size is labelled with its slot.' );
		self::assertStringStartsWith( 'The build so far: Deck: Part 103; Trucks: Part 149; ', $answer['picture']['description'] );
		self::assertStringEndsWith( '; Grip: Part 701.', $answer['picture']['description'] );
	}

	// D-208: an answer that 0.6.0's container builds, in the request that puts 0.7.0's files in its place, has no
	// artwork, and draws the board with each part's labelled photo; the answer the container gives it is not changed.
	public function test_an_answer_built_without_artwork_draws_no_layer_and_keeps_every_part(): void {
		$this->layers->attachments = array( 103 => 13 );
		$this->layers->images      = array( 13 => new ImageFacts( 'image/png', 432, 1626, true ) );
		$picks                     = array( SlotKey::DECK => 103 );
		$without                   = $this->answer( $picks, SlotKey::TRUCKS, artwork: false )['picture'];
		self::assertSame( array(), $without['layers'] );
		self::assertSame( array( SlotKey::DECK => false ), array_slice( array_column( $without['items'], 'drawn', 'slot' ), 0, 1, true ) );
		self::assertSame( array( SlotKey::DECK ), array_column( $this->answer( $picks, SlotKey::TRUCKS )['picture']['layers'], 'slot' ) );
		self::assertSame( $this->answer( $picks, SlotKey::TRUCKS )['picture']['items'][0]['part'], $without['items'][0]['part'] );
	}

	// The slow deck swap (M11, D-308): each deck the step offers carries the board it would draw, alone, so the store can
	// fetch its image the moment the shopper points at the card, before the pick is asked. The same layout as the
	// picture's, so the same file is chosen; a deck with no layer image has none, and no other step carries one.
	public function test_each_deck_on_the_deck_step_carries_the_board_it_would_draw_and_no_other_step_does(): void {
		$this->layers->attachments = array( 103 => 13 );
		$this->layers->images      = array( 13 => new ImageFacts( 'image/png', 432, 1626, true ) );
		$this->renditions->make( 13, Renditions::drawn( SlotKey::DECK, Shop::parts()[ SlotKey::DECK ][2]->specs, 432, 1626 ) );

		$answer = $this->answer( array( SlotKey::DECK => 101 ), SlotKey::DECK );
		$boards = array_column( $answer['options'], 'board', 'id' );
		self::assertNull( $boards[101], 'a deck with no layer image' );
		$board = $boards[103];
		self::assertIsArray( $board );
		self::assertSame( array( 2159000, 8128000 ), array( $board['width'], $board['height'] ), 'an 8.5 in deck, 32 in long, alone' );
		self::assertSame( array( SlotKey::DECK ), array_column( $board['layers'], 'slot' ) );
		$picked = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS )['picture'];
		self::assertSame( $picked['layers'][0]['image'], $board['layers'][0]['image'], 'the image the picture draws once it is picked' );
		self::assertNotSame( array(), $board['layers'][0]['image']['sources'] );

		$trucks = $this->answer( array( SlotKey::DECK => 103 ), SlotKey::TRUCKS );
		self::assertSame( array( null ), array_values( array_unique( array_column( $trucks['options'], 'board' ), SORT_REGULAR ) ), 'the trucks\' cards carry none' );
	}

	// D-308 and F14: each deck card on the shelf carries its board, sold out or not, though the step's list, cut to its
	// filters and its first page, leaves it out; and a card is as it is listed, without the picture's large photo.
	public function test_each_deck_the_shelf_lists_carries_its_board_sold_out_or_not_and_its_card_no_large_photo(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::DECK ][] = Shop::priced( Builds::deck( '8.5', 104 ), 7000, false );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$this->layers->attachments = array( 103 => 13, 104 => 14 );
		$this->layers->images      = array(
			13 => new ImageFacts( 'image/png', 432, 1626, true ),
			14 => new ImageFacts( 'image/png', 432, 1626, true ),
		);
		$state = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, array(), null, SlotKey::DECK );
		self::assertNotNull( $state );
		$facts = new InMemoryPartLooks();
		foreach ( $parts[ SlotKey::DECK ] as $part ) {
			$facts->facts[ $part->id ] = new LookFact( $part->id, $part->id, 0, 'Part ' . $part->id, array(), array(), false, 0, 0 );
		}
		$folding = new class() implements TextFolding {
			public function fold( string $text ): string {
				return strtolower( $text );
			}
		};
		$answer = ( new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ) )
			->withArtwork( new LayerArtwork( $this->layers, $this->renditions ) )
			->withShelf( new StepShelf( $facts, $this->cards, new FakeRanking(), $folding, $this->settings ) );
		$link   = BuildLink::read(
			array(
				'step'   => 'deck',
				'_width' => '8.5in',
				'_brand' => 'nobody',
			)
		);
		$shown  = $answer->of( $state, $link );
		self::assertSame( array(), $shown['options'], 'no deck is of that brand: the step\'s list is empty' );
		$cards = array_column( array_column( array( ...$shown['shelf']['looks'], ...$shown['shelf']['sold_out'] ), 'part' ), null, 'id' );
		self::assertSame( array( 103, 104 ), array_keys( $cards ) );
		foreach ( $cards as $id => $card ) {
			self::assertIsArray( $card['board'], 'deck ' . $id );
			self::assertSame( array( SlotKey::DECK ), array_column( $card['board']['layers'], 'slot' ) );
			self::assertArrayHasKey( 'large', InMemoryPartCards::card( $id ) );
			self::assertIsArray( $card['card'] );
			self::assertArrayNotHasKey( 'large', $card['card'], 'the large photo is the picture\'s alone' );
		}
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
				SlotKey::WHEELS => 302,
			),
			SlotKey::BEARINGS
		);
		$picture = $answer['picture'];
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::TRUCKS ), array_column( $picture['layers'], 'slot' ), 'the wheels picked have no layer image' );
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

	// Issue #46: the top "from its own layer image's shape", "the grip's layer image becomes a swatch filled into the
	// deck's shape", "bolt heads drawn at the deck's holes"; the composed picture stays the underside.
	public function test_the_top_draws_the_decks_shape_the_grip_over_it_and_the_bolt_heads_and_those_parts_lose_their_labelled_photos(): void {
		$this->layers->attachments = array(
			103 => 13,
			701 => 71,
			601 => 61,
		);
		$this->layers->images      = array(
			13 => new ImageFacts( 'image/png', 432, 1626, true ),
			71 => new ImageFacts( 'image/png', 51, 1626, true ),
			61 => new ImageFacts( 'image/png', 40, 40, true ),
		);
		$picture = $this->answer( self::COMPLETE, SlotKey::GRIP )['picture'];

		self::assertSame( array( SlotKey::DECK ), array_column( $picture['layers'], 'slot' ), 'underneath, the deck alone has a layer image: no grip strip' );
		self::assertSame( array( SlotKey::DECK, SlotKey::GRIP, ...array_fill( 0, 8, SlotKey::HARDWARE ) ), array_column( $picture['top'], 'slot' ), 'the wheels have no layer image; the trucks are never on the top' );
		// From 1.1.0 the grip is its whole sheet, the usual 9 by 33 in, centred on the 8.5 by 32 in deck (F18, D-307).
		self::assertSame( array( -63500, -127000, 2286000, 8382000 ), array( $picture['top'][1]['x'], $picture['top'][1]['y'], $picture['top'][1]['width'], $picture['top'][1]['height'] ), 'the grip is its sheet, centred on the deck' );
		self::assertSame( 'https://example.com/layer-71.png', $picture['top'][1]['image']['src'] );
		self::assertSame(
			array( SlotKey::DECK => true, SlotKey::TRUCKS => false, SlotKey::WHEELS => false, SlotKey::BEARINGS => false, SlotKey::HARDWARE => true, SlotKey::GRIP => true ),
			array_column( $picture['items'], 'drawn', 'slot' ),
			'a part either side draws has no labelled photo'
		);
	}

	public function test_a_deck_with_no_layer_image_has_no_top_whatever_the_grip_has(): void {
		$this->layers->attachments = array( 701 => 71 );
		$this->layers->images      = array( 71 => new ImageFacts( 'image/png', 51, 1626, true ) );
		$picture                   = $this->answer( self::COMPLETE, SlotKey::GRIP )['picture'];

		self::assertSame( array(), $picture['top'], 'the deck\'s image is the top\'s shape' );
		self::assertSame( array(), array_filter( array_column( $picture['items'], 'drawn' ) ), 'so the grip keeps its labelled photo' );
	}

	public function test_a_part_whose_product_has_gone_is_in_the_box_with_no_name_and_out_of_the_sentence(): void {
		$this->cards->missing = array( 103 );
		$answer               = $this->answer( self::COMPLETE, SlotKey::DECK );
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

	// #142: the filters' context also has the kit's assembly time in business days, for an assembled build, and the
	// parts the total counts with how many of each one build takes, so a plugin can give no date for a part on
	// backorder. A slot left empty is in neither.
	public function test_the_delivery_filters_are_given_the_assembly_time_and_each_part_with_its_quantity(): void {
		// Bolts sold in fours, so a build of eight takes two.
		$parts                       = Shop::parts();
		$parts[ SlotKey::HARDWARE ][0] = Shop::priced( Parts::of( array( 'bolt_length' => '1', 'set_size' => 4 ), 601 ), 200 );
		$this->kits->add( self::KIT, Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, null, array( 'schema_version' => 1, 'lead_days' => '2-4' ) ), $parts );
		$seen                           = array();
		$GLOBALS['cbuild_test_filters'] = array(
			'cbuild_arrival_estimate' => static function ( string $said, array $context ) use ( &$seen ): string {
				$seen['arrival'] = $context;
				return '';
			},
			'cbuild_delivery_line'    => static function ( string $said, array $context ) use ( &$seen ): string {
				$seen['line'] = $context;
				return '';
			},
		);
		$this->answer( self::COMPLETE, null, null, true );
		self::assertSame( $seen['arrival'], $seen['line'], 'the same for both' );
		self::assertSame( '2-4', $seen['line']['lead_days'] );
		self::assertSame( self::COMPLETE, $seen['line']['parts'] );
		self::assertSame( array_keys( self::COMPLETE ), array_keys( $seen['line']['quantities'] ), 'the risers left empty are in neither' );
		self::assertSame( 2, $seen['line']['quantities'][ SlotKey::HARDWARE ], 'two packs of four bolts' );
		self::assertSame( 1, $seen['line']['quantities'][ SlotKey::TRUCKS ], 'one pair of trucks' );
		self::assertSame( array( 'kit', 'assembly', 'lead_time', 'total', 'decimals', 'lead_days', 'parts', 'quantities', 'replace' ), array_keys( $seen['line'] ), 'added after 1.1.0\'s keys, and 1.4.0\'s after 1.2.0\'s' );
		self::assertSame( '', $seen['line']['replace'], 'no Edit build, and a Delivery with no cart' );

		$this->answer( self::COMPLETE, null, null, false );
		self::assertSame( '', $seen['line']['lead_days'], 'a build the shop does not assemble has none' );
		self::assertSame( self::COMPLETE, $seen['line']['parts'], 'and the same parts' );
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

	public function test_an_answer_without_the_shelf_says_null_as_an_earlier_releases_container_builds_it(): void {
		self::assertNull( $this->answer( array(), SlotKey::WHEELS )['shelf'] );
	}

	public function test_the_shelf_lists_a_step_as_cards_with_their_part_sizes_and_choices(): void {
		$looks = array(
			301 => new LookFact( 30, 5, 0, 'Retna', array( 'pa_colour' => array( 'mint' ) ), array( 'pa_colour' => array( 'mint' => 'Mint' ) ), false, 0, 0 ),
			302 => new LookFact( 30, 5, 0, 'Retna', array( 'pa_colour' => array( 'black' ) ), array( 'pa_colour' => array( 'black' => 'Black' ) ), false, 0, 0 ),
		);
		$shelf = $this->shelf( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ), SlotKey::WHEELS, null, $looks );
		self::assertNotNull( $shelf );
		$cards = array_column( $shelf['looks'], null, 'key' );
		self::assertArrayHasKey( '30:5:0', $cards, 'two wheels on one photo, one card' );
		$card = $cards['30:5:0'];
		self::assertSame( 30, $card['product'] );
		self::assertContains( $card['part']['id'], array( 301, 302 ) );
		self::assertSame( array( 'id', 'brand', 'size', 'delta', 'fit', 'in_stock', 'available', 'quantity', 'picked', 'recommended', 'messages', 'card', 'board', 'presets' ), array_keys( $card['part'] ), 'the part as an option carries it' );
		self::assertSame(
			array(
				array(
					'value' => '52mm',
					'text'  => "52\u{a0}mm",
				),
				array(
					'value' => '54mm',
					'text'  => "54\u{a0}mm",
				),
			),
			$card['sizes']
		);
		self::assertSame(
			array(
				'pa_colour' => array(
					array(
						'value' => 'mint',
						'label' => 'Mint',
					),
					array(
						'value' => 'black',
						'label' => 'Black',
					),
				),
			),
			(array) $card['choices'],
			'an object, so that none is {} in JSON'
		);
		self::assertIsObject( $card['choices'] );
		self::assertSame( array( 'name' => 'wheel_diameter', 'label' => 'Diameter' ), array_intersect_key( $shelf['chips'][0], array_flip( array( 'name', 'label' ) ) ), 'the size where the shopper picks it; the shop\'s parts have no brand' );
		self::assertSame( array( 'value' => '52mm', 'label' => "52\u{a0}mm", 'count' => 1, 'selected' => false ), $shelf['chips'][0]['chips'][0] );
		self::assertSame( array(), $shelf['widths'] );
		self::assertSame( array( 2, 2, null, '', 'recommended' ), array( $shelf['offered'], $shelf['matching'], $shelf['more'], $shelf['query'], $shelf['sort'] ) );
		self::assertSame( array(), $shelf['sold_out'] );
	}

	// A card names its product, not the size-suffixed variation, and a look by what tells it from its product's
	// other looks (D-273).
	public function test_a_card_names_its_product_its_brand_and_what_tells_its_look_from_the_products_others(): void {
		$colour = static fn( string $slug, string $name ): array => array( 'pa_colour' => array( $slug => $name ) );
		$looks  = array(
			301 => new LookFact( 30, 5, 0, 'Conical', array( 'pa_colour' => array( 'mint' ) ), $colour( 'mint', 'Mint' ), false, 0, 0 ),
			302 => new LookFact( 30, 6, 0, 'Conical', array( 'pa_colour' => array( 'black' ) ), $colour( 'black', 'Black' ), false, 0, 0 ),
			303 => new LookFact( 40, 7, 0, 'Classic', array( 'pa_colour' => array( 'red' ) ), $colour( 'red', 'Red' ), false, 0, 0 ),
		);
		$shelf = $this->shelf( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ), SlotKey::WHEELS, null, $looks );
		self::assertNotNull( $shelf );
		$cards = array_column( $shelf['looks'], null, 'key' );
		ksort( $cards );
		self::assertSame(
			array(
				'30:5:0' => array( 'Conical', '', array( 'Mint' ) ),
				'30:6:0' => array( 'Conical', '', array( 'Black' ) ),
			),
			array_map( static fn( array $card ): array => array( $card['name'], $card['brand'], $card['look'] ), array_intersect_key( $cards, array_flip( array( '30:5:0', '30:6:0' ) ) ) ),
			'the shop\'s parts have no brand'
		);
		$all = array_column( array( ...$shelf['looks'], ...$shelf['sold_out'] ), null, 'key' );
		self::assertSame( array(), $all['40:7:0']['look'] ?? null, 'a product with one look needs no name' );
	}

	public function test_the_deck_step_asks_the_width_and_the_link_carries_the_shelf(): void {
		$asking = $this->shelf( array(), SlotKey::DECK );
		self::assertSame( array(), $asking['looks'] ?? null );
		self::assertSame(
			array(
				array(
					'value'    => '8in',
					'text'     => '8″',
					'in_stock' => 1,
					'selected' => false,
				),
				array(
					'value'    => '8.25in',
					'text'     => '8.25″',
					'in_stock' => 1,
					'selected' => true,
				),
				array(
					'value'    => '8.5in',
					'text'     => '8.5″',
					'in_stock' => 1,
					'selected' => false,
				),
			),
			$this->shelf( array(), SlotKey::DECK, BuildLink::read( array( '_width' => '8.25in' ) ) )['widths'] ?? null
		);
		$listed = $this->shelf( array(), SlotKey::DECK, BuildLink::read( array( '_width' => '8.25in', '_sort' => 'price-desc', '_q' => 'part' ) ) );
		self::assertSame( array( 102 ), array_column( array_column( $listed['looks'] ?? array(), 'part' ), 'id' ) );
		self::assertSame( array( 'part', 'price-desc' ), array( $listed['query'] ?? null, $listed['sort'] ?? null ) );
		$query = $this->shelf( array(), SlotKey::DECK, BuildLink::read( array( '_width' => '8.25in' ) ), array(), new ShelfQuery( '8.5in' ) );
		self::assertSame( array( 103 ), array_column( array_column( $query['looks'] ?? array(), 'part' ), 'id' ), 'arguments sent beside the link are the route\'s own, and come first' );
		self::assertNull( $this->shelf( self::COMPLETE, 'review' ) );
	}

	public function test_a_page_follows_the_links_count_and_a_page_from_the_route_starts_where_asked(): void {
		$looks = array();
		foreach ( array( 301, 302, 303 ) as $id ) {
			$looks[ $id ] = new LookFact( $id, $id, 0, 'Wheel ' . $id, array(), array(), false, 0, 0 );
		}
		$page = $this->shelf( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ), SlotKey::WHEELS, null, $looks, new ShelfQuery( from: 1, shown: 1 ) );
		self::assertCount( 1, $page['looks'] ?? array() );
		self::assertSame( 2, $page['more'] ?? null );
	}

	/**
	 * The answer for a build of the usual kit, with smart swaps (#135).
	 *
	 * @param array<mixed>    $picks The picks.
	 * @param array<int, int> $ranks The shop's ranks, by part.
	 * @return array<string, mixed>
	 */
	private function swapping( array $picks, string $step, array $ranks = array() ): array {
		$state = ( new BuildState( $this->kits, $this->settings, $this->logger ) )->of( self::KIT, $picks, null, $step, null );
		self::assertNotNull( $state );
		$ranked = new class( $ranks ) implements PartRanks {
			/**
			 * The parts asked about, each call's.
			 *
			 * @var list<list<int>>
			 */
			public array $asked = array();

			/**
			 * @param array<int, int> $ranks The ranks.
			 */
			public function __construct( private array $ranks ) {}

			public function ranks( int ...$parts ): array {
				$this->asked[] = $parts;
				return array_intersect_key( $this->ranks, array_flip( $parts ) );
			}

			public function of( Part ...$parts ): array {
				return $this->ranks( ...array_map( static fn( Part $part ): int => $part->id, $parts ) );
			}
		};
		$answer = ( new StateAnswer( $this->cards, new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ) )->withSwaps( new BuildSwaps( $ranked ) );
		$of     = $answer->of( $state, null );
		$of['asked'] = $ranked->asked;
		return $of;
	}

	public function test_the_review_offers_the_board_for_less_then_each_slots_swaps_each_with_the_totals_change(): void {
		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '10' ) );
		$review                = $this->swapping( self::COMPLETE, State::REVIEW );
		$kinds                 = array_map( static fn( array $swap ): string => $swap['kind'] . ' ' . implode( ',', array_column( $swap['parts'], 'part', 'slot' ) ), $review['swaps'] );
		self::assertSame( array( 'board 301', 'less 301', 'better 602' ), $kinds, 'the board for less, then each slot in step order: the trucks and wheels for 8.5 have nothing better that fits' );
		self::assertSame( array( SlotKey::WHEELS ), array_column( $review['swaps'][0]['parts'], 'slot' ) );
		self::assertSame( 302, $review['swaps'][1]['parts'][0]['was'] );
		self::assertSame( 'Part 301', $review['swaps'][1]['parts'][0]['name'], 'named by its card' );
		foreach ( $review['swaps'] as $swap ) {
			$after = self::COMPLETE;
			foreach ( $swap['parts'] as $part ) {
				$after[ $part['slot'] ] = $part['part'];
			}
			$total = $this->swapping( $after, State::REVIEW )['total']['total']['amount'];
			self::assertSame( $total - $review['total']['total']['amount'], $swap['change']['amount'], $swap['kind'] . ', with the build discount' );
		}
		self::assertSame( array( -180, '$1.80' ), array( $review['swaps'][1]['change']['amount'], $review['swaps'][1]['change']['text'] ), 'the 2.00 less is 1.80 with 10 % off; the text has no sign' );
		self::assertSame( array( 45, '$0.45' ), array( $review['swaps'][2]['change']['amount'], $review['swaps'][2]['change']['text'] ) );
	}

	public function test_a_step_asks_for_the_ranks_of_its_own_slots_parts_alone_and_a_step_with_no_pick_asks_nothing(): void {
		$wheels = $this->swapping( self::COMPLETE, SlotKey::WHEELS, array( 302 => 1, 303 => 5, 301 => 2 ) );
		self::assertSame( array( 'better 301' ), array_map( static fn( array $swap ): string => $swap['kind'] . ' ' . $swap['parts'][0]['part'], $wheels['swaps'] ), 'ranked above the pick and fitting: the 58s need risers' );
		self::assertCount( 1, $wheels['asked'] );
		sort( $wheels['asked'][0] );
		self::assertSame( array( 301, 302, 303 ), $wheels['asked'][0] );

		$open = self::COMPLETE;
		unset( $open[ SlotKey::WHEELS ] );
		foreach ( array( $this->swapping( $open, SlotKey::WHEELS ), $this->swapping( self::COMPLETE, SlotKey::DECK ) ) as $none ) {
			self::assertSame( array(), $none['swaps'] );
			self::assertSame( array(), $none['asked'], 'nothing looked up' );
		}
		self::assertSame( array(), $this->answer( self::COMPLETE, State::REVIEW )['swaps'], 'none without the swaps' );
	}

	// #135: the board for less takes, in a later slot, a part the build's own picks hid. The 58 mm wheels need the 1/4"
	// risers, which hide the 1" bolts; once the board drops to the 52s and the 1/8" risers, the 1" bolts fit and cost less.
	public function test_the_board_for_less_takes_a_part_the_picks_it_changes_had_hidden(): void {
		$picks  = array(
			SlotKey::WHEELS   => 303,
			SlotKey::RISERS   => 402,
			SlotKey::HARDWARE => 602,
		) + self::COMPLETE;
		$review = $this->swapping( $picks, State::REVIEW );
		self::assertSame( 'board', $review['swaps'][0]['kind'] );
		self::assertSame(
			array(
				SlotKey::WHEELS   => 301,
				SlotKey::RISERS   => 401,
				SlotKey::HARDWARE => 601,
			),
			array_column( $review['swaps'][0]['parts'], 'part', 'slot' )
		);
	}

	// #135: a part for less whose saving the build discount rounds away is no saving to offer, alone or on the board.
	// A better one is offered whatever it costs.
	public function test_a_part_for_less_whose_saving_rounds_to_nothing_is_not_offered(): void {
		$parts                      = Shop::parts();
		$parts[ SlotKey::WHEELS ][] = Shop::priced( Builds::wheel( '52', 305 ), 2999 );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$picks = array( SlotKey::WHEELS => 301 ) + self::COMPLETE;
		$kinds = static fn( array $answer ): array => array_map( static fn( array $swap ): string => $swap['kind'] . ' ' . implode( ',', array_column( $swap['parts'], 'part', 'slot' ) ) . ' ' . $swap['change']['amount'], $answer['swaps'] );

		self::assertSame( array( 'board 305 -1', 'less 305 -1', 'better 302 200', 'better 602 50' ), $kinds( $this->swapping( $picks, State::REVIEW ) ), 'a cent less is a saving' );
		$this->settings->terms = BuildTerms::read( array( BuildTerms::DISCOUNT => '50' ) );
		self::assertSame( array( 'better 302 100', 'better 602 25' ), $kinds( $this->swapping( $picks, State::REVIEW ) ), 'half a cent less rounds to the same total' );
	}

	// #135: the kit's Offer smart swaps, and a plugin's answer to cbuild_swaps, turn the swaps off on a build that has some.
	public function test_a_plugin_that_turns_the_swaps_off_leaves_a_build_none(): void {
		self::assertNotSame( array(), $this->swapping( self::COMPLETE, State::REVIEW )['swaps'] );
		$GLOBALS['cbuild_test_filters']['cbuild_swaps'] = static fn( bool $offers, int $kit ): bool => ! ( $offers && self::KIT === $kit );
		self::assertSame( array(), $this->swapping( self::COMPLETE, State::REVIEW )['swaps'] );
	}
}
