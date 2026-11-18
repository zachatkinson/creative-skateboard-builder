<?php
/**
 * What the workshop reads on an order: the mismatch, the ticket's specs and steps, the column and the refund help.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Order;

use CreativeBranding\SkateboardBuilder\Order\WorkshopWords;
use CreativeBranding\SkateboardBuilder\Service\Mismatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, On orders; Pricing, stock, assembly and returns, the assembly ticket and Returns. The services answer
 * codes, and each has one sentence here; a filter lets a workshop change the ticket's specs and steps.
 */
#[CoversClass( WorkshopWords::class )]
final class WorkshopWordsTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'], $GLOBALS['cbuild_test_decimal'] );
	}

	/**
	 * Each mismatch and its sentence.
	 *
	 * @return array<string, array{Mismatch, string, string, string}>
	 */
	public static function mismatches(): array {
		return array(
			'a part removed'             => array( new Mismatch( Mismatch::PART_REMOVED, 'a', 'trucks' ), 'Trucks', '', 'The Trucks line is no longer on the order.' ),
			'a quantity changed'         => array( new Mismatch( Mismatch::QUANTITY_CHANGED, 'a', 'wheels', 14, 8, 1234, 'Formula Four' ), 'Wheels', '', 'The Wheels line has 1,234, and the build needs 8.' ),
			'copied without its keys'    => array( new Mismatch( Mismatch::COPIED_WITHOUT_KEYS, 'a', 'trucks', 31, 0, 0, 'Stage 11' ), 'Trucks', '', 'The Trucks line was added again without the build\'s details, so it is no longer tied to the build.' ),
			'a kit with no build'        => array( new Mismatch( Mismatch::KIT_WITHOUT_BUILD, '', '', 51, 0, 0, 'Complete' ), '', '', 'Complete is a kit\'s line with no build behind it. It was added outside the builder, so there is no ticket to print for it.' ),
			'the kit removed'            => array( new Mismatch( Mismatch::KIT_REMOVED, 'a' ), '', '', 'The build\'s kit line is no longer on the order. Its parts are listed from their own lines.' ),
			'the snapshot unread'        => array( new Mismatch( Mismatch::SNAPSHOT_UNREAD, 'a', '', 11, 0, 0, 'Complete' ), '', '', 'The record of this build as it was sold cannot be read. Its parts are listed from their own lines.' ),
			'a slot\'s line twice'       => array( new Mismatch( Mismatch::PART_TWICE, 'a', 'trucks', 16, 0, 0, 'Stage 11' ), 'Trucks', '', 'The Trucks line is on the order twice.' ),
			'the kit\'s line twice'      => array( new Mismatch( Mismatch::PART_TWICE, 'a', '', 17, 0, 0, 'Complete' ), '', '', 'The build\'s kit line is on the order twice.' ),
			'another product'            => array( new Mismatch( Mismatch::PART_CHANGED, 'a', 'trucks', 13, 149, 150, 'Stage 11 Hollow' ), 'Trucks', 'Stage 11', 'The Trucks line is now Stage 11 Hollow. The build was sold with Stage 11.' ),
			'a slot the build never had' => array( new Mismatch( Mismatch::PART_NOT_IN_BUILD, 'a', 'bearings', 15, 0, 0, 'Bones Reds' ), '', '', 'Bones Reds is tied to this build, but the build was sold without it.' ),
			'a code this version lacks'  => array( new Mismatch( 'later_code', 'a' ), '', '', 'A line of the order no longer matches the build. Check its lines before you print the assembly ticket.' ),
		);
	}

	#[DataProvider( 'mismatches' )]
	public function test_each_mismatch_has_its_sentence( Mismatch $mismatch, string $label, string $sold, string $sentence ): void {
		self::assertSame( $sentence, WorkshopWords::mismatch( $mismatch, $label, $sold ) );
	}

	public function test_the_ticket_shows_the_specs_that_matter_for_each_slot_and_a_filter_changes_them(): void {
		self::assertSame( array( 'deck_width' ), WorkshopWords::ticketSpecs( 'deck', 77 ) );
		self::assertSame( array( 'axle_width' ), WorkshopWords::ticketSpecs( 'trucks', 77 ) );
		self::assertSame( array( 'wheel_diameter' ), WorkshopWords::ticketSpecs( 'wheels', 77 ) );
		self::assertSame( array( 'riser_thickness' ), WorkshopWords::ticketSpecs( 'risers', 77 ) );
		self::assertSame( array( 'bolt_length' ), WorkshopWords::ticketSpecs( 'hardware', 77 ) );
		self::assertSame( array(), WorkshopWords::ticketSpecs( 'grip', 77 ) );
		self::assertSame( array(), WorkshopWords::ticketSpecs( 'bearings', 77 ) );

		$asked                                               = array();
		$GLOBALS['cbuild_test_filters']['cbuild_ticket_specs'] = static function ( array $specs, string $slot, int $kit ) use ( &$asked ): mixed {
			$asked[] = array( $specs, $slot, $kit );
			return match ( $slot ) {
				'deck'   => array( 'deck_width', 'deck_length', 3, 'deck_width', array() ),
				'wheels' => array( 'wheel_diameter', 'wheel_diameter', 'wheel_width' ),
				default  => 'nothing',
			};
		};
		self::assertSame( array( 'deck_width', 'deck_length' ), WorkshopWords::ticketSpecs( 'deck', 77 ), 'what is no text is left out, and each key is said once' );
		self::assertSame( array( 'wheel_diameter', 'wheel_width' ), WorkshopWords::ticketSpecs( 'wheels', 77 ), 'a list, numbered from 0, after a key said twice' );
		self::assertSame( array(), WorkshopWords::ticketSpecs( 'trucks', 78 ), 'an answer that is no list shows none' );
		self::assertSame( array( array( array( 'deck_width' ), 'deck', 77 ), array( array( 'wheel_diameter' ), 'wheels', 77 ), array( array( 'axle_width' ), 'trucks', 78 ) ), $asked );
	}

	/**
	 * Slots picked, and the steps an assembled build's ticket lists for them.
	 *
	 * @return array<string, array{list<string>, list<string>}>
	 */
	public static function steps(): array {
		$grip    = 'Lay the grip tape on the deck, press it down and trim it to the edge.';
		$trucks  = 'Bolt the trucks to the deck.';
		$risers  = 'Bolt the trucks to the deck, with the risers between them.';
		$press   = 'Press the bearings into the wheels.';
		$wheels  = 'Put the wheels on the trucks and tighten the axle nuts until each wheel spins without wobbling.';
		$checked = 'Check every bolt and nut, and spin each wheel.';
		return array(
			'a complete board'         => array( array( 'deck', 'trucks', 'wheels', 'risers', 'bearings', 'hardware', 'grip' ), array( $grip, $risers, $press, $wheels, $checked ) ),
			'no risers and no grip'    => array( array( 'deck', 'trucks', 'wheels', 'bearings', 'hardware' ), array( $trucks, $press, $wheels, $checked ) ),
			'no wheels and no bearings' => array( array( 'deck', 'trucks', 'hardware' ), array( $trucks, $checked ) ),
			'wheels and no bearings'   => array( array( 'deck', 'trucks', 'wheels' ), array( $trucks, $wheels, $checked ) ),
			'bearings and no wheels'   => array( array( 'deck', 'trucks', 'bearings' ), array( $trucks, $checked ) ),
			'a deck alone'             => array( array( 'deck' ), array( $checked ) ),
			'risers with no trucks'    => array( array( 'deck', 'risers' ), array( $checked ) ),
		);
	}

	/**
	 * @param list<string> $slots The slots picked.
	 * @param list<string> $steps The steps.
	 */
	#[DataProvider( 'steps' )]
	public function test_an_assembled_builds_ticket_lists_a_step_for_what_it_has( array $slots, array $steps ): void {
		self::assertSame( $steps, WorkshopWords::steps( array_fill_keys( $slots, 9 ), true, 77, 1001 ) );
	}

	public function test_a_build_the_shop_does_not_assemble_has_no_steps_unless_a_filter_gives_some(): void {
		self::assertSame( array(), WorkshopWords::steps( array( 'deck' => 103 ), false, 77, 1001 ) );
	}

	public function test_a_filter_changes_the_steps_and_what_is_no_sentence_is_left_out(): void {
		$asked                                                 = array();
		$GLOBALS['cbuild_test_filters']['cbuild_ticket_steps'] = static function ( array $steps, array $context ) use ( &$asked ): mixed {
			$asked[] = array( $steps, $context );
			return array( ...$steps, 'Wax the <b>rails</b>.', '', '<i></i>', 7, 'Wax the rails.' );
		};
		self::assertSame( array( 'Check every bolt and nut, and spin each wheel.', 'Wax the rails.' ), WorkshopWords::steps( array( 'deck' => 103 ), true, 77, 1001 ), 'tags are taken out, an empty step is dropped and each is said once' );
		self::assertSame(
			array(
				array(
					array( 'Check every bolt and nut, and spin each wheel.' ),
					array(
						'kit'      => 77,
						'order'    => 1001,
						'parts'    => array( 'deck' => 103 ),
						'assembly' => true,
					),
				),
			),
			$asked
		);
		$GLOBALS['cbuild_test_filters']['cbuild_ticket_steps'] = static fn(): mixed => 'Wax the rails.';
		self::assertSame( array(), WorkshopWords::steps( array( 'deck' => 103 ), true, 77, 1001 ), 'an answer that is no list lists none' );
		$GLOBALS['cbuild_test_filters']['cbuild_ticket_steps'] = static fn(): array => array_map( static fn( int $n ): string => 'Step ' . $n . '.', range( 1, 40 ) );
		self::assertCount( WorkshopWords::STEPS_MOST, WorkshopWords::steps( array( 'deck' => 103 ), true, 77, 1001 ), 'a ticket holds at most so many steps' );
		self::assertSame( 20, WorkshopWords::STEPS_MOST );
	}

	/**
	 * Specs as sold and as the ticket says them.
	 *
	 * @return array<string, array{string, string, array{label: string, value: string}|null}>
	 */
	public static function specs(): array {
		return array(
			'a deck\'s width'        => array( 'deck_width', '8.5000', array( 'label' => 'Deck width', 'value' => '8.5″' ) ),
			'a wheel in millimetres' => array( 'wheel_diameter', '54.0000', array( 'label' => 'Wheel diameter', 'value' => "54\u{00A0}mm" ) ),
			'a bolt in fractions'    => array( 'bolt_length', '1.2500', array( 'label' => 'Bolt length', 'value' => '1¼″' ) ),
			'a riser in fractions'   => array( 'riser_thickness', '0.1250', array( 'label' => 'Riser thickness', 'value' => '⅛″' ) ),
			'an axle in decimals'    => array( 'axle_width', '8.2500', array( 'label' => 'Axle width', 'value' => '8.25″' ) ),
			'a choice'               => array( 'hole_pattern', 'new_school', array( 'label' => 'Deck hole pattern', 'value' => 'New school' ) ),
			'a count'                => array( 'set_size', '4', array( 'label' => 'Number in a set', 'value' => '4' ) ),
			'a flag'                 => array( 'spacers_built_in', '1', array( 'label' => 'Spacers built in', 'value' => 'Yes' ) ),
			'a measure gone wrong'   => array( 'deck_width', 'wide', array( 'label' => 'Deck width', 'value' => 'wide' ) ),
			'a key it does not know' => array( 'deck_colour', 'red', null ),
		);
	}

	/**
	 * @param string                                   $key   The spec's key.
	 * @param string                                   $value Its value, as the index held it.
	 * @param array{label: string, value: string}|null $said  What the ticket says.
	 */
	#[DataProvider( 'specs' )]
	public function test_a_spec_as_sold_is_said_with_its_name_and_its_unit( string $key, string $value, ?array $said ): void {
		self::assertSame( $said, WorkshopWords::spec( $key, $value ) );
	}

	public function test_the_assembly_column_says_how_many_boards_to_assemble_or_that_there_are_none(): void {
		self::assertSame( '1 board to assemble', WorkshopWords::column( 1, 1 ) );
		self::assertSame( '2 boards to assemble', WorkshopWords::column( 3, 2 ) );
		self::assertSame( 'Not assembled', WorkshopWords::column( 2, 0 ) );
		self::assertSame( '', WorkshopWords::column( 0, 0 ), 'an order with no build says nothing' );
	}

	public function test_the_refund_help_says_two_refunds_are_needed_once_the_grip_is_applied(): void {
		self::assertSame(
			'Restock refunded items covers a whole refund. Where the grip is already applied, refund the unused parts first with it ticked, then the grip and the rest in a second refund with it cleared.',
			WorkshopWords::refund()
		);
	}

	public function test_the_build_is_described_once_for_whoever_cannot_see_its_picture(): void {
		self::assertSame( 'The build: Deck: Eight and a half; Trucks: Stage 11, raw.', WorkshopWords::described( array( array( 'Deck', 'Eight and a half' ), array( 'Trucks', 'Stage 11, raw' ) ) ) );
		self::assertSame( '', WorkshopWords::described( array() ) );
	}

	public function test_a_build_says_how_many_boards_and_whether_the_shop_assembles_them(): void {
		self::assertSame( '1 board, assembled by the shop.', WorkshopWords::assembled( true, 1 ) );
		self::assertSame( '1,200 boards, assembled by the shop.', WorkshopWords::assembled( true, 1200 ) );
		self::assertSame( '1 board, not assembled. The parts ship as they are.', WorkshopWords::assembled( false, 1 ) );
		self::assertSame( '2 boards, not assembled. The parts ship as they are.', WorkshopWords::assembled( false, 2 ) );
	}

	public function test_a_build_and_a_board_are_counted_where_an_order_has_more_than_one(): void {
		self::assertSame( 'Complete', WorkshopWords::title( 1, 1, 'Complete' ) );
		self::assertSame( 'Build 2 of 3: Complete', WorkshopWords::title( 2, 3, 'Complete' ) );
		self::assertSame( 'Build', WorkshopWords::title( 1, 1, '' ), 'a kit\'s line that is gone' );
		self::assertSame( 'Build 1 of 2: Build', WorkshopWords::title( 1, 2, '' ) );
		self::assertSame( '', WorkshopWords::board( 1, 1 ) );
		self::assertSame( 'Board 1 of 2', WorkshopWords::board( 1, 2 ) );
		self::assertSame( 'Board 1,000 of 1,000', WorkshopWords::board( 1000, 1000 ) );
	}
}
