<?php
/**
 * An order's builds, read from its lines, and where the lines no longer match.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\Mismatch;
use CreativeBranding\SkateboardBuilder\Service\OrderLine;
use CreativeBranding\SkateboardBuilder\Service\OrderReading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, On orders: the Build box lists a build by slot from `_cbuild_snapshot`, and says so before anyone
 * prints a ticket when the order's lines no longer match it: a part removed, a quantity changed, a line copied without
 * its keys, or a kit's line with no `_cbuild_id` (D-139). The reading is worked out each time the box is shown, from
 * the lines as they are, so a change by any route is caught.
 */
#[CoversClass( OrderReading::class )]
#[CoversClass( Mismatch::class )]
final class OrderReadingTest extends TestCase {

	private const KIT = 77;

	/**
	 * A snapshot as the checkout writes it: a deck, a pair of trucks and a set of wheels.
	 *
	 * @param bool $assembly Whether the shop assembles it.
	 * @return array<string, mixed>
	 */
	private static function snapshot( bool $assembly = true ): array {
		$pick = static fn( string $label, string $name, string $sku, int $part, int $quantity, array $specs ): array => array(
			'label'    => $label,
			'name'     => $name,
			'sku'      => $sku,
			'part'     => $part,
			'quantity' => $quantity,
			'specs'    => $specs,
		);
		return array(
			'schema_version' => 1,
			'kit'            => self::KIT,
			'assembly'       => $assembly,
			'picks'          => array(
				'deck'   => $pick( 'Deck', 'Eight and a half', 'DK-85', 103, 1, array( 'deck_width' => '8.5000' ) ),
				'trucks' => $pick( 'Trucks', 'Stage 11', 'IND-149', 149, 1, array( 'axle_width' => '8.5000' ) ),
				'wheels' => $pick( 'Wheels', 'Formula Four', '', 54, 4, array( 'wheel_diameter' => '54.0000' ) ),
			),
			'lead_time'      => '',
			'returns_notice' => '',
		);
	}

	/**
	 * An order with one build, as the checkout writes it: the kit's line, then a line per part.
	 *
	 * @param int $boards How many of the build.
	 * @return list<OrderLine>
	 */
	private static function order( int $boards = 1 ): array {
		return array(
			new OrderLine( 11, self::KIT, $boards, 'Complete', 'a', 'kit', '', true, self::snapshot() ),
			new OrderLine( 12, 103, $boards, 'Eight and a half', 'a', 'part', 'deck' ),
			new OrderLine( 13, 149, $boards, 'Stage 11', 'a', 'part', 'trucks' ),
			new OrderLine( 14, 54, 4 * $boards, 'Formula Four', 'a', 'part', 'wheels' ),
		);
	}

	/**
	 * The mismatches as compared lists: code, build, slot, item, expected, found, name.
	 *
	 * @param OrderReading $reading The reading.
	 * @return list<list<int|string>>
	 */
	private static function told( OrderReading $reading ): array {
		return array_map(
			static fn( Mismatch $mismatch ): array => array( $mismatch->code, $mismatch->build, $mismatch->slot, $mismatch->item, $mismatch->expected, $mismatch->found, $mismatch->name ),
			$reading->mismatches
		);
	}

	public function test_an_order_as_the_checkout_wrote_it_reads_as_its_build_by_slot_and_matches(): void {
		$reading = OrderReading::of( self::order() );
		self::assertSame( array(), $reading->mismatches );
		self::assertTrue( $reading->has() );
		self::assertCount( 1, $reading->builds );
		$build = $reading->builds[0];
		self::assertSame( array( 'a', 11, self::KIT, 1, true, true ), array( $build->id, $build->item, $build->kit, $build->boards, $build->assembly, $build->read ) );
		self::assertSame( array( 'deck', 'trucks', 'wheels' ), array_keys( $build->picks ), 'in step order, as sold' );
		$wheels = $build->picks['wheels'];
		self::assertSame( array( 'wheels', 'Wheels', 'Formula Four', '', 54, 4, 14 ), array( $wheels->slot, $wheels->label, $wheels->name, $wheels->sku, $wheels->part, $wheels->quantity, $wheels->item ) );
		self::assertSame( array( 'wheel_diameter' => '54.0000' ), $wheels->specs );
		self::assertSame( array( 'IND-149', 13 ), array( $build->picks['trucks']->sku, $build->picks['trucks']->item ) );
		self::assertSame( array(), $reading->about( 'a' ) );
		self::assertSame( 1, $reading->toAssemble() );
	}

