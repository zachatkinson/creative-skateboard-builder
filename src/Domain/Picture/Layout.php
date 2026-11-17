<?php
/**
 * The picture's layout.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\HolePattern;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;

use function intdiv;
use function max;
use function usort;

/**
 * The board laid out from its parts' specs, seen from underneath with the nose up: the deck by its width and length,
 * the trucks by the wheelbase and the wheels at the axles' ends (spec: The builder, The picture; D-060). Both of
 * milestone 7's renderers draw from this one layout. From 0.8.0 it also gives the board's top, which the page alone
 * draws (#46): the wheels where they stand out past the deck, the deck, the grip over the whole of it, and a bolt
 * head at each of the deck's eight holes. Every number is a whole ten-thousandth of a millimetre, so a renderer's
 * scale is one multiplication. Where a spec the layout needs is not known, a usual value stands in and the layout
 * says which.
 */
final readonly class Layout {

	/**
	 * The slots the picture draws, in the order it stacks them when the kit sets none.
	 */
	public const array DRAWN = array( SlotKey::DECK, SlotKey::GRIP, SlotKey::TRUCKS, SlotKey::WHEELS );

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
	 * The grip's image: a strip 1 in wide along the deck's length, which the top repeats across the deck.
	 */
	private const int GRIP_STRIP = 254000;

	/**
	 * Between a baseplate's two columns of holes, new school or old: 1.625 in.
	 */
	private const int HOLE_COLUMNS = 412750;

	/**
	 * A bolt's head, seen from the top: 3/8 in across.
	 */
	private const int BOLT_HEAD = 95250;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 The top.
	 *
	 * @param int           $width   The board across, wheels included.
	 * @param int           $height  The board along.
	 * @param list<Layer>   $layers  The layers seen from underneath, from the bottom of the stack.
	 * @param list<SpecKey> $assumed The specs a usual value stood in for.
	 * @param list<Layer>   $top     The layers seen from the top, from the bottom of the stack, in the same places:
	 *                               the board is turned over about its length, and every place on it is the same either
	 *                               way up.
	 */
	public function __construct( public int $width, public int $height, public array $layers, public array $assumed, public array $top = array() ) {}

	/**
	 * The layout of the picks so far. Nothing is laid out until a deck with a width is picked. From 0.7.0 the kit's
	 * picture settings stack the layers in its order and anchor the trucks on a deck that gives no wheelbase.
	 *
	 * @since 0.2.0
	 *
	 * @param Picks                $picks    The picks.
	 * @param PictureSettings|null $settings The kit's picture settings; none, the usual order and no anchor.
	 */
	public static function of( Picks $picks, ?PictureSettings $settings = null ): self {
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
			$base      = $settings->wheelbase ?? self::usualWheelbase( $width, $length );
		}
		if ( null === $length ) {
			$assumed[] = SpecKey::DeckLength;
			$length    = Measure::inches( self::USUAL_LENGTH );
		}
		$wide   = self::mm( $width );
		$long   = self::mm( $length );
		$board  = new Layer( SlotKey::DECK, $deck->id, 0, 0, $wide, $long );
		$layers = array( $board );

		$trucks = $picks->part( SlotKey::TRUCKS );
		$wheels = $picks->part( SlotKey::WHEELS );
		$axle   = $trucks?->specs->measure( SpecKey::AxleWidth );
		if ( null === $axle && ( null !== $trucks || null !== $wheels ) ) {
			$assumed[] = SpecKey::AxleWidth;
		}
		if ( null !== $wheels && null === $wheels->specs->measure( SpecKey::WheelDiameter ) ) {
			$assumed[] = SpecKey::WheelDiameter;
		}
		$across  = self::mm( $axle ?? $width );
		$spacing = HolePattern::OldSchool->value === $deck->specs->choice( SpecKey::HolePattern ) ? self::OLD_SCHOOL_SPACING : self::HOLE_SPACING;
		// The wheelbase runs between the inner holes, so a truck's middle is half a spacing further out.
		$out    = intdiv( self::mm( $base ) + $spacing, 2 );
		$round  = self::wheels( $wheels, $out, $across );
		$layers = array( ...$layers, ...self::trucks( $trucks, $out, $across, $spacing ), ...$round );
		if ( null !== $settings ) {
			// usort() keeps equal ranks in the order they came in (PHP 8.0), so a slot's own layers stay in theirs.
			usort( $layers, static fn( Layer $a, Layer $b ): int => $settings->rank( $a->slot ) <=> $settings->rank( $b->slot ) );
		}
		// The top is stacked as a board is built, whatever the kit's order: the wheels under the deck, so only what
		// stands out past its edges shows, then the grip over the whole deck, then the bolts' heads through it.
		$grip = $picks->part( SlotKey::GRIP );
		$top  = array( ...$round, $board );
		if ( null !== $grip ) {
			$top[] = new Layer( SlotKey::GRIP, $grip->id, 0, 0, $wide, $long );
		}
		$top = array( ...$top, ...self::bolts( $picks->part( SlotKey::HARDWARE ), $out, $spacing ) );
		return new self( max( $wide, $across ), $long, $layers, $assumed, $top );
	}

	/**
	 * A layer's top left corner from the board's, the nose at the top: where a renderer draws it from.
	 *
	 * @since 0.7.0
	 *
	 * @param Layer $layer One of this layout's layers.
	 * @return array{int, int} Across and down.
	 */
	public function corner( Layer $layer ): array {
		return array( intdiv( $this->width - $layer->width, 2 ) + $layer->x, intdiv( $this->height - $layer->height, 2 ) - $layer->y );
	}

	/**
	 * The size one part of a slot is drawn at, alone: across and along, as `of()` draws it on a usual 32 in deck with
	 * new school holes. Null where the picture does not draw the slot, or the part lacks the spec its size comes from.
	 * The Layer image field checks an image against it (D-190).
	 *
	 * @since 0.7.0
	 *
	 * @param string $slot  The slot's key.
	 * @param Specs  $specs The part's specs.
	 * @return array{int, int}|null
	 */
	public static function drawn( string $slot, Specs $specs ): ?array {
		$usual = self::mm( Measure::inches( self::USUAL_LENGTH ) );
		switch ( $slot ) {
			case SlotKey::DECK:
				$width  = $specs->measure( SpecKey::DeckWidth );
				$length = $specs->measure( SpecKey::DeckLength );
				return null === $width ? null : array( self::mm( $width ), null === $length ? $usual : self::mm( $length ) );
			case SlotKey::GRIP:
				return array( self::GRIP_STRIP, $usual );
			case SlotKey::TRUCKS:
				$axle = $specs->measure( SpecKey::AxleWidth );
				return null === $axle ? null : array( self::mm( $axle ), self::HOLE_SPACING + self::BASEPLATE_MARGIN );
			case SlotKey::WHEELS:
				$diameter = $specs->measure( SpecKey::WheelDiameter );
				$round    = null === $diameter ? self::USUAL_WHEEL : self::mm( $diameter );
				return array( intdiv( $round * 3, 5 ), $round );
		}
		return null;
	}

	/**
	 * The two trucks' layers, the nose's first; none when no trucks are picked.
	 *
	 * @param Part|null $trucks  The trucks.
	 * @param int       $out     How far a truck's middle is from the deck's.
	 * @param int       $across  The axle's width.
	 * @param int       $spacing Between a baseplate's two rows of holes.
	 * @return list<Layer>
	 */
	private static function trucks( ?Part $trucks, int $out, int $across, int $spacing ): array {
		$layers = array();
		if ( null !== $trucks ) {
			foreach ( array( $out, -$out ) as $y ) {
				$layers[] = new Layer( SlotKey::TRUCKS, $trucks->id, 0, $y, $across, $spacing + self::BASEPLATE_MARGIN );
			}
		}
		return $layers;
	}

	/**
	 * The four wheels' layers, the nose's pair first and the left of each pair first; none when no wheels are picked.
	 *
	 * @param Part|null $wheels The wheels.
	 * @param int       $out    How far an axle is from the deck's middle.
	 * @param int       $across The axle's width.
	 * @return list<Layer>
	 */
	private static function wheels( ?Part $wheels, int $out, int $across ): array {
		$layers = array();
		if ( null !== $wheels ) {
			$diameter = $wheels->specs->measure( SpecKey::WheelDiameter );
			$round    = null === $diameter ? self::USUAL_WHEEL : self::mm( $diameter );
			// A wheel is about three fifths as wide as it is tall, and its outer face sits at the axle's end.
			$thick = intdiv( $round * 3, 5 );
			$side  = intdiv( $across - $thick, 2 );
			foreach ( array( $out, -$out ) as $y ) {
				foreach ( array( -$side, $side ) as $x ) {
					$layers[] = new Layer( SlotKey::WHEELS, $wheels->id, $x, $y, $thick, $round );
				}
			}
		}
		return $layers;
	}

	/**
	 * A bolt's head at each of the deck's eight holes, seen from the top: from the nose, each row's left first; none
	 * when no hardware is picked. The rows are the trucks' baseplate holes, half a spacing either side of each truck's
	 * middle, so the inner rows are the wheelbase apart.
	 *
	 * @param Part|null $hardware The hardware.
	 * @param int       $out      How far a truck's middle is from the deck's.
	 * @param int       $spacing  Between a baseplate's two rows of holes.
	 * @return list<Layer>
	 */
	private static function bolts( ?Part $hardware, int $out, int $spacing ): array {
		$layers = array();
		if ( null !== $hardware ) {
			$half = intdiv( $spacing, 2 );
			foreach ( array( $out + $half, $out - $half, -$out + $half, -$out - $half ) as $y ) {
				foreach ( array( -1, 1 ) as $side ) {
					$layers[] = new Layer( SlotKey::HARDWARE, $hardware->id, $side * intdiv( self::HOLE_COLUMNS, 2 ), $y, self::BOLT_HEAD, self::BOLT_HEAD );
				}
			}
		}
		return $layers;
	}

	/**
	 * The usual wheelbase of a deck that gives none: a share of its length when that is known, otherwise by its width.
	 *
	 * @param Measure      $width  The deck's width, in inches.
	 * @param Measure|null $length The deck's length, in inches, when it gives one.
	 */
	private static function usualWheelbase( Measure $width, ?Measure $length ): Measure {
		if ( null !== $length ) {
			return Measure::inches( intdiv( $length->tenThousandths * self::WHEELBASE_SHARE, 1000 ) );
		}
		foreach ( self::USUAL_WHEELBASES as $from => $usual ) {
			if ( $width->tenThousandths >= $from ) {
				return Measure::inches( $usual );
			}
		}
		return Measure::inches( self::NARROW_WHEELBASE );
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
