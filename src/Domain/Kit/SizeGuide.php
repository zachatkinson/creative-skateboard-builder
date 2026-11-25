<?php
/**
 * A kit's width guide.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;

/**
 * "Not sure of your width?" under the deck step's width question, as a kit sets it (#114, D-350): shown or hidden,
 * with the plugin's rows or the shop's own. From the `width_guide` object of the `_cbuild_settings` document
 * (D-354); a kit with none shows the plugin's rows, as 1.1.0 did.
 *
 * @since 1.2.0
 */
final readonly class SizeGuide {

	/**
	 * The most rows a guide takes, and the most characters a shoe size may have, as the schema says.
	 *
	 * @since 1.2.0
	 */
	public const int MAX_ROWS = 12;

	public const int MAX_SHOE = 40;

	/**
	 * The narrowest and the widest deck a row may name, in ten-thousandths of an inch: 5 in and 12 in, past any deck.
	 *
	 * @since 1.2.0
	 */
	public const int NARROWEST = 50000;

	public const int WIDEST = 120000;

	/**
	 * Constructor.
	 *
	 * @param bool                    $shown Whether the guide is on the page at all.
	 * @param list<SizeGuideRow>|null $rows  The shop's own rows, or null for the plugin's.
	 */
	private function __construct( public bool $shown, public ?array $rows ) {}

	/**
	 * The plugin's rows, shown: what a kit with no width guide has.
	 *
	 * @since 1.2.0
	 */
	public static function plugins(): self {
		return new self( true, null );
	}

	/**
	 * From the settings document's `width_guide` object.
	 *
	 * @since 1.2.0
	 *
	 * @param Fields $guide The object.
	 *
	 * @throws InvalidDocument When it breaks the object's shape, or names a shoe size twice.
	 */
	public static function fromFields( Fields $guide ): self {
		$shown = ! $guide->has( 'shown' ) || $guide->flag( 'shown' );
		if ( ! $guide->has( 'rows' ) ) {
			return new self( $shown, null );
		}
		$rows = array();
		$seen = array();
		foreach ( $guide->rowsFilled( 'rows', self::MAX_ROWS ) as $row ) {
			$shoe = $row->text( 'shoe', self::MAX_SHOE );
			// The page keys each row by its shoe size, so a guide names each once.
			if ( isset( $seen[ $shoe ] ) ) {
				$refused = InvalidDocument::because( DocumentError::Duplicate, $row->path( 'shoe' ) );
				throw $refused;
			}
			$seen[ $shoe ] = $shoe;
			$from          = self::width( $row, $row->amount( 'from' ), 'from' );
			$to            = $row->amountOrNull( 'to' );
			if ( null !== $to ) {
				self::width( $row, $to, 'to' );
				if ( $to < $from ) {
					$refused = InvalidDocument::because( DocumentError::OutOfOrder, $row->path( 'to' ) );
					throw $refused;
				}
			}
			$rows[] = new SizeGuideRow( $shoe, Measure::of( $from, Unit::Inch ), null === $to ? null : Measure::of( $to, Unit::Inch ) );
		}
		return new self( $shown, $rows );
	}

	/**
	 * A width held to what a deck can be.
	 *
	 * @param Fields $row   The row.
	 * @param int    $width The width, in ten-thousandths of an inch.
	 * @param string $key   Its field.
	 *
	 * @throws InvalidDocument When it is narrower or wider than a deck.
	 */
	private static function width( Fields $row, int $width, string $key ): int {
		if ( $width < self::NARROWEST || $width > self::WIDEST ) {
			$refused = InvalidDocument::because( DocumentError::OutOfRange, $row->path( $key ) );
			throw $refused;
		}
		return $width;
	}
}
