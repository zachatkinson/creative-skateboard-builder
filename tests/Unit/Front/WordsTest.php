<?php
/**
 * The builder's sentences.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, French and other languages: the domain speaks in codes, and the words are added at the edge. Every code has
 * its sentence, a measure is written the way skaters say it, and no sentence is the code itself.
 */
final class WordsTest extends TestCase {

	private static function in( string $decimal ): Measure {
		return Measure::inches( (int) round( (float) $decimal * 10000 ) );
	}

	private static function mm( string $decimal ): Measure {
		return Measure::millimetres( (int) round( (float) $decimal * 10000 ) );
	}

	/**
	 * @return iterable<string, array{Measure, bool, string}>
	 */
	public static function measures(): iterable {
		yield 'a deck, as a decimal' => array( self::in( '8.25' ), false, '8.25 in' );
		yield 'a deck, to three places' => array( self::in( '8.375' ), false, '8.375 in' );
		yield 'a whole number of inches' => array( self::in( '9' ), false, '9 in' );
		yield 'one place' => array( self::in( '8.5' ), false, '8.5 in' );
		yield 'four places' => array( self::in( '8.0625' ), false, '8.0625 in' );
		yield 'a small part of an inch' => array( self::in( '0.0625' ), false, '0.0625 in' );
		yield 'wheels' => array( self::mm( '54' ), false, '54 mm' );
		yield 'half a millimetre' => array( self::mm( '1.5' ), false, '1.5 mm' );
		yield 'bolts, as a fraction' => array( self::in( '1.25' ), true, '1¼ in' );
		yield 'bolts of a whole inch' => array( self::in( '1' ), true, '1 in' );
		yield 'a riser under an inch' => array( self::in( '0.125' ), true, '⅛ in' );
		yield 'seven eighths' => array( self::in( '0.875' ), true, '⅞ in' );
		yield 'each eighth has its sign' => array( self::in( '2.625' ), true, '2⅝ in' );
		yield 'a great many inches and a quarter' => array( self::in( '10000.25' ), true, '10,000¼ in' );
		yield 'three eighths' => array( self::in( '1.375' ), true, '1⅜ in' );
		yield 'a half' => array( self::in( '1.5' ), true, '1½ in' );
		yield 'three quarters' => array( self::in( '1.75' ), true, '1¾ in' );
		yield 'not a whole number of eighths stays a decimal' => array( self::in( '1.1' ), true, '1.1 in' );
		yield 'millimetres are never fractions' => array( self::mm( '3.25' ), true, '3.25 mm' );
		yield 'nothing' => array( self::in( '0' ), true, '0 in' );
	}

	#[DataProvider( 'measures' )]
	public function test_a_measure_is_written_the_way_skaters_say_it( Measure $measure, bool $fraction, string $text ): void {
		self::assertSame( $text, Words::measure( $measure, $fraction ) );
	}

	public function test_a_measure_is_a_decimal_unless_its_kind_is_said_in_fractions(): void {
		self::assertSame( '1.25 in', Words::measure( self::in( '1.25' ) ) );
	}

