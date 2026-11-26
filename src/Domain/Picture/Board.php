<?php
/**
 * The board's measured frame, shared by the picture and the scene.
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

/**
 * The sizes and places the 2D picture (`Layout`) and the 3D scene (`Domain\Scene\Scene`) both draw from, so the two
 * never disagree (1.4.0, #139, D-470): the deck's width, length and wheelbase, the hole pattern the trucks are bolted
 * to, where each truck's middle is, the axle's width and the wheels' size, each with the usual value that stood in for
 * a spec the parts don't give. Every length is a whole ten-thousandth of a millimetre, the picture's unit, from the
 * deck's middle.
 *
 * The wheelbase runs between the two trucks' inner holes, as decks are measured, so a truck's middle is half a hole
 * spacing further out than half the wheelbase.
 *
 * @since 1.4.0
 */
final readonly class Board {

	/**
	 * The length of a deck that gives none: 32 in, in ten-thousandths of an inch.
	 */
	public const int USUAL_LENGTH = 320000;

	/**
	 * The usual wheelbase by deck width, from the widest, in ten-thousandths of an inch: from 8.5 in, 14.5 in; from
	 * 8 in, 14.25 in.
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
	public const int HOLE_SPACING = 539750;

	public const int OLD_SCHOOL_SPACING = 635000;

	/**
	 * Between a baseplate's two columns of holes, new school or old: 1.625 in.
	 */
	public const int HOLE_COLUMNS = 412750;

	/**
	 * A baseplate past its holes, both ends together: 0.875 in.
	 */
	public const int BASEPLATE_MARGIN = 222250;

	/**
	 * The diameter of a wheel that gives none: 54 mm.
	 */
	public const int USUAL_WHEEL = 540000;

	/**
	 * A wheel that gives no width is this many fifths of its diameter across: 32.4 mm at 54 mm, as the classic street
	 * shapes are (D-471).
	 */
	private const int WHEEL_FIFTHS = 3;

	/**
	 * Constructor.
	 *
	 * @param Part          $deck       The deck.
	 * @param int           $width      The deck across.
	 * @param int           $length     The deck along, nose to tail.
	 * @param int           $wheelbase  Between the trucks' inner holes.
	 * @param HolePattern   $pattern    The pattern the trucks are bolted to: new school or old, never both.
	 * @param int           $spacing    Between a baseplate's two rows of holes, by that pattern.
	 * @param int           $out        How far each truck's middle is from the deck's.
	 * @param int           $axle       The axle's width, or the deck's where the trucks give none.
	 * @param Part|null     $trucks     The trucks, when picked.
	 * @param Part|null     $wheels     The wheels, when picked.
	 * @param int           $wheel      The wheels' diameter.
	 * @param int           $wheelWidth The wheels' width.
	 * @param list<SpecKey> $assumed    The specs a usual value stood in for. A wheel's width is not among them: the
	 *                                  picture has always drawn a wheel three fifths as wide as it is tall, and the scene
	 *                                  names the width itself.
	 */
	private function __construct(
		public Part $deck,
		public int $width,
		public int $length,
		public int $wheelbase,
		public HolePattern $pattern,
		public int $spacing,
		public int $out,
		public int $axle,
		public ?Part $trucks,
		public ?Part $wheels,
		public int $wheel,
		public int $wheelWidth,
		public array $assumed
	) {}

	/**
	 * The board of the picks so far, or null before a deck with a width is picked. The kit's picture settings anchor
	 * the trucks on a deck that gives no wheelbase.
	 *
	 * @since 1.4.0
	 *
	 * @param Picks                $picks    The picks.
	 * @param PictureSettings|null $settings The kit's picture settings; none, no anchor.
	 */
	public static function of( Picks $picks, ?PictureSettings $settings = null ): ?self {
		$deck  = $picks->part( SlotKey::DECK );
		$width = $deck?->specs->measure( SpecKey::DeckWidth );
		if ( null === $deck || null === $width ) {
			return null;
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
		$trucks = $picks->part( SlotKey::TRUCKS );
		$wheels = $picks->part( SlotKey::WHEELS );
		$axle   = $trucks?->specs->measure( SpecKey::AxleWidth );
		if ( null === $axle && ( null !== $trucks || null !== $wheels ) ) {
			$assumed[] = SpecKey::AxleWidth;
		}
		$specs = $wheels->specs ?? Specs::none();
		if ( null !== $wheels && null === $specs->measure( SpecKey::WheelDiameter ) ) {
			$assumed[] = SpecKey::WheelDiameter;
		}
		$pattern = self::pattern( $deck->specs, $trucks?->specs );
		$spacing = HolePattern::OldSchool === $pattern ? self::OLD_SCHOOL_SPACING : self::HOLE_SPACING;
		$between = self::mm( $base );
		return new self(
			$deck,
			self::mm( $width ),
			self::mm( $length ),
			$between,
			$pattern,
			$spacing,
			intdiv( $between + $spacing, 2 ),
			self::mm( $axle ?? $width ),
			$trucks,
			$wheels,
			self::wheelDiameter( $specs ),
			self::wheelWidth( $specs ),
			$assumed
		);
	}

	/**
	 * The deck's eight holes, which the trucks' baseplates are bolted through: along the deck (toward the nose) and
	 * across it. From the nose: its truck's outer row, then its inner row, then the tail truck's inner row and its outer
	 * row, each row's left first. The inner rows are the wheelbase apart.
	 *
	 * @since 1.4.0
	 *
	 * @return list<array{int, int}>
	 */
	public function holes(): array {
		$half   = intdiv( $this->spacing, 2 );
		$across = intdiv( self::HOLE_COLUMNS, 2 );
		$holes  = array();
		foreach ( array( $this->out + $half, $this->out - $half, -$this->out + $half, -$this->out - $half ) as $along ) {
			$holes[] = array( $along, -$across );
			$holes[] = array( $along, $across );
		}
		return $holes;
	}

	/**
	 * The pattern a deck's trucks are bolted to: an old school deck's, or a deck drilled for both with trucks whose
	 * baseplates take only old school holes; otherwise new school, the usual one (D-470).
	 *
	 * @param Specs      $deck   The deck's specs.
	 * @param Specs|null $trucks The trucks' specs, when picked.
	 */
	private static function pattern( Specs $deck, ?Specs $trucks ): HolePattern {
		$drilled = HolePattern::tryFrom( (string) $deck->choice( SpecKey::HolePattern ) ) ?? HolePattern::USUAL;
		if ( HolePattern::Both === $drilled ) {
			$drilled = HolePattern::OldSchool->value === $trucks?->choice( SpecKey::Baseplate ) ? HolePattern::OldSchool : HolePattern::NewSchool;
		}
		return $drilled;
	}

	/**
	 * A wheel's diameter, or the usual 54 mm where it gives none.
	 *
	 * @since 1.4.0
	 *
	 * @param Specs $specs The wheel's specs.
	 */
	public static function wheelDiameter( Specs $specs ): int {
		$diameter = $specs->measure( SpecKey::WheelDiameter );
		return null === $diameter ? self::USUAL_WHEEL : self::mm( $diameter );
	}

	/**
	 * A wheel's width, or three fifths of its diameter where it gives none (D-471).
	 *
	 * @since 1.4.0
	 *
	 * @param Specs $specs The wheel's specs.
	 */
	public static function wheelWidth( Specs $specs ): int {
		$width = $specs->measure( SpecKey::WheelWidth );
		return null === $width ? intdiv( self::wheelDiameter( $specs ) * self::WHEEL_FIFTHS, 5 ) : self::mm( $width );
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
	 * @since 1.4.0
	 *
	 * @param Measure $length The length.
	 */
	public static function mm( Measure $length ): int {
		return $length->to( Unit::Millimetre )->tenThousandths;
	}
}
