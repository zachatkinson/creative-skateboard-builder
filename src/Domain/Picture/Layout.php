<?php
/**
 * The picture's layout.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function intdiv;
use function max;

/**
 * The board laid out from its parts' specs, seen from underneath with the nose up: the deck by its width and length,
 * the grip as a strip along its edge, the trucks by the wheelbase and the wheels at the axles' ends (spec: The
 * builder, The picture; D-060). Both of milestone 7's renderers draw from this one layout. Every number is a whole
 * ten-thousandth of a millimetre, so a renderer's scale is one multiplication. Where a spec the layout needs is not
 * known, a usual value stands in and the layout says which.
 */
final readonly class Layout {

	/**
	 * The length of a deck that gives none: 32 in.
	 */
	private const int USUAL_LENGTH = 320000;

	/**
	 * The usual wheelbase by deck width, from the widest: from 8.5 in, 14.5 in; from 8 in, 14.25 in.
	 */
	private const array USUAL_WHEELBASES = array(
		85000 => 145000,
		80000 => 142500,
	);

	/**
	 * The usual wheelbase of a deck narrower than those: 14 in.
	 */
	private const int NARROW_WHEELBASE = 140000;

	/**
	 * The usual wheelbase of a deck whose length is known: this many thousandths of the length.
	 */
	private const int WHEELBASE_SHARE = 445;

	/**
	 * Between a baseplate's two rows of holes: 2.125 in, and 2.5 in on an old school deck.
	 */
	private const int HOLE_SPACING = 539750;

	private const int OLD_SCHOOL_SPACING = 635000;

	/**
	 * A baseplate past its holes, both ends together: 0.875 in.
	 */
	private const int BASEPLATE_MARGIN = 222250;

	/**
	 * The diameter of a wheel that gives none: 54 mm.
	 */
	private const int USUAL_WHEEL = 540000;

	/**
	 * The grip's strip: 1 in wide.
	 */
	private const int GRIP_STRIP = 254000;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param int           $width   The board across, wheels included.
	 * @param int           $height  The board along.
	 * @param list<Layer>   $layers  The layers, from the bottom of the stack.
	 * @param list<SpecKey> $assumed The specs a usual value stood in for.
	 */
	public function __construct( public int $width, public int $height, public array $layers, public array $assumed ) {}

	/**
	 * The layout of the picks so far. Nothing is laid out until a deck with a width is picked.
	 *
	 * @since 0.2.0
	 *
	 * @param Picks $picks The picks.
	 */
	public static function of( Picks $picks ): self {
		$deck  = $picks->part( SlotKey::DECK );
		$width = $deck?->specs->measure( SpecKey::DeckWidth );
		if ( null === $deck || null === $width ) {
			return new self( 0, 0, array(), array() );
		}
		$assumed = array();
		$length  = $deck->specs->measure( SpecKey::DeckLength );
		$base    = $deck->specs->measure( SpecKey::Wheelbase );
		if ( null === $base ) {
			$assumed[] = SpecKey::Wheelbase;
			$base      = Measure::inches( null === $length ? self::usualWheelbase( $width ) : intdiv( $length->tenThousandths * self::WHEELBASE_SHARE, 1000 ) );
		}
		if ( null === $length ) {
			$assumed[] = SpecKey::DeckLength;
			$length    = Measure::inches( self::USUAL_LENGTH );
		}
		$wide   = self::mm( $width );
		$long   = self::mm( $length );
		$layers = array( new Layer( SlotKey::DECK, $deck->id, 0, 0, $wide, $long ) );
		$grip   = $picks->part( SlotKey::GRIP );
		if ( null !== $grip ) {
			$layers[] = new Layer( SlotKey::GRIP, $grip->id, intdiv( $wide - self::GRIP_STRIP, 2 ), 0, self::GRIP_STRIP, $long );
		}

		$trucks = $picks->part( SlotKey::TRUCKS );
		$wheels = $picks->part( SlotKey::WHEELS );
		$axle   = $trucks?->specs->measure( SpecKey::AxleWidth );
		if ( null === $axle && ( null !== $trucks || null !== $wheels ) ) {
			$assumed[] = SpecKey::AxleWidth;
		}
		$across  = self::mm( $axle ?? $width );
		$spacing = 'old_school' === $deck->specs->choice( SpecKey::HolePattern ) ? self::OLD_SCHOOL_SPACING : self::HOLE_SPACING;
		// The wheelbase runs between the inner holes, so a truck's middle is half a spacing further out.
		$out = intdiv( self::mm( $base ) + $spacing, 2 );
		if ( null !== $trucks ) {
			foreach ( array( $out, -$out ) as $y ) {
				$layers[] = new Layer( SlotKey::TRUCKS, $trucks->id, 0, $y, $across, $spacing + self::BASEPLATE_MARGIN );
			}
		}
		if ( null !== $wheels ) {
			$diameter = $wheels->specs->measure( SpecKey::WheelDiameter );
			if ( null === $diameter ) {
				$assumed[] = SpecKey::WheelDiameter;
			}
			$round = null === $diameter ? self::USUAL_WHEEL : self::mm( $diameter );
			// A wheel is about three fifths as wide as it is tall, and its outer face sits at the axle's end.
			$thick = intdiv( $round * 3, 5 );
			$side  = intdiv( $across - $thick, 2 );
			foreach ( array( $out, -$out ) as $y ) {
				foreach ( array( -$side, $side ) as $x ) {
					$layers[] = new Layer( SlotKey::WHEELS, $wheels->id, $x, $y, $thick, $round );
				}
			}
		}
		return new self( max( $wide, $across ), $long, $layers, $assumed );
	}

	/**
	 * The usual wheelbase of a deck this wide, in ten-thousandths of an inch.
	 *
	 * @param Measure $width The deck's width, in inches.
	 */
	private static function usualWheelbase( Measure $width ): int {
		foreach ( self::USUAL_WHEELBASES as $from => $usual ) {
			if ( $width->tenThousandths >= $from ) {
				return $usual;
			}
		}
		return self::NARROW_WHEELBASE;
	}

	/**
	 * A length in ten-thousandths of a millimetre.
	 *
	 * @param Measure $length The length.
	 */
	private static function mm( Measure $length ): int {
		return $length->to( Unit::Millimetre )->tenThousandths;
	}
}