	/**
	 * @return iterable<string, array{MessageCode, array<string, Measure|string>, string}>
	 */
	public static function findings(): iterable {
		yield 'a spec missing' => array( MessageCode::SpecMissing, array( 'spec' => 'axle_width' ), 'Axle width is not filled in for this part, so its fit cannot be checked.' );
		yield 'a deck below the range' => array(
			MessageCode::DeckBelowRange,
			array(
				'deck_width' => self::in( '7.75' ),
				'deck_min'   => self::in( '8' ),
				'deck_max'   => self::in( '8.5' ),
			),
			'The trucks are made for decks from 8 in to 8.5 in wide. The deck is 7.75 in.',
		);
		yield 'a deck below a range with no top' => array(
			MessageCode::DeckBelowRange,
			array(
				'deck_width' => self::in( '7.75' ),
				'deck_min'   => self::in( '8' ),
			),
			'The trucks are made for decks 8 in wide and over. The deck is 7.75 in.',
		);
		yield 'a deck above the range' => array(
			MessageCode::DeckAboveRange,
			array(
				'deck_width' => self::in( '9' ),
				'deck_min'   => self::in( '8' ),
				'deck_max'   => self::in( '8.5' ),
			),
			'The trucks are made for decks from 8 in to 8.5 in wide. The deck is 9 in.',
		);
		yield 'a deck above a range with no bottom' => array(
			MessageCode::DeckAboveRange,
			array(
				'deck_width' => self::in( '9' ),
				'deck_max'   => self::in( '8.5' ),
			),
			'The trucks are made for decks up to 8.5 in wide. The deck is 9 in.',
		);
		yield 'a range whose bottom is nothing has no bottom' => array(
			MessageCode::DeckAboveRange,
			array(
				'deck_width' => self::in( '9' ),
				'deck_min'   => self::in( '0' ),
				'deck_max'   => self::in( '8.5' ),
			),
			'The trucks are made for decks up to 8.5 in wide. The deck is 9 in.',
		);
		$axle = array(
			'deck_width' => self::in( '8.5' ),
			'axle_width' => self::in( '8' ),
			'gap'        => self::in( '0.5' ),
		);
		yield 'a narrower axle' => array( MessageCode::AxleNarrower, $axle, 'The trucks\' axle is 8 in, which is 0.5 in narrower than the 8.5 in deck.' );
		yield 'a wider axle' => array( MessageCode::AxleWider, $axle, 'The trucks\' axle is 8 in, which is 0.5 in wider than the 8.5 in deck.' );
		yield 'hole patterns' => array(
			MessageCode::PatternMismatch,
			array(
				'hole_pattern' => 'old_school',
				'baseplate'    => 'new_school',
			),
			'The deck\'s hole pattern is Old school and the trucks\' baseplate is New school.',
		);
		yield 'a pattern taken' => array( MessageCode::PatternAssumed, array( 'hole_pattern' => 'new_school' ), 'The deck gives no hole pattern, so the usual one is taken: New school.' );
		$wheels = array(
			'wheel_diameter' => self::mm( '58' ),
			'limit'          => self::mm( '56' ),
			'over'           => self::mm( '2' ),
		);
		yield 'wheels that need a riser' => array( MessageCode::WheelNeedsRiser, $wheels + array( 'riser' => self::in( '0.25' ) ), 'Wheels of 58 mm are 2 mm over what the trucks clear, which is 56 mm. A riser of ¼ in or thicker makes the room.' );
		yield 'wheels no riser makes room for' => array( MessageCode::WheelNoRiser, $wheels, 'Wheels of 58 mm are 2 mm over what the trucks clear, which is 56 mm. That is more than a riser makes up for.' );
		yield 'a limit from the height' => array(
			MessageCode::LimitFromClass,
			array(
				'height_class' => 'low',
				'limit'        => self::mm( '53' ),
			),
			'The trucks give no largest wheel, so the usual one for their height (Low) is taken: 53 mm.',
		);
		$bolts = array(
			'bolt_length'     => self::in( '0.875' ),
			'shortest'        => self::in( '1.125' ),
			'usual'           => self::in( '1.25' ),
			'riser_thickness' => self::in( '0.25' ),
		);
		$bare  = array( 'riser_thickness' => self::in( '0' ) ) + $bolts;
		yield 'bolts too short for a riser' => array( MessageCode::BoltsTooShort, $bolts, 'Bolts of ⅞ in are too short with a ¼ in riser. Use 1⅛ in or longer.' );
		yield 'bolts too short with no riser' => array( MessageCode::BoltsTooShort, $bare, 'Bolts of ⅞ in are too short. Use 1⅛ in or longer.' );
		yield 'bolts too long for a riser' => array( MessageCode::BoltsTooLong, $bolts, 'Bolts of ⅞ in are longer than a ¼ in riser needs. The usual length is 1¼ in.' );
		yield 'bolts too long with no riser' => array( MessageCode::BoltsTooLong, $bare, 'Bolts of ⅞ in are longer than a board with no riser needs. The usual length is 1¼ in.' );
		yield 'a bore' => array(
			MessageCode::BoreMismatch,
			array(
				'bearing_bore'  => self::mm( '10' ),
				'axle_diameter' => self::mm( '8' ),
			),
			'The bearings\' bore is 10 mm and the trucks\' axle is 8 mm.',
		);
		yield 'an axle taken' => array( MessageCode::AxleAssumed, array( 'axle_diameter' => self::mm( '8' ) ), 'The trucks give no axle size, so the usual one is taken: 8 mm.' );
		yield 'spacers built in' => array( MessageCode::SpacersBuiltIn, array(), 'The bearings have spacers built in, so they take no others.' );
		yield 'grip too narrow' => array(
			MessageCode::GripTooNarrow,
			array(
				'grip_width' => self::in( '8' ),
				'deck_width' => self::in( '8.5' ),
			),
			'The grip is 8 in wide and the deck is 8.5 in.',
		);
		yield 'grip too short' => array(
			MessageCode::GripTooShort,
			array(
				'grip_length' => self::in( '31' ),
				'deck_length' => self::in( '32.125' ),
			),
			'The grip is 31 in long and the deck is 32.125 in.',
		);
		yield 'a length not checked' => array( MessageCode::LengthUnchecked, array(), 'The deck gives no length, so the grip\'s length is not checked.' );
	}

