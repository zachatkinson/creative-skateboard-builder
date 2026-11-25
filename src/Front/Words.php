<?php
/**
 * The builder's sentences, as a shopper reads them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Admin\Labels;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Guide\RidingStyle;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Span;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Tier;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The domain speaks in codes (spec: French and other languages); here each becomes a sentence in the language the
 * request is answered in. A sentence names the parts by what they are ("the deck", "the trucks") and never as "this
 * part", so it reads the same whichever of them is the option on show. Every sentence is whole: a value takes a
 * numbered place in it, and none is built from pieces.
 *
 * @since 0.4.0
 */
final class Words {

	/**
	 * The values written as fractions, the way skaters say them (spec: Numbers and units): bolts and risers.
	 */
	private const array FRACTIONS = array( 'bolt_length', 'shortest', 'usual', 'riser_thickness', 'riser' );

	/**
	 * The eighths of an inch, by their ten-thousandths.
	 */
	private const array EIGHTHS = array(
		1250 => '⅛',
		2500 => '¼',
		3750 => '⅜',
		5000 => '½',
		6250 => '⅝',
		7500 => '¾',
		8750 => '⅞',
	);

	/**
	 * A length as a person reads it: "8.25 in", "54 mm", or "1¼ in" where its kind is said in fractions and it is
	 * a whole number of eighths.
	 *
	 * @since 0.4.0
	 *
	 * @param Measure $measure  The length.
	 * @param bool    $fraction Whether its kind is said in fractions.
	 */
	public static function measure( Measure $measure, bool $fraction = false ): string {
		$whole  = intdiv( $measure->tenThousandths, 10000 );
		$rest   = $measure->tenThousandths % 10000;
		$eighth = self::EIGHTHS[ $rest ] ?? null;
		if ( $fraction && Unit::Inch === $measure->unit && null !== $eighth ) {
			// A numeral, not a sentence: the whole number and the fraction's sign stand together in every language, as 1¼.
			$number = 0 === $whole ? $eighth : number_format_i18n( $whole ) . $eighth;
		} else {
			$number = number_format_i18n( $measure->tenThousandths / 10000, strlen( rtrim( sprintf( '%04d', $rest ), '0' ) ) );
		}
		// Cowork's review, minor 13: the spec's and the shop's notation, the inch sign against the number, and a
		// no-break space before a unit's word, so that the number and its unit never wrap apart.
		if ( Unit::Inch === $measure->unit ) {
			/* translators: %s: a number of inches. The double prime is the inch sign; French writes "%s po", with a no-break space. */
			return sprintf( _x( '%s″', 'inches', 'creative-skateboard-builder' ), $number );
		}
		/* translators: %s: a number of millimetres. The space before mm is a no-break space, written as itself: make-pot copies an escape as written, and the msgid would never match (D-228). */
		return sprintf( _x( '%s mm', 'millimetres', 'creative-skateboard-builder' ), $number );
	}

	/**
	 * What a rule found, as a card's short label where it has one, or empty: wheels over the trucks' limit say the
	 * riser they need before the pick (spec: Risers, "Before the pick").
	 *
	 * @since 1.0.0
	 *
	 * @param Finding $finding The finding.
	 */
	public static function short( Finding $finding ): string {
		if ( MessageCode::WheelNeedsRiser !== $finding->code ) {
			return '';
		}
		/* translators: %s: a riser's thickness, such as ⅛″. */
		return sprintf( __( 'Needs %s risers with your trucks', 'creative-skateboard-builder' ), self::value( $finding, 'riser' ) );
	}

	/**
	 * Why the risers step appeared, from R3's finding on the wheels picked (spec: Risers, "It opens on the riser that
	 * clears them, with the reason"), or empty for any other finding.
	 *
	 * @since 1.0.0
	 *
	 * @param Finding $finding The finding.
	 */
	public static function riserLead( Finding $finding ): string {
		if ( MessageCode::WheelNeedsRiser !== $finding->code ) {
			return '';
		}
		/* translators: 1: the wheels' diameter, such as 56 mm. 2: a riser's thickness, such as ½″. */
		return sprintf( __( 'Your %1$s wheels need %2$s risers with these trucks.', 'creative-skateboard-builder' ), self::value( $finding, 'wheel_diameter' ), self::value( $finding, 'riser' ) );
	}

	/**
	 * One of a finding's values, as words: a measure with its unit, a spec by its name, a choice by its label.
	 *
	 * @param Finding $finding The finding.
	 * @param string  $name    The value's name.
	 */
	private static function value( Finding $finding, string $name ): string {
		$value = $finding->values[ $name ] ?? '';
		if ( $value instanceof Measure ) {
			return self::measure( $value, in_array( $name, self::FRACTIONS, true ) );
		}
		$spec = 'spec' === $name ? SpecKey::tryFrom( $value ) : null;
		return null === $spec ? Labels::choice( $value ) : Labels::spec( $spec );
	}

