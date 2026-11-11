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
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

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
		if ( Unit::Inch === $measure->unit ) {
			/* translators: %s: a number of inches. */
			return sprintf( _x( '%s in', 'inches', 'creative-skateboard-builder' ), $number );
		}
		/* translators: %s: a number of millimetres. */
		return sprintf( _x( '%s mm', 'millimetres', 'creative-skateboard-builder' ), $number );
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
		$v      = static function ( string $name ) use ( $values ): string {
			$value = $values[ $name ] ?? '';
			if ( $value instanceof Measure ) {
				return self::measure( $value, in_array( $name, self::FRACTIONS, true ) );
			}
			$spec = 'spec' === $name ? SpecKey::tryFrom( $value ) : null;
			return null === $spec ? Labels::choice( $value ) : Labels::spec( $spec );
		};
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
			case MessageCode::BoreMismatch:
				/* translators: 1: the bearings' bore. 2: the axle's diameter. */
				return sprintf( __( 'The bearings\' bore is %1$s and the trucks\' axle is %2$s.', 'creative-skateboard-builder' ), $v( 'bearing_bore' ), $v( 'axle_diameter' ) );
			case MessageCode::AxleAssumed:
				/* translators: %s: an axle's diameter. */
				return sprintf( __( 'The trucks give no axle size, so the usual one is taken: %s.', 'creative-skateboard-builder' ), $v( 'axle_diameter' ) );
			case MessageCode::SpacersBuiltIn:
				return __( 'The bearings have spacers built in, so they take no others.', 'creative-skateboard-builder' );
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
			case MessageCode::BoreMismatch:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the bearings would not fit the axle.', '%s parts hidden: the bearings would not fit the axle.', $count, 'creative-skateboard-builder' ), $number );
			case MessageCode::SpacersBuiltIn:
				/* translators: %s: a number of parts. */
				return sprintf( _n( '%s part hidden: the bearings have spacers built in.', '%s parts hidden: the bearings have spacers built in.', $count, 'creative-skateboard-builder' ), $number );
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
}
