<?php
/**
 * A kit's wheel guide.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Guide;

use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Fields;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

use function array_column;
use function count;
use function in_array;

/**
 * The wheel sizes the guided start recommends for each riding style (#134): the shop's own range for a style, from
 * the kit's Build tab, or the plugin's, from published wheel guides (D-400). From the `wheel_guide` object of the
 * `_cbuild_settings` document (D-354); a kit with none, as every kit saved before 1.3.0, has the plugin's sizes.
 *
 * @since 1.3.0
 */
final readonly class WheelGuide {

	/**
	 * The smallest and the largest wheel a row may name, in ten-thousandths of a millimetre: 40 mm and 90 mm, past any
	 * skateboard wheel.
	 *
	 * @since 1.3.0
	 */
	public const int SMALLEST = 400000;

	public const int LARGEST = 900000;

	/**
	 * The plugin's sizes, in ten-thousandths of a millimetre, by style (D-400): street 50 to 54 mm (Tactics, Sector 9,
	 * Skate Warehouse's 54 mm and under), park 53 to 56 mm (skatedeluxe's ramps and mini ramps), bowl 55 to 58 mm
	 * (Skate Warehouse's transition), cruising 59 to 65 mm (Skate Warehouse's 59 mm and larger, Sector 9's 60 to 65).
	 *
	 * @since 1.3.0
	 */
	public const array DEFAULTS = array(
		'street'   => array( 500000, 540000 ),
		'park'     => array( 530000, 560000 ),
		'bowl'     => array( 550000, 580000 ),
		'cruising' => array( 590000, 650000 ),
	);

	/**
	 * The most characters a style's code is read from.
	 */
	private const int STYLE = 16;

	/**
	 * Constructor.
	 *
	 * @param array<string, Span> $own The shop's own range for a style, by its code.
	 */
	private function __construct( private array $own ) {}

	/**
	 * The plugin's sizes for every style: what a kit with no wheel guide has.
	 *
	 * @since 1.3.0
	 */
	public static function plugins(): self {
		return new self( array() );
	}

	/**
	 * From the settings document's `wheel_guide` object: a row a style at most, each a size from 40 to 90 mm or a
	 * range of them. A style with no row has the plugin's sizes.
	 *
	 * @since 1.3.0
	 *
	 * @param Fields $guide The object.
	 *
	 * @throws InvalidDocument When it breaks the object's shape, or names a style twice.
	 */
	public static function fromFields( Fields $guide ): self {
		$styles = array_column( RidingStyle::cases(), 'value' );
		$own    = array();
		foreach ( $guide->rows( 'rows', count( $styles ) ) as $row ) {
			$style = $row->text( 'style', self::STYLE );
			if ( ! in_array( $style, $styles, true ) ) {
				$refused = InvalidDocument::because( DocumentError::OutOfRange, $row->path( 'style' ) );
				throw $refused;
			}
			if ( isset( $own[ $style ] ) ) {
				$refused = InvalidDocument::because( DocumentError::Duplicate, $row->path( 'style' ) );
				throw $refused;
			}
			$from = self::size( $row, $row->amount( 'from' ), 'from' );
			$to   = $row->amountOrNull( 'to' );
			if ( null !== $to && self::size( $row, $to, 'to' ) < $from ) {
				$refused = InvalidDocument::because( DocumentError::OutOfOrder, $row->path( 'to' ) );
				throw $refused;
			}
			$own[ $style ] = new Span( Measure::millimetres( $from ), Measure::millimetres( $to ?? $from ) );
		}
		return new self( $own );
	}

	/**
	 * The wheel sizes for a style: the shop's, or the plugin's.
	 *
	 * @since 1.3.0
	 *
	 * @param RidingStyle $style The style.
	 */
	public function span( RidingStyle $style ): Span {
		$plugins = self::DEFAULTS[ $style->value ];
		return $this->own[ $style->value ] ?? new Span( Measure::millimetres( $plugins[0] ), Measure::millimetres( $plugins[1] ) );
	}

	/**
	 * Whether the shop gave a style its own sizes.
	 *
	 * @since 1.3.0
	 *
	 * @param RidingStyle $style The style.
	 */
	public function isOwn( RidingStyle $style ): bool {
		return isset( $this->own[ $style->value ] );
	}

	/**
	 * A size held to what a wheel can be.
	 *
	 * @param Fields $row  The row.
	 * @param int    $size The size, in ten-thousandths of a millimetre.
	 * @param string $key  Its field.
	 *
	 * @throws InvalidDocument When it is smaller or larger than a wheel.
	 */
	private static function size( Fields $row, int $size, string $key ): int {
		if ( $size < self::SMALLEST || $size > self::LARGEST ) {
			$refused = InvalidDocument::because( DocumentError::OutOfRange, $row->path( $key ) );
			throw $refused;
		}
		return $size;
	}
}