	/**
	 * What a rule found, as a sentence.
	 *
	 * @since 0.4.0
	 *
	 * @param Finding $finding The finding.
	 */
	public static function finding( Finding $finding ): string {
		$values = $finding->values;
		$has    = static fn( string $name ): bool => ( $values[ $name ] ?? null ) instanceof Measure && $values[ $name ]->tenThousandths > 0;
		$v      = static fn( string $name ): string => self::value( $finding, $name );
		switch ( $finding->code ) {
			case MessageCode::SpecMissing:
				/* translators: %s: a spec's name, such as Deck width. */
				return sprintf( __( '%s is not filled in for this part, so its fit cannot be checked.', 'creative-skateboard-builder' ), $v( 'spec' ) );
			case MessageCode::DeckBelowRange:
			case MessageCode::DeckAboveRange:
				if ( $has( 'deck_min' ) && $has( 'deck_max' ) ) {
					/* translators: 1: the narrowest deck the trucks take. 2: the widest. 3: the deck's width. */
					return sprintf( __( 'The trucks are made for decks from %1$s to %2$s wide. The deck is %3$s.', 'creative-skateboard-builder' ), $v( 'deck_min' ), $v( 'deck_max' ), $v( 'deck_width' ) );
				}
				if ( $has( 'deck_min' ) ) {
					/* translators: 1: the narrowest deck the trucks take. 2: the deck's width. */
					return sprintf( __( 'The trucks are made for decks %1$s wide and over. The deck is %2$s.', 'creative-skateboard-builder' ), $v( 'deck_min' ), $v( 'deck_width' ) );
				}
				/* translators: 1: the widest deck the trucks take. 2: the deck's width. */
				return sprintf( __( 'The trucks are made for decks up to %1$s wide. The deck is %2$s.', 'creative-skateboard-builder' ), $v( 'deck_max' ), $v( 'deck_width' ) );
			case MessageCode::AxleNarrower:
				/* translators: 1: the axle's width. 2: how much narrower it is. 3: the deck's width. */
				return sprintf( __( 'The trucks\' axle is %1$s, which is %2$s narrower than the %3$s deck.', 'creative-skateboard-builder' ), $v( 'axle_width' ), $v( 'gap' ), $v( 'deck_width' ) );
			case MessageCode::AxleWider:
				/* translators: 1: the axle's width. 2: how much wider it is. 3: the deck's width. */
				return sprintf( __( 'The trucks\' axle is %1$s, which is %2$s wider than the %3$s deck.', 'creative-skateboard-builder' ), $v( 'axle_width' ), $v( 'gap' ), $v( 'deck_width' ) );
			case MessageCode::PatternMismatch:
				/* translators: 1: the deck's hole pattern, such as New school. 2: the baseplate's. */
				return sprintf( __( 'The deck\'s hole pattern is %1$s and the trucks\' baseplate is %2$s.', 'creative-skateboard-builder' ), $v( 'hole_pattern' ), $v( 'baseplate' ) );
			case MessageCode::PatternAssumed:
				/* translators: %s: a hole pattern, such as New school. */
				return sprintf( __( 'The deck gives no hole pattern, so the usual one is taken: %s.', 'creative-skateboard-builder' ), $v( 'hole_pattern' ) );
			case MessageCode::WheelNeedsRiser:
				/* translators: 1: the wheels' diameter. 2: how far over the trucks' limit they are. 3: the limit. 4: a riser's thickness. */
				return sprintf( __( 'Wheels of %1$s are %2$s over what the trucks clear, which is %3$s. A riser of %4$s or thicker makes the room.', 'creative-skateboard-builder' ), $v( 'wheel_diameter' ), $v( 'over' ), $v( 'limit' ), $v( 'riser' ) );
			case MessageCode::WheelNoRiser:
				/* translators: 1: the wheels' diameter. 2: how far over the trucks' limit they are. 3: the limit. */
				return sprintf( __( 'Wheels of %1$s are %2$s over what the trucks clear, which is %3$s. That is more than a riser makes up for.', 'creative-skateboard-builder' ), $v( 'wheel_diameter' ), $v( 'over' ), $v( 'limit' ) );
			case MessageCode::RiserNotInStock:
				/* translators: %s: a riser's thickness. */
				return sprintf( __( 'Needs %s risers, and none are in stock.', 'creative-skateboard-builder' ), $v( 'riser' ) );
			case MessageCode::RiserNoBolts:
				/* translators: %s: a riser's thickness. */
				return sprintf( __( 'Needs %s risers, and no bolts here are long enough for them.', 'creative-skateboard-builder' ), $v( 'riser' ) );
			case MessageCode::RiserNoSlot:
				/* translators: %s: a riser's thickness. */
				return sprintf( __( 'Needs %s risers, and this kit has none.', 'creative-skateboard-builder' ), $v( 'riser' ) );
			case MessageCode::LimitFromClass:
				/* translators: 1: the trucks' height, such as Low. 2: a wheel diameter. */
				return sprintf( __( 'The trucks give no largest wheel, so the usual one for their height (%1$s) is taken: %2$s.', 'creative-skateboard-builder' ), $v( 'height_class' ), $v( 'limit' ) );
			case MessageCode::BoltsTooShort:
				if ( $has( 'riser_thickness' ) ) {
					/* translators: 1: the bolts' length. 2: a riser's thickness. 3: the shortest bolts that reach. */
					return sprintf( __( 'Bolts of %1$s are too short with a %2$s riser. Use %3$s or longer.', 'creative-skateboard-builder' ), $v( 'bolt_length' ), $v( 'riser_thickness' ), $v( 'shortest' ) );
				}
				/* translators: 1: the bolts' length. 2: the shortest bolts that reach. */
				return sprintf( __( 'Bolts of %1$s are too short. Use %2$s or longer.', 'creative-skateboard-builder' ), $v( 'bolt_length' ), $v( 'shortest' ) );
			case MessageCode::BoltsTooLong:
				if ( $has( 'riser_thickness' ) ) {
					/* translators: 1: the bolts' length. 2: a riser's thickness. 3: the usual length. */
					return sprintf( __( 'Bolts of %1$s are longer than a %2$s riser needs. The usual length is %3$s.', 'creative-skateboard-builder' ), $v( 'bolt_length' ), $v( 'riser_thickness' ), $v( 'usual' ) );
				}
				/* translators: 1: the bolts' length. 2: the usual length. */
				return sprintf( __( 'Bolts of %1$s are longer than a board with no riser needs. The usual length is %2$s.', 'creative-skateboard-builder' ), $v( 'bolt_length' ), $v( 'usual' ) );
			case MessageCode::RiserBoltsShort:
				/* translators: 1: a riser's thickness. 2: the shortest bolts that reach through it. */
				return sprintf( __( 'No bolts here are long enough for a %1$s riser. It takes %2$s or longer.', 'creative-skateboard-builder' ), $v( 'riser_thickness' ), $v( 'shortest' ) );
			case MessageCode::BoreMismatch:
				/* translators: 1: the bearings' bore. 2: the axle's diameter. */
				return sprintf( __( 'The bearings\' bore is %1$s and the trucks\' axle is %2$s.', 'creative-skateboard-builder' ), $v( 'bearing_bore' ), $v( 'axle_diameter' ) );
			case MessageCode::AxleAssumed:
				/* translators: %s: an axle's diameter. */
				return sprintf( __( 'The trucks give no axle size, so the usual one is taken: %s.', 'creative-skateboard-builder' ), $v( 'axle_diameter' ) );
			case MessageCode::SpacersBuiltIn:
				return __( 'The bearings have spacers built in, so they take no others.', 'creative-skateboard-builder' );
			case MessageCode::BearingsIncluded:
				return __( 'The wheels come with their bearings, so they take no others.', 'creative-skateboard-builder' );
			case MessageCode::GripTooNarrow:
				/* translators: 1: the grip's width. 2: the deck's width. */
				return sprintf( __( 'The grip is %1$s wide and the deck is %2$s.', 'creative-skateboard-builder' ), $v( 'grip_width' ), $v( 'deck_width' ) );
			case MessageCode::GripTooShort:
				/* translators: 1: the grip's length. 2: the deck's length. */
				return sprintf( __( 'The grip is %1$s long and the deck is %2$s.', 'creative-skateboard-builder' ), $v( 'grip_length' ), $v( 'deck_length' ) );
			case MessageCode::LengthUnchecked:
				return __( 'The deck gives no length, so the grip\'s length is not checked.', 'creative-skateboard-builder' );
		}
	}

