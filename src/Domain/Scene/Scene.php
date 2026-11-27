<?php
/**
 * The 3D view's scene.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Scene;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Rounding;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Board;
use CreativeBranding\SkateboardBuilder\Domain\Picture\PictureSettings;
use CreativeBranding\SkateboardBuilder\Domain\Spec\HeightClass;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;

use function abs;
use function array_map;
use function intdiv;
use function max;
use function min;

/**
 * The board in three dimensions, measured from its parts' specs (1.4.0, #139, D-470): every part's size and place, for
 * the 3D view to draw and decide nothing about. It stands on `Board`, the frame the 2D picture draws from, so the two
 * place the trucks, the holes and the wheels alike.
 *
 * Every length is a whole micrometre and every angle a tenth of a degree. The origin is on the deck's top face, midway
 * between the trucks; x runs toward the nose, y across the deck and z up. The deck's nose and tail are measured from
 * the outer holes to the tips, so the nose, the tail and the outer holes' span make the deck's length. A truck's height
 * runs from its baseplate's bottom to its axle's centre, so the axle and the wheels' centres are at z = -(the deck's
 * thickness + the riser + the truck's height). That height is measured here alone: each truck's axle's centre is sent
 * with the trucks, as the wheels' centres are, so the view draws both from the same point (the audit's F9). Where a
 * spec is not known, a usual value stands in and `assumed` names it.
 *
 * @phpstan-type Point array{int, int}
 * @phpstan-type Shape array{
 *     assumed: list<string>,
 *     deck: array{part: int, width: int, length: int, wheelbase: int, nose: int, tail: int, concave: int, kick: int, thickness: int, plies: int, holes: array{pattern: string, spacing: int, columns: int, points: list<Point>}, texture: array{top: null, bottom: null}},
 *     trucks: array{part: int, axle: int, height: int, hanger: int, baseplate: array{length: int, width: int}, holes: list<Point>, centres: list<array{int, int, int}>, color: null}|null,
 *     wheels: array{part: int, diameter: int, width: int, contact: int, texture: null, color: null, places: list<array{int, int, int}>}|null,
 *     risers: array{part: int, thickness: int}|null,
 *     bolts: array{part: int, length: int, points: list<Point>}|null,
 *     grip: array{part: int, texture: null}|null
 * }
 *
 * @since 1.4.0
 */
final class Scene {

	/**
	 * The sizes the scene alone reads of a deck and of a wheel: the rules and the picture name the rest.
	 */
	private const array OWN = array(
		SlotKey::DECK   => array( SpecKey::NoseLength, SpecKey::TailLength, SpecKey::ConcaveDepth, SpecKey::KickAngle ),
		SlotKey::WHEELS => array( SpecKey::WheelWidth ),
	);

	/**
	 * How much longer a deck's nose is than its tail where it gives neither: a quarter inch, in micrometres (D-473).
	 */
	private const int NOSE_LEAD = 6350;

	/**
	 * The concave of a deck that gives none: 0.45 in, in ten-thousandths of an inch (D-473).
	 */
	private const int USUAL_CONCAVE = 4500;

	/**
	 * A concave is at most this share of the deck's width: a quarter.
	 */
	private const int CONCAVE_PARTS = 4;

	/**
	 * The kick of a deck that gives none, and the most a kick is drawn at: 20 and 45 degrees, in tenths (D-473).
	 */
	private const int USUAL_KICK = 200;

	private const int MAX_KICK = 450;

	/**
	 * A deck's thickness and plies: seven plies of maple, 11 mm (no spec gives them).
	 */
	private const int THICKNESS = 11000;

	private const int PLIES = 7;

	/**
	 * A truck's height from its baseplate's bottom to its axle's centre, by height class, where it gives none, in
	 * micrometres (D-473).
	 */
	private const array HEIGHTS = array(
		'low'  => 49000,
		'mid'  => 52000,
		'high' => 55000,
	);

	/**
	 * A hanger is two thirds of its axle's width, as Independent's are named (a 144 on an 8.25 in axle).
	 */
	private const int HANGER_THIRDS = 2;

	/**
	 * A riser that gives no thickness: 1/8 in; bolts that give no length: 1 in. In ten-thousandths of an inch.
	 */
	private const int USUAL_RISER = 1250;

	private const int USUAL_BOLT = 10000;

