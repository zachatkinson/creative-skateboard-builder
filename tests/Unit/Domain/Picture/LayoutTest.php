<?php
/**
 * Layout.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The picture's layout from the specs: the deck by its width and length, the trucks by the wheelbase or the shape's
 * usual one, the wheels at the axles and the grip as a strip along the edge (spec: The builder, The picture). Every
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
		return array_map( static fn( Layer $layer ): array => array( $layer->slot, $layer->part, $layer->x, $layer->y, $layer->width, $layer->height ), $layout->layers );
	}

	public function test_nothing_is_laid_out_before_a_deck_is_picked(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::TRUCKS => Parts::indy( '144' ), SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ), 300 ) ) ) );

		self::assertSame( 0, $layout->width );
		self::assertSame( 0, $layout->height );
		self::assertSame( array(), $layout->layers );
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
		$layout = Layout::of(
			Parts::picks(
				array(
					SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ), 300 ),
					SlotKey::GRIP   => Parts::of( array(), 700 ),
					SlotKey::TRUCKS => Parts::indy( '144' ),
					SlotKey::DECK   => self::deck(),
				)
			)
		);

		self::assertSame(
			array(
				array( SlotKey::DECK, 100, 0, 0, 2095500, 8128000 ),
				// The strip is 1 in wide, flush with the deck's right edge.
				array( SlotKey::GRIP, 700, 920750, 0, 254000, 8128000 ),
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

	public function test_the_grip_alone_is_a_strip_on_the_deck(): void {
		$layout = Layout::of( Parts::picks( array( SlotKey::DECK => self::deck( array( 'deck_width' => '8.5' ) ), SlotKey::GRIP => Parts::of( array( 'grip_width' => '9', 'grip_length' => '33' ), 700 ) ) ) );

		self::assertSame( array( SlotKey::GRIP, 700, 952500, 0, 254000, 8128000 ), self::rows( $layout )[1] );
		self::assertCount( 2, $layout->layers );
		self::assertSame( array(), $layout->assumed );
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
	}
}
