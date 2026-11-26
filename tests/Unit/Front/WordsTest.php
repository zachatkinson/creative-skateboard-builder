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
		yield 'a deck, as a decimal' => array( self::in( '8.25' ), false, '8.25″' );
		yield 'a deck, to three places' => array( self::in( '8.375' ), false, '8.375″' );
		yield 'a whole number of inches' => array( self::in( '9' ), false, '9″' );
		yield 'one place' => array( self::in( '8.5' ), false, '8.5″' );
		yield 'four places' => array( self::in( '8.0625' ), false, '8.0625″' );
		yield 'a small part of an inch' => array( self::in( '0.0625' ), false, '0.0625″' );
		yield 'wheels' => array( self::mm( '54' ), false, "54\u{00A0}mm" );
		yield 'half a millimetre' => array( self::mm( '1.5' ), false, "1.5\u{00A0}mm" );
		yield 'bolts, as a fraction' => array( self::in( '1.25' ), true, '1¼″' );
		yield 'bolts of a whole inch' => array( self::in( '1' ), true, '1″' );
		yield 'a riser under an inch' => array( self::in( '0.125' ), true, '⅛″' );
		yield 'seven eighths' => array( self::in( '0.875' ), true, '⅞″' );
		yield 'each eighth has its sign' => array( self::in( '2.625' ), true, '2⅝″' );
		yield 'a great many inches and a quarter' => array( self::in( '10000.25' ), true, '10,000¼″' );
		yield 'three eighths' => array( self::in( '1.375' ), true, '1⅜″' );
		yield 'a half' => array( self::in( '1.5' ), true, '1½″' );
		yield 'three quarters' => array( self::in( '1.75' ), true, '1¾″' );
		yield 'not a whole number of eighths stays a decimal' => array( self::in( '1.1' ), true, '1.1″' );
		yield 'millimetres are never fractions' => array( self::mm( '3.25' ), true, "3.25\u{00A0}mm" );
		yield 'nothing' => array( self::in( '0' ), true, '0″' );
	}

	#[DataProvider( 'measures' )]
	public function test_a_measure_is_written_the_way_skaters_say_it( Measure $measure, bool $fraction, string $text ): void {
		self::assertSame( $text, Words::measure( $measure, $fraction ) );
	}

	public function test_a_measure_is_a_decimal_unless_its_kind_is_said_in_fractions(): void {
		self::assertSame( '1.25″', Words::measure( self::in( '1.25' ) ) );
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
			'The trucks are made for decks from 8″ to 8.5″ wide. The deck is 7.75″.',
		);
		yield 'a deck below a range with no top' => array(
			MessageCode::DeckBelowRange,
			array(
				'deck_width' => self::in( '7.75' ),
				'deck_min'   => self::in( '8' ),
			),
			'The trucks are made for decks 8″ wide and over. The deck is 7.75″.',
		);
		yield 'a deck above the range' => array(
			MessageCode::DeckAboveRange,
			array(
				'deck_width' => self::in( '9' ),
				'deck_min'   => self::in( '8' ),
				'deck_max'   => self::in( '8.5' ),
			),
			'The trucks are made for decks from 8″ to 8.5″ wide. The deck is 9″.',
		);
		yield 'a deck above a range with no bottom' => array(
			MessageCode::DeckAboveRange,
			array(
				'deck_width' => self::in( '9' ),
				'deck_max'   => self::in( '8.5' ),
			),
			'The trucks are made for decks up to 8.5″ wide. The deck is 9″.',
		);
		yield 'a range whose bottom is nothing has no bottom' => array(
			MessageCode::DeckAboveRange,
			array(
				'deck_width' => self::in( '9' ),
				'deck_min'   => self::in( '0' ),
				'deck_max'   => self::in( '8.5' ),
			),
			'The trucks are made for decks up to 8.5″ wide. The deck is 9″.',
		);
		$axle = array(
			'deck_width' => self::in( '8.5' ),
			'axle_width' => self::in( '8' ),
			'gap'        => self::in( '0.5' ),
		);
		yield 'a narrower axle' => array( MessageCode::AxleNarrower, $axle, 'The trucks\' axle is 8″, which is 0.5″ narrower than the 8.5″ deck.' );
		yield 'a wider axle' => array( MessageCode::AxleWider, $axle, 'The trucks\' axle is 8″, which is 0.5″ wider than the 8.5″ deck.' );
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
		yield 'wheels that need a riser' => array( MessageCode::WheelNeedsRiser, $wheels + array( 'riser' => self::in( '0.25' ) ), 'Wheels of 58 mm are 2 mm over what the trucks clear, which is 56 mm. A riser of ¼″ or thicker makes the room.' );
		yield 'wheels no riser makes room for' => array( MessageCode::WheelNoRiser, $wheels, 'Wheels of 58 mm are 2 mm over what the trucks clear, which is 56 mm. That is more than a riser makes up for.' );
		yield 'wheels whose riser is not in stock' => array( MessageCode::RiserNotInStock, $wheels + array( 'riser' => self::in( '0.5' ) ), 'Needs ½″ risers, and none are in stock.' );
		yield 'wheels whose riser no bolts reach through' => array( MessageCode::RiserNoBolts, $wheels + array( 'riser' => self::in( '0.5' ) ), 'Needs ½″ risers, and no bolts here are long enough for them.' );
		yield 'wheels in a kit with no risers' => array( MessageCode::RiserNoSlot, $wheels + array( 'riser' => self::in( '0.5' ) ), 'Needs ½″ risers, and this kit has none.' );
		yield 'a riser no bolts reach through' => array(
			MessageCode::RiserBoltsShort,
			array(
				'riser_thickness' => self::in( '0.5' ),
				'shortest'        => self::in( '1.375' ),
			),
			'No bolts here are long enough for a ½″ riser. It takes 1⅜″ or longer.',
		);
		yield 'a limit from the height' => array(
			MessageCode::LimitFromClass,
			array(
				'height_class' => 'low',
				'limit'        => self::mm( '53' ),
			),
			'The trucks give no largest wheel, so the usual one for their height (Low) is taken: 53 mm.',
		);
		$bolts = array(
			'bolt_length'     => self::in( '0.875' ),
			'shortest'        => self::in( '1.125' ),
			'usual'           => self::in( '1.25' ),
			'riser_thickness' => self::in( '0.25' ),
		);
		$bare  = array( 'riser_thickness' => self::in( '0' ) ) + $bolts;
		yield 'bolts too short for a riser' => array( MessageCode::BoltsTooShort, $bolts, 'Bolts of ⅞″ are too short with a ¼″ riser. Use 1⅛″ or longer.' );
		yield 'bolts too short with no riser' => array( MessageCode::BoltsTooShort, $bare, 'Bolts of ⅞″ are too short. Use 1⅛″ or longer.' );
		yield 'bolts too long for a riser' => array( MessageCode::BoltsTooLong, $bolts, 'Bolts of ⅞″ are longer than a ¼″ riser needs. The usual length is 1¼″.' );
		yield 'bolts too long with no riser' => array( MessageCode::BoltsTooLong, $bare, 'Bolts of ⅞″ are longer than a board with no riser needs. The usual length is 1¼″.' );
		yield 'a bore' => array(
			MessageCode::BoreMismatch,
			array(
				'bearing_bore'  => self::mm( '10' ),
				'axle_diameter' => self::mm( '8' ),
			),
			'The bearings\' bore is 10 mm and the trucks\' axle is 8 mm.',
		);
		yield 'an axle taken' => array( MessageCode::AxleAssumed, array( 'axle_diameter' => self::mm( '8' ) ), 'The trucks give no axle size, so the usual one is taken: 8 mm.' );
		yield 'spacers built in' => array( MessageCode::SpacersBuiltIn, array(), 'The bearings have spacers built in, so they take no others.' );
		yield 'bearings included' => array( MessageCode::BearingsIncluded, array(), 'The wheels come with their bearings, so they take no others.' );
		yield 'grip too narrow' => array(
			MessageCode::GripTooNarrow,
			array(
				'grip_width' => self::in( '8' ),
				'deck_width' => self::in( '8.5' ),
			),
			'The grip is 8″ wide and the deck is 8.5″.',
		);
		yield 'grip too short' => array(
			MessageCode::GripTooShort,
			array(
				'grip_length' => self::in( '31' ),
				'deck_length' => self::in( '32.125' ),
			),
			'The grip is 31″ long and the deck is 32.125″.',
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

	// F14 (#99): a deck's compact card says the one width its look is made in, and nothing where it has no width.
	public function test_a_look_in_one_size_is_only_in_that_size(): void {
		self::assertSame( array( 'Only in 8.25″', '' ), array( Words::only( '8.25″' ), Words::only( '' ) ) );
	}

	// Cowork's 1.2.0 review, F31 (#148, D-399): a deck's other widths take one line of its card. One or two are named; past
	// two they are counted, so a deck sold in seven widths says six in a few words, not three lines of them.
	public function test_a_decks_other_widths_are_named_up_to_two_and_counted_past_two(): void {
		self::assertSame( '', Words::also( array() ) );
		self::assertSame( 'Also in 8″', Words::also( array( '8″' ) ) );
		self::assertSame( 'Also in 8″ and 8.5″', Words::also( array( '8″', '8.5″' ) ) );
		self::assertSame( 'Also in 3 other widths', Words::also( array( '8″', '8.5″', '8.75″' ) ) );
		self::assertSame( 'Also in 6 other widths', Words::also( array( '7.75″', '8″', '8.125″', '8.25″', '8.38″', '8.75″' ) ) );
	}

	// #137 (D-392): a part a ready build picks says which, by the builds' names as the site's language lists them, and
	// nothing where none does.
	public function test_a_part_a_ready_build_picks_says_which(): void {
		self::assertSame(
			array( 'Picked for Street', 'Picked for Street and Cruiser', 'Picked for Street, Park and Cruiser', '' ),
			array( Words::pickedFor( array( 'Street' ) ), Words::pickedFor( array( 'Street', 'Cruiser' ) ), Words::pickedFor( array( 'Street', 'Park', 'Cruiser' ) ), Words::pickedFor( array() ) )
		);
	}

	/**
	 * @return iterable<string, array{float, int, string, string}>
	 */
	public static function ratings(): iterable {
		yield 'one review' => array( 5.0, 1, '.', '5 out of 5, 1 review' );
		yield 'a half' => array( 4.5, 12, '.', '4.5 out of 5, 12 reviews' );
		yield 'as WooCommerce keeps it, two places' => array( 4.33, 3, '.', '4.3 out of 5, 3 reviews' );
		yield 'rounded to a whole' => array( 3.96, 1234, '.', '4 out of 5, 1,234 reviews' );
		yield "the shop's decimal comma" => array( 4.5, 2, ',', '4,5 out of 5, 2 reviews' );
	}

	// #137 (D-392): a rating said as a person reads it, its one decimal in the shop's own separator, its count in the
	// site's, and a review or reviews by the count.
	#[DataProvider( 'ratings' )]
	public function test_a_rating_is_said_out_of_five_with_its_count( float $average, int $count, string $decimal, string $said ): void {
		$GLOBALS['cbuild_test_decimal'] = $decimal;
		try {
			self::assertSame( $said, Words::rating( $average, $count ) );
		} finally {
			unset( $GLOBALS['cbuild_test_decimal'] );
		}
	}

	/**
	 * @return iterable<string, array{float, int, string, string}>
	 */
	public static function scores(): iterable {
		yield 'one review' => array( 5.0, 1, '.', '5 (1)' );
		yield 'a half' => array( 4.5, 12, '.', '4.5 (12)' );
		yield 'as WooCommerce keeps it, two places' => array( 4.33, 3, '.', '4.3 (3)' );
		yield 'rounded to a whole' => array( 3.96, 1234, '.', '4 (1,234)' );
		yield "the shop's decimal comma" => array( 4.5, 2, ',', '4,5 (2)' );
	}

	// #137 (D-393): the rating shown beside the stars, short so it shares a line with the ready builds' pick on a card
	// as narrow as a desktop's: the average as the sentence has it, and the count. The sentence is what is said.
	#[DataProvider( 'scores' )]
	public function test_a_rating_is_shown_short_beside_its_stars( float $average, int $count, string $decimal, string $shown ): void {
		$GLOBALS['cbuild_test_decimal'] = $decimal;
		try {
			self::assertSame( $shown, Words::score( $average, $count ) );
		} finally {
			unset( $GLOBALS['cbuild_test_decimal'] );
		}
	}

	// The auditor's 1.3.0 minor: a rating is no price, so it is written in the language's decimal separator, as its
	// count is, not the shop's for prices: a shop in dollars with a page in French says "4,5".
	public function test_a_rating_takes_the_languages_decimal_separator_not_the_shops_for_prices(): void {
		$GLOBALS['cbuild_test_decimal']       = ',';
		$GLOBALS['cbuild_test_price_decimal'] = '.';
		try {
			self::assertSame( '4,5 (2)', Words::score( 4.5, 2 ) );
			self::assertSame( '4,5 out of 5, 2 reviews', Words::rating( 4.5, 2 ) );
		} finally {
			unset( $GLOBALS['cbuild_test_decimal'], $GLOBALS['cbuild_test_price_decimal'] );
		}
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
		yield 'bearings_included' => array( MessageCode::BearingsIncluded, '1 part hidden: the wheels come with their bearings.', '9 parts hidden: the wheels come with their bearings.' );
		yield 'grip_too_narrow' => array( MessageCode::GripTooNarrow, '1 part hidden: the grip would be narrower than the deck.', '9 parts hidden: the grip would be narrower than the deck.' );
		yield 'grip_too_short' => array( MessageCode::GripTooShort, '1 part hidden: the grip would be shorter than the deck.', '9 parts hidden: the grip would be shorter than the deck.' );
		yield 'riser_not_in_stock' => array( MessageCode::RiserNotInStock, '1 part hidden: the risers it needs are not in stock.', '9 parts hidden: the risers they need are not in stock.' );
		yield 'riser_no_bolts' => array( MessageCode::RiserNoBolts, '1 part hidden: no bolts here are long enough for the risers it needs.', '9 parts hidden: no bolts here are long enough for the risers they need.' );
		yield 'riser_no_slot' => array( MessageCode::RiserNoSlot, '1 part hidden: it needs risers, and this kit has none.', '9 parts hidden: they need risers, and this kit has none.' );
		yield 'riser_bolts_short' => array( MessageCode::RiserBoltsShort, '1 part hidden: no bolts here are long enough for it.', '9 parts hidden: no bolts here are long enough for them.' );
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

	public function test_a_price_change_is_said_with_its_sign_or_as_the_same_price_or_included(): void {
		self::assertSame( '+$12.00', Words::delta( 'more', '$12.00' ) );
		self::assertSame( '−$5.00', Words::delta( 'less', '$5.00' ), 'A minus sign, not a hyphen.' );
		// Cowork's review, should-fix 8 (Zach: "Same price"): Free read as no charge.
		self::assertSame( 'Same price', Words::delta( 'free', '$0.00' ) );
		self::assertSame( 'Included', Words::delta( 'included', '$0.00' ) );
		self::assertSame( '', Words::delta( 'none', '$0.00' ) );
	}

	public function test_a_part_in_the_box_is_labelled_with_its_slot_and_what_it_has_of_a_brand_and_a_size(): void {
		self::assertSame( 'Wheels: Retna, 54 mm', Words::boxed( 'Wheels', 'Retna', '54 mm' ) );
		self::assertSame( 'Wheels: Retna', Words::boxed( 'Wheels', 'Retna', '' ) );
		self::assertSame( 'Wheels: 54 mm', Words::boxed( 'Wheels', '', '54 mm' ) );
		self::assertSame( 'Wheels', Words::boxed( 'Wheels', '', '' ) );
	}

	public function test_the_build_is_said_in_one_sentence_or_as_nothing_chosen(): void {
		self::assertSame( 'Nothing is chosen yet.', Words::built( array() ) );
		self::assertSame( 'The build so far: Deck: A deck.', Words::built( array( array( 'Deck', 'A deck' ) ) ) );
		// Cowork's review, minor 15: names hold commas of their own, so each part is said by its slot.
		self::assertSame(
			'The build so far: Deck: Philly\'s, Rascals - 8.00″; Trucks: Test trucks 139; Wheels: Blotto, Blue (54mm, 101A).',
			Words::built( array( array( 'Deck', 'Philly\'s, Rascals - 8.00″' ), array( 'Trucks', 'Test trucks 139' ), array( 'Wheels', 'Blotto, Blue (54mm, 101A)' ) ) )
		);
	}

	public function test_a_slots_label_starts_small_inside_a_sentence_unless_it_is_written_in_capitals(): void {
		// Cowork's review, minors 5 and 14: "Change Deck", "Fits your Trucks".
		self::assertSame( 'deck', Words::inSentence( 'Deck' ) );
		self::assertSame( 'grip tape', Words::inSentence( 'Grip tape' ) );
		self::assertSame( 'HZRD', Words::inSentence( 'HZRD' ), 'A name in capitals is kept.' );
		self::assertSame( 'X', Words::inSentence( 'X' ) );
		self::assertSame( 'éclat', Words::inSentence( 'Éclat' ) );
		self::assertSame( '', Words::inSentence( '' ) );
		self::assertSame( 'XLarge', Words::inSentence( 'XLarge' ), 'read by its second letter alone' );
		self::assertSame( 'ÉCOLE', Words::inSentence( 'ÉCOLE' ), 'a capital after an accented one' );
		self::assertSame( 'aéro', Words::inSentence( 'Aéro' ), 'an accented small letter is a small letter' );
	}

	// #72: without mbstring the case changes are PHP's own, so a letter outside ASCII keeps its case, as WpTextFolding
	// folds; an ASCII one changes as before.
	public function test_without_mbstring_a_label_changes_only_its_ascii_letters(): void {
		$GLOBALS['cbuild_test_no_mbstring'] = true;
		try {
			self::assertSame( 'deck', Words::inSentence( 'Deck' ) );
			self::assertSame( 'HZRD', Words::inSentence( 'HZRD' ) );
			self::assertSame( 'Éclat', Words::inSentence( 'Éclat' ), 'its capital is not an ASCII letter' );
			self::assertSame( 'Aéro', Words::inSentence( 'Aéro' ), 'nor is its second letter, so it reads as a capital' );
		} finally {
			unset( $GLOBALS['cbuild_test_no_mbstring'] );
		}
		self::assertSame( 'éclat', Words::inSentence( 'Éclat' ), 'and with it, as before' );
	}

	// Spec: A step, Sold out, "A preset or link that holds one names it".
	public function test_a_problem_about_a_part_names_the_part(): void {
		self::assertSame( 'Wheels: Retna is sold out.', Words::named( Problem::PartSoldOut, 'Wheels', 'Retna' ) );
		self::assertSame( 'Wheels: the shop has fewer of Retna than a build takes.', Words::named( Problem::PartShort, 'Wheels', 'Retna' ) );
		self::assertSame( 'Wheels: Retna is no longer offered here.', Words::named( Problem::PartNotInSlot, 'Wheels', 'Retna' ) );
		self::assertSame( 'Wheels: the part chosen does not fit the rest of the build.', Words::named( Problem::PartBlocked, 'Wheels', 'Retna' ), 'one with no sentence of its own says the slot\'s' );
		self::assertSame( 'Wheels: the part chosen is sold out.', Words::named( Problem::PartSoldOut, 'Wheels', '' ), 'and one with no name' );
	}

	public function test_a_part_on_the_share_card_is_named_by_its_brand_and_its_size_or_either_alone(): void {
		self::assertSame( "Retna 54\u{00A0}mm", Words::branded( 'Retna', "54\u{00A0}mm" ) );
		self::assertSame( 'Rascals', Words::branded( 'Rascals', '' ) );
		self::assertSame( '8.5″', Words::branded( '', '8.5″' ) );
		self::assertSame( '', Words::branded( '', '' ) );
	}

	public function test_a_complete_on_the_share_card_is_named_by_its_decks_width_and_its_parts(): void {
		self::assertSame( '8.25″ complete: Rascals, Independent 8.5″ and Retna 54 mm', Words::complete( '8.25″', array( 'Rascals', 'Independent 8.5″', 'Retna 54 mm' ) ) );
		self::assertSame( '8.25″ complete: Rascals', Words::complete( '8.25″', array( 'Rascals' ) ) );
		self::assertSame( '8.25″ complete', Words::complete( '8.25″', array() ) );
		self::assertSame( 'Complete: Rascals and Retna 54 mm', Words::complete( '', array( 'Rascals', 'Retna 54 mm' ) ) );
		self::assertSame( 'A complete', Words::complete( '', array() ) );
	}

	// #135 (D-397): what each swap does to the total, said on its control, and what one swapped, said after it.
	public function test_a_swap_says_what_it_does_to_the_total_and_after_it_what_it_swapped(): void {
		self::assertSame( 'Same board for $3.00 less', Words::swap( 'board', '', -300, '$3.00' ) );
		self::assertSame( 'For less: Retna 52 mm, $2.00 less', Words::swap( 'less', 'Retna 52 mm', -200, '$2.00' ) );
		self::assertSame( 'Step up: Bones 54 mm, $1.50 more', Words::swap( 'better', 'Bones 54 mm', 150, '$1.50' ) );
		self::assertSame( 'Step up: Bones 54 mm, $0.50 less', Words::swap( 'better', 'Bones 54 mm', -50, '$0.50' ) );
		self::assertSame( 'Step up: Bones 54 mm, same price', Words::swap( 'better', 'Bones 54 mm', 0, '$0.00' ) );
		self::assertSame( 'Swapped: wheels.', Words::swapped( array( 'Wheels' ) ) );
		// The slots as the site's language joins a list, each in its sentence's case, not run together with commas (the
		// shim's %l writes English's).
		self::assertSame( 'Swapped: wheels and hardware.', Words::swapped( array( 'Wheels', 'Hardware' ) ) );
		self::assertSame( 'Swapped: wheels, hardware and bearings.', Words::swapped( array( 'Wheels', 'Hardware', 'Bearings' ) ) );
	}
}