	/**
	 * The scene of the picks so far, in the contract's shape (`schema/state.json`, `view.scene`), or null before a deck
	 * with a width is picked. The kit's picture settings anchor the trucks on a deck that gives no wheelbase, as they do
	 * in the picture. The textures are left for the edge to fill.
	 *
	 * @since 1.4.0
	 *
	 * @param Picks                $picks    The picks.
	 * @param PictureSettings|null $settings The kit's picture settings.
	 * @return Shape|null
	 */
	public static function of( Picks $picks, ?PictureSettings $settings = null ): ?array {
		$board = Board::of( $picks, $settings );
		if ( null === $board ) {
			return null;
		}
		$assumed = $board->assumed;
		$holes   = array_map( static fn( array $hole ): array => array( self::um( $hole[0] ), self::um( $hole[1] ) ), $board->holes() );
		$deck    = self::deck( $board, $holes, $assumed );
		$trucks  = $board->trucks;
		$height  = self::height( $trucks, null !== $trucks || null !== $board->wheels, $assumed );
		$wheels  = $board->wheels;
		if ( null !== $wheels && null === $wheels->specs->measure( SpecKey::WheelWidth ) ) {
			$assumed[] = SpecKey::WheelWidth;
		}
		$risers = $picks->part( SlotKey::RISERS );
		$riser  = null === $risers ? 0 : self::size( $risers->specs, SpecKey::RiserThickness, self::USUAL_RISER, $assumed );
		$bolts  = $picks->part( SlotKey::HARDWARE );
		$fixed  = null === $bolts ? null : array(
			'part'   => $bolts->id,
			'length' => self::size( $bolts->specs, SpecKey::BoltLength, self::USUAL_BOLT, $assumed ),
			'points' => $holes,
		);
		$grip   = $picks->part( SlotKey::GRIP );
		$axle   = self::um( $board->axle );
		$wide   = self::um( $board->wheelWidth );
		$under  = -( self::THICKNESS + $riser + $height );
		$out    = self::um( $board->out );
		return array(
			'assumed' => array_map( static fn( SpecKey $key ): string => $key->value, $assumed ),
			'deck'    => $deck,
			'trucks'  => null === $trucks ? null : array(
				'part'      => $trucks->id,
				'axle'      => $axle,
				'height'    => $height,
				'hanger'    => intdiv( $axle * self::HANGER_THIRDS, 3 ),
				'baseplate' => array(
					'length' => self::um( $board->spacing + Board::BASEPLATE_MARGIN ),
					'width'  => self::um( Board::HOLE_COLUMNS + Board::BASEPLATE_MARGIN ),
				),
				'holes'     => $holes,
				'centres'   => array(
					array( $out, 0, $under ),
					array( -$out, 0, $under ),
				),
				'color'     => null,
			),
			'wheels'  => null === $wheels ? null : array(
				'part'     => $wheels->id,
				'diameter' => self::um( $board->wheel ),
				'width'    => $wide,
				'contact'  => intdiv( $wide, 2 ),
				'texture'  => null,
				'color'    => null,
				'places'   => self::places( $out, Rounding::halfUp( max( $axle, $wide ) - $wide, 2 ), $under ),
			),
			'risers'  => null === $risers ? null : array(
				'part'      => $risers->id,
				'thickness' => $riser,
			),
			'bolts'   => $fixed,
			'grip'    => null === $grip ? null : array(
				'part'    => $grip->id,
				'texture' => null,
			),
		);
	}

	/**
	 * The sizes the scene alone reads that a part of a slot lacks, so the 3D view assumes the usual ones: for the
	 * Catalogue check. A deck's nose, tail, concave and kick, and a wheel's width; none for any other slot, whose
	 * missing sizes the rules name.
	 *
	 * @since 1.4.0
	 *
	 * @param string $slot  The slot's key.
	 * @param Specs  $specs The part's specs.
	 * @return list<SpecKey>
	 */
	public static function assumes( string $slot, Specs $specs ): array {
		$lacks = array();
		foreach ( self::OWN[ $slot ] ?? array() as $key ) {
			if ( ! $specs->has( $key ) ) {
				$lacks[] = $key;
			}
		}
		return $lacks;
	}

