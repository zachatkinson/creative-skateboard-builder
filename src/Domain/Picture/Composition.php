<?php
/**
 * The layout placed on a canvas.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

use function hash;
use function intdiv;
use function implode;
use function max;
use function min;
use function substr;

/**
 * The composed picture's drawing, before any library draws it (spec: The picture; D-196): the board fitted inside a
 * margin of a twenty-fifth of the canvas's shorter side, nose up and centred, and each layer image fitted into its
 * layer's rectangle and centred in it, as the page fits it. A part with no art is not drawn. Its name is a hash of the
 * placements, which hold each image's file, of the canvas and of this version, so the same build gives the same file
 * and nothing a visitor sends is in it.
 *
 * @since 0.7.0
 */
final readonly class Composition {

	/**
	 * The drawing's version: a change to how a composition is drawn changes every name, so no old file is shown.
	 */
	public const int VERSION = 1;

	/**
	 * The margin: this share of the canvas's shorter side, in hundredths.
	 */
	private const int MARGIN = 4;

	/**
	 * Constructor.
	 *
	 * @param Canvas          $canvas     The canvas.
	 * @param list<Placement> $placements From the bottom of the stack.
	 */
	private function __construct( public Canvas $canvas, public array $placements ) {}

	/**
	 * Place a layout on a canvas.
	 *
	 * @since 0.7.0
	 *
	 * @param Canvas               $canvas The canvas.
	 * @param Layout               $layout The layout.
	 * @param array<int, LayerArt> $art    The art of the parts with a layer image, by part ID.
	 */
	public static function of( Canvas $canvas, Layout $layout, array $art ): self {
		if ( $layout->width < 1 || $layout->height < 1 ) {
			return new self( $canvas, array() );
		}
		$margin = intdiv( 2 * min( $canvas->width, $canvas->height ) * self::MARGIN + 100, 200 );
		$board  = self::fitted( $canvas->width - 2 * $margin, $canvas->height - 2 * $margin, $layout->width, $layout->height );
		$left   = intdiv( $canvas->width - $board[0], 2 );
		$top    = intdiv( $canvas->height - $board[1], 2 );
		$placed = array();
		foreach ( $layout->layers as $layer ) {
			$image = $art[ $layer->part ] ?? null;
			if ( null === $image ) {
				continue;
			}
			$corner   = $layout->corner( $layer );
			$box      = array(
				$left + self::rounded( $corner[0] * $board[0], $layout->width ),
				$top + self::rounded( $corner[1] * $board[1], $layout->height ),
				max( 1, self::rounded( $layer->width * $board[0], $layout->width ) ),
				max( 1, self::rounded( $layer->height * $board[1], $layout->height ) ),
			);
			$fit      = self::fitted( $box[2], $box[3], $image->width, $image->height );
			$placed[] = new Placement( $image->attachment, $image->url, $box[0] + intdiv( $box[2] - $fit[0], 2 ), $box[1] + intdiv( $box[3] - $fit[1], 2 ), $fit[0], $fit[1] );
		}
		return new self( $canvas, $placed );
	}

	/**
	 * Whether nothing is drawn.
	 *
	 * @since 0.7.0
	 */
	public function isEmpty(): bool {
		return array() === $this->placements;
	}

	/**
	 * The file's name: a hash of what is drawn and how, with the type's extension.
	 *
	 * @since 0.7.0
	 */
	public function name(): string {
		// One line for the canvas and one for each placement, its address last: no address holds a line break.
		$drawn = array( implode( '|', array( self::VERSION, $this->canvas->width, $this->canvas->height, $this->canvas->type ) ) );
		foreach ( $this->placements as $placement ) {
			$drawn[] = implode( '|', array( $placement->attachment, $placement->x, $placement->y, $placement->width, $placement->height, $placement->url ) );
		}
		return substr( hash( 'sha256', implode( "\n", $drawn ) ), 0, 32 ) . '.' . $this->canvas->extension();
	}

	/**
	 * A rectangle of this shape fitted into a box, keeping its shape.
	 *
	 * @param int $across The box's width.
	 * @param int $down   Its height.
	 * @param int $width  The rectangle's width.
	 * @param int $height Its height.
	 * @return array{int, int}
	 */
	private static function fitted( int $across, int $down, int $width, int $height ): array {
		if ( $width * $down <= $height * $across ) {
			return array( max( 1, self::rounded( $width * $down, $height ) ), $down );
		}
		return array( $across, max( 1, self::rounded( $height * $across, $width ) ) );
	}

	/**
	 * A quotient rounded to the nearest whole number, a half up.
	 *
	 * @param int $dividend The dividend.
	 * @param int $divisor  The divisor, above 0.
	 */
	private static function rounded( int $dividend, int $divisor ): int {
		return intdiv( 2 * $dividend + $divisor, 2 * $divisor );
	}
}