	/**
	 * How many parts a slot hides for one reason, as a sentence: "9 parts hidden: the trucks would be too narrow for
	 * the deck."
	 *
	 * @since 0.4.0
	 *
	 * @param MessageCode $code  The reason.
	 * @param int         $count How many.
	 */
	public static function hidden( MessageCode $code, int $count ): string {
		$number = number_format_i18n( $count );
		switch ( $code ) {
			case MessageCode::SpecMissing:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: a spec its fit is checked by is not filled in.', '%s parts hidden: a spec their fit is checked by is not filled in.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::DeckBelowRange:
			case MessageCode::DeckAboveRange:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the trucks are made for another width of deck.', '%s parts hidden: the trucks are made for another width of deck.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::AxleNarrower:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the trucks would be too narrow for the deck.', '%s parts hidden: the trucks would be too narrow for the deck.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::AxleWider:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the trucks would be too wide for the deck.', '%s parts hidden: the trucks would be too wide for the deck.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::PatternMismatch:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the mounting holes would not line up.', '%s parts hidden: the mounting holes would not line up.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::BoltsTooShort:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the bolts would be too short.', '%s parts hidden: the bolts would be too short.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::RiserNotInStock:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the risers it needs are not in stock.', '%s parts hidden: the risers they need are not in stock.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::RiserNoBolts:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: no bolts here are long enough for the risers it needs.', '%s parts hidden: no bolts here are long enough for the risers they need.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::RiserNoSlot:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: it needs risers, and this kit has none.', '%s parts hidden: they need risers, and this kit has none.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::RiserBoltsShort:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: no bolts here are long enough for it.', '%s parts hidden: no bolts here are long enough for them.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::BoreMismatch:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the bearings would not fit the axle.', '%s parts hidden: the bearings would not fit the axle.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::SpacersBuiltIn:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the bearings have spacers built in.', '%s parts hidden: the bearings have spacers built in.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::BearingsIncluded:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the wheels come with their bearings.', '%s parts hidden: the wheels come with their bearings.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::GripTooNarrow:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the grip would be narrower than the deck.', '%s parts hidden: the grip would be narrower than the deck.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::GripTooShort:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the grip would be shorter than the deck.', '%s parts hidden: the grip would be shorter than the deck.', $count, 'creative-skateboard-builder' ), $number );
			default:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: it does not fit the rest of the build.', '%s parts hidden: they do not fit the rest of the build.', $count, 'creative-skateboard-builder' ), $number );
		}
	}

