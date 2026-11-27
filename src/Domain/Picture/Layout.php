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
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;

use function in_array;
use function intdiv;
use function max;
use function usort;

/**
 * The board laid out from its parts' specs, seen from underneath with the nose up: the deck by its width and length,
 * the trucks by the wheelbase and the wheels at the axles' ends (spec: The builder, The picture; D-060). Both of
 * milestone 7's renderers draw from this one layout. From 0.8.0 it also gives the board's top, which the page alone
 * draws (#46): the wheels where they stand out past the deck, the deck, the grip's whole sheet centred on it (1.1.0),
 * and a bolt head at each of the deck's eight holes. Every number is a whole ten-thousandth of a millimetre, so a renderer's
 * scale is one multiplication. Where a spec the layout needs is not known, a usual value stands in and the layout
 * says which.
 */
final readonly class Layout {

	/**
	 * The slots the picture draws, in the order it stacks them when the kit sets none.
	 */
	public const array DRAWN = array( SlotKey::DECK, SlotKey::GRIP, SlotKey::TRUCKS, SlotKey::WHEELS );

	/**
	 * The slots the top draws and the underside does not: the grip, laid over the deck, and the hardware, whose bolt
	 * heads come up through it (F27, #127).
	 */
	private const array ON_TOP = array( SlotKey::HARDWARE, SlotKey::GRIP );

	/**
	 * The sheet of a grip that gives no size: 9 by 33 in, the usual sheet (F18, #103).
	 */
	private const int USUAL_GRIP_WIDTH = 90000;

	/**
	 * See USUAL_GRIP_WIDTH.
	 */
	private const int USUAL_GRIP_LENGTH = 330000;

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
		$frame = Board::of( $picks, $settings );
		if ( null === $frame ) {
			return new self( 0, 0, array(), array() );
		}
		$wide   = $frame->width;
		$long   = $frame->length;
		$board  = new Layer( SlotKey::DECK, $frame->deck->id, 0, 0, $wide, $long );
		$across = $frame->axle;
		$out    = $frame->out;
		$round  = self::wheels( $frame );
		$layers = array( $board, ...self::trucks( $frame->trucks, $out, $across, $frame->spacing ), ...$round );
		if ( null !== $settings ) {
			// usort() keeps equal ranks in the order they came in (PHP 8.0), so a slot's own layers stay in theirs.
			usort( $layers, static fn( Layer $a, Layer $b ): int => $settings->rank( $a->slot ) <=> $settings->rank( $b->slot ) );
		}
		// The top is stacked as a board is built, whatever the kit's order: the wheels under the deck, so only what
		// stands out past its edges shows, then the grip over the whole deck, then the bolts' heads through it.
		$grip = $picks->part( SlotKey::GRIP );
		$top  = array( ...$round, $board );
		if ( null !== $grip ) {
			// The whole sheet, centred on the deck; the deck's outline cuts it (F18, #103).
			$sheet = self::sheet( $grip->specs );
			$top[] = new Layer( SlotKey::GRIP, $grip->id, 0, 0, $sheet[0], $sheet[1] );
		}
		$top = array( ...$top, ...self::bolts( $picks->part( SlotKey::HARDWARE ), $frame ) );
		return new self( max( $wide, $across ), $long, $layers, $frame->assumed, $top );
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
		$usual = self::mm( Measure::inches( Board::USUAL_LENGTH ) );
		switch ( $slot ) {
			case SlotKey::DECK:
				$width  = $specs->measure( SpecKey::DeckWidth );
				$length = $specs->measure( SpecKey::DeckLength );
				return null === $width ? null : array( self::mm( $width ), null === $length ? $usual : self::mm( $length ) );
			case SlotKey::GRIP:
				return self::sheet( $specs );
			case SlotKey::TRUCKS:
				$axle = $specs->measure( SpecKey::AxleWidth );
				return null === $axle ? null : array( self::mm( $axle ), Board::HOLE_SPACING + Board::BASEPLATE_MARGIN );
			case SlotKey::WHEELS:
				return array( Board::wheelWidth( $specs ), Board::wheelDiameter( $specs ) );
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
				$layers[] = new Layer( SlotKey::TRUCKS, $trucks->id, 0, $y, $across, $spacing + Board::BASEPLATE_MARGIN );
			}
		}
		return $layers;
	}

	/**
	 * The four wheels' layers, the nose's pair first and the left of each pair first; none when no wheels are picked.
	 * Each wheel's outer face sits at the axle's end.
	 *
	 * @param Board $frame The board.
	 * @return list<Layer>
	 */
	private static function wheels( Board $frame ): array {
		$layers = array();
		if ( null !== $frame->wheels ) {
			$side = intdiv( $frame->axle - $frame->wheelWidth, 2 );
			foreach ( array( $frame->out, -$frame->out ) as $y ) {
				foreach ( array( -$side, $side ) as $x ) {
					$layers[] = new Layer( SlotKey::WHEELS, $frame->wheels->id, $x, $y, $frame->wheelWidth, $frame->wheel );
				}
			}
		}
		return $layers;
	}

	/**
	 * A bolt's head at each of the deck's eight holes, seen from the top, in `Board::holes()`'s order; none when no
	 * hardware is picked.
	 *
	 * @param Part|null $hardware The hardware.
	 * @param Board     $frame    The board.
	 * @return list<Layer>
	 */
	private static function bolts( ?Part $hardware, Board $frame ): array {
		$layers = array();
		if ( null !== $hardware ) {
			foreach ( $frame->holes() as [ $along, $across ] ) {
				$layers[] = new Layer( SlotKey::HARDWARE, $hardware->id, $across, $along, self::BOLT_HEAD, self::BOLT_HEAD );
			}
		}
		return $layers;
	}

	/**
	 * Whether a slot's part is seen on the board's top: the top draws it and the underside does not (F27, #127). A
	 * step for one opens on the top, so the shopper sees the part they are choosing. Every other slot opens underneath,
	 * where the deck, the trucks and the wheels are, and so do the review and any slot of a shop's own, which the picture
	 * does not draw.
	 *
	 * @since 1.2.0
	 *
	 * @param string $slot The slot's key, or the review's.
	 */
	public static function onTop( string $slot ): bool {
		return in_array( $slot, self::ON_TOP, true );
	}

	/**
	 * Whether a slot's layer image is a cut-out, its background clear to its corners: every part's but the grip's,
	 * whose image is the whole sheet shot flat (F18, #103).
	 *
	 * @since 1.1.0
	 *
	 * @param string $slot The slot's key.
	 */
	public static function cutOut( string $slot ): bool {
		return SlotKey::GRIP !== $slot;
	}

	/**
	 * A grip's whole sheet, across and along: its own width and length, or the usual 9 by 33 in for either it lacks.
	 *
	 * @param Specs $specs The grip's specs.
	 * @return array{int, int}
	 */
	private static function sheet( Specs $specs ): array {
		return array(
			self::mm( $specs->measure( SpecKey::GripWidth ) ?? Measure::inches( self::USUAL_GRIP_WIDTH ) ),
			self::mm( $specs->measure( SpecKey::GripLength ) ?? Measure::inches( self::USUAL_GRIP_LENGTH ) ),
		);
	}

	/**
	 * A length in ten-thousandths of a millimetre.
	 *
	 * @param Measure $length The length.
	 */
	private static function mm( Measure $length ): int {
		return Board::mm( $length );
	}
}
