<?php
/**
 * SizePicker.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SizePicker;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The size the build picks from a look's sizes, where the build decides it (SPEC-CONFIGURATOR, One card per look;
 * D-240).
 */
final class SizePickerTest extends TestCase {

	/**
	 * The id of the part picked from a look's parts, or null.
	 *
	 * @param string       $slot   The slot.
	 * @param list<Part>   $parts  The look's parts.
	 * @param Picks        $build  The build so far.
	 * @param Measure|null $wanted The width asked, or the size the shopper's chip or the recommendation wants.
	 */
	private static function pick( string $slot, array $parts, Picks $build, ?Measure $wanted = null ): ?int {
		return ( new SizePicker( FitEngine::standard() ) )->pick( KitDocuments::kit(), $slot, $parts, $build, $wanted )?->part->id;
	}

	/**
	 * A part with a stock count.
	 *
	 * @param Part $part  The part.
	 * @param int  $stock How many the shop holds.
	 */
	private static function stocked( Part $part, int $stock ): Part {
		return new Part( $part->id, $part->brand, $part->specs, $part->price, $stock > 0, $stock );
	}

	public function test_a_deck_comes_in_the_width_asked(): void {
		$decks = array( Builds::deck( '8', 101 ), Builds::deck( '8.25', 102 ), Builds::deck( '8.5', 103 ) );
		self::assertSame( 102, self::pick( SlotKey::DECK, $decks, Picks::none(), Measure::inches( 82500 ) ) );
		self::assertNull( self::pick( SlotKey::DECK, $decks, Picks::none(), Measure::inches( 87500 ) ), 'a look not in the width asked has no card in it' );
		self::assertNull( self::pick( SlotKey::DECK, $decks, Picks::none() ), 'no width asked, no deck' );
		$widthless = Parts::of( array( 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), 104 );
		self::assertSame( 102, self::pick( SlotKey::DECK, array( $widthless, Builds::deck( '8.25', 102 ) ), Picks::none(), Measure::inches( 82500 ) ), 'a deck with no width is in no width' );
	}

	public function test_a_deck_sold_out_in_the_width_asked_is_still_its_size_so_the_card_folds_with_the_sold_out(): void {
		$decks = array( Builds::deck( '8', 101 ), self::stocked( Builds::deck( '8.25', 102 ), 0 ) );
		self::assertSame( 102, self::pick( SlotKey::DECK, $decks, Picks::none(), Measure::inches( 82500 ) ) );
	}