	public function test_two_boards_of_a_build_need_twice_each_part_and_still_match(): void {
		$reading = OrderReading::of( self::order( 2 ) );
		self::assertSame( array(), $reading->mismatches );
		self::assertSame( 2, $reading->builds[0]->boards );
		self::assertSame( 4, $reading->builds[0]->picks['wheels']->quantity, 'how many one board takes' );
		self::assertSame( 2, $reading->toAssemble() );
	}

	public function test_a_part_line_removed_is_a_mismatch_named_for_its_slot(): void {
		$lines = self::order();
		unset( $lines[2] );
		$reading = OrderReading::of( array_values( $lines ) );
		self::assertSame( array( array( Mismatch::PART_REMOVED, 'a', 'trucks', 0, 0, 0, '' ) ), self::told( $reading ) );
		self::assertSame( 0, $reading->builds[0]->picks['trucks']->item, 'the slot is still listed, as sold, with no line' );
		self::assertSame( array( 'Stage 11', 'IND-149' ), array( $reading->builds[0]->picks['trucks']->name, $reading->builds[0]->picks['trucks']->sku ) );
	}

	public function test_a_quantity_changed_on_a_part_or_on_the_kit_is_a_mismatch_with_both_numbers(): void {
		$lines    = self::order();
		$lines[3] = new OrderLine( 14, 54, 3, 'Formula Four', 'a', 'part', 'wheels' );
		self::assertSame( array( array( Mismatch::QUANTITY_CHANGED, 'a', 'wheels', 14, 4, 3, 'Formula Four' ) ), self::told( OrderReading::of( $lines ) ) );

		$lines    = self::order();
		$lines[0] = new OrderLine( 11, self::KIT, 2, 'Complete', 'a', 'kit', '', true, self::snapshot() );
		self::assertSame(
			array(
				array( Mismatch::QUANTITY_CHANGED, 'a', 'deck', 12, 2, 1, 'Eight and a half' ),
				array( Mismatch::QUANTITY_CHANGED, 'a', 'trucks', 13, 2, 1, 'Stage 11' ),
				array( Mismatch::QUANTITY_CHANGED, 'a', 'wheels', 14, 8, 4, 'Formula Four' ),
			),
			self::told( OrderReading::of( $lines ) ),
			'two boards of a build whose parts are still for one'
		);
	}

	public function test_a_part_line_copied_without_its_keys_is_named_as_that_and_not_as_removed(): void {
		$lines    = self::order();
		$lines[2] = new OrderLine( 31, 149, 1, 'Stage 11', '', '', '', false, null );
		$reading  = OrderReading::of( $lines );
		self::assertSame( array( array( Mismatch::COPIED_WITHOUT_KEYS, 'a', 'trucks', 31, 0, 0, 'Stage 11' ) ), self::told( $reading ) );
		self::assertSame( 31, $reading->builds[0]->picks['trucks']->item );
	}

	public function test_a_line_of_a_part_bought_besides_the_build_is_no_mismatch(): void {
		$lines   = self::order();
		$lines[] = new OrderLine( 41, 54, 4, 'Formula Four', '', '', '', false, null );
		self::assertSame( array(), OrderReading::of( $lines )->mismatches, 'the slot has its own line, so this one is another purchase' );
	}