	/**
	 * The deck: its sizes, its outline's nose and tail, its concave and kick, and its holes.
	 *
	 * @param Board                           $board   The board.
	 * @param non-empty-list<array{int, int}> $holes   The holes, in micrometres.
	 * @param list<SpecKey>                   $assumed The specs assumed so far, to add to.
	 * @return array{part: int, width: int, length: int, wheelbase: int, nose: int, tail: int, concave: int, kick: int, thickness: int, plies: int, holes: array{pattern: string, spacing: int, columns: int, points: list<array{int, int}>}, texture: array{top: null, bottom: null}}
	 */
	private static function deck( Board $board, array $holes, array &$assumed ): array {
		$specs  = $board->deck->specs;
		$width  = self::um( $board->width );
		$length = self::um( $board->length );
		// The outer holes' span, nose row to tail row; the rest of the length is the nose and the tail.
		$along = array_column( $holes, 0 );
		$rest  = max( 0, $length - ( max( $along ) - min( $along ) ) );
		$nose  = self::length( $specs->measure( SpecKey::NoseLength ) );
		$tail  = self::length( $specs->measure( SpecKey::TailLength ) );
		if ( null === $nose ) {
			$assumed[] = SpecKey::NoseLength;
		}
		if ( null === $tail ) {
			$assumed[] = SpecKey::TailLength;
		}
		if ( null !== $nose && null !== $tail ) {
			// Both given: shared out in their proportion, so they and the span make the length whatever was typed.
			$nose = Rounding::halfUp( $rest * $nose, $nose + $tail );
		} elseif ( null !== $tail ) {
			$nose = $rest - min( $tail, $rest );
		} else {
			$nose = min( $nose ?? Rounding::halfUp( $rest + self::NOSE_LEAD, 2 ), $rest );
		}
		$concave = self::size( $specs, SpecKey::ConcaveDepth, self::USUAL_CONCAVE, $assumed );
		$kick    = $specs->count( SpecKey::KickAngle );
		if ( null === $kick ) {
			$assumed[] = SpecKey::KickAngle;
		}
		return array(
			'part'      => $board->deck->id,
			'width'     => $width,
			'length'    => $length,
			'wheelbase' => self::um( $board->wheelbase ),
			'nose'      => $nose,
			'tail'      => $rest - $nose,
			'concave'   => min( $concave, self::um( intdiv( $board->width, self::CONCAVE_PARTS ) ) ),
			'kick'      => null === $kick ? self::USUAL_KICK : min( $kick * 10, self::MAX_KICK ),
			'thickness' => self::THICKNESS,
			'plies'     => self::PLIES,
			'holes'     => array(
				'pattern' => $board->pattern->value,
				'spacing' => self::um( $board->spacing ),
				'columns' => self::um( Board::HOLE_COLUMNS ),
				'points'  => $holes,
			),
			'texture'   => array(
				'top'    => null,
				'bottom' => null,
			),
		);
	}

	/**
	 * The trucks' height, or their class's usual one, and none where nothing hangs under the deck.
	 *
	 * @param Part|null     $trucks  The trucks.
	 * @param bool          $hangs   Whether trucks or wheels are picked.
	 * @param list<SpecKey> $assumed The specs assumed so far, to add to.
	 */
	private static function height( ?Part $trucks, bool $hangs, array &$assumed ): int {
		$height = $trucks?->specs->measure( SpecKey::TruckHeight );
		if ( null !== $height ) {
			return self::um( Board::mm( $height ) );
		}
		if ( $hangs ) {
			$assumed[] = SpecKey::TruckHeight;
		}
		return self::HEIGHTS[ HeightClass::of( $trucks?->specs->choice( SpecKey::HeightClass ) )->value ];
	}

	/**
	 * The four wheels' centres, the nose's pair first and each pair's left first.
	 *
	 * @param int $out  How far an axle is from the middle, along the deck.
	 * @param int $side How far a wheel's centre is from the deck's centre line, across it.
	 * @param int $z    The axle's height.
	 * @return list<array{int, int, int}>
	 */
	private static function places( int $out, int $side, int $z ): array {
		$places = array();
		foreach ( array( $out, -$out ) as $along ) {
			foreach ( array( -$side, $side ) as $across ) {
				$places[] = array( $along, $across, $z );
			}
		}
		return $places;
	}

	/**
	 * A length in inches a part gives, or its usual one, in micrometres; the usual one is named.
	 *
	 * @param Specs         $specs   The part's specs.
	 * @param SpecKey       $key     The spec.
	 * @param int           $usual   The usual length, in ten-thousandths of an inch.
	 * @param list<SpecKey> $assumed The specs assumed so far, to add to.
	 */
	private static function size( Specs $specs, SpecKey $key, int $usual, array &$assumed ): int {
		$given = $specs->measure( $key );
		if ( null === $given ) {
			$assumed[] = $key;
		}
		return self::um( Board::mm( $given ?? Measure::inches( $usual ) ) );
	}

	/**
	 * A length a part gives in micrometres, or null where it gives none.
	 *
	 * @param Measure|null $given The length.
	 */
	private static function length( ?Measure $given ): ?int {
		return null === $given ? null : self::um( Board::mm( $given ) );
	}

	/**
	 * Ten-thousandths of a millimetre as whole micrometres, a half rounded away from the middle so the board stays
	 * symmetric.
	 *
	 * @param int $tenThousandths The length, either side of the middle.
	 */
	private static function um( int $tenThousandths ): int {
		$um = Rounding::halfUp( abs( $tenThousandths ), 10 );
		return abs( $tenThousandths ) === $tenThousandths ? $um : -$um;
	}
}
