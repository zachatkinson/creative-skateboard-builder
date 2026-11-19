<?php
/**
 * Spec, The builder: which step a build lands on, and the document the one template is bound to.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Front\PageRenderer;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuilderView;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\Delivery;
use CreativeBranding\SkateboardBuilder\Front\ShippingWords;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeMoney;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeShippingZones;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCards;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

final class BuilderTest extends TestCase {

	private InMemoryLayerImages $layers;

	private InMemoryLayerRenditions $renditions;

	private const int KIT = 77;

	private const string BASE = 'https://shop.test/kit/';

	private const array STREET = array(
		SlotKey::DECK     => 101,
		SlotKey::TRUCKS   => 139,
		SlotKey::WHEELS   => 301,
		SlotKey::BEARINGS => 501,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

	private InMemoryKits $kits;

	protected function setUp(): void {
		$this->layers     = new InMemoryLayerImages();
		$this->renditions = new InMemoryLayerRenditions();
		$this->kits = new InMemoryKits();
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'] );
	}

	private function builder(): Builder {
		return new Builder(
			new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ),
			( new StateAnswer( new InMemoryPartCards(), new FakeMoney(), $this->kits, new Delivery( $this->kits, new FakeShippingZones(), new ShippingWords( new FakeMoney() ) ), new PageRenderer() ) )->withArtwork( new LayerArtwork( $this->layers, $this->renditions ) ),
			new BuilderView()
		);
	}

	/**
	 * @return array{state: State, answer: array<string, mixed>, view: array<string, mixed>}
	 */
	private function show( BuildLink $link, string $base = self::BASE ): array {
		$shown = $this->builder()->show( self::KIT, $link, $base );
		self::assertNotNull( $shown );
		return $shown;
	}

	/**
	 * @param array<string, string> $query The build's part of the query.
	 */
	private static function url( array $query, string $base = self::BASE ): string {
		return $base . '?' . http_build_query( array( 'cbuild' => $query ) );
	}

	/**
	 * A build's part of the query as the hidden fields of a form.
	 *
	 * @param array<string, string> $query The build's part of the query.
	 * @return list<array{name: string, value: string}>
	 */
	private static function fields( array $query ): array {
		$fields = array();
		foreach ( $query as $key => $value ) {
			$fields[] = array(
				'name'  => 'cbuild[' . $key . ']',
				'value' => $value,
			);
		}
		return $fields;
	}

	/**
	 * A preset whose wheels the shop has sold out of; Street is all in stock.
	 */
	private const array PARK = array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149, SlotKey::WHEELS => 302 ) + self::STREET;

	private function presets(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::WHEELS ] = array_map( static fn( Part $part ): Part => 302 === $part->id ? Shop::priced( $part, $part->price, false, 0 ) : $part, $parts[ SlotKey::WHEELS ] );
		$this->kits->add(
			self::KIT,
			Shop::kit(
				presets: array(
					array(
						'name'        => 'Street',
						'description' => 'For ledges and stairs.',
						'picks'       => self::STREET,
					),
					array(
						'name'  => 'Park',
						'picks' => self::PARK,
					),
				)
			),
			$parts
		);
	}

	// Spec, Presets: "Up to six tiles at the start ... Each shows its picture, its total and a line on who it's for",
	// and "A preset holding a sold-out part says so on its tile". Spec, Search: presets are buttons in GET forms.
	public function test_a_blank_build_starts_with_a_tile_for_each_preset_that_starts_its_build_at_the_review(): void {
		$this->presets();
		$presets = $this->show( new BuildLink() )['view']['presets'];
		self::assertTrue( $presets['shown'] );
		self::assertSame( 'Start from a ready build', $presets['heading'] );
		self::assertCount( 2, $presets['tiles'] );

		$street = $presets['tiles'][0];
		self::assertSame( 'Street', $street['name'] );
		self::assertSame( 'For ledges and stairs.', $street['description'] );
		self::assertTrue( $street['hasDescription'] );
		self::assertSame( $this->show( new BuildLink( self::STREET ) )['answer']['total']['total']['text'], $street['total'] );
		self::assertSame( array(), $street['notes'] );
		self::assertSame(
			self::fields(
				array(
					'deck'     => '101',
					'trucks'   => '139',
					'wheels'   => '301',
					'bearings' => '501',
					'hardware' => '601',
					'grip'     => '701',
					'step'     => 'review',
					'preset'   => '0',
				)
			),
			$street['fields'],
			'IDs, the review and which preset, as the build link writes them'
		);
		self::assertSame( 'Start from Street', $street['button'], 'each button has a name of its own (P11)' );
		self::assertTrue( $street['hasImage'] );
		self::assertSame( 'https://shop.test/101.jpg', $street['src'] );
		self::assertSame( 'https://shop.test/103.jpg', $presets['tiles'][1]['src'] );

		$park = $presets['tiles'][1];
		self::assertFalse( $park['hasDescription'] );
		self::assertSame( array( array( 'text' => 'Wheels: Part 302 is sold out.' ) ), $park['notes'] );
		self::assertSame( 'Start from Park', $park['button'] );
		self::assertContains( array( 'name' => 'cbuild[preset]', 'value' => '1' ), $park['fields'] );

		self::assertFalse( $this->show( new BuildLink( array( SlotKey::DECK => 101 ) ) )['view']['presets']['shown'], 'a build begun has no tiles' );
		self::assertTrue( $this->show( new BuildLink( array(), null, SlotKey::DECK, null, null, '', '8.5in' ) )['view']['presets']['shown'], 'the first step, a width pressed' );
		self::assertTrue( $this->show( new BuildLink( array(), null, BuildLink::REVIEW ) )['view']['presets']['shown'], 'a review asked for with nothing picked stops at the start' );
		self::assertFalse( $this->show( new BuildLink( array(), null, null, null, 0 ) )['view']['presets']['shown'], 'a build that began as a preset' );
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
		self::assertSame(
			array(
				'shown'   => false,
				'heading' => 'Start from a ready build',
				'tiles'   => array(),
			),
			$this->show( new BuildLink() )['view']['presets'],
			'a kit with no presets has none'
		);
	}

	public function test_a_preset_whose_part_is_no_longer_offered_says_so_once_by_name_and_shows_no_total(): void {
		// Cowork's review, minor 16: the tile said "Trucks: the part chosen is not one this kit offers here. Trucks:
		// nothing is chosen yet." and a total for a build that lacks its trucks.
		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array_values( array_filter( $parts[ SlotKey::TRUCKS ], static fn( Part $part ): bool => 139 !== $part->id ) );
		$this->kits->add(
			self::KIT,
			Shop::kit(
				presets: array(
					array(
						'name'  => 'Street',
						'picks' => self::STREET,
					),
				)
			),
			$parts
		);
		$tile = $this->show( new BuildLink() )['view']['presets']['tiles'][0];

		self::assertSame( array( array( 'text' => 'Trucks: Part 139 is no longer offered here.' ) ), $tile['notes'] );
		self::assertFalse( $tile['hasTotal'] );

		$this->presets();
		self::assertTrue( $this->show( new BuildLink() )['view']['presets']['tiles'][1]['hasTotal'], 'a sold-out part leaves the build whole, so its total stands' );
	}

	public function test_the_picture_says_nothing_under_it_at_a_blank_start_where_the_summary_says_it(): void {
		// Cowork's review, minor 15: "Nothing is chosen yet." showed twice, under the picture and in the summary.
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$blank = $this->show( new BuildLink() )['view'];
		self::assertFalse( $blank['picture']['captioned'] );
		self::assertTrue( $blank['summary']['empty'] );

		$begun = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS ) )['view'];
		self::assertTrue( $begun['picture']['captioned'] );
		self::assertStringStartsWith( 'The build so far: Deck: ', $begun['picture']['description'] );
	}

	// Spec, The picture: "Each part that has a layer image is drawn to scale"; the bar's small picture becomes the
	// board (plan, milestone 7; D-195).
	public function test_the_board_places_each_layer_as_a_share_of_it_and_the_bar_shows_the_same_board(): void {
		$this->layers->attachments = array(
			101 => 11,
			149 => 49,
		);
		$this->layers->images      = array(
			11 => new ImageFacts( 'image/png', 420, 1626, true ),
			49 => new ImageFacts( 'image/png', 432, 160, true ),
		);
		$this->renditions->make( 11, Renditions::drawn( SlotKey::DECK, Shop::parts()[ SlotKey::DECK ][0]->specs, 420, 1626 ) );
		$view  = $this->show( new BuildLink( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 149, SlotKey::WHEELS => 302 ), null, SlotKey::BEARINGS ) )['view'];
		$board = $view['picture']['board'];
		self::assertTrue( $board['shown'] );
		self::assertSame( array( 'deck-1', 'trucks-1', 'trucks-2' ), array_column( $board['layers'], 'key' ) );
		self::assertMatchesRegularExpression( '#^--cbuild-board-ratio:\d+ / 8128000$#', $board['style'] );
		self::assertSame( '--cbuild-layer-x:2.9412%;--cbuild-layer-y:0%;--cbuild-layer-width:94.1176%;--cbuild-layer-height:100%', $board['layers'][0]['style'], 'an 8 in deck centred on a board as wide as the 8.5 in axle, end to end' );
		self::assertSame( 'cbuild-board__layer cbuild-board__layer--deck', $board['layers'][0]['classes'] );
		self::assertSame( array( 'high', 'auto', 'auto' ), array_column( $board['layers'], 'priority' ), 'the deck is fetched first' );
		self::assertStringStartsWith( '(min-height: 960px) ', $board['layers'][0]['sizes'] );
		self::assertSame( array( 'image/webp' ), array_column( $board['layers'][0]['sources'], 'type' ) );
		self::assertSame( $board['layers'][0]['sizes'], $board['layers'][0]['sources'][0]['sizes'] );
		self::assertSame( array(), $board['layers'][1]['sources'], 'the trucks\' renditions are not made yet' );
		self::assertSame( 'https://example.com/layer-49.png', $board['layers'][1]['src'] );

		$items = array_column( $view['picture']['items'], 'drawn', 'key' );
		self::assertTrue( $items[ SlotKey::DECK ] && $items[ SlotKey::TRUCKS ] );
		self::assertFalse( $items[ SlotKey::WHEELS ], 'a part with no layer image keeps its labelled photo' );
		// Both reviews of milestone 7: a hidden photo that is eager is still fetched, at high priority, against the
		// deck's layer. A drawn deck's photo waits, and the layer alone is fetched first.
		self::assertSame( array( 'lazy', 'auto' ), array( $view['picture']['items'][0]['loading'], $view['picture']['items'][0]['priority'] ), 'the drawn deck\'s hidden photo' );

		$bar = $view['bar']['board'];
		self::assertTrue( $view['bar']['seen'] );
		self::assertSame( array_column( $board['layers'], 'style' ), array_column( $bar['layers'], 'style' ), 'the same board' );
		self::assertMatchesRegularExpression( '#^\d+px$#', $bar['layers'][0]['sizes'], 'at the bar\'s size' );
		self::assertSame( array( 'auto', 'auto', 'auto' ), array_column( $bar['layers'], 'priority' ) );
	}

	// The arithmetic of each layer's place and the sizes it is fetched at, where a share is not a whole number of
	// pixels: the board and the bar round each width up, so a rendition is never fetched smaller than it is drawn.
	public function test_each_layer_is_placed_and_sized_by_its_share_of_the_board_rounded_up(): void {
		$this->layers->attachments = array(
			102 => 11,
			139 => 49,
			301 => 31,
		);
		$this->layers->images      = array(
			11 => new ImageFacts( 'image/png', 420, 1626, true ),
			49 => new ImageFacts( 'image/png', 432, 160, true ),
			31 => new ImageFacts( 'image/png', 200, 200, true ),
		);
		$view = $this->show( new BuildLink( array( SlotKey::DECK => 102, SlotKey::TRUCKS => 139, SlotKey::WHEELS => 301 ), null, SlotKey::WHEELS ) )['view'];
		$board = $view['picture']['board'];
		self::assertSame( '--cbuild-board-ratio:2095500 / 8128000', $board['style'], 'an 8.25 in deck, wider than the axle' );
		self::assertSame(
			array(
				'(min-height: 960px) 149px, 15.4688vh',
				'(min-height: 960px) 144px, 15vh',
				'(min-height: 960px) 144px, 15vh',
				'(min-height: 960px) 23px, 2.3031vh',
				'(min-height: 960px) 23px, 2.3031vh',
				'(min-height: 960px) 23px, 2.3031vh',
				'(min-height: 960px) 23px, 2.3031vh',
			),
			array_column( $board['layers'], 'sizes' ),
			'576 pixels at most, and 60 % of the screen\'s height, times each layer\'s width over the board\'s length: 148.5 and 22.1 are 149 and 23'
		);
		self::assertSame(
			array(
				'--cbuild-layer-x:0%;--cbuild-layer-y:0%;--cbuild-layer-width:100%;--cbuild-layer-height:100%',
				'--cbuild-layer-x:1.5152%;--cbuild-layer-y:19.7422%;--cbuild-layer-width:96.9697%;--cbuild-layer-height:9.375%',
				'--cbuild-layer-x:1.5152%;--cbuild-layer-y:70.8828%;--cbuild-layer-width:96.9697%;--cbuild-layer-height:9.375%',
				'--cbuild-layer-x:1.5152%;--cbuild-layer-y:21.2309%;--cbuild-layer-width:14.889%;--cbuild-layer-height:6.3976%',
				'--cbuild-layer-x:83.5958%;--cbuild-layer-y:21.2309%;--cbuild-layer-width:14.889%;--cbuild-layer-height:6.3976%',
				'--cbuild-layer-x:1.5152%;--cbuild-layer-y:72.3715%;--cbuild-layer-width:14.889%;--cbuild-layer-height:6.3976%',
				'--cbuild-layer-x:83.5958%;--cbuild-layer-y:72.3715%;--cbuild-layer-width:14.889%;--cbuild-layer-height:6.3976%',
			),
			array_column( $board['layers'], 'style' ),
			'each place a share of the board\'s width across and its length along'
		);
		self::assertSame( array( '12px', '11px', '11px', '2px', '2px', '2px', '2px' ), array_column( $view['bar']['board']['layers'], 'sizes' ), 'at the bar\'s 44 pixels: 11.3 and 1.7 are 12 and 2' );
	}

	/**
	 * The deck, the grip and the hardware with layer images, the deck's renditions made.
	 */
	private function topArt(): void {
		$this->layers->attachments = array(
			101 => 11,
			701 => 71,
			601 => 61,
		);
		$this->layers->images      = array(
			11 => new ImageFacts( 'image/png', 420, 1626, true ),
			71 => new ImageFacts( 'image/png', 51, 1626, true ),
			61 => new ImageFacts( 'image/png', 40, 40, true ),
		);
		$this->renditions->make( 11, Renditions::drawn( SlotKey::DECK, Shop::parts()[ SlotKey::DECK ][0]->specs, 420, 1626 ) );
	}

	// Issue #46, settled on 17 November 2026: the deck's top "from its own layer image's shape: the cut-out's
	// transparency is the outline, used as a CSS mask"; "the grip's layer image becomes a swatch filled into the deck's
	// shape, nose to tail"; "bolt heads are drawn at the deck's holes"; "the grip step turns the board to the top".
	public function test_the_top_is_the_decks_outline_filled_with_the_grip_and_bolt_heads_and_the_grip_step_shows_it(): void {
		$this->topArt();
		$picks = array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139, SlotKey::WHEELS => 301, SlotKey::BEARINGS => 501, SlotKey::HARDWARE => 601, SlotKey::GRIP => 701 );
		$view  = $this->show( new BuildLink( $picks, null, SlotKey::GRIP ) )['view'];

		$picture = $view['picture'];
		self::assertTrue( $picture['turnable'] );
		self::assertTrue( $picture['turned'], 'the grip step shows the top' );
		self::assertSame( 'Show the top', $picture['turn'] );
		self::assertSame( array( 'under' => 'Seen from underneath.', 'top' => 'Seen from the top.' ), $picture['seen'] );

		$top = $picture['top'];
		self::assertTrue( $top['shown'] );
		self::assertSame( $picture['board']['style'], $top['style'], 'the same board, the other way up' );
		self::assertSame( array( 'deck-1', 'grip-1', 'hardware-1', 'hardware-2', 'hardware-3', 'hardware-4', 'hardware-5', 'hardware-6', 'hardware-7', 'hardware-8' ), array_column( $top['layers'], 'key' ), 'the wheels and trucks have no layer image' );
		self::assertSame( 'cbuild-board__layer cbuild-board__layer--grip', $top['layers'][1]['classes'] );

		// The deck's largest WebP rendition is the outline of the deck and of the grip over it.
		$mask = $picture['board']['layers'][0]['sources'][0]['srcset'];
		self::assertMatchesRegularExpression( '#(?:^|, )(https://example\.com/11-\d+x\d+\.webp) \d+w$#', $mask );
		preg_match( '#(https://example\.com/11-\d+x\d+\.webp) \d+w$#', $mask, $largest );
		self::assertSame( '--cbuild-layer-x:0%;--cbuild-layer-y:0%;--cbuild-layer-width:100%;--cbuild-layer-height:100%;--cbuild-mask:url("' . $largest[1] . '")', $top['layers'][0]['style'], 'the deck alone is maple in its own shape' );
		self::assertSame( '--cbuild-layer-x:0%;--cbuild-layer-y:0%;--cbuild-layer-width:100%;--cbuild-layer-height:100%;--cbuild-mask:url("' . $largest[1] . '");--cbuild-image:url("https://example.com/layer-71.png")', $top['layers'][1]['style'], 'the grip\'s own file, its renditions not made' );
		self::assertStringEndsWith( ';--cbuild-image:url("https://example.com/layer-61.png")', $top['layers'][2]['style'] );
		self::assertStringStartsWith( '--cbuild-layer-x:', $top['layers'][2]['style'] );
		self::assertStringNotContainsString( '--cbuild-mask', $top['layers'][2]['style'], 'a bolt head is its own image' );

		$items = array_column( $picture['items'], 'drawn', 'key' );
		self::assertTrue( $items[ SlotKey::GRIP ] && $items[ SlotKey::HARDWARE ], 'drawn on the top, so no labelled photo' );

		$after = $this->show( new BuildLink( $picks, null, BuildLink::REVIEW ) )['view']['picture'];
		self::assertFalse( $after['turned'], 'and back after it' );
		self::assertTrue( $after['turnable'] );
		$before = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS ) )['view']['picture'];
		self::assertSame( array( true, false ), array( $before['turnable'], $before['turned'] ), 'a step before the grip shows the underside' );
		self::assertSame( array( 'deck-1' ), array_column( $before['top']['layers'], 'key' ), 'the plain top, maple in the deck\'s shape' );
	}

	public function test_a_deck_with_no_layer_image_has_no_top_to_turn_to_even_on_the_grip_step(): void {
		$this->topArt();
		$this->layers->attachments = array( 701 => 71 );
		$picture                   = $this->show( new BuildLink( array( SlotKey::DECK => 101, SlotKey::GRIP => 701 ), null, SlotKey::GRIP ) )['view']['picture'];

		self::assertSame( array( false, false, false ), array( $picture['turnable'], $picture['turned'], $picture['top']['shown'] ) );
		self::assertSame( array(), $picture['top']['layers'] );
	}

	/**
	 * The top from an answer as it is: a board with no width or no length draws no top, rather than dividing by
	 * nothing; the first deck layer's image is the outline; and a source that is not a map is not read.
	 */
	public function test_the_top_needs_a_size_takes_the_first_decks_outline_and_reads_only_sources_that_are_maps(): void {
		$top    = new \ReflectionMethod( BuilderView::class, 'top' );
		$layer  = static fn( string $slot, string $src, array $sources = array() ): array => array(
			'slot'   => $slot,
			'part'   => 9,
			'x'      => 0,
			'y'      => 0,
			'width'  => 216,
			'height' => 813,
			'image'  => array(
				'src'     => $src,
				'sources' => $sources,
			),
		);
		$layers = array( $layer( SlotKey::DECK, 'https://shop.test/first.png' ), $layer( SlotKey::DECK, 'https://shop.test/second.png' ) );
		foreach ( array( array( 216, 0 ), array( 0, 813 ) ) as list( $width, $height ) ) {
			$drawn = $top->invoke( null, array( 'width' => $width, 'height' => $height, 'top' => $layers ), 77 );
			self::assertSame( array( false, '', array() ), array( $drawn['shown'], $drawn['style'], $drawn['layers'] ), "a board {$width} by {$height}" );
		}

		$drawn = $top->invoke( null, array( 'width' => 216, 'height' => 813, 'top' => $layers ), 77 );
		self::assertStringEndsWith( '--cbuild-mask:url("https://shop.test/first.png")', $drawn['layers'][1]['style'], 'the first deck\'s outline, for every layer' );

		$object = (object) array( 'type' => 'image/webp', 'srcset' => 'https://shop.test/object.webp 216w' );
		$drawn  = $top->invoke( null, array( 'width' => 216, 'height' => 813, 'top' => array( $layer( SlotKey::DECK, 'https://shop.test/own.png', array( $object ) ) ) ), 77 );
		self::assertStringEndsWith( '--cbuild-mask:url("https://shop.test/own.png")', $drawn['layers'][0]['style'], 'the image\'s own file' );
	}

	public function test_an_address_is_written_into_a_style_as_a_quoted_url_that_nothing_in_it_can_close(): void {
		$url = new \ReflectionMethod( BuilderView::class, 'url' );
		self::assertSame( 'url("https://shop.test/a%20b%22c%5C)%0A.png?x=1&y=(2)")', $url->invoke( null, "https://shop.test/a b\"c\\)\n.png?x=1&y=(2)" ) );
	}

	public function test_a_picture_with_no_width_or_no_length_draws_no_board(): void {
		foreach ( array( array( 0, 8128000 ), array( 2095500, 0 ), array( 0, 0 ) ) as [ $width, $length ] ) {
			$picture = array(
				'width'  => $width,
				'height' => $length,
				'layers' => array(
					array(
						'slot'   => SlotKey::DECK,
						'part'   => 101,
						'x'      => 0,
						'y'      => 0,
						'width'  => 2032000,
						'height' => 8128000,
					),
				),
			);
			$board   = ( new \ReflectionMethod( BuilderView::class, 'board' ) )->invoke( null, $picture, self::KIT, null );
			self::assertSame( array( 'shown' => false, 'style' => '', 'layers' => array() ), $board, $width . ' by ' . $length );
		}
	}

	// Spec, A step, Sold out: "A preset or link that holds one names it, and the build can't go to the cart until
	// it's swapped".
	public function test_a_sold_out_part_a_preset_brings_is_named_at_the_review_and_keeps_the_build_from_the_cart(): void {
		$this->presets();
		$view = $this->show( new BuildLink( self::PARK, null, BuildLink::REVIEW, null, 1 ) )['view'];
		self::assertTrue( $view['isReview'] );
		self::assertSame( array( 'Wheels: Part 302 is sold out.' ), array_column( $view['errors']['items'], 'text' ) );
		self::assertSame( self::url( array( 'deck' => '103', 'trucks' => '149', 'wheels' => '302', 'bearings' => '501', 'hardware' => '601', 'grip' => '701', 'step' => 'wheels', 'preset' => '1' ) ), $view['errors']['items'][0]['url'], 'to the step that swaps it' );
		self::assertFalse( $view['review']['ready'] );
		self::assertFalse( $view['presets']['shown'] );
	}

	// Spec, The total: "On phones it's a bar at the bottom: a small picture of the board, the total, and the next
	// step on its button ("Next: Wheels"). Tapping the total opens the parts list above the bar" (W7).
	public function test_the_phone_bar_holds_the_total_the_parts_and_the_next_step_on_its_button(): void {
		$view = $this->show( new BuildLink( array( SlotKey::DECK => 101 ) ) )['view'];
		self::assertSame(
			array(
				'shown' => true,
				'seen'  => true,
				'total'  => 'Total: ' . $view['summary']['total'],
				// The bar shows the amount alone, and reads the sentence to a screen reader: beside the board's
				// picture "Total:" broke the line at 412 pixels, and the bar grew under the thumb (Zach, 16 Nov).
				'amount' => $view['summary']['total'],
				'parts'  => 'Show the parts',
				'hide'  => 'Hide the parts',
				// The footer holds Back beside Next, as the prototype has it; the step's own Back is hidden.
				'back'  => array(
					'shown' => true,
					'url'   => self::url( array( 'deck' => '101', 'step' => 'deck' ) ),
					'text'  => 'Back',
					'label' => 'Back: Deck',
				),
				'next'  => array(
					'shown'    => true,
					'onStep'   => true,
					'onReview' => false,
					'text'     => 'Next: Wheels',
					'form'     => 'cbuild-step-form',
					'name'     => 'cbuild[step]',
					'value'    => 'wheels',
					'disabled' => false,
				),
			),
			// The estimate has a test of its own, below.
			array_diff_key( $view['bar'], array_flip( array( 'photo', 'picture', 'board', 'hasEstimate', 'estimate' ) ) )
		);
		self::assertSame( $view['step']['next']['text'], $view['bar']['next']['text'], "the step's own Next, sent from its form" );
		$bar   = array_diff_key( $view['bar']['photo'], array( 'classes' => true ) );
		$photo = array_intersect_key( $view['picture']['items'][0], $bar );
		self::assertSame( $photo, $bar, 'a small picture of the board: for now, the first part\'s photo' );
		self::assertSame( 'cbuild-bar__photo', $view['bar']['photo']['classes'] );
		self::assertTrue( $view['bar']['photo']['hasImage'] );
		self::assertSame( 'See the picture', $view['bar']['picture'] );
		self::assertFalse( $this->show( new BuildLink() )['view']['bar']['photo']['hasImage'], 'nothing picked, no photo' );
		self::assertFalse( $this->show( new BuildLink() )['view']['bar']['seen'], 'and no picture to link to' );
		self::assertFalse( $view['bar']['board']['shown'], 'no part has a layer image' );

		$review = $this->show( new BuildLink( self::STREET, null, BuildLink::REVIEW ) )['view']['bar']['next'];
		self::assertSame(
			array(
				'shown'    => true,
				'onStep'   => false,
				'onReview' => true,
				'text'     => 'Add to cart',
				'form'     => 'cbuild-review-form',
				'name'     => '',
				'value'    => '',
				'disabled' => false,
			),
			$review
		);
		self::assertTrue( $this->show( new BuildLink( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 139 ) + self::STREET, null, BuildLink::REVIEW ) )['view']['bar']['next']['disabled'], 'a build that cannot go to the cart' );

		$was   = array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139, SlotKey::WHEELS => 301 );
		$panel = $this->show( new BuildLink( array( SlotKey::DECK => 103 ) + $was, $was, SlotKey::TRUCKS ) )['view']['bar'];
		self::assertTrue( $panel['shown'] );
		self::assertFalse( $panel['next']['shown'], 'the panel asks for Use these or Undo, in its own forms' );
		self::assertFalse( $panel['next']['onStep'] || $panel['next']['onReview'], 'so neither of the bar\'s buttons is shown' );
	}

	public function test_a_kit_the_shop_does_not_have_shows_nothing(): void {
		self::assertNull( $this->builder()->show( 5, new BuildLink(), self::BASE ) );
	}

	public function test_a_new_build_opens_on_the_deck_with_a_card_for_each_deck_and_no_way_back(): void {
		$view = $this->show( new BuildLink() )['view'];

		self::assertSame( self::KIT, $view['kit'] );
		self::assertSame( 'Choose your deck', $view['heading'] );
		self::assertSame( 'Step 1 of 7:', $view['count'] );
		self::assertSame( 'Step 1 of 7: Deck', $view['title'], 'The title keeps the count and the name; the heading says the question (D-269).' );
		self::assertTrue( $view['isStep'] );
		self::assertFalse( $view['isChanges'] );
		self::assertFalse( $view['isReview'] );
		self::assertFalse( $view['errors']['shown'] );
		self::assertSame( array(), $view['errors']['items'] );
		self::assertSame( self::url( array( 'step' => 'deck' ) ), $view['url'], 'The address names the step it shows.' );
		self::assertSame( self::BASE, $view['clean'] );
		self::assertSame( self::BASE, $view['action'] );
		self::assertSame( array(), $view['carry'] );

		self::assertSame( array( 'deck', 'trucks', 'wheels', 'bearings', 'hardware', 'grip', 'review' ), array_column( $view['progress'], 'key' ), 'Risers the kit does not require are no step.' );
		self::assertSame(
			array(
				'key'     => 'deck',
				'label'   => 'Deck',
				'state'   => 'Current step',
				'done'    => false,
				'current' => true,
				'url'     => '',
				'aria'    => 'step',
			),
			$view['progress'][0]
		);
		self::assertSame( array( 'To come', false, false, '', false ), array( $view['progress'][1]['state'], $view['progress'][1]['done'], $view['progress'][1]['current'], $view['progress'][1]['url'], $view['progress'][1]['aria'] ) );
		self::assertSame( 'Review', $view['progress'][6]['label'] );

		$step = $view['step'];
		self::assertSame( 'deck', $step['slot'] );
		self::assertSame( 'Deck', $step['legend'] );
		self::assertSame( 'cbuild[deck]', $step['name'] );
		self::assertSame( 'cbuild[step]', $step['stepName'] );
		self::assertTrue( $step['hasLead'], 'The deck step says to start with a width until a deck is chosen.' );
		self::assertSame( 'Start with a width. Every later step offers only what fits the deck you choose.', $step['lead'] );
		self::assertFalse( $step['hasHelp'] );
		self::assertSame( array( 101, 102, 103 ), array_column( $step['options'], 'id' ) );
		self::assertFalse( $step['empty'] );
		self::assertFalse( $step['leave']['shown'], 'A deck is required.' );
		// A hidden None checked would be the group's checked radio: Safari's Tab then reaches no card at all.
		self::assertFalse( $step['leave']['checked'], 'A step that offers no None has none checked.' );
		self::assertSame( 'cbuild-77-deck-none', $step['leave']['id'] );
		self::assertSame( 'None', $step['leave']['label'] );
		self::assertSame(
			array(
				'shown' => false,
				'url'   => '',
				'text'  => '',
			),
			$step['back']
		);
		self::assertSame(
			array(
				'value' => 'trucks',
				'text'  => 'Next: Trucks',
			),
			$step['next']
		);
		self::assertSame( array(), $step['before'] );
		self::assertSame( array(), $step['after'] );
		self::assertSame( array( array( 'name' => 'cbuild[_on]', 'value' => 'deck' ) ), $step['tail'], 'the step its widths are pressed on' );

		$card = $step['options'][0];
		self::assertSame( 'cbuild-77-101', $card['input'] );
		self::assertSame( array( 'cbuild-77-101-about', 'cbuild-77-101-notes', 'cbuild-77-101-about cbuild-77-101-notes' ), array( $card['about'], $card['notesId'], $card['describedby'] ), 'The radio is described by the rest of its card.' );
		self::assertSame( '101', $card['value'] );
		self::assertSame( 'Part 101', $card['name'] );
		self::assertSame( 'Choose Part 101', $card['choose'] );
		self::assertSame( 'See the page for Part 101', $card['page'] );
		self::assertSame( 'https://shop.test/?p=101', $card['url'] );
		self::assertFalse( $card['checked'] );
		self::assertFalse( $card['disabled'] );
		self::assertFalse( $card['soldOut'] );
		self::assertSame( array( '$101', '', false ), array( $card['price'], $card['delta'], $card['hasDelta'] ), 'the full price, and no change before the step has a pick (D-249)' );
		self::assertSame( array( '', false, '', false, '', false ), array( $card['brand'], $card['hasBrand'], $card['look'], $card['hasLook'], $card['also'], $card['hasAlso'] ), 'a part listed alone has no look' );
		self::assertSame( 'In stock', $card['stock'] );
		self::assertSame( 'cbuild-card__stock cbuild-card__stock--in-stock', $card['stockClass'] );
		self::assertSame( '', $card['fit'], 'A deck is not fitted against a deck.' );
		self::assertSame( 'cbuild-card__fit cbuild-card__fit--fits', $card['fitClass'] );
		self::assertSame( 'cbuild-card cbuild-card--fits', $card['classes'] );
		self::assertSame( array(), $card['notes'] );
		self::assertSame( 'Recommended', $card['recommendedAs'] );
		self::assertSame( array( true, 'https://shop.test/101.jpg', 300, 300 ), array( $card['hasImage'], $card['src'], $card['width'], $card['height'] ) );
		self::assertFalse( $step['options'][1]['hasImage'], 'Part 102 has no photo.' );
		self::assertSame( '', $step['options'][1]['src'] );

		self::assertTrue( $view['summary']['empty'] );
		self::assertSame( array(), $view['summary']['rows'] );
		self::assertSame( 'Assembly: not added', $view['summary']['assembly'] );
		self::assertSame( 'Add assembly', $view['summary']['toggle']['text'] );
		// A button in a GET form, as every way into a state is (spec: SEO and sharing; milestone 6's audit 29).
		self::assertSame( self::fields( array( 'step' => 'deck', 'assembly' => '1' ) ), $view['summary']['toggle']['fields'] );
		self::assertArrayNotHasKey( 'url', $view['summary']['toggle'], 'No link into a state for a crawler to follow.' );
		self::assertSame( '$0.00', $view['summary']['total'] );
		self::assertFalse( $view['summary']['hasSaved'] );
		self::assertFalse( $view['summary']['hasShips'] );
		self::assertFalse( $view['summary']['hasArrival'] );
		self::assertSame( self::KIT, $view['summary']['kit'] );
		self::assertSame( 'Nothing is chosen yet. Total: $0.00.', $view['status'] );
		// The stage is headed as the prototype's is, with the side shown and a place-holder until a deck is picked.
		self::assertSame( 'Your board', $view['picture']['heading'] );
		self::assertSame( 'Pick a deck to start', $view['picture']['empty'] );
		self::assertSame( array( 'under' => 'Seen from underneath', 'top' => 'Seen from the top' ), $view['picture']['side'] );
		self::assertSame( array(), $view['picture']['items'] );
		self::assertFalse( $view['review']['ready'] );
	}

	public function test_a_later_step_carries_the_picks_round_it_and_recommends_what_fits_the_deck(): void {
		$link = new BuildLink( array( SlotKey::DECK => 103, SlotKey::GRIP => 701 ), null, SlotKey::TRUCKS, true, 4 );
		$view = $this->show( $link )['view'];
		$step = $view['step'];

		self::assertSame( 'Choose your trucks', $view['heading'] );
		self::assertSame( 'Step 2 of 7:', $view['count'] );
		self::assertSame( 'done', $view['progress'][0]['done'] ? 'done' : 'not done' );
		self::assertSame( 'Done', $view['progress'][0]['state'] );
		self::assertSame( self::url( array( 'deck' => '103', 'grip' => '701', 'step' => 'deck', 'assembly' => '1', 'preset' => '4' ) ), $view['progress'][0]['url'] );
		self::assertFalse( $step['hasLead'] );
		self::assertSame(
			array(
				array(
					'name'  => 'cbuild[deck]',
					'value' => '103',
				),
			),
			$step['before']
		);
		self::assertSame(
			array(
				array(
					'name'  => 'cbuild[grip]',
					'value' => '701',
				),
			),
			$step['after']
		);
		self::assertSame(
			array(
				array(
					'name'  => 'cbuild[assembly]',
					'value' => '1',
				),
				array(
					'name'  => 'cbuild[preset]',
					'value' => '4',
				),
				array(
					'name'  => 'cbuild[_was][deck]',
					'value' => '103',
				),
				array(
					'name'  => 'cbuild[_was][grip]',
					'value' => '701',
				),
			),
			$step['tail'],
			'What the server needs to say what the pick does to the parts after it.'
		);
		self::assertSame( 'Back: Deck', $step['back']['text'] );
		self::assertTrue( $step['back']['shown'] );
		self::assertSame( self::url( array( 'deck' => '103', 'grip' => '701', 'step' => 'deck', 'assembly' => '1', 'preset' => '4' ) ), $step['back']['url'] );
		self::assertSame( 'Next: Wheels', $step['next']['text'] );

		// Nothing is picked for the shopper (D-213): the trucks that fit an 8.5″ deck are labelled, and not chosen.
		self::assertSame( array(), array_values( array_filter( $step['options'], static fn( array $card ): bool => $card['checked'] ) ) );
		$recommended = array_values( array_filter( $step['options'], static fn( array $card ): bool => $card['recommended'] ) );
		self::assertCount( 1, $recommended );
		self::assertSame( 149, $recommended[0]['id'] );
		self::assertSame( 'Recommended for your 8.5″ deck', $recommended[0]['recommendedAs'] );
		self::assertSame( 'Fits your 8.5″ deck', $recommended[0]['fit'] );
		self::assertSame( 'cbuild-card cbuild-card--fits', $recommended[0]['classes'] );

		self::assertSame( array( 'deck', 'grip' ), array_column( $view['summary']['rows'], 'key' ), 'Each slot picked is counted, and no other.' );
		self::assertSame( array( 'Deck', 'Part 103', '', '$70.00' ), array( $view['summary']['rows'][0]['label'], $view['summary']['rows'][0]['name'], $view['summary']['rows'][0]['count'], $view['summary']['rows'][0]['amount'] ) );
		self::assertSame( 'Assembly: $15.00', $view['summary']['assembly'] );
		self::assertSame( 'Remove assembly', $view['summary']['toggle']['text'] );
		self::assertSame( 'deck', $view['picture']['items'][0]['key'] );
		self::assertSame( 'Deck: 8.5″', $view['picture']['items'][0]['label'] );
		self::assertSame( 'Part 103', $view['picture']['items'][0]['name'] );
		// Spec, The picture: the deck is the page's largest paint, and the rest wait to be scrolled to.
		self::assertSame( array( 'eager', 'high' ), array( $view['picture']['items'][0]['loading'], $view['picture']['items'][0]['priority'] ) );
		self::assertSame( array( 'lazy', 'auto' ), array( $view['picture']['items'][1]['loading'], $view['picture']['items'][1]['priority'] ) );
	}

	public function test_a_part_that_does_not_fit_says_so_and_one_the_shop_cannot_supply_stays_chosen_while_the_build_holds_it(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array( Shop::priced( $parts[ SlotKey::TRUCKS ][0], 5000, false ), $parts[ SlotKey::TRUCKS ][2] );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$step = $this->show( new BuildLink( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139 ), null, SlotKey::TRUCKS ) )['view']['step'];

		self::assertSame( array( 139 ), array_column( $step['options'], 'id' ), 'The 149s do not fit an 8″ deck, so they are hidden.' );
		self::assertTrue( $step['options'][0]['soldOut'] );
		self::assertFalse( $step['options'][0]['disabled'], 'The build holds it, so the form still sends it.' );
		self::assertSame( 'cbuild-card cbuild-card--fits cbuild-card--sold-out cbuild-card--chosen', $step['options'][0]['classes'] );
		self::assertSame( 'Fits your 8″ deck', $step['options'][0]['fit'] );

		$parts[ SlotKey::DECK ] = array( Shop::priced( $parts[ SlotKey::DECK ][0], 6000, false ), $parts[ SlotKey::DECK ][1] );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$step = $this->show( new BuildLink( array( SlotKey::DECK => 102 ), null, SlotKey::DECK ) )['view']['step'];
		self::assertSame( array( 102, 101 ), array_column( $step['options'], 'id' ), 'What the shop cannot supply comes last.' );
		self::assertTrue( $step['options'][1]['disabled'], 'One the build does not hold cannot be picked.' );
		self::assertFalse( $step['options'][1]['checked'] );
		self::assertFalse( $step['options'][0]['disabled'] );
	}

	public function test_a_cards_fit_label_names_what_the_part_was_checked_against(): void {
		// Cowork's review, should-fix 5: wheels said "Fits your 8 in deck" though the trucks are what they are read with.
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$step = $this->show( new BuildLink( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139 ), null, SlotKey::WHEELS ) )['view']['step'];
		self::assertSame( 'Fits your trucks', $step['options'][0]['fit'] );
		self::assertSame( 'Recommended for your trucks', $step['options'][0]['recommendedAs'] );

		$deck = $this->show( new BuildLink( array(), null, SlotKey::DECK ) )['view']['step'];
		self::assertSame( '', $deck['options'][0]['fit'], 'the deck is checked against nothing before it' );
	}

	public function test_a_review_asked_for_with_no_deck_stops_on_the_deck_and_links_to_it(): void {
		$view = $this->show( new BuildLink( array(), null, State::REVIEW ) )['view'];

		self::assertSame( 'Choose your deck', $view['heading'] );
		self::assertSame( 'Step 1 of 7:', $view['count'] );
		self::assertSame( 'Error: Step 1 of 7: Deck', $view['title'] );
		self::assertTrue( $view['errors']['shown'] );
		self::assertSame( 'Fix these before going on', $view['errors']['heading'], 'The heading says what to do; each item names its problem (audit minor 27).' );
		self::assertSame(
			array(
				array(
					'text' => 'Deck: nothing is chosen yet.',
					'url'  => self::url( array( 'step' => 'deck' ) ),
				),
			),
			$view['errors']['items']
		);
	}

	public function test_risers_are_a_step_once_the_build_holds_some_and_can_be_left_out(): void {
		$state = $this->show( new BuildLink( array( SlotKey::DECK => 101 ) ) )['state'];
		self::assertNotContains( SlotKey::RISERS, Builder::steps( $state ) );

		$view = $this->show( new BuildLink( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139, SlotKey::WHEELS => 301, SlotKey::RISERS => 401 ), null, SlotKey::RISERS ) )['view'];
		self::assertSame( 'Choose your risers', $view['heading'] );
		self::assertSame( 'Step 4 of 8:', $view['count'] );
		self::assertTrue( $view['step']['leave']['shown'] );
		self::assertSame( 'No risers', $view['step']['leave']['label'] );
		self::assertFalse( $view['step']['leave']['checked'] );
		self::assertSame( 'cbuild-77-risers-none', $view['step']['leave']['id'] );
		self::assertSame( '−$5.00', $view['step']['leave']['delta'], 'Leaving them out takes their line off.' );

		$kit                         = KitDocuments::slots();
		$kit['slots'][3]['required'] = true;
		$this->kits->add( self::KIT, KitDocuments::kit( $kit ), Shop::parts() );
		self::assertContains( SlotKey::RISERS, Builder::steps( $this->show( new BuildLink() )['state'] ), 'A kit that requires risers asks for them.' );
	}

	/**
	 * The shelf's part of a step with no shelf: every field there, and nothing shown.
	 *
	 * @return array<string, mixed>
	 */
	private static function emptyShelf(): array {
		return array(
			'width'   => array(
				'shown'   => false,
				'asking'  => true,
				'legend'  => 'What width do you ride?',
				'name'    => 'cbuild[_width]',
				'options' => array(),
				'hasNone' => false,
				'none'    => '',
			),
			'search'  => array(
				'shown'  => false,
				'label'  => 'Search parts',
				'hint'   => 'Brand or name',
				'name'   => 'cbuild[_q]',
				'value'  => '',
				'button' => 'Search',
			),
			'chips'   => array(
				'shown'  => false,
				'groups' => array(),
				'apply'  => 'Apply',
			),
			'sort'    => array(
				'shown'   => false,
				'label'   => 'Sort',
				'name'    => 'cbuild[_sort]',
				'options' => array(
					array( 'value' => 'recommended', 'text' => 'Recommended', 'selected' => true ),
					array( 'value' => 'price-asc', 'text' => 'Price low to high', 'selected' => false ),
					array( 'value' => 'price-desc', 'text' => 'Price high to low', 'selected' => false ),
					array( 'value' => 'newest', 'text' => 'Newest', 'selected' => false ),
				),
			),
			'soldOut' => array(
				'shown'   => false,
				'summary' => 'Show 0 sold out',
				'options' => array(),
			),
		);
	}

	public function test_the_review_shows_no_step_and_its_step_fields_are_all_there_and_empty(): void {
		$view = $this->show( new BuildLink( self::STREET, null, State::REVIEW ) )['view'];
		self::assertSame(
			array(
				'slot'      => '',
				'legend'    => '',
				'help'      => '',
				'hasHelp'   => false,
				'lead'      => '',
				'hasLead'   => false,
				'name'      => '',
				'before'    => array(),
				'after'     => array(),
				'tail'      => array(),
				'options'   => array(),
				'hidden'    => array(
					'shown'   => false,
					'summary' => '',
					'groups'  => array(),
					'items'   => array(),
				),
				'filters'   => array(
					'shown'   => false,
					'open'    => false,
					'heading' => 'Filter the parts',
					'groups'  => array(),
				),
				'more'      => array(
					'shown' => false,
					'name'  => 'cbuild[_shown]',
					'value' => '',
					'text'  => '',
					'count' => '0 of 0 parts shown',
				),
				'shelf'     => self::emptyShelf(),
				'empty'     => false,
				'emptyText' => '',
				'leave'     => array(
					'shown'   => false,
					'id'      => '',
					'label'   => '',
					'checked' => false,
					'delta'   => '',
				),
				'back'      => array(
					'shown' => false,
					'url'   => '',
					'text'  => '',
				),
				'next'      => array(
					'value' => '',
					'text'  => '',
				),
				'stepName'  => '',
			),
			$view['step'],
			'The store binds every field whatever the step.'
		);
		self::assertSame( 'Your build', $view['summary']['heading'] );
		self::assertSame( 'Nothing is chosen yet.', $view['summary']['emptyText'] );
		self::assertSame( 'Total', $view['summary']['totalLabel'] );
		self::assertSame( array( 'Done', 'Done', 'Done', 'Done', 'Done', 'Done', 'Current step' ), array_column( $view['progress'], 'state' ) );
	}

	public function test_a_pick_the_kit_cannot_take_is_named_with_a_link_to_the_step(): void {
		$shown = $this->show( BuildLink::read( array( 'deck' => '101', 'trucks' => '301' ) ) );
		$view  = $shown['view'];
		self::assertTrue( $view['errors']['shown'] );
		self::assertSame(
			array(
				array(
					'text' => $shown['answer']['refused'][0]['message'],
					'url'  => $view['url'],
				),
			),
			$view['errors']['items']
		);
		self::assertNotSame( '', $view['errors']['items'][0]['text'] );
		self::assertStringStartsWith( 'Error: ', $view['title'] );
	}

	// Zach, 16 Nov 2026 (#45): the total counts what was picked, and an estimate under it says what a complete board
	// would come to with each recommendation (D-213).
	public function test_the_summary_says_the_estimate_under_the_total_while_a_recommendation_fills_a_slot(): void {
		$view = $this->show( new BuildLink( array( SlotKey::DECK => 101 ) ) )['view'];
		self::assertSame( '$60.00', $view['summary']['total'] );
		self::assertTrue( $view['summary']['hasEstimate'] );
		self::assertMatchesRegularExpression( '/^A complete board from about \$\d+\.\d\d$/', $view['summary']['estimate'] );
		self::assertStringEndsWith( ' Total: $60.00. ' . $view['summary']['estimate'] . '.', $view['status'], 'said once, with the pick' );

		$whole = $this->show( new BuildLink( self::STREET, null, BuildLink::REVIEW ) )['view'];
		self::assertFalse( $whole['summary']['hasEstimate'], 'every slot picked: the total says it all' );
		self::assertStringEndsWith( ' Total: ' . $whole['summary']['total'] . '.', $whole['status'] );
		self::assertFalse( $this->show( new BuildLink() )['view']['summary']['hasEstimate'], 'nothing to estimate before the deck' );
	}

	// Cowork's 0.8.0 review, F2 (#54): on a phone the summary is in the shut parts sheet, so the bar gives the estimate
	// itself, on a line under the amount, while one is given. The amount stays alone (D-207).
	public function test_the_phone_bar_gives_the_estimate_under_its_amount_while_one_is_given(): void {
		$view = $this->show( new BuildLink( array( SlotKey::DECK => 101 ) ) )['view'];
		self::assertSame( '$60.00', $view['bar']['amount'], 'the amount alone' );
		self::assertTrue( $view['bar']['hasEstimate'] );
		self::assertMatchesRegularExpression( '/^Complete from about \$\d+\.\d\d$/', $view['bar']['estimate'] );
		self::assertStringEndsWith( substr( $view['summary']['estimate'], strlen( 'A complete board from about ' ) ), $view['bar']['estimate'], 'the summary\'s amount' );

		$whole = $this->show( new BuildLink( self::STREET, null, BuildLink::REVIEW ) )['view'];
		self::assertFalse( $whole['bar']['hasEstimate'], 'every slot picked: no estimate' );
		self::assertFalse( $this->show( new BuildLink() )['view']['bar']['hasEstimate'], 'nothing before the deck' );
	}

	public function test_a_card_lists_its_notes_and_the_summary_what_other_plugins_add(): void {
		$specs                    = array(
			'baseplate'     => 'new_school',
			'axle_diameter' => '8',
			'set_size'      => 2,
		);
		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array( Shop::priced( Parts::indy( '149', $specs ), 5400 ) );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$GLOBALS['cbuild_test_filters']['cbuild_summary_notes'] = static fn(): array => array( 'Ships in a box.', 'Free stickers.' );
		$view = $this->show( new BuildLink( array( SlotKey::DECK => 103 ), null, SlotKey::TRUCKS ) )['view'];

		// Cowork's review, should-fix 4: the trucks give no largest wheel, which is the owner's to fix (the catalogue
		// check says so), not a note on a card that fits.
		self::assertSame( array(), $view['step']['options'][0]['notes'], 'a card that fits says nothing of what was assumed' );
		self::assertSame( array( array( 'text' => 'Ships in a box.' ), array( 'text' => 'Free stickers.' ) ), $view['summary']['notes'] );

		$wheels = $this->show( new BuildLink( array( SlotKey::DECK => 103, SlotKey::TRUCKS => 149 ), null, SlotKey::WHEELS ) )['view']['step']['options'];
		$warned = array_values( array_filter( $wheels, static fn( array $card ): bool => 303 === $card['id'] ) )[0];
		self::assertStringContainsString( 'cbuild-card--warn', $warned['classes'] );
		self::assertSame( array( array( 'text' => "Wheels of 58\u{00A0}mm are 2\u{00A0}mm over what the trucks clear, which is 56\u{00A0}mm. A riser of ¼″ or thicker makes the room." ) ), $warned['notes'], 'a card that warns keeps its notes' );
	}

	// Spec, Review and add to cart: "It also shows assembly on or off with its fee and lead time, the arrival date,
	// the total, and the returns notice the owner writes, all before Add to cart."
	public function test_the_review_says_assembly_the_arrival_and_the_total_before_add_to_cart(): void {
		$GLOBALS['cbuild_test_filters']['cbuild_arrival_estimate'] = static fn(): string => 'Arrives by Thu, Nov 19, assembled';
		$view = $this->show( new BuildLink( self::STREET, null, BuildLink::REVIEW, true ) )['view'];
		$summary = $view['summary'];
		self::assertSame( $summary['assembly'], $view['review']['assembly'] );
		self::assertSame( 'Assembly: $15.00', $view['review']['assembly'] );
		self::assertSame( 'Total: ' . $summary['total'], $view['review']['total'] );
		self::assertTrue( $view['review']['hasArrival'] );
		self::assertSame( 'Arrives by Thu, Nov 19, assembled', $view['review']['arrival'] );

		unset( $GLOBALS['cbuild_test_filters'] );
		$review = $this->show( new BuildLink( self::STREET, null, BuildLink::REVIEW, false ) )['view']['review'];
		self::assertSame( 'Assembly: not added', $review['assembly'] );
		self::assertFalse( $review['hasArrival'] );
		self::assertSame( '', $review['arrival'] );
	}

	public function test_a_later_step_fits_against_the_parts_its_rules_read(): void {
		// The trucks picked count, and the wheels are read with them (Cowork's review, should-fix 5).
		$cards = $this->show( new BuildLink( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139 ), null, SlotKey::WHEELS ) )['view']['step']['options'];
		self::assertSame( 'Fits your trucks', $cards[0]['fit'] );
		self::assertSame( 303, $cards[2]['id'] );
		self::assertSame( 'Fits, with a note', $cards[2]['fit'], '58 mm wheels want a riser under these trucks.' );
		self::assertSame( 'cbuild-card__fit cbuild-card__fit--warn', $cards[2]['fitClass'] );
	}

	public function test_picks_left_empty_by_choice_travel_as_nothing_and_picks_before_without_a_change_stay_out_of_the_address(): void {
		$step = $this->show( new BuildLink( self::STREET, null, SlotKey::GRIP, none: array( SlotKey::RISERS ) ) )['view']['step'];
		self::assertContains(
			array(
				'name'  => 'cbuild[risers]',
				'value' => '0',
			),
			$step['before']
		);

		$view = $this->show( new BuildLink( self::STREET, self::STREET, SlotKey::GRIP ) )['view'];
		self::assertFalse( $view['isChanges'] );
		self::assertStringNotContainsString( '_was', urldecode( $view['url'] ) );
	}

	public function test_undo_goes_back_to_the_first_step_that_changed(): void {
		$was  = array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139, SlotKey::WHEELS => 301 );
		$now  = array( SlotKey::DECK => 103, SlotKey::TRUCKS => 139, SlotKey::WHEELS => 302 );
		$undo = $this->show( new BuildLink( $now, $was, SlotKey::WHEELS ) )['view']['changes']['undo']['fields'];
		self::assertSame( self::fields( array( 'deck' => '101', 'trucks' => '139', 'wheels' => '301', 'step' => 'deck' ) ), $undo );
	}

	public function test_a_page_address_with_odd_queries_keeps_them_as_fields(): void {
		$view = $this->show( new BuildLink(), 'https://shop.test/?5=a?b' )['view'];
		self::assertSame( 'https://shop.test/', $view['action'] );
		self::assertSame(
			array(
				array(
					'name'  => '5',
					'value' => 'a?b',
				),
			),
			$view['carry']
		);
	}

	public function test_wheels_that_need_risers_keep_the_risers_step_even_with_no_risers_chosen(): void {
		$picks = array(
			SlotKey::DECK   => 101,
			SlotKey::TRUCKS => 139,
			SlotKey::WHEELS => 303,
		);
		$state = $this->show( new BuildLink( $picks, null, SlotKey::WHEELS ) )['state'];
		self::assertContains( SlotKey::RISERS, Builder::steps( $state ), '58 mm wheels want a riser under these trucks, so one is recommended.' );

		$view = $this->show( new BuildLink( $picks, null, SlotKey::RISERS, none: array( SlotKey::RISERS ) ) )['view'];
		self::assertSame( 'Choose your risers', $view['heading'], 'Left out, they are still asked about while the wheels need them.' );
		self::assertSame( 'Step 4 of 8:', $view['count'] );
		self::assertTrue( $view['step']['leave']['checked'] );
		self::assertSame( 'No risers', $view['step']['leave']['label'] );
	}

	public function test_wheels_that_now_need_risers_stop_on_the_risers_step_before_going_past_it(): void {
		$picks = array(
			SlotKey::DECK   => 101,
			SlotKey::TRUCKS => 139,
			SlotKey::WHEELS => 303,
		);
		$shown = $this->show( new BuildLink( $picks, null, SlotKey::BEARINGS ) );
		self::assertSame( SlotKey::RISERS, $shown['state']->step, 'The wheels step\'s Next said Bearings before 58 mm wheels made risers a step.' );
		self::assertSame( 'Choose your risers', $shown['view']['heading'] );
		self::assertSame( 'Step 4 of 8:', $shown['view']['count'] );
		self::assertFalse( $shown['view']['errors']['shown'], 'Nothing is wrong: a riser is recommended, to be seen.' );

		self::assertSame( SlotKey::BEARINGS, $this->show( new BuildLink( $picks + array( SlotKey::RISERS => 402 ), null, SlotKey::BEARINGS ) )['state']->step, 'Seen and kept, they let the shopper on.' );
		self::assertSame( SlotKey::BEARINGS, $this->show( new BuildLink( $picks, null, SlotKey::BEARINGS, none: array( SlotKey::RISERS ) ) )['state']->step, 'So does No risers.' );
		self::assertSame( SlotKey::WHEELS, $this->show( new BuildLink( $picks, null, SlotKey::WHEELS ) )['state']->step, 'A step before them is shown as asked.' );
	}

	public function test_a_step_that_is_no_step_gives_way_to_the_next_and_the_last_to_the_review(): void {
		$shown = $this->show( new BuildLink( self::STREET, null, SlotKey::RISERS ) );
		self::assertSame( SlotKey::BEARINGS, $shown['state']->step );
		self::assertSame( 'Choose your bearings', $shown['view']['heading'] );
		self::assertSame( 'Step 4 of 7:', $shown['view']['count'] );
	}

	public function test_edit_builds_review_sends_the_build_it_replaces_and_its_shared_link_does_not(): void {
		$review = $this->show( new BuildLink( self::STREET, array( 'deck' => 101 ), State::REVIEW, true, 0, 'Acme', '8in', 48, 'abc-123' ) )['view']['review'];
		self::assertContains(
			array(
				'name'  => 'cbuild[replace]',
				'value' => 'abc-123',
			),
			$review['fields'],
			'The add takes the place of the build in the cart Edit build came from (D-157).'
		);
		self::assertSame(
			self::url( array_map( 'strval', self::STREET ) + array( 'step' => 'review', 'assembly' => '1' ) ),
			$review['link'],
			'A shared link names parts, the step and assembly, and nothing of the shopper\'s cart, the builder\'s own or the preset (spec: Share links).'
		);
		$link = new BuildLink( self::STREET, array( 'deck' => 101 ), State::REVIEW, true, 0, 'Acme', '8in', 48, 'abc-123' );
		self::assertSame( self::url( $link->shared()->query( Shop::kit()->slots ) ), $review['link'], 'One rule for what a shared link holds: BuildLink::shared() (audit 15).' );
		self::assertNotContains( 'cbuild[replace]', array_column( $this->show( new BuildLink( self::STREET, null, State::REVIEW ) )['view']['review']['fields'], 'name' ) );

		// Cowork's review, should-fix 7: the spec's "Saving there replaces the build", in the words. The bar's
		// button is the review's.
		$view = $this->show( $link )['view'];
		self::assertSame( 'Save changes', $view['review']['add'] );
		self::assertSame( 'Save changes', $view['bar']['next']['text'] );
		self::assertTrue( $view['review']['replacing'] );
		self::assertSame( 'Saving replaces the build in your cart.', $view['review']['replaces'] );
		self::assertSame( 'Your cart has the changed build.', $view['review']['added'] );
		$fresh = $this->show( new BuildLink( self::STREET, null, State::REVIEW ) )['view']['review'];
		self::assertSame( array( 'Add to cart', false, 'The build is in your cart.' ), array( $fresh['add'], $fresh['replacing'], $fresh['added'] ) );
	}

	public function test_the_review_names_every_step_and_posts_the_build_to_the_carts_own_handler(): void {
		$view = $this->show( new BuildLink( self::STREET, null, State::REVIEW ), 'https://shop.test/?page_id=5&lang=fr' )['view'];

		self::assertSame( 'Check your build', $view['heading'] );
		self::assertSame( 'Step 7 of 7:', $view['count'] );
		self::assertTrue( $view['isReview'] );
		self::assertFalse( $view['isStep'] );
		self::assertSame( 'https://shop.test/', $view['action'] );
		self::assertSame(
			array(
				array(
					'name'  => 'page_id',
					'value' => '5',
				),
				array(
					'name'  => 'lang',
					'value' => 'fr',
				),
			),
			$view['carry'],
			'A GET form drops its address\'s query, so the page\'s own travels as fields.'
		);
		self::assertSame( '', $view['step']['slot'], 'The review has no step.' );
		self::assertSame( array(), $view['step']['options'] );

		$review = $view['review'];
		self::assertSame( 'Check your build', $review['heading'] );
		self::assertTrue( $review['ready'] );
		self::assertSame( 'Add to cart', $review['add'] );
		self::assertSame( 'The build is in your cart.', $review['added'], 'Said once an add without a page load is done (D-155).' );
		self::assertSame( array( 'Link to this build', 'Copy link', 'Link copied.' ), array( $review['share'], $review['copy'], $review['copied'] ), 'The link is the view\'s own address (D-156).' );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'bearings', 'hardware', 'grip' ), array_column( $review['items'], 'key' ) );
		self::assertSame(
			array(
				'key'     => 'trucks',
				'classes' => 'cbuild-review__item',
				'label'   => 'Trucks',
				'name'    => 'Part 139',
				'fits'    => true,
				'fit'     => 'Fits',
				// Each row carries its price, as the prototype's review has it.
				'amount'  => '$50.00',
				'url'     => 'https://shop.test/?page_id=5&lang=fr&' . http_build_query( array( 'cbuild' => array_map( 'strval', self::STREET ) + array( 'step' => 'trucks' ) ) ),
				// "Change" to the eye, read with what it changes.
				'text'    => 'Change',
				'change'  => 'Change trucks',
			),
			$review['items'][1]
		);
		self::assertSame( 'https://shop.test/?page_id=5&lang=fr&' . http_build_query( array( 'cbuild' => array_map( 'strval', self::STREET ) + array( 'step' => 'review' ) ) ), $review['action'] );
		self::assertSame(
			array(
				array(
					'name'  => 'add-to-cart',
					'value' => '77',
				),
				array(
					'name'  => 'cbuild[picks][deck]',
					'value' => '101',
				),
				array(
					'name'  => 'cbuild[picks][trucks]',
					'value' => '139',
				),
				array(
					'name'  => 'cbuild[picks][wheels]',
					'value' => '301',
				),
				array(
					'name'  => 'cbuild[picks][bearings]',
					'value' => '501',
				),
				array(
					'name'  => 'cbuild[picks][hardware]',
					'value' => '601',
				),
				array(
					'name'  => 'cbuild[picks][grip]',
					'value' => '701',
				),
				array(
					'name'  => 'cbuild[assembly]',
					'value' => '0',
				),
			),
			$review['fields']
		);
		$saving    = $review['fields'];
		$saving[0] = array(
			'name'  => 'cbuild_kit',
			'value' => '77',
		);
		self::assertSame( $saving, $review['saveFields'], 'Save build sends the same build, with the kit under the plugin\'s own field, not add-to-cart, which WooCommerce answers by adding to the cart.' );
		self::assertFalse( $review['hasLead'] );
		self::assertFalse( $review['hasNotice'] );
	}

	public function test_the_review_says_the_lead_time_with_assembly_and_the_returns_notice_and_marks_what_does_not_fit(): void {
		$kit = KitDocuments::kit(
			KitDocuments::slots(),
			null,
			null,
			array(
				'schema_version' => 1,
				'default_on'     => true,
				'fee'            => '0',
				'lead_time'      => 'Two days',
				'returns_notice' => 'Assembled builds come back as parts.',
			)
		);
		$this->kits->add( self::KIT, $kit, Shop::parts() );
		$view = $this->show( new BuildLink( array( SlotKey::DECK => 103 ) + self::STREET, null, State::REVIEW ) )['view'];

		self::assertSame( 'Assembly: free', $view['summary']['assembly'] );
		self::assertTrue( $view['review']['hasLead'] );
		self::assertSame( 'Assembly time: Two days', $view['review']['lead'], 'what takes two days is said (Cowork minor 17)' );
		self::assertTrue( $view['review']['hasNotice'] );
		self::assertSame( 'Assembled builds come back as parts.', $view['review']['notice'] );
		self::assertFalse( $view['review']['ready'] );
		self::assertSame( array( false, false, true ), array_column( array_slice( $view['review']['items'], 0, 3 ), 'fits' ) );
		self::assertSame( array( 'cbuild-review__item cbuild-review__item--wrong', 'cbuild-review__item cbuild-review__item--wrong', 'cbuild-review__item' ), array_column( array_slice( $view['review']['items'], 0, 3 ), 'classes' ), 'marked so the styles can say so' );
		self::assertSame( 'Trucks: the part chosen does not fit the rest of the build.', $view['review']['items'][1]['fit'] );
		self::assertSame( 'cbuild[assembly]', end( $view['review']['fields'] )['name'] );
		self::assertSame( '1', end( $view['review']['fields'] )['value'] );
		self::assertSame( 'Trucks: the part chosen does not fit the rest of the build.', $view['errors']['items'][1]['text'] );
		self::assertSame( self::url( array_map( 'strval', array( SlotKey::DECK => 103 ) + self::STREET ) + array( 'step' => 'trucks' ) ), $view['errors']['items'][1]['url'], 'Each problem links to its step.' );
	}

	public function test_a_change_that_breaks_a_later_pick_is_a_panel_with_use_these_and_undo(): void {
		$was  = array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139, SlotKey::WHEELS => 301 );
		$now  = array( SlotKey::DECK => 103 ) + $was;
		$view = $this->show( new BuildLink( $now, $was, SlotKey::TRUCKS ) )['view'];

		self::assertTrue( $view['isChanges'] );
		self::assertFalse( $view['isStep'] );
		self::assertFalse( $view['isReview'] );
		self::assertSame( '1 part no longer fits.', $view['status'] );
		$changes = $view['changes'];
		self::assertSame( 'This change affects parts you chose', $changes['heading'] );
		self::assertTrue( $changes['hasKept'] );
		self::assertSame( array( array( 'text' => 'Wheels: Part 301' ) ), $changes['kept'] );
		self::assertSame( 'trucks', $changes['items'][0]['key'] );
		self::assertSame( 'Trucks: Part 139 no longer fits.', $changes['items'][0]['text'] );
		self::assertSame( 'The trucks are made for decks from 7.8″ to 8.2″ wide. The deck is 8.5″.', $changes['items'][0]['reasons'][0]['text'] );
		self::assertSame( 'Closest fit: Part 149', $changes['items'][0]['closest'] );
		self::assertSame( self::fields( array( 'deck' => '103', 'trucks' => '149', 'wheels' => '301', 'step' => 'trucks' ) ), $changes['use']['fields'], 'A button in a GET form, as every change of state is (spec: SEO and sharing).' );
		self::assertSame( 'Use these', $changes['use']['text'] );
		self::assertSame( self::fields( array( 'deck' => '101', 'trucks' => '139', 'wheels' => '301', 'step' => 'deck' ) ), $changes['undo']['fields'], 'Undo goes back to the step that changed.' );
		self::assertSame( 'Undo', $changes['undo']['text'] );
		self::assertSame( self::url( array( 'deck' => '103', 'trucks' => '139', 'wheels' => '301', 'step' => 'trucks', '_was' => array( 'deck' => '101', 'trucks' => '139', 'wheels' => '301' ) ) ), $view['url'], 'The address keeps what the picks were while the change is explained.' );
	}

	public function test_a_line_of_more_than_one_says_how_many(): void {
		$slots                     = KitDocuments::slots();
		$slots['slots'][6]['units'] = 2;
		$this->kits->add( self::KIT, KitDocuments::kit( $slots ), Shop::parts() );
		$rows = $this->show( new BuildLink( self::STREET ) )['view']['summary']['rows'];

		self::assertSame( 'grip', $rows[5]['key'] );
		self::assertSame( '× 2', $rows[5]['count'] );
		self::assertSame( '', $rows[4]['count'] );
	}

	public function test_use_these_keeps_assembly_and_the_preset_and_drops_the_picks_before(): void {
		$was  = array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139 );
		$now  = array( SlotKey::DECK => 103 ) + $was;
		$link = new BuildLink( $now, $was, SlotKey::TRUCKS, false, 2 );
		self::assertSame( self::fields( array( 'deck' => '103', 'trucks' => '149', 'step' => 'trucks', 'assembly' => '0', 'preset' => '2' ) ), $this->show( $link )['view']['changes']['use']['fields'] );
	}

	public function test_with_no_change_the_panel_has_nowhere_to_go(): void {
		$changes = $this->show( new BuildLink( array( SlotKey::DECK => 101 ) ) )['view']['changes'];
		self::assertFalse( $changes['hasKept'] );
		self::assertSame( array(), $changes['items'] );
		self::assertSame( array(), $changes['use']['fields'] );
		self::assertSame( array(), $changes['undo']['fields'] );
	}

	public function test_parts_that_do_not_fit_are_out_of_the_list_but_not_out_of_sight(): void {
		$hidden = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS ) )['view']['step']['hidden'];

		self::assertTrue( $hidden['shown'] );
		self::assertSame( '3 parts hidden: the trucks are made for another width of deck.', $hidden['summary'], 'one reason: its own sentence' );
		self::assertSame( array(), $hidden['groups'], 'one reason is said once, in the summary' );
		self::assertSame( array( 144, 149, 169 ), array_column( $hidden['items'], 'id' ) );
		self::assertSame(
			array(
				'id'      => 144,
				'name'    => 'Part 144',
				'url'     => 'https://shop.test/?p=144',
				'reasons' => array( array( 'text' => 'The trucks are made for decks from 8.2″ to 8.375″ wide. The deck is 8″.' ) ),
			),
			$hidden['items'][0]
		);

		$none = $this->show( new BuildLink( array(), null, SlotKey::DECK ) )['view']['step']['hidden'];
		self::assertFalse( $none['shown'], 'no deck is hidden' );
		self::assertSame( '', $none['summary'] );
		self::assertSame( array(), $none['items'] );
	}

	public function test_parts_hidden_for_more_than_one_reason_are_counted_with_each_reason_listed(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array_merge( $parts[ SlotKey::TRUCKS ], array( Shop::priced( Parts::of( array( 'axle_width' => '8.0', 'deck_min' => '7.8', 'deck_max' => '8.2', 'baseplate' => 'old_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 2 ), 150, 2999, 'independent' ), 5000 ) ) );
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$hidden = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS ) )['view']['step']['hidden'];

		self::assertSame( '4 parts hidden: they do not fit the parts before this step.', $hidden['summary'] );
		self::assertCount( 2, $hidden['groups'] );
		self::assertSame( '3 parts hidden: the trucks are made for another width of deck.', $hidden['groups'][0]['text'] );
	}

	public function test_a_long_list_has_filters_as_buttons_in_the_steps_form_and_the_deck_starts_with_a_width(): void {
		$step = $this->show( new BuildLink( array(), null, SlotKey::DECK ) )['view']['step'];

		self::assertTrue( $step['filters']['shown'] );
		self::assertFalse( $step['filters']['open'], 'a drawer on phones, closed with nothing pressed (audit 21)' );
		self::assertSame( 'Filter the parts', $step['filters']['heading'] );
		self::assertSame( array( 'size' ), array_column( $step['filters']['groups'], 'key' ), 'the decks have no brands to choose by' );
		$sizes = $step['filters']['groups'][0];
		self::assertSame( 'Width', $sizes['legend'], 'a deck is sized by its width' );
		self::assertSame( 'cbuild[_size]', $sizes['name'] );
		self::assertSame(
			array(
				'value'   => '8in',
				'text'    => '8″ (1)',
				'pressed' => 'false',
			),
			$sizes['options'][0]
		);
		self::assertSame(
			array(
				'shown' => false,
				'text'  => 'Any width',
			),
			$sizes['clear']
		);
		self::assertSame( array( 'name' => 'cbuild[_on]', 'value' => 'deck' ), $step['tail'][0], 'a filter keeps the shopper on the step; Next leaves it behind' );

		$step = $this->show( new BuildLink( array(), null, SlotKey::DECK, size: '8.25in' ) )['view']['step'];
		$sizes = $step['filters']['groups'][0];
		self::assertSame( 'true', $sizes['options'][1]['pressed'] );
		self::assertTrue( $sizes['clear']['shown'] );
		self::assertTrue( $step['filters']['open'], 'open while a filter is pressed' );
		self::assertSame( array( 102 ), array_column( $step['options'], 'id' ) );
		self::assertContains( array( 'name' => 'cbuild[_size]', 'value' => '8.25in' ), $step['tail'], 'Show more keeps the filter' );
		self::assertSame( '1 of 1 part shown', $step['more']['count'] );
	}

	public function test_a_step_with_one_size_and_one_brand_has_no_filters(): void {
		$step = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS ) )['view']['step'];
		self::assertFalse( $step['filters']['shown'] );
		self::assertSame( array(), $step['filters']['groups'] );
		self::assertFalse( $step['more']['shown'] );
		self::assertNotContains( 'cbuild[_on]', array_column( $step['tail'], 'name' ), 'a step with nothing to filter keeps its address clean' );
	}

	public function test_a_step_lists_24_at_a_time_with_a_button_for_the_next(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::GRIP ]   = array();
		foreach ( range( 1, 30 ) as $n ) {
			$parts[ SlotKey::GRIP ][] = Shop::priced( Parts::of( array( 'grip_width' => '9', 'grip_length' => '33', 'set_size' => 1 ), 7000 + $n, 1000, 0 === $n % 2 ? 'mob' : 'jessup' ), 1000 + $n );
		}
		$this->kits->add( self::KIT, Shop::kit(), $parts );
		$before = array_diff_key( self::STREET, array( SlotKey::GRIP => true ) );
		$link   = new BuildLink( $before, null, SlotKey::GRIP );
		$step   = $this->show( $link )['view']['step'];

		self::assertCount( 24, $step['options'] );
		self::assertSame(
			array(
				'shown' => true,
				'name'  => 'cbuild[_shown]',
				'value' => '30',
				'text'  => 'Show 6 more',
				'count' => '24 of 30 parts shown',
			),
			$step['more']
		);
		self::assertSame( array( 'brand' ), array_column( $step['filters']['groups'], 'key' ) );
		self::assertSame( 'Brand', $step['filters']['groups'][0]['legend'] );
		self::assertSame( 'Any brand', $step['filters']['groups'][0]['clear']['text'] );

		$all = $this->show( new BuildLink( $before, null, SlotKey::GRIP, shown: 30 ) )['view']['step'];
		self::assertCount( 30, $all['options'] );
		self::assertFalse( $all['more']['shown'] );
		self::assertSame( '30 of 30 parts shown', $all['more']['count'] );
	}

	public function test_filters_and_more_parts_stay_only_on_the_step_they_were_asked_on(): void {
		$link = new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS, brand: 'independent', size: '8', shown: 48 );
		self::assertSame( self::url( array( 'deck' => '101', 'step' => 'trucks', '_brand' => 'independent', '_size' => '8', '_shown' => '48' ) ), $this->show( $link )['view']['url'] );

		$link = new BuildLink( array(), null, SlotKey::TRUCKS, brand: 'independent' );
		self::assertSame( self::url( array( 'step' => 'deck' ) ), $this->show( $link )['view']['url'], 'Stopped on the deck, the trucks\' filters go.' );
	}

	public function test_a_step_with_nothing_to_offer_says_why(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::TRUCKS ] = array( $parts[ SlotKey::TRUCKS ][3] );
		$this->kits->add( self::KIT, Shop::kit(), $parts );

		$step = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS ) )['view']['step'];
		self::assertTrue( $step['empty'], 'The 169s do not fit an 8″ deck.' );
		self::assertFalse( $step['hasLead'], 'Only the deck step says to start with a width.' );
		self::assertSame( 'Nothing the shop has fits the parts before this step.', $step['emptyText'] );

		$step = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS, brand: 'independent' ) )['view']['step'];
		self::assertSame( 'No part matches these filters.', $step['emptyText'] );
		$step = $this->show( new BuildLink( array( SlotKey::DECK => 101 ), null, SlotKey::TRUCKS, size: '8' ) )['view']['step'];
		self::assertSame( 'No part matches these filters.', $step['emptyText'] );
	}

	public function test_a_filtered_step_still_lists_the_part_it_counts(): void {
		$step = $this->show( new BuildLink( array( SlotKey::DECK => 101, SlotKey::TRUCKS => 139 ), null, SlotKey::TRUCKS, brand: 'nobody' ) )['view']['step'];
		self::assertFalse( $step['empty'] );
		self::assertSame( array( 139 ), array_column( $step['options'], 'id' ) );
	}

	public function test_another_plugin_adds_classes_to_a_card_after_the_builders_own_made_safe(): void {
		$GLOBALS['cbuild_test_filters']['cbuild_part_classes'] = static function ( array $classes, int $part, string $slot, int $kit ): array {
			return 101 === $part && SlotKey::DECK === $slot && self::KIT === $kit ? array( 'is-staff-pick', 'cbuild-card', 'bad class"', 7 ) : array();
		};
		$cards = $this->show( new BuildLink() )['view']['step']['options'];
		self::assertSame( 'cbuild-card cbuild-card--fits is-staff-pick badclass', $cards[0]['classes'] );
		self::assertSame( 'cbuild-card cbuild-card--fits', $cards[1]['classes'] );

		$GLOBALS['cbuild_test_filters']['cbuild_part_classes'] = static fn(): string => 'is-staff-pick';
		self::assertSame( 'cbuild-card cbuild-card--fits', $this->show( new BuildLink() )['view']['step']['options'][0]['classes'], 'An answer that is no list adds nothing.' );
	}

	// Audit, spec review S8. The spec: "A flagged part blurs on its card in a step, in the parts picture and in the
	// review", by cbuild_part_classes: the same answer reaches every place the part is shown.
	public function test_another_plugins_classes_reach_the_part_in_the_picture_the_bar_and_the_review(): void {
		$GLOBALS['cbuild_test_filters']['cbuild_part_classes'] = static function ( array $classes, int $part, string $slot, int $kit ): array {
			return 101 === $part && SlotKey::DECK === $slot && self::KIT === $kit ? array( 'is-flagged', 'bad class"' ) : array();
		};
		$view = $this->show( new BuildLink( self::STREET, null, State::REVIEW ) )['view'];

		self::assertSame( 'cbuild-picture__item is-flagged badclass', $view['picture']['items'][0]['classes'] );
		self::assertSame( 'cbuild-picture__item', $view['picture']['items'][1]['classes'] );
		self::assertSame( 'cbuild-bar__photo is-flagged badclass', $view['bar']['photo']['classes'] );
		$review = array_column( $view['review']['items'], 'classes', 'key' );
		self::assertSame( 'cbuild-review__item is-flagged badclass', $review[ SlotKey::DECK ] );
		self::assertSame( 'cbuild-review__item', $review[ SlotKey::TRUCKS ] );
	}
}