	public function test_one_line_without_keys_stands_in_for_one_slot_alone(): void {
		$snapshot                    = self::snapshot();
		$snapshot['picks']['spare']  = $snapshot['picks']['wheels'];
		$snapshot['picks']['spare']['label'] = 'Spare';
		$lines    = array(
			new OrderLine( 11, self::KIT, 1, 'Complete', 'a', 'kit', '', true, $snapshot ),
			new OrderLine( 12, 103, 1, 'Eight and a half', 'a', 'part', 'deck' ),
			new OrderLine( 13, 149, 1, 'Stage 11', 'a', 'part', 'trucks' ),
			new OrderLine( 41, 54, 4, 'Formula Four', '', '', '', false, null ),
		);
		self::assertSame(
			array(
				array( Mismatch::COPIED_WITHOUT_KEYS, 'a', 'wheels', 41, 0, 0, 'Formula Four' ),
				array( Mismatch::PART_REMOVED, 'a', 'spare', 0, 0, 0, '' ),
			),
			self::told( OrderReading::of( $lines ) )
		);
	}

	public function test_a_kits_line_with_no_build_is_a_mismatch_of_the_order_and_no_build(): void {
		$reading = OrderReading::of( array( new OrderLine( 51, self::KIT, 1, 'Complete', '', '', '', true, null ), new OrderLine( 52, 7, 1, 'Wax', '', '', '', false, null ) ) );
		self::assertSame( array(), $reading->builds );
		self::assertSame( array( array( Mismatch::KIT_WITHOUT_BUILD, '', '', 51, 0, 0, 'Complete' ) ), self::told( $reading ) );
		self::assertTrue( $reading->has(), 'the box shows, to say so' );
		self::assertSame( 0, $reading->toAssemble() );
		self::assertSame( $reading->mismatches, $reading->about( '' ) );
	}

	public function test_an_order_with_no_build_has_nothing_to_show(): void {
		$reading = OrderReading::of( array( new OrderLine( 52, 7, 1, 'Wax', '', '', '', false, null ) ) );
		self::assertFalse( $reading->has() );
		self::assertSame( array(), $reading->builds );
		self::assertFalse( OrderReading::of( array() )->has() );
	}

	public function test_parts_whose_kit_line_was_removed_are_read_as_a_build_from_the_lines_with_the_mismatch(): void {
		$lines = self::order();
		unset( $lines[0] );
		$reading = OrderReading::of( array_values( $lines ) );
		self::assertSame( array( array( Mismatch::KIT_REMOVED, 'a', '', 0, 0, 0, '' ) ), self::told( $reading ) );
		$build = $reading->builds[0];
		self::assertSame( array( 'a', 0, 0, 1, false, false ), array( $build->id, $build->item, $build->kit, $build->boards, $build->assembly, $build->read ) );
		self::assertSame( array( 'deck', 'trucks', 'wheels' ), array_keys( $build->picks ) );
		$wheels = $build->picks['wheels'];
		self::assertSame( array( 'wheels', 'wheels', 'Formula Four', '', 54, 4, 14, array() ), array( $wheels->slot, $wheels->label, $wheels->name, $wheels->sku, $wheels->part, $wheels->quantity, $wheels->item, $wheels->specs ), 'the line as it is: its key for a label, and no specs' );
		self::assertSame( 0, $reading->toAssemble(), 'nothing says it was to be assembled' );
	}

