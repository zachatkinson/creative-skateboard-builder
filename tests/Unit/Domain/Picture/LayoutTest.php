<?php
/**
 * Layout.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Picture\PictureSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The picture's layout from the specs: the deck by its width and length, the trucks by the wheelbase or the shape's
 * usual one and the wheels at the axles, seen from underneath; and from 0.8.0 the board's top, with the grip filled
 * into the deck nose to tail and a bolt head at each of the deck's holes (spec: The builder, The picture; #46). Every
 * number is a ten-thousandth of a millimetre from the deck's centre.
 */
final class LayoutTest extends TestCase {

	/**
	 * An 8.25 in deck, 32 in long, with a 14.25 in wheelbase, and other specs if wanted.
	 *
	 * @param array<string, string> $specs Specs to add or replace.
	 */
	private static function deck( array $specs = array() ): Part {
		return Parts::of( $specs + array( 'deck_width' => '8.25', 'deck_length' => '32', 'wheelbase' => '14.25' ), 100 );
	}

	/**
	 * A layer as a row: slot, part, x, y, width, height.
	 *
	 * @return list<array{string, int, int, int, int, int}>
	 */
	private static function rows( Layout $layout ): array {
		return self::table( $layout->layers );
	}

	/**
	 * Layers as rows: slot, part, x, y, width, height.
	 *
	 * @param list<Layer> $layers The layers.
	 * @return list<array{string, int, int, int, int, int}>
	 */
	private static function table( array $layers ): array {
		return array_map( static fn( Layer $layer ): array => array( $layer->slot, $layer->part, $layer->x, $layer->y, $layer->width, $layer->height ), $layers );
	}

	/**
	 * A whole board: deck, trucks, wheels, grip and hardware.
	 *
	 * @param array<string, string> $deck Specs to add to the deck's or replace.
	 */
	private static function whole( array $deck = array() ): Picks {
		return Parts::picks(
			array(
				SlotKey::WHEELS   => Parts::of( array( 'wheel_diameter' => '54' ), 300 ),
				SlotKey::GRIP     => Parts::of( array(), 700 ),
				SlotKey::HARDWARE => Parts::of( array( 'bolt_length' => '1' ), 600 ),
				SlotKey::TRUCKS   => Parts::indy( '144' ),
				SlotKey::DECK     => self::deck( $deck ),
			)
		);
	}

