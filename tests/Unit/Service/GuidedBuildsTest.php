<?php
/**
 * The ready builds the guided start recommends.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\GuidedBuilds;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeMoney;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCards;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Issue #134, Help me choose: the builds a shopper's answers recommend, made from the parts the state read, each priced
 * as the cart prices a build and kept within a budget, or the closest where none is, with its deck's picture. The kit
 * is the usual one: an 8, an 8.25 and an 8.5 deck, the 8.25 with no photo.
 */
final class GuidedBuildsTest extends TestCase {

	private const int KIT = 7;

	private InMemoryKits $kits;

	private InMemoryPartCards $cards;

	protected function setUp(): void {
		$this->kits  = new InMemoryKits();
		$this->cards = new InMemoryPartCards();
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
	}

	/**
	 * The build's state at the kit's start, or at a step with picks.
	 *
	 * @param array<string, int> $picks The picks.
	 */
	private function state( array $picks = array(), string $step = SlotKey::DECK ): State {
		$state = ( new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ) )->of( self::KIT, $picks, null, $step );
		self::assertNotNull( $state );
		return $state;
	}

	/**
	 * The builds recommended.
	 *
	 * @param PartCards|null $cards The parts' cards, or the usual ones.
	 * @return array{budget: int|null, over: bool, builds: list<array<string, mixed>>}
	 */
	private function builds( ?Measure $width = null, ?int $budget = null, ?PartCards $cards = null ): array {
		return ( new GuidedBuilds( $this->kits, $cards ?? $this->cards, new FakeMoney() ) )->of( $this->state(), $width, null, $budget );
	}

	/**
	 * What each build is, short: its tier, its picks, its total, and how far over the budget it is.
	 *
	 * @param array{budget: int|null, over: bool, builds: list<array<string, mixed>>} $made The builds.
	 * @return list<array{string, array<string, int>, string, int, string}>
	 */
	private static function made( array $made ): array {
		return array_map( static fn( array $build ): array => array( $build['tier']->value, $build['picks'], $build['total'], $build['amount'], $build['over'] ), $made['builds'] );
	}

	/**
	 * A complete of the usual parts.
	 *
	 * @return array<string, int>
	 */
	private static function complete( int $deck, int $trucks, int $wheels, int $bearings = 501 ): array {
		return array(
			SlotKey::DECK     => $deck,
			SlotKey::TRUCKS   => $trucks,
			SlotKey::WHEELS   => $wheels,
			SlotKey::BEARINGS => $bearings,
			SlotKey::HARDWARE => 601,
			SlotKey::GRIP     => 701,
		);
	}

	// #134: with no answer, a build at each tier, each its picks in the kit's order, its total as the shop writes it and in
	// minor units, and its deck's picture whole: the photo at the single-product size, none for a deck with none.
	public function test_each_tier_is_a_build_with_its_picks_its_total_and_its_decks_picture(): void {
		$made = $this->builds();
		self::assertSame( array( null, false ), array( $made['budget'], $made['over'] ) );
		self::assertSame(
			array(
				array( 'budget', self::complete( 101, 139, 301 ), '$167.00', 16700, '' ),
				array( 'mid', self::complete( 102, 144, 301 ), '$174.00', 17400, '' ),
				array( 'premium', self::complete( 103, 149, 302 ), '$183.00', 18300, '' ),
			),
			self::made( $made )
		);
		self::assertSame( array( 101, 102, 103 ), array_column( $made['builds'], 'deck' ) );
		self::assertSame( array( InMemoryPartCards::card( 101 )['large'], null, InMemoryPartCards::card( 103 )['large'] ), array_column( $made['builds'], 'image' ), 'the large photo, not the cropped thumbnail' );
		self::assertSame( array( false, false, false ), array_column( $made['builds'], 'drawn' ) );
		self::assertSame( array( array( 101, 102, 103 ) ), $this->cards->asked, 'the decks\' cards, asked once' );
	}

	// #134: the budget is a whole amount, held against the totals in the currency's smallest units. Builds within it are
	// kept, one at it among them; with none within, the closest, saying how far over it is.
	public function test_builds_within_the_budget_are_kept_and_with_none_the_closest_says_how_far_over_it_is(): void {
		$within = $this->builds( null, 200 );
		self::assertSame( array( 20000, false ), array( $within['budget'], $within['over'] ) );
		self::assertSame( array( 'budget', 'mid', 'premium' ), array_column( self::made( $within ), 0 ) );

		$at = $this->builds( null, 167 );
		self::assertSame( array( 16700, false ), array( $at['budget'], $at['over'] ) );
		self::assertSame( array( array( 'budget', self::complete( 101, 139, 301 ), '$167.00', 16700, '' ) ), self::made( $at ), 'a total at the budget is within it' );

		$over = $this->builds( null, 150 );
		self::assertSame( array( 15000, true ), array( $over['budget'], $over['over'] ) );
		self::assertSame( array( 3, 3, 3 ), array( $within['fit'], $at['fit'], $over['fit'] ), 'F40 (#163): every build that fits, whatever the budget keeps' );
		self::assertSame( array( array( 'budget', self::complete( 101, 139, 301 ), '$167.00', 16700, '$17.00' ) ), self::made( $over ) );

		$this->kits->decimals = 0;
		self::assertSame( 200, $this->builds( null, 200 )['budget'], 'a currency with no decimals' );
	}

	// #134: a width keeps the decks of that width; two builds on one deck ask for its card once, and no build asks none.
	public function test_builds_at_a_width_share_its_decks_card_and_no_build_asks_for_none(): void {
		$made = $this->builds( Measure::inches( 85000 ) );
		self::assertSame( array( 103, 103 ), array_column( $made['builds'], 'deck' ) );
		self::assertSame( array( array( 103 ) ), $this->cards->asked );

		$this->cards->asked = array();
		self::assertSame( array(), $this->builds( Measure::inches( 90000 ) )['builds'], 'no 9 inch deck' );
		self::assertSame( array(), $this->cards->asked );
	}

	// #134: the decks' cards are asked for as a list, each deck once, in the order the builds have them.
	public function test_the_decks_cards_are_asked_for_as_a_list_each_once(): void {
		$parts                        = Shop::parts();
		$parts[ SlotKey::DECK ]       = array( $parts[ SlotKey::DECK ][0], $parts[ SlotKey::DECK ][2] );
		$parts[ SlotKey::BEARINGS ][] = Shop::priced( Builds::bearings( '8', 502 ), 1600 );
		$parts[ SlotKey::BEARINGS ][] = Shop::priced( Builds::bearings( '8', 503 ), 1700 );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		self::assertSame(
			array(
				array( 'budget', self::complete( 101, 139, 301 ), '$167.00', 16700, '' ),
				array( 'mid', self::complete( 101, 139, 301, 502 ), '$168.00', 16800, '' ),
				array( 'premium', self::complete( 103, 149, 302, 503 ), '$185.00', 18500, '' ),
			),
			self::made( $this->builds() )
		);
		self::assertSame( array( array( 101, 103 ) ), $this->cards->asked );
	}

	// D-282, D-408, D-461: a deck with no photo shows its layer image, as its card has it, marked drawn; a photo is not,
	// and a card that does not say which it shows is taken for a photo.
	public function test_a_deck_shown_by_its_layer_image_is_marked_drawn(): void {
		$cards = new class() implements PartCards {
			public function cards( array $ids ): array {
				$image = static fn( int $id ): array => array( 'src' => "https://shop.test/{$id}.png" );
				return array(
					101 => array(
						'image' => $image( 101 ),
						'photo' => false,
						'large' => null,
					),
					102 => array( 'image' => $image( 102 ) ),
					103 => array(
						'image' => null,
						'photo' => false,
					),
				);
			}

			public function brands( array $slugs ): array {
				return array();
			}

			public function forget( int ...$ids ): void {}

			public function photo( int $attachment ): ?array {
				return null;
			}
		};
		$made  = $this->builds( null, null, $cards );
		self::assertSame( array( array( 'src' => 'https://shop.test/101.png' ), array( 'src' => 'https://shop.test/102.png' ), null ), array_column( $made['builds'], 'image' ) );
		self::assertSame( array( true, false, false ), array_column( $made['builds'], 'drawn' ) );
	}

	// #134: a kit whose deck is optional makes builds with none, and they have no deck's picture.
	public function test_a_build_with_no_deck_has_no_decks_picture(): void {
		$slots                         = KitDocuments::slots();
		$slots['slots'][0]['required'] = false;
		$this->kits->add( self::KIT, KitDocuments::kit( $slots ), Shop::parts() );
		$made = $this->builds();
		self::assertNotSame( array(), $made['builds'] );
		foreach ( $made['builds'] as $build ) {
			self::assertArrayNotHasKey( SlotKey::DECK, $build['picks'] );
			self::assertSame( array( 0, null, false ), array( $build['deck'], $build['image'], $build['drawn'] ) );
		}
		self::assertSame( array( array( 0 ) ), $this->cards->asked, 'no deck is 0, which no product is' );
	}

	// #134: each slot's parts the builds are made from are those the state read, offered and hidden alike.
	public function test_the_builds_are_made_from_each_slots_parts_offered_and_hidden(): void {
		$ids   = static fn( array $parts ): array => array_map( static fn( Part $part ): int => $part->id, $parts );
		$start = GuidedBuilds::candidates( $this->state() );
		self::assertSame( array( 101, 102, 103 ), $ids( $start[ SlotKey::DECK ] ) );

		$state  = $this->state( array( SlotKey::DECK => 101 ), SlotKey::TRUCKS );
		$hidden = array();
		foreach ( $state->steps as $step ) {
			$hidden[ $step->slot->key ] = $step->options->hidden;
		}
		self::assertNotSame( array(), $hidden[ SlotKey::TRUCKS ], 'trucks that do not fit the 8 inch deck' );
		$trucks = $ids( GuidedBuilds::candidates( $state )[ SlotKey::TRUCKS ] );
		sort( $trucks );
		self::assertSame( array( 139, 144, 149, 169 ), $trucks );
	}
}