	/**
	 * Why the cart refuses a build for a reason that is not one of the build's own problems, as a sentence.
	 *
	 * @since 0.5.0
	 *
	 * @param CartRefusal $refusal The refusal.
	 */
	public static function refusal( CartRefusal $refusal ): string {
		switch ( $refusal ) {
			case CartRefusal::NoBuild:
				return __( 'Choose the parts for this build first, then add it to the cart.', 'creative-skateboard-builder' );
			case CartRefusal::KitNotFound:
				return __( 'This build is no longer sold. Nothing was added to the cart.', 'creative-skateboard-builder' );
			case CartRefusal::BuildRefused:
				return __( 'This build cannot be sold as it is. Nothing was added to the cart.', 'creative-skateboard-builder' );
			case CartRefusal::NoPrice:
				return __( 'This build cannot be priced at the moment. Nothing was added to the cart.', 'creative-skateboard-builder' );
			case CartRefusal::NotAdded:
				return __( 'One of the parts could not be added, so nothing of the build was. Check the parts and add the build again.', 'creative-skateboard-builder' );
			case CartRefusal::PartLocked:
				return __( 'A part of a build comes in the quantity the build needs. Change the quantity of the build instead.', 'creative-skateboard-builder' );
			case CartRefusal::BuildIncomplete:
				return __( 'A build in your cart had lost one of its parts and was removed. Add the build again.', 'creative-skateboard-builder' );
		}
	}