	/**
	 * @param array<string, Measure|string> $values The finding's values.
	 */
	#[DataProvider( 'findings' )]
	public function test_a_finding_is_a_sentence_with_its_values_in_it( MessageCode $code, array $values, string $sentence ): void {
		self::assertSame( $sentence, Words::finding( new Finding( RuleId::R1, Severity::Block, $code, Source::Maker, array( 'deck' ), $values ) ) );
	}

	public function test_every_code_has_a_sentence_of_its_own(): void {
		$told = array();
		foreach ( self::findings() as $case ) {
			$told[ $case[0]->value ] = true;
		}
		self::assertSame( array(), array_values( array_diff( array_column( MessageCode::cases(), 'value' ), array_keys( $told ) ) ), 'A code with no case here has no sentence that is tested.' );
	}

	public function test_a_spec_this_version_does_not_know_is_named_as_it_came(): void {
		self::assertSame(
			'kingpin_angle is not filled in for this part, so its fit cannot be checked.',
			Words::finding( new Finding( RuleId::R1, Severity::Block, MessageCode::SpecMissing, Source::None, array( 'deck' ), array( 'spec' => 'kingpin_angle' ) ) )
		);
	}

	/**
	 * @return iterable<string, array{MessageCode, string, string}>
	 */
	public static function reasons(): iterable {
		yield 'spec_missing' => array( MessageCode::SpecMissing, '1 part hidden: a spec its fit is checked by is not filled in.', '9 parts hidden: a spec their fit is checked by is not filled in.' );
		yield 'deck_below_range' => array( MessageCode::DeckBelowRange, '1 part hidden: the trucks are made for another width of deck.', '9 parts hidden: the trucks are made for another width of deck.' );
		yield 'deck_above_range' => array( MessageCode::DeckAboveRange, '1 part hidden: the trucks are made for another width of deck.', '9 parts hidden: the trucks are made for another width of deck.' );
		yield 'axle_narrower' => array( MessageCode::AxleNarrower, '1 part hidden: the trucks would be too narrow for the deck.', '9 parts hidden: the trucks would be too narrow for the deck.' );
		yield 'axle_wider' => array( MessageCode::AxleWider, '1 part hidden: the trucks would be too wide for the deck.', '9 parts hidden: the trucks would be too wide for the deck.' );
		yield 'pattern_mismatch' => array( MessageCode::PatternMismatch, '1 part hidden: the mounting holes would not line up.', '9 parts hidden: the mounting holes would not line up.' );
		yield 'bolts_too_short' => array( MessageCode::BoltsTooShort, '1 part hidden: the bolts would be too short.', '9 parts hidden: the bolts would be too short.' );
		yield 'bore_mismatch' => array( MessageCode::BoreMismatch, '1 part hidden: the bearings would not fit the axle.', '9 parts hidden: the bearings would not fit the axle.' );
		yield 'spacers_built_in' => array( MessageCode::SpacersBuiltIn, '1 part hidden: the bearings have spacers built in.', '9 parts hidden: the bearings have spacers built in.' );
		yield 'grip_too_narrow' => array( MessageCode::GripTooNarrow, '1 part hidden: the grip would be narrower than the deck.', '9 parts hidden: the grip would be narrower than the deck.' );
		yield 'grip_too_short' => array( MessageCode::GripTooShort, '1 part hidden: the grip would be shorter than the deck.', '9 parts hidden: the grip would be shorter than the deck.' );
		yield 'a code that hides nothing today' => array( MessageCode::WheelNoRiser, '1 part hidden: it does not fit the rest of the build.', '9 parts hidden: they do not fit the rest of the build.' );
	}

	#[DataProvider( 'reasons' )]
	public function test_a_hidden_count_says_how_many_and_why( MessageCode $code, string $one, string $nine ): void {
		self::assertSame( $one, Words::hidden( $code, 1 ) );
		self::assertSame( $nine, Words::hidden( $code, 9 ) );
	}

	public function test_a_count_is_written_as_the_language_writes_numbers(): void {
		self::assertSame( '1,234 parts hidden: the bolts would be too short.', Words::hidden( MessageCode::BoltsTooShort, 1234 ) );
	}