	public function test_nothing_is_laid_out_before_a_deck_is_picked(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::TRUCKS => Parts::indy( '144' ), SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ), 300 ) ) ) );

		self::assertSame( 0, $layout->width );
		self::assertSame( 0, $layout->height );
		self::assertSame( array(), $layout->layers );
		self::assertSame( array(), $layout->top );
		self::assertSame( array(), $layout->assumed );
	}

	public function test_nothing_is_laid_out_for_a_deck_with_no_width(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => Parts::of( array( 'deck_length' => '32' ), 100 ), SlotKey::GRIP => Parts::of( array(), 700 ) ) ) );

		self::assertSame( array(), $layout->layers );
		self::assertSame( array( 0, 0 ), array( $layout->width, $layout->height ) );
		self::assertSame( array(), $layout->assumed );
	}

	public function test_the_deck_is_drawn_by_its_width_and_length_at_the_centre(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck() ) ) );

		// 8.25 in is 209.55 mm, and 32 in is 812.8 mm.
		self::assertSame( array( array( SlotKey::DECK, 100, 0, 0, 2095500, 8128000 ) ), self::rows( $layout ) );
		self::assertSame( 2095500, $layout->width );
		self::assertSame( 8128000, $layout->height );
		self::assertSame( array(), $layout->assumed );
	}

	public function test_a_whole_board_from_the_bottom_of_the_stack_up(): void {
		$layout = Layout::of( self::whole() );

		// Seen from underneath, the grip and the hardware are out of sight: the grip is drawn on the top (#46).
		self::assertSame(
			array(
				array( SlotKey::DECK, 100, 0, 0, 2095500, 8128000 ),
				// Half the 14.25 in wheelbase and half the 2.125 in between a baseplate's holes; the axle is 8.25 in and
				// the baseplate 3 in.
				array( SlotKey::TRUCKS, 144, 0, 2079625, 2095500, 762000 ),
				array( SlotKey::TRUCKS, 144, 0, -2079625, 2095500, 762000 ),
				// A 54 mm wheel is drawn 32.4 mm wide, its outer face at the axle's end.
				array( SlotKey::WHEELS, 300, -885750, 2079625, 324000, 540000 ),
				array( SlotKey::WHEELS, 300, 885750, 2079625, 324000, 540000 ),
				array( SlotKey::WHEELS, 300, -885750, -2079625, 324000, 540000 ),
				array( SlotKey::WHEELS, 300, 885750, -2079625, 324000, 540000 ),
			),
			self::rows( $layout )
		);
		self::assertSame( array( 2095500, 8128000 ), array( $layout->width, $layout->height ) );
		self::assertSame( array(), $layout->assumed );
	}

	// Issue #46: "On the top, the wheels peek out at the edges and the trucks are hidden"; "The grip's layer image becomes
	// a swatch filled into the deck's shape, nose to tail"; "Bolt heads are drawn at the deck's holes".
	public function test_the_top_is_the_wheels_under_the_deck_then_the_grip_nose_to_tail_and_a_bolt_head_at_each_hole(): void {
		$layout = Layout::of( self::whole() );

		self::assertSame(
			array(
				// The wheels where they are underneath: the deck covers all but what stands out past its edges.
				array( SlotKey::WHEELS, 300, -885750, 2079625, 324000, 540000 ),
				array( SlotKey::WHEELS, 300, 885750, 2079625, 324000, 540000 ),
				array( SlotKey::WHEELS, 300, -885750, -2079625, 324000, 540000 ),
				array( SlotKey::WHEELS, 300, 885750, -2079625, 324000, 540000 ),
				array( SlotKey::DECK, 100, 0, 0, 2095500, 8128000 ),
				// The grip is its whole sheet, 9 by 33 in where it gives none, centred on the deck, which the deck's
				// shape then cuts (F18, #103).
				array( SlotKey::GRIP, 700, 0, 0, 2286000, 8382000 ),
				// A 3/8 in head at each hole, from the nose: the inner rows half the wheelbase out, the outer 2.125 in
				// further, the columns 1.625 in apart; the left of each row first.
				array( SlotKey::HARDWARE, 600, -206375, 2349500, 95250, 95250 ),
				array( SlotKey::HARDWARE, 600, 206375, 2349500, 95250, 95250 ),
				array( SlotKey::HARDWARE, 600, -206375, 1809750, 95250, 95250 ),
				array( SlotKey::HARDWARE, 600, 206375, 1809750, 95250, 95250 ),
				array( SlotKey::HARDWARE, 600, -206375, -1809750, 95250, 95250 ),
				array( SlotKey::HARDWARE, 600, 206375, -1809750, 95250, 95250 ),
				array( SlotKey::HARDWARE, 600, -206375, -2349500, 95250, 95250 ),
				array( SlotKey::HARDWARE, 600, 206375, -2349500, 95250, 95250 ),
			),
			self::table( $layout->top )
		);
	}

	public function test_an_old_school_decks_bolt_heads_are_at_its_wider_rows(): void {
		$top = self::table( Layout::of( self::whole( array( 'hole_pattern' => 'old_school' ) ) )->top );

		// The inner rows stay half the wheelbase out; the outer are 2.5 in further.
		self::assertSame( array( SlotKey::HARDWARE, 600, -206375, 2444750, 95250, 95250 ), $top[6] );
		self::assertSame( array( SlotKey::HARDWARE, 600, 206375, 1809750, 95250, 95250 ), $top[9] );
		self::assertSame( array( SlotKey::HARDWARE, 600, 206375, -2444750, 95250, 95250 ), $top[13] );
	}

	public function test_a_top_with_no_grip_or_hardware_is_the_deck_alone_and_the_trucks_are_never_on_it(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck(), SlotKey::TRUCKS => Parts::indy( '144' ) ) ) );

		self::assertSame( array( array( SlotKey::DECK, 100, 0, 0, 2095500, 8128000 ) ), self::table( $layout->top ) );
	}

	public function test_the_kits_layer_order_stacks_the_underside_and_leaves_the_top_as_a_board_is_built(): void {
		$settings = PictureSettings::fromDocument(
			array(
				'schema_version' => 1,
				'order'          => array( 'wheels', 'deck' ),
			)
		);

		self::assertSame( self::table( Layout::of( self::whole() )->top ), self::table( Layout::of( self::whole(), $settings )->top ) );
		self::assertSame( SlotKey::WHEELS, Layout::of( self::whole(), $settings )->layers[0]->slot );
	}

	public function test_the_wheels_are_scaled_by_their_diameter(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck(), SlotKey::TRUCKS => Parts::indy( '144' ), SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '60' ), 300 ) ) ) );

		self::assertSame( array( SlotKey::WHEELS, 300, -867750, 2079625, 360000, 600000 ), self::rows( $layout )[3] );
		self::assertSame( array(), $layout->assumed );
	}

	public function test_the_board_is_as_wide_as_axles_that_stand_out_past_the_deck(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck( array( 'deck_width' => '8.0' ) ), SlotKey::TRUCKS => Parts::indy( '149' ) ) ) );

		// The deck is 203.2 mm and the 8.5 in axle 215.9 mm.
		self::assertSame( 2159000, $layout->width );
		self::assertSame( 2032000, $layout->layers[0]->width );
		self::assertSame( 2159000, $layout->layers[1]->width );
	}

	public function test_the_board_is_as_wide_as_a_deck_that_covers_its_axles(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck( array( 'deck_width' => '8.5' ) ), SlotKey::TRUCKS => Parts::indy( '139' ) ) ) );

		self::assertSame( 2159000, $layout->width );
		self::assertSame( 2032000, $layout->layers[1]->width );
	}

	public function test_an_old_school_deck_sets_its_trucks_by_the_wider_spacing(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck( array( 'hole_pattern' => 'old_school' ) ), SlotKey::TRUCKS => Parts::indy( '144' ) ) ) );

		// Half the wheelbase and half of 2.5 in; the baseplate is 3.375 in.
		self::assertSame( array( SlotKey::TRUCKS, 144, 0, 2127250, 2095500, 857250 ), self::rows( $layout )[1] );
		self::assertSame( array( SlotKey::TRUCKS, 144, 0, -2127250, 2095500, 857250 ), self::rows( $layout )[2] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function newerPatterns(): array {
		return array(
			'new school' => array( 'new_school' ),
			'both'       => array( 'both' ),
		);
	}

	#[DataProvider( 'newerPatterns' )]
	public function test_any_other_deck_sets_them_by_the_usual_spacing( string $pattern ): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck( array( 'hole_pattern' => $pattern ) ), SlotKey::TRUCKS => Parts::indy( '144' ) ) ) );

		self::assertSame( array( SlotKey::TRUCKS, 144, 0, 2079625, 2095500, 762000 ), self::rows( $layout )[1] );
	}

	public function test_a_deck_with_no_wheelbase_gets_its_lengths_usual_one(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '31.875' ), 100 ), SlotKey::TRUCKS => Parts::indy( '144' ) ) ) );

		// 0.445 of 31.875 in is 14.1843 in, which is 360.2812 mm.
		self::assertSame( 2071281, $layout->layers[1]->y );
		self::assertSame( 8096250, $layout->height );
		self::assertSame( array( SpecKey::Wheelbase ), $layout->assumed );
	}

	/**
	 * @return array<string, array{string, int, int}>
	 */
	public static function widths(): array {
		return array(
			'under 8 in: 14 in'       => array( '7.9999', 2031975, 2047875 ),
			'8 in: 14.25 in'          => array( '8.0', 2032000, 2079625 ),
			'under 8.5 in: 14.25 in'  => array( '8.4999', 2158975, 2079625 ),
			'8.5 in: 14.5 in'         => array( '8.5', 2159000, 2111375 ),
			'wider still: 14.5 in'    => array( '10.0', 2540000, 2111375 ),
			'narrower still: 14 in'   => array( '7.0', 1778000, 2047875 ),
		);
	}

	#[DataProvider( 'widths' )]
	public function test_a_deck_with_no_length_either_gets_its_widths_usual_wheelbase_and_the_usual_length( string $width, int $wide, int $out ): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => Parts::of( array( 'deck_width' => $width ), 100 ), SlotKey::TRUCKS => Parts::indy( '144' ) ) ) );

		self::assertSame( array( SlotKey::DECK, 100, 0, 0, $wide, 8128000 ), self::rows( $layout )[0] );
		self::assertSame( $out, $layout->layers[1]->y );
		self::assertSame( -$out, $layout->layers[2]->y );
		self::assertSame( array( SpecKey::Wheelbase, SpecKey::DeckLength ), $layout->assumed );
	}

	public function test_a_deck_with_a_wheelbase_and_no_length_is_drawn_at_the_usual_length(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => Parts::of( array( 'deck_width' => '8.25', 'wheelbase' => '15' ), 100 ), SlotKey::TRUCKS => Parts::indy( '144' ) ) ) );

		self::assertSame( 8128000, $layout->height );
		// Half of 15 in and half of 2.125 in.
		self::assertSame( 2174875, $layout->layers[1]->y );
		self::assertSame( array( SpecKey::DeckLength ), $layout->assumed );
	}

	public function test_trucks_with_no_axle_width_are_drawn_as_wide_as_the_deck(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck(), SlotKey::TRUCKS => Parts::of( array( 'deck_min' => '8.0', 'deck_max' => '8.5' ), 200 ) ) ) );

		self::assertSame( array( SlotKey::TRUCKS, 200, 0, 2079625, 2095500, 762000 ), self::rows( $layout )[1] );
		self::assertSame( array( SpecKey::AxleWidth ), $layout->assumed );
	}

	public function test_wheels_picked_before_trucks_sit_at_the_decks_edges(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck(), SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ), 300 ) ) ) );

		self::assertCount( 5, $layout->layers );
		self::assertSame( array( SlotKey::WHEELS, 300, -885750, 2079625, 324000, 540000 ), self::rows( $layout )[1] );
		self::assertSame( array( SpecKey::AxleWidth ), $layout->assumed );
	}

	public function test_wheels_with_no_diameter_are_drawn_at_the_usual_one(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck(), SlotKey::TRUCKS => Parts::indy( '144' ), SlotKey::WHEELS => Parts::of( array(), 300 ) ) ) );

		self::assertSame( array( SlotKey::WHEELS, 300, 885750, -2079625, 324000, 540000 ), self::rows( $layout )[6] );
		self::assertSame( array( SpecKey::WheelDiameter ), $layout->assumed );
	}

	public function test_every_assumption_is_named_in_the_order_it_was_made(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => Parts::of( array( 'deck_width' => '8.25' ), 100 ), SlotKey::WHEELS => Parts::of( array(), 300 ) ) ) );

		self::assertSame( array( SpecKey::Wheelbase, SpecKey::DeckLength, SpecKey::AxleWidth, SpecKey::WheelDiameter ), $layout->assumed );
	}

	// Cowork's 1.0.0 review, F18 (#103): "a grip's layer image should be the whole sheet, drawn like a deck's artwork:
	// once, at the sheet's real size, centred on the deck nose to tail, clipped to the deck's outline".
	public function test_the_grip_is_drawn_on_the_top_alone_as_its_whole_sheet_centred_on_the_deck(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck( array( 'deck_width' => '8.5' ) ), SlotKey::GRIP => Parts::of( array( 'grip_width' => '10', 'grip_length' => '34' ), 700 ) ) ) );

		self::assertCount( 1, $layout->layers, 'no strip underneath' );
		$grip = $layout->top[1];
		self::assertSame( array( SlotKey::GRIP, 700, 0, 0, 2540000, 8636000 ), self::table( $layout->top )[1] );
		// Centred: its corner is half the overhang outside the deck's, each way.
		self::assertSame( array( -190500, -254000 ), $layout->corner( $grip ) );
		self::assertSame( array(), $layout->assumed, 'a grip with no size is drawn at the usual sheet, as nothing else in the picture rests on it' );
	}

	public function test_parts_the_board_hides_are_not_drawn(): void {
		$layout = Layout::of(
			Parts::picks(
				array(
					SlotKey::DECK     => self::deck(),
					SlotKey::RISERS   => Parts::of( array( 'riser_thickness' => '0.125' ), 400 ),
					SlotKey::BEARINGS => Parts::of( array( 'bearing_bore' => '8' ), 500 ),
					SlotKey::HARDWARE => Parts::of( array( 'bolt_length' => '1' ), 600 ),
				)
			)
		);

		self::assertCount( 1, $layout->layers );
		self::assertSame( array( SlotKey::DECK, SlotKey::HARDWARE ), array_values( array_unique( array_column( self::table( $layout->top ), 0 ) ) ), 'the hardware shows on the top alone' );
	}
}