	/**
	 * Snapshots that do not read.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function unread(): array {
		$snapshot = self::snapshot();
		$no       = static function ( callable $change ) use ( $snapshot ): array {
			return array( $change( $snapshot ) );
		};
		return array(
			'none'                 => array( null ),
			'a string'             => array( 'a:1:{}' ),
			'no picks'             => $no( static fn( array $s ): array => array_diff_key( $s, array( 'picks' => 0 ) ) ),
			'picks empty'          => $no( static fn( array $s ): array => array( 'picks' => array() ) + $s ),
			'picks not a list'     => $no( static fn( array $s ): array => array( 'picks' => 'deck' ) + $s ),
			'a pick not an array'  => $no( static fn( array $s ): array => array( 'picks' => array( 'deck' => 103 ) ) + $s ),
			'a part not a number'  => $no( static fn( array $s ): array => array( 'picks' => array( 'deck' => array( 'part' => '103', 'quantity' => 1 ) ) ) + $s ),
			'a part of nothing'    => $no( static fn( array $s ): array => array( 'picks' => array( 'deck' => array( 'part' => 0, 'quantity' => 1 ) ) ) + $s ),
			'a quantity of none'   => $no( static fn( array $s ): array => array( 'picks' => array( 'deck' => array( 'part' => 103, 'quantity' => 0 ) ) ) + $s ),
			'a quantity as words'  => $no( static fn( array $s ): array => array( 'picks' => array( 'deck' => array( 'part' => 103, 'quantity' => 'one' ) ) ) + $s ),
			'a slot keyed by a number' => $no( static fn( array $s ): array => array( 'picks' => array( 0 => array( 'part' => 103, 'quantity' => 1 ) ) ) + $s ),
		);
	}

	#[DataProvider( 'unread' )]
	public function test_a_snapshot_that_does_not_read_is_said_and_the_build_is_read_from_its_lines( mixed $snapshot ): void {
		$lines    = self::order();
		$lines[0] = new OrderLine( 11, self::KIT, 1, 'Complete', 'a', 'kit', '', true, $snapshot );
		$reading  = OrderReading::of( $lines );
		self::assertSame( array( array( Mismatch::SNAPSHOT_UNREAD, 'a', '', 11, 0, 0, 'Complete' ) ), self::told( $reading ) );
		$build = $reading->builds[0];
		self::assertSame( array( 11, 0, 1, false, false ), array( $build->item, $build->kit, $build->boards, $build->assembly, $build->read ) );
		self::assertSame( array( 'deck', 'trucks', 'wheels' ), array_keys( $build->picks ) );
		self::assertSame( array( 54, 4, 14 ), array( $build->picks['wheels']->part, $build->picks['wheels']->quantity, $build->picks['wheels']->item ) );
	}

	public function test_a_snapshot_short_of_words_or_specs_still_reads_with_what_it_has(): void {
		$snapshot          = self::snapshot( false );
		$snapshot['kit']   = 'seventy-seven';
		$snapshot['picks'] = array(
			'deck' => array(
				'part'     => 103,
				'quantity' => 1,
				'specs'    => array( 'deck_width' => '8.5000', 'deck_length' => 32, 'hole' => array() ),
			),
		);
		unset( $snapshot['assembly'] );
		$reading = OrderReading::of(
			array(
				new OrderLine( 11, self::KIT, 1, 'Complete', 'a', 'kit', '', true, $snapshot ),
				new OrderLine( 12, 103, 1, 'Deck', 'a', 'part', 'deck' ),
			)
		);
		self::assertSame( array(), $reading->mismatches );
		$build = $reading->builds[0];
		self::assertSame( array( 0, false, true ), array( $build->kit, $build->assembly, $build->read ), 'a kit that is no number is none, and assembly not said is no assembly' );
		$deck = $build->picks['deck'];
		self::assertSame( array( 'deck', '', '' ), array( $deck->label, $deck->name, $deck->sku ), 'a slot with no label is called by its key' );
		self::assertSame( array( 'deck_width' => '8.5000' ), $deck->specs, 'a value that is not text is left out' );
		self::assertSame( 0, $reading->toAssemble() );
	}

	public function test_a_line_tied_to_a_slot_the_build_never_had_is_a_mismatch(): void {
		$lines   = self::order();
		$lines[] = new OrderLine( 15, 88, 1, 'Bones Reds', 'a', 'part', 'bearings' );
		self::assertSame( array( array( Mismatch::PART_NOT_IN_BUILD, 'a', 'bearings', 15, 0, 0, 'Bones Reds' ) ), self::told( OrderReading::of( $lines ) ) );
	}

	public function test_a_slots_line_twice_is_a_mismatch_on_the_second(): void {
		$lines   = self::order();
		$lines[] = new OrderLine( 16, 149, 1, 'Stage 11', 'a', 'part', 'trucks' );
		$reading = OrderReading::of( $lines );
		self::assertSame( array( array( Mismatch::PART_TWICE, 'a', 'trucks', 16, 0, 0, 'Stage 11' ) ), self::told( $reading ) );
		self::assertSame( 13, $reading->builds[0]->picks['trucks']->item, 'the first is the slot\'s' );
	}

	public function test_a_slots_line_of_another_product_is_a_mismatch_with_both_products(): void {
		$lines    = self::order();
		$lines[2] = new OrderLine( 13, 150, 1, 'Stage 11 Hollow', 'a', 'part', 'trucks' );
		self::assertSame( array( array( Mismatch::PART_CHANGED, 'a', 'trucks', 13, 149, 150, 'Stage 11 Hollow' ) ), self::told( OrderReading::of( $lines ) ) );
	}

	public function test_two_builds_on_an_order_are_read_apart_in_the_order_of_their_kits(): void {
		$second = array(
			new OrderLine( 21, self::KIT, 1, 'Complete', 'b', 'kit', '', true, self::snapshot( false ) ),
			new OrderLine( 22, 103, 1, 'Eight and a half', 'b', 'part', 'deck' ),
			new OrderLine( 23, 149, 1, 'Stage 11', 'b', 'part', 'trucks' ),
		);
		$reading = OrderReading::of( array( ...$second, ...self::order() ) );
		self::assertSame( array( 'b', 'a' ), array_map( static fn( $build ): string => $build->id, $reading->builds ) );
		self::assertSame( array( array( Mismatch::PART_REMOVED, 'b', 'wheels', 0, 0, 0, '' ) ), self::told( $reading ) );
		self::assertSame( self::told( $reading ), array_map( static fn( Mismatch $m ): array => array( $m->code, $m->build, $m->slot, $m->item, $m->expected, $m->found, $m->name ), $reading->about( 'b' ) ) );
		self::assertSame( array(), $reading->about( 'a' ) );
		self::assertSame( 1, $reading->toAssemble(), 'b is not assembled' );
	}

	public function test_a_second_kit_line_for_one_build_is_a_line_twice(): void {
		$lines   = self::order();
		$lines[] = new OrderLine( 17, self::KIT, 1, 'Complete', 'a', 'kit', '', true, self::snapshot() );
		$reading = OrderReading::of( $lines );
		self::assertSame( array( array( Mismatch::PART_TWICE, 'a', '', 17, 0, 0, 'Complete' ) ), self::told( $reading ) );
		self::assertSame( 11, $reading->builds[0]->item );
	}

	public function test_a_line_copied_without_keys_is_claimed_once_and_by_the_first_slot_that_lost_its_line(): void {
		$lines   = self::order( 1 );
		$lines[3] = new OrderLine( 41, 54, 4, 'Formula Four', '', '', '', false, null );
		$second  = array(
			new OrderLine( 21, self::KIT, 1, 'Complete', 'b', 'kit', '', true, self::snapshot() ),
			new OrderLine( 22, 103, 1, 'Eight and a half', 'b', 'part', 'deck' ),
			new OrderLine( 23, 149, 1, 'Stage 11', 'b', 'part', 'trucks' ),
		);
		self::assertSame(
			array(
				array( Mismatch::COPIED_WITHOUT_KEYS, 'a', 'wheels', 41, 0, 0, 'Formula Four' ),
				array( Mismatch::PART_REMOVED, 'b', 'wheels', 0, 0, 0, '' ),
			),
			self::told( OrderReading::of( array( ...$lines, ...$second ) ) )
		);
	}
}