	/**
	 * @return iterable<string, array{Problem, string}>
	 */
	public static function problems(): iterable {
		yield 'slot_unknown' => array( Problem::SlotUnknown, 'This kit has no slot called "Trucks".' );
		yield 'slot_empty' => array( Problem::SlotEmpty, 'Trucks: nothing is chosen yet.' );
		yield 'part_not_in_slot' => array( Problem::PartNotInSlot, 'Trucks: the part chosen is not one this kit offers here.' );
		yield 'part_sold_out' => array( Problem::PartSoldOut, 'Trucks: the part chosen is sold out.' );
		yield 'part_short' => array( Problem::PartShort, 'Trucks: the shop has fewer of the part chosen than a build takes.' );
		yield 'part_blocked' => array( Problem::PartBlocked, 'Trucks: the part chosen does not fit the rest of the build.' );
	}

	#[DataProvider( 'problems' )]
	public function test_a_problem_names_its_slot( Problem $problem, string $sentence ): void {
		self::assertSame( $sentence, Words::problem( $problem, 'Trucks' ) );
	}

	public function test_every_problem_has_a_sentence_of_its_own(): void {
		self::assertCount( count( Problem::cases() ), iterator_to_array( self::problems() ) );
	}

	/**
	 * @return iterable<string, array{CartRefusal, string}>
	 */
	public static function refusals(): iterable {
		yield 'cbuild_no_build' => array( CartRefusal::NoBuild, 'Choose the parts for this build first, then add it to the cart.' );
		yield 'cbuild_kit_not_found' => array( CartRefusal::KitNotFound, 'This build is no longer sold. Nothing was added to the cart.' );
		yield 'cbuild_build_refused' => array( CartRefusal::BuildRefused, 'This build cannot be sold as it is. Nothing was added to the cart.' );
		yield 'cbuild_no_price' => array( CartRefusal::NoPrice, 'This build cannot be priced at the moment. Nothing was added to the cart.' );
		yield 'cbuild_not_added' => array( CartRefusal::NotAdded, 'One of the parts could not be added, so nothing of the build was. Check the parts and add the build again.' );
		yield 'cbuild_part_locked' => array( CartRefusal::PartLocked, 'A part of a build comes in the quantity the build needs. Change the quantity of the build instead.' );
		yield 'cbuild_build_incomplete' => array( CartRefusal::BuildIncomplete, 'A build in your cart had lost one of its parts and was removed. Add the build again.' );
	}

	#[DataProvider( 'refusals' )]
	public function test_a_refusal_says_what_was_not_done( CartRefusal $refusal, string $sentence ): void {
		self::assertSame( $sentence, Words::refusal( $refusal ) );
	}

	public function test_every_refusal_has_a_sentence_of_its_own_under_its_own_code(): void {
		self::assertSame( array_column( CartRefusal::cases(), 'value' ), array_keys( iterator_to_array( self::refusals() ) ) );
	}

	public function test_a_price_change_is_said_with_its_sign_or_as_free_or_included(): void {
		self::assertSame( '+$12.00', Words::delta( 'more', '$12.00' ) );
		self::assertSame( '−$5.00', Words::delta( 'less', '$5.00' ), 'A minus sign, not a hyphen.' );
		self::assertSame( 'Free', Words::delta( 'free', '$0.00' ) );
		self::assertSame( 'Included', Words::delta( 'included', '$0.00' ) );
		self::assertSame( '', Words::delta( 'none', '$0.00' ) );
	}

	public function test_a_part_in_the_box_is_labelled_with_its_slot_and_what_it_has_of_a_brand_and_a_size(): void {
		self::assertSame( 'Wheels: Retna, 54 mm', Words::boxed( 'Wheels', 'Retna', '54 mm' ) );
		self::assertSame( 'Wheels: Retna', Words::boxed( 'Wheels', 'Retna', '' ) );
		self::assertSame( 'Wheels: 54 mm', Words::boxed( 'Wheels', '', '54 mm' ) );
		self::assertSame( 'Wheels', Words::boxed( 'Wheels', '', '' ) );
	}

	public function test_the_build_is_said_in_one_sentence_or_as_nothing_chosen(): void {
		self::assertSame( 'Nothing is chosen yet.', Words::built( array() ) );
		self::assertSame( 'The build so far: A deck.', Words::built( array( 'A deck' ) ) );
		self::assertSame( 'The build so far: A deck, Trucks and Wheels.', Words::built( array( 'A deck', 'Trucks', 'Wheels' ) ) );
	}
}