	/**
	 * What keeps a build from the cart, or a pick from being taken, as a sentence.
	 *
	 * @since 0.4.0
	 *
	 * @param Problem $problem The problem.
	 * @param string  $label   The slot's label, or its key as sent where the kit has no such slot.
	 */
	public static function problem( Problem $problem, string $label ): string {
		switch ( $problem ) {
			case Problem::SlotUnknown:
				/* translators: %s: a slot's key, as the request sent it. */
				return sprintf( __( 'This kit has no slot called "%s".', 'creative-skateboard-builder' ), $label );
			case Problem::SlotEmpty:
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( '%s: nothing is chosen yet.', 'creative-skateboard-builder' ), $label );
			case Problem::PartNotInSlot:
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( '%s: the part chosen is not one this kit offers here.', 'creative-skateboard-builder' ), $label );
			case Problem::PartSoldOut:
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( '%s: the part chosen is sold out.', 'creative-skateboard-builder' ), $label );
			case Problem::PartShort:
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( '%s: the shop has fewer of the part chosen than a build takes.', 'creative-skateboard-builder' ), $label );
			case Problem::PartBlocked:
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( '%s: the part chosen does not fit the rest of the build.', 'creative-skateboard-builder' ), $label );
		}
	}

	/**
	 * A problem with the part it is about named, where it has one (spec: A step, Sold out: "A preset or link that
	 * holds one names it"). A problem about no part says what `problem()` says.
	 *
	 * @since 0.6.0
	 *
	 * @param Problem $problem The problem.
	 * @param string  $label   The slot's label, such as Wheels.
	 * @param string  $name    The part's name, or empty.
	 */
	public static function named( Problem $problem, string $label, string $name ): string {
		if ( '' === $name ) {
			return self::problem( $problem, $label );
		}
		return match ( $problem ) {
			/* translators: 1: a slot's label, such as Wheels. 2: a part's name. */
			Problem::PartSoldOut   => sprintf( __( '%1$s: %2$s is sold out.', 'creative-skateboard-builder' ), $label, $name ),
			/* translators: 1: a slot's label, such as Wheels. 2: a part's name. */
			Problem::PartShort     => sprintf( __( '%1$s: the shop has fewer of %2$s than a build takes.', 'creative-skateboard-builder' ), $label, $name ),
			/* translators: 1: a slot's label, such as Trucks. 2: a part's name. */
			Problem::PartNotInSlot => sprintf( __( '%1$s: %2$s is no longer offered here.', 'creative-skateboard-builder' ), $label, $name ),
			default                => self::problem( $problem, $label ),
		};
	}

	/**
	 * What taking a part does to the total, as its card says it (spec: A step, P4): "+$12.00", "−$5.00", "Same price"
	 * for a part that costs no more and no less, and "Included" for the part the total counts already.
	 *
	 * @since 0.6.0
	 *
	 * @param string $kind   Which: `more`, `less`, `free` or `included`. Anything else has no words.
	 * @param string $amount How much, without a sign, as the shop writes money.
	 */
	public static function delta( string $kind, string $amount ): string {
		switch ( $kind ) {
			case 'more':
				/* translators: %s: an amount of money, such as $12.00. The plus sign says it is added to the total. */
				return sprintf( _x( '+%s', 'added to the total', 'creative-skateboard-builder' ), $amount );
			case 'less':
				/* translators: %s: an amount of money, such as $5.00. The minus sign says it comes off the total. */
				return sprintf( _x( '−%s', 'taken off the total', 'creative-skateboard-builder' ), $amount );
			case 'free':
				// Cowork's review, should-fix 8: "Free" read as no charge (P4, as Zach changed it).
				return _x( 'Same price', 'a part that costs the same as the one picked', 'creative-skateboard-builder' );
			case 'included':
				return _x( 'Included', 'a part the total counts already', 'creative-skateboard-builder' );
			default:
				return '';
		}
	}

	/**
	 * The change a card's part makes to the total, against the step's pick, said under its full price (D-249):
	 * "+$12.00 over your pick", "Same price", "$8.00 less", or "Included" for the pick itself.
	 *
	 * @since 1.0.0
	 *
	 * @param string $kind   `more`, `less`, `free` or `included`; any other kind says nothing.
	 * @param string $amount The change as an amount of money, without a sign.
	 */
	public static function change( string $kind, string $amount ): string {
		switch ( $kind ) {
			case 'more':
				/* translators: %s: an amount of money, such as $12.00. What a part adds to the total, against the part picked. */
				return sprintf( __( '+%s over your pick', 'creative-skateboard-builder' ), $amount );
			case 'less':
				/* translators: %s: an amount of money, such as $8.00. What a part takes off the total, against the part picked. */
				return sprintf( __( '%s less', 'creative-skateboard-builder' ), $amount );
			case 'free':
				return _x( 'Same price', 'a part that costs the same as the one picked', 'creative-skateboard-builder' );
			case 'included':
				return _x( 'Included', 'a part the total counts already', 'creative-skateboard-builder' );
			default:
				return '';
		}
	}

	/**
	 * The other sizes a look comes in, on its card, in a line: named where there are one or two, as "Also in 8″ and
	 * 8.5″" (D-240), and counted past two, as "Also in 6 other widths", so a deck sold in seven widths does not list six
	 * over three lines (F31, D-399).
	 *
	 * @since 1.0.0
	 *
	 * @param list<string> $sizes The other sizes, as a person reads them, in order.
	 */
	public static function also( array $sizes ): string {
		if ( array() === $sizes ) {
			return '';
		}
		$count = count( $sizes );
		if ( $count > 2 ) {
			/* translators: %s: how many other widths a deck is made in, such as 6. */
			return sprintf( _n( 'Also in %s other width', 'Also in %s other widths', $count, 'creative-skateboard-builder' ), number_format_i18n( $count ) );
		}
		/* translators: %s: one size, or two joined as the site's language joins a list, such as 8″ and 8.5″. */
		return sprintf( __( 'Also in %s', 'creative-skateboard-builder' ), wp_sprintf( '%l', $sizes ) );
	}

	/**
	 * The one size a look comes in, on a deck's compact card, as "Only in 8.25″" (F14, #99).
	 *
	 * @since 1.1.0
	 *
	 * @param string $size The size, as a person reads it, or empty.
	 */
	public static function only( string $size ): string {
		if ( '' === $size ) {
			return '';
		}
		/* translators: %s: the one size a deck is made in, such as 8.25″. */
		return sprintf( __( 'Only in %s', 'creative-skateboard-builder' ), $size );
	}

	/**
	 * The ready builds that pick a part, on its card, as "Picked for Street and Cruiser" (#137, D-392); empty where
	 * none does.
	 *
	 * @since 1.3.0
	 *
	 * @param list<string> $names The ready builds' names, as the shopper reads them on their tiles, in the kit's order.
	 */
	public static function pickedFor( array $names ): string {
		if ( array() === $names ) {
			return '';
		}
		/* translators: %s: ready builds' names joined as the site's language joins a list, such as Street and Cruiser. */
		return sprintf( __( 'Picked for %s', 'creative-skateboard-builder' ), wp_sprintf( '%l', $names ) );
	}

	/**
	 * A part's star rating as words, the text the stars stand for, as "4.5 out of 5, 12 reviews" (#137, D-392): the
	 * average to one decimal, a whole one without it, in the shop's decimal separator, and the count in the site's.
	 *
	 * @since 1.3.0
	 *
	 * @param float $average The average rating, 0 to 5, as WooCommerce keeps it.
	 * @param int   $count   How many reviews it is the average of.
	 */
	public static function rating( float $average, int $count ): string {
		return sprintf(
			/* translators: 1: an average star rating, such as 4.5. 2: how many reviews it is the average of. */
			_n( '%1$s out of 5, %2$s review', '%1$s out of 5, %2$s reviews', $count, 'creative-skateboard-builder' ),
			self::average( $average ),
			number_format_i18n( $count )
		);
	}

	/**
	 * A part's star rating shown short beside its stars, as "4.5 (12)" (#137, D-393), so it shares a line with the
	 * ready builds' pick on a card as narrow as a desktop's. The sentence from rating() is what a screen reader says.
	 *
	 * @since 1.3.0
	 *
	 * @param float $average The average rating, 0 to 5, as WooCommerce keeps it.
	 * @param int   $count   How many reviews it is the average of.
	 */
	public static function score( float $average, int $count ): string {
		return sprintf(
			/* translators: 1: an average star rating, such as 4.5. 2: how many reviews it is the average of. Shown beside the stars. */
			_x( '%1$s (%2$s)', 'a rating and its count of reviews', 'creative-skateboard-builder' ),
			self::average( $average ),
			number_format_i18n( $count )
		);
	}

	/**
	 * An average rating to one decimal, a whole one without it, in the shop's decimal separator.
	 *
	 * @param float $average The average rating, 0 to 5.
	 */
	private static function average( float $average ): string {
		$tenths = (int) round( $average * 10 );
		return number_format( $tenths / 10, 0 === $tenths % 10 ? 0 : 1, wc_get_price_decimal_separator(), '' );
	}

	/**
	 * A part's label in the picture of what is in the box (spec: The picture): its slot, then its brand and its
	 * size where it has them, as "Wheels: Retna, 54 mm".
	 *
	 * @since 0.6.0
	 *
	 * @param string $label The slot's label.
	 * @param string $brand The brand's name, or empty.
	 * @param string $size  The part's size as a person reads it, or empty.
	 */
	public static function boxed( string $label, string $brand, string $size ): string {
		if ( '' !== $brand && '' !== $size ) {
			/* translators: 1: a slot's label, such as Wheels. 2: a brand's name. 3: a size, such as 54 mm. */
			return sprintf( __( '%1$s: %2$s, %3$s', 'creative-skateboard-builder' ), $label, $brand, $size );
		}
		if ( '' === $brand && '' === $size ) {
			return $label;
		}
		/* translators: 1: a slot's label, such as Wheels. 2: a brand's name, or a size such as 54 mm. */
		return sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $label, '' === $brand ? $size : $brand );
	}

	/**
	 * A part as a share card names it, by its brand and its size, as "Retna 54 mm"; either alone where it has one.
	 *
	 * @since 0.7.0
	 *
	 * @param string $brand The brand's name, or empty.
	 * @param string $size  The part's size as a person reads it, or empty.
	 */
	public static function branded( string $brand, string $size ): string {
		if ( '' === $brand || '' === $size ) {
			return '' === $brand ? $size : $brand;
		}
		/* translators: 1: a brand's name, such as Retna. 2: a size, such as 54 mm. */
		return sprintf( _x( '%1$s %2$s', 'a brand and a size', 'creative-skateboard-builder' ), $brand, $size );
	}

	/**
	 * Values named as a choice, as "7.75″, 8.125″ or 9″": the last two joined by "or", the rest by commas.
	 *
	 * @since 1.0.0
	 *
	 * @param list<string> $values The values, in order.
	 */
	public static function either( array $values ): string {
		$last = array_pop( $values );
		if ( null === $last ) {
			return '';
		}
		if ( array() === $values ) {
			return $last;
		}
		/* translators: 1: values joined by commas, such as 7.75″, 8.125″. 2: the last value, such as 9″. */
		return sprintf( __( '%1$s or %2$s', 'creative-skateboard-builder' ), implode( _x( ', ', 'between values in a list', 'creative-skateboard-builder' ), $values ), $last );
	}

	/**
	 * A complete's name on its share card (spec: SEO), as "8.25 in complete: Rascals, Independent 8.5 in, Retna
	 * 54 mm". The parts are joined as the site's language joins a list.
	 *
	 * @since 0.7.0
	 *
	 * @param string       $width The deck's width as a person reads it, or empty.
	 * @param list<string> $parts The parts a complete is known by, each as branded() names it.
	 */
	public static function complete( string $width, array $parts ): string {
		$list = wp_sprintf( '%l', $parts );
		if ( '' === $width ) {
			if ( '' === $list ) {
				return __( 'A complete', 'creative-skateboard-builder' );
			}
			/* translators: %s: the build's parts, as "Rascals, Independent 8.5 in and Retna 54 mm". */
			return sprintf( __( 'Complete: %s', 'creative-skateboard-builder' ), $list );
		}
		if ( '' === $list ) {
			/* translators: %s: the deck's width, such as 8.25 in. */
			return sprintf( __( '%s complete', 'creative-skateboard-builder' ), $width );
		}
		/* translators: 1: the deck's width, such as 8.25 in. 2: the build's parts, as "Rascals, Independent 8.5 in and Retna 54 mm". */
		return sprintf( __( '%1$s complete: %2$s', 'creative-skateboard-builder' ), $width, $list );
	}

	/**
	 * The build in a sentence, for whoever cannot see its picture (spec: The picture, a written description). Each
	 * part is said by its slot, since a part's name may hold commas of its own (Cowork's review, minor 15).
	 *
	 * @since 0.6.0
	 *
	 * @param list<array{0: string, 1: string}> $parts Each part's slot label and name, in step order.
	 */
	public static function built( array $parts ): string {
		if ( array() === $parts ) {
			return __( 'Nothing is chosen yet.', 'creative-skateboard-builder' );
		}
		$said = array();
		foreach ( $parts as [ $label, $name ] ) {
			/* translators: 1: a slot's label, such as Deck. 2: the part's name. */
			$said[] = sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $label, $name );
		}
		/* translators: Between two parts of the build, as in "Deck: …; Trucks: …". */
		$between = _x( '; ', 'between the parts of a build', 'creative-skateboard-builder' );
		/* translators: %s: each part of the build, by its slot, as "Deck: …; Trucks: …". */
		return sprintf( __( 'The build so far: %s.', 'creative-skateboard-builder' ), implode( $between, $said ) );
	}

	/**
	 * A smart swap's control (#135, D-397): what it swaps in and what it does to the total. The board's says its saving
	 * alone; a slot's names the part.
	 *
	 * @since 1.3.0
	 *
	 * @param string $kind   `board`, `less` or `better`.
	 * @param string $name   The part it swaps in, for a slot's.
	 * @param int    $change The total's change in minor units: below 0 for less.
	 * @param string $amount The change with no sign, as the shop writes prices.
	 */
	public static function swap( string $kind, string $name, int $change, string $amount ): string {
		if ( 'board' === $kind ) {
			/* translators: %s: an amount of money, such as $3.00. */
			return sprintf( __( 'Same board for %s less', 'creative-skateboard-builder' ), $amount );
		}
		if ( 'less' === $kind ) {
			/* translators: 1: a part's name. 2: an amount of money, such as $2.00. */
			return sprintf( __( 'For less: %1$s, %2$s less', 'creative-skateboard-builder' ), $name, $amount );
		}
		if ( $change > 0 ) {
			/* translators: 1: a part's name. 2: an amount of money, such as $2.00. */
			return sprintf( __( 'Step up: %1$s, %2$s more', 'creative-skateboard-builder' ), $name, $amount );
		}
		return $change < 0
			/* translators: 1: a part's name. 2: an amount of money, such as $2.00. */
			? sprintf( __( 'Step up: %1$s, %2$s less', 'creative-skateboard-builder' ), $name, $amount )
			/* translators: %s: a part's name. */
			: sprintf( __( 'Step up: %s, same price', 'creative-skateboard-builder' ), $name );
	}

	/**
	 * What a smart swap changed, said after it (#135, D-397): the slots by their labels, as the site's language joins a
	 * list.
	 *
	 * @since 1.3.0
	 *
	 * @param list<string> $labels The slots' labels.
	 */
	public static function swapped( array $labels ): string {
		/* translators: %s: slots' labels joined as the site's language joins a list, such as wheels and hardware. */
		return sprintf( __( 'Swapped: %s.', 'creative-skateboard-builder' ), wp_sprintf( '%l', array_map( self::inSentence( ... ), $labels ) ) );
	}

	/**
	 * A slot's label inside a sentence, as "Change deck": its first letter small, unless the label is written in
	 * capitals, as a brand's may be, or the language keeps a noun's capital (Cowork's review, minors 5 and 14).
	 *
	 * @since 0.6.0
	 *
	 * @param string $label The slot's label, as the shop wrote it.
	 */
	public static function inSentence( string $label ): string {
		/* translators: Leave as "lowercase" where a slot's label, such as Grip tape, starts with a small letter inside a sentence, as in English and French. Write anything else, such as "keep", where it keeps its capital, as in German. */
		if ( 'lowercase' !== _x( 'lowercase', 'a slot label inside a sentence', 'creative-skateboard-builder' ) ) {
			return $label;
		}
		// mb_substr() is WordPress's where PHP has no mbstring; the case changes are not, so without it a letter outside
		// ASCII keeps its case, as WpTextFolding folds (#72).
		$second = mb_substr( $label, 1, 1 );
		$upper  = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $second, 'UTF-8' ) : strtoupper( $second );
		if ( '' === $second || $upper === $second ) {
			return $label;
		}
		$first = mb_substr( $label, 0, 1 );
		return ( function_exists( 'mb_strtolower' ) ? mb_strtolower( $first, 'UTF-8' ) : strtolower( $first ) ) . mb_substr( $label, 1 );
	}

	/**
	 * A riding style as the guided start's question offers it, and as the kit's Wheel guide names its row (#134).
	 *
	 * @since 1.3.0
	 *
	 * @param RidingStyle $style The style.
	 */
	public static function style( RidingStyle $style ): string {
		return match ( $style ) {
			RidingStyle::Street   => _x( 'Street', 'where a shopper skates', 'creative-skateboard-builder' ),
			RidingStyle::Park     => _x( 'Skatepark', 'where a shopper skates', 'creative-skateboard-builder' ),
			RidingStyle::Bowl     => _x( 'Bowl and transition', 'where a shopper skates', 'creative-skateboard-builder' ),
			RidingStyle::Cruising => _x( 'Cruising', 'where a shopper skates', 'creative-skateboard-builder' ),
		};
	}

	/**
	 * Sizes from one to another, one alone, or one and larger: "50 mm to 54 mm", "59 mm and larger" (#134).
	 *
	 * @since 1.3.0
	 *
	 * @param Span $span The sizes.
	 */
	public static function span( Span $span ): string {
		if ( null === $span->to ) {
			/* translators: %s: a size, such as 59 mm. */
			return sprintf( __( '%s and larger', 'creative-skateboard-builder' ), self::measure( $span->from ) );
		}
		return $span->from->equals( $span->to )
			? self::measure( $span->from )
			/* translators: 1: the smaller of a range, such as a deck width of 7″ or a US shoe size of 7. 2: the larger, such as 7.75″ or 8.5. */
			: sprintf( __( '%1$s to %2$s', 'creative-skateboard-builder' ), self::measure( $span->from ), self::measure( $span->to ) );
	}

	/**
	 * Why the guided start's wheels are the sizes they are: where the shopper skates, and the sizes (#134). One
	 * sentence a style, so each language says it whole.
	 *
	 * @since 1.3.0
	 *
	 * @param RidingStyle $style The style.
	 * @param Span        $span  The sizes.
	 */
	public static function wheelsFor( RidingStyle $style, Span $span ): string {
		$sizes = self::span( $span );
		return match ( $style ) {
			/* translators: %s: wheel sizes, such as 50 mm to 54 mm. */
			RidingStyle::Street   => sprintf( __( 'Wheels of %s suit street skating.', 'creative-skateboard-builder' ), $sizes ),
			/* translators: %s: wheel sizes, such as 53 mm to 56 mm. */
			RidingStyle::Park     => sprintf( __( 'Wheels of %s suit a skatepark.', 'creative-skateboard-builder' ), $sizes ),
			/* translators: %s: wheel sizes, such as 55 mm to 58 mm. */
			RidingStyle::Bowl     => sprintf( __( 'Wheels of %s suit bowls and transition.', 'creative-skateboard-builder' ), $sizes ),
			/* translators: %s: wheel sizes, such as 59 mm to 65 mm. */
			RidingStyle::Cruising => sprintf( __( 'Wheels of %s suit cruising.', 'creative-skateboard-builder' ), $sizes ),
		};
	}

	/**
	 * A ready build's name, by its tier (#134).
	 *
	 * @since 1.3.0
	 *
	 * @param Tier $tier The tier.
	 */
	public static function tier( Tier $tier ): string {
		return match ( $tier ) {
			Tier::Budget  => _x( 'Budget build', 'a ready build from the guided start', 'creative-skateboard-builder' ),
			Tier::Mid     => _x( 'Mid-range build', 'a ready build from the guided start', 'creative-skateboard-builder' ),
			Tier::Premium => _x( 'High-end build', 'a ready build from the guided start', 'creative-skateboard-builder' ),
			Tier::Only    => _x( 'Ready build', 'a ready build from the guided start', 'creative-skateboard-builder' ),
		};
	}

	/**
	 * What a ready build is, by its tier: the line under its name (#134).
	 *
	 * @since 1.3.0
	 *
	 * @param Tier $tier The tier.
	 */
	public static function tierLine( Tier $tier ): string {
		return match ( $tier ) {
			Tier::Budget  => __( 'The lowest-priced part that fits in each slot.', 'creative-skateboard-builder' ),
			Tier::Mid     => __( 'A part from the middle of the prices in each slot.', 'creative-skateboard-builder' ),
			Tier::Premium => __( 'The highest-priced part that fits in each slot.', 'creative-skateboard-builder' ),
			Tier::Only    => __( 'The one build the shop can make from these answers.', 'creative-skateboard-builder' ),
		};
	}
}