	public function test_trucks_come_in_the_size_that_fits_the_deck_nearest_its_width(): void {
		$trucks = array( Builds::truck( '139' ), Builds::truck( '144' ), Builds::truck( '149' ), Builds::truck( '159' ) );
		self::assertSame( 144, self::pick( SlotKey::TRUCKS, $trucks, Parts::picks( array( SlotKey::DECK => Builds::deck( '8.25' ) ) ) ) );
		self::assertSame( 149, self::pick( SlotKey::TRUCKS, $trucks, Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) ) );
		$trucks = array( Builds::truck( '144' ), Builds::truck( '146' ), Builds::truck( '149' ) );
		self::assertSame( 146, self::pick( SlotKey::TRUCKS, $trucks, Parts::picks( array( SlotKey::DECK => Builds::deck( '8.375' ) ) ) ), 'all three fit 8.375 in; 146 is nearest, not the narrowest' );
		self::assertSame( 144, self::pick( SlotKey::TRUCKS, array( Builds::truck( '149' ), Builds::truck( '144' ) ), Picks::none() ), 'with no deck yet, the narrowest' );
	}

	public function test_a_size_in_stock_comes_before_a_nearer_one_sold_out(): void {
		$trucks = array( Builds::truck( '146' ), self::stocked( Builds::truck( '149' ), 0 ), Builds::truck( '151' ) );
		self::assertSame( 146, self::pick( SlotKey::TRUCKS, $trucks, Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) ), '146 and 151 are as near; the narrower' );
		$single = Builds::truck( '149' );
		$trucks = array( new Part( 149, $single->brand, $single->specs->with( SpecKey::SetSize, 1 ), $single->price, true, 1 ), Builds::truck( '151' ) );
		self::assertSame( 151, self::pick( SlotKey::TRUCKS, $trucks, Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) ), '149 sold singly with one left, and a build takes two' );
	}

	public function test_a_look_with_no_size_that_fits_is_none(): void {
		self::assertNull( self::pick( SlotKey::TRUCKS, array( Builds::truck( '109' ), Builds::truck( '215' ) ), Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) ) );
		self::assertNull( self::pick( SlotKey::TRUCKS, array(), Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) ) );
	}

	/**
	 * The build so far, with wheels of a diameter on trucks that take 56 mm.
	 *
	 * @param string $diameter The wheels' diameter in millimetres.
	 */
	private static function rolling( string $diameter ): Picks {
		return Parts::picks(
			array(
				SlotKey::DECK   => Builds::deck( '8.5' ),
				SlotKey::TRUCKS => Builds::truck( '149' ),
				SlotKey::WHEELS => Builds::wheel( $diameter ),
			)
		);
	}

	public function test_risers_come_in_the_thickness_the_wheels_ask_for(): void {
		$risers = array( Builds::riser( '0.125', 401 ), Builds::riser( '0.25', 402 ), Builds::riser( '0.5', 403 ), Builds::riser( '0.75', 404 ) );
		self::assertSame( 403, self::pick( SlotKey::RISERS, $risers, self::rolling( '60' ) ), '4 mm over takes ½ in' );
		self::assertSame( 402, self::pick( SlotKey::RISERS, $risers, self::rolling( '57' ) ), '1 mm over takes ¼ in' );
	}

	public function test_risers_cover_the_thickness_asked_before_coming_nearest_to_it(): void {
		$risers = array( Builds::riser( '0.375', 401 ), Builds::riser( '0.75', 402 ) );
		self::assertSame( 402, self::pick( SlotKey::RISERS, $risers, self::rolling( '60' ) ), '⅜ in is nearer ½ in, and falls short' );
	}

	public function test_risers_nothing_asks_for_come_in_the_thinnest(): void {
		$risers = array( Builds::riser( '0.25', 401 ), Builds::riser( '0.125', 402 ), Builds::riser( '0.5', 403 ) );
		self::assertSame( 402, self::pick( SlotKey::RISERS, $risers, self::rolling( '54' ) ) );
	}

	/**
	 * The build so far, with a riser or none.
	 *
	 * @param string|null $riser The riser's thickness in inches, or null for none.
	 */
	private static function risen( ?string $riser ): Picks {
		$build = self::rolling( '54' );
		return null === $riser ? $build : $build->with( SlotKey::RISERS, Builds::riser( $riser ) );
	}

	public function test_bolts_come_in_the_usual_length_one_inch_plus_the_riser(): void {
		$bolts = array( Builds::bolts( '0.875', 601 ), Builds::bolts( '1', 602 ), Builds::bolts( '1.25', 603 ), Builds::bolts( '1.5', 604 ) );
		self::assertSame( 602, self::pick( SlotKey::HARDWARE, $bolts, self::risen( null ) ) );
		self::assertSame( 603, self::pick( SlotKey::HARDWARE, $bolts, self::risen( '0.25' ) ) );
		self::assertSame( 604, self::pick( SlotKey::HARDWARE, $bolts, self::risen( '0.5' ) ) );
	}

	public function test_bolts_with_no_usual_length_come_in_the_nearest_length_allowed(): void {
		$bolts = array( Builds::bolts( '0.875', 601 ), Builds::bolts( '1.25', 602 ) );
		self::assertSame( 601, self::pick( SlotKey::HARDWARE, $bolts, self::risen( null ) ), 'no 1 in: ⅞ in is nearer, and allowed' );
		self::assertSame( 602, self::pick( SlotKey::HARDWARE, $bolts, self::risen( '0.125' ) ), '⅞ in is too short over a ⅛ in riser' );
		self::assertNull( self::pick( SlotKey::HARDWARE, $bolts, self::risen( '0.5' ) ), 'nothing reaches through ½ in' );
	}

	public function test_bolts_as_near_either_way_come_in_the_longer(): void {
		$bolts = array( Builds::bolts( '0.875', 601 ), Builds::bolts( '1.125', 602 ) );
		self::assertSame( 602, self::pick( SlotKey::HARDWARE, $bolts, self::risen( null ) ) );
	}

	public function test_grip_comes_in_the_smallest_sheet_that_covers_the_deck(): void {
		$sheet = static fn( int $id, string $width, string $length ): Part => Parts::of( array( 'grip_width' => $width, 'grip_length' => $length, 'set_size' => 1 ), $id );
		$grip  = array( $sheet( 701, '10', '33' ), $sheet( 702, '8', '33' ), $sheet( 703, '9', '35' ), $sheet( 704, '9', '33' ), $sheet( 705, '11', '33' ) );
		self::assertSame( 704, self::pick( SlotKey::GRIP, $grip, Builds::complete() ), '8 in is narrower than the 8.5 in deck; 9 by 33 is the smallest that covers it' );
		self::assertSame( 704, self::pick( SlotKey::GRIP, $grip, Builds::complete(), Measure::inches( 110000 ) ), 'the build decides it, whatever is wanted' );
	}

	public function test_wheels_come_in_the_size_wanted_or_the_nearest_to_it(): void {
		$wheels = array( Builds::wheel( '56', 303 ), Builds::wheel( '54', 302 ), Builds::wheel( '52', 301 ) );
		$build  = self::rolling( '54' )->without( SlotKey::WHEELS );
		self::assertSame( 302, self::pick( SlotKey::WHEELS, $wheels, $build, Measure::millimetres( 540000 ) ) );
		self::assertSame( 301, self::pick( SlotKey::WHEELS, $wheels, $build, Measure::millimetres( 530000 ) ), '52 and 54 are as near; the smaller' );
		self::assertSame( 301, self::pick( SlotKey::WHEELS, $wheels, $build ), 'nothing wanted: the smallest' );
	}

	public function test_where_nothing_is_wanted_the_smallest_that_fits_and_then_the_first_given(): void {
		$bearings = array( self::stocked( Builds::bearings( '8', 501 ), 0 ), Builds::bearings( '8', 502 ), Builds::bearings( '10', 503 ) );
		self::assertSame( 502, self::pick( SlotKey::BEARINGS, $bearings, self::rolling( '54' ) ) );
		$bearings = array( self::stocked( Builds::bearings( '8', 501 ), 0 ), Builds::bearings( '8', 502 ), Builds::bearings( '8', 503 ) );
		self::assertSame( 502, self::pick( SlotKey::BEARINGS, $bearings, self::rolling( '54' ) ), 'the same size: the first given' );
	}

	public function test_a_part_with_no_figure_on_the_slots_size_is_the_furthest(): void {
		$ranged = Parts::of( array( 'deck_min' => '8.4', 'deck_max' => '9.0', 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 2 ), 1 );
		self::assertSame( 149, self::pick( SlotKey::TRUCKS, array( $ranged, Builds::truck( '149' ) ), Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) ), 'a range and no axle fits, and is nearest to nothing' );
		self::assertSame( 1, self::pick( SlotKey::TRUCKS, array( $ranged ), Parts::picks( array( SlotKey::DECK => Builds::deck( '8.5' ) ) ) ), 'and is still a size' );
	}

	public function test_a_slot_the_rules_do_not_know_comes_in_the_first_given_that_fits(): void {
		$slots            = KitDocuments::slots();
		$slots['slots'][] = array(
			'key'      => 'tool',
			'label'    => 'Skate tool',
			'required' => false,
			'units'    => 1,
		);
		$tools            = array( self::stocked( Parts::of( array(), 901 ), 0 ), Parts::of( array(), 902 ), Parts::of( array(), 903 ) );
		$option           = ( new SizePicker( FitEngine::standard() ) )->pick( KitDocuments::kit( $slots ), 'tool', $tools, Builds::complete(), Measure::inches( 10000 ) );
		self::assertSame( 902, $option?->part->id );
	}

	/**
	 * @return iterable<string, array{string, bool}>
	 */
	public static function deciders(): iterable {
		yield 'deck' => array( SlotKey::DECK, true );
		yield 'trucks' => array( SlotKey::TRUCKS, true );
		yield 'risers' => array( SlotKey::RISERS, true );
		yield 'hardware' => array( SlotKey::HARDWARE, true );
		yield 'grip' => array( SlotKey::GRIP, true );
		yield 'wheels' => array( SlotKey::WHEELS, false );
		yield 'bearings' => array( SlotKey::BEARINGS, false );
		yield 'a slot the rules do not know' => array( 'tool', false );
	}

	#[DataProvider( 'deciders' )]
	public function test_the_build_decides_the_size_on_deck_trucks_risers_hardware_and_grip( string $slot, bool $decides ): void {
		self::assertSame( $decides, SizePicker::decides( $slot ) );
	}
}
