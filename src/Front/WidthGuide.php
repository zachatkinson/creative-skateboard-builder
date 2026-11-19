<?php
/**
 * The deck width guide by shoe size.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The rows of "Not sure of your width?" under the deck step's width question (spec: The deck's width first): a shoe
 * size and the deck width it usually rides, after Skate Warehouse's deck buying guide, in the plugin's own words.
 * A shop gives its own rows through `cbuild_width_guide`.
 *
 * @since 1.0.0
 */
final class WidthGuide {

	/**
	 * The most rows the guide shows.
	 */
	private const int MOST = 12;

	/**
	 * The guide's rows for a kit, as plain text, each with a shoe size and a width. A row without both is left out.
	 *
	 * @since 1.0.0
	 *
	 * @param int $kit The kit product's ID.
	 * @return list<array{shoe: string, width: string}>
	 */
	public static function rows( int $kit ): array {
		$inches = static fn( int $tenThousandths ): string => Words::measure( Measure::inches( $tenThousandths ) );
		/* translators: 1: the narrower deck width, such as 7″. 2: the wider, such as 7.75″. */
		$between = static fn( int $from, int $to ): string => sprintf( __( '%1$s to %2$s', 'creative-skateboard-builder' ), $inches( $from ), $inches( $to ) );
		$rows    = array(
			array(
				/* translators: %s: a US shoe size, such as 7. */
				'shoe'  => sprintf( __( '%s or smaller', 'creative-skateboard-builder' ), number_format_i18n( 7 ) ),
				'width' => $between( 70000, 77500 ),
			),
			array(
				/* translators: 1: the smaller US shoe size, such as 7. 2: the larger, such as 8.5. */
				'shoe'  => sprintf( __( '%1$s to %2$s', 'creative-skateboard-builder' ), number_format_i18n( 7 ), number_format_i18n( 8.5, 1 ) ),
				'width' => $between( 77500, 80000 ),
			),
			array(
				/* translators: 1: the smaller US shoe size, such as 7. 2: the larger, such as 8.5. */
				'shoe'  => sprintf( __( '%1$s to %2$s', 'creative-skateboard-builder' ), number_format_i18n( 8.5, 1 ), number_format_i18n( 9 ) ),
				'width' => $between( 80000, 82500 ),
			),
			array(
				/* translators: %s: a US shoe size, such as 9. */
				'shoe'  => sprintf( __( '%s and larger', 'creative-skateboard-builder' ), number_format_i18n( 9 ) ),
				/* translators: %s: a deck width, such as 8.25″. */
				'width' => sprintf( __( '%s and wider', 'creative-skateboard-builder' ), $inches( 82500 ) ),
			),
		);
		/**
		 * Filters the rows of the deck width guide by shoe size, "Not sure of your width?", under the deck step's width
		 * question. Each row is an array with `shoe` (the shoe size, as the shop writes it) and `width` (the deck width
		 * it rides). Each is shown as plain text; a row without both is left out, and twelve rows are shown at most. An
		 * empty list takes the guide away.
		 *
		 * @since 1.0.0
		 *
		 * @param list<array{shoe: string, width: string}> $rows The plugin's rows, after Skate Warehouse's deck buying guide.
		 * @param int                                      $kit  The kit product's ID.
		 */
		$said = apply_filters( 'cbuild_width_guide', $rows, $kit );
		$kept = array();
		foreach ( Cast::items( $said ) as $row ) {
			$shoe  = is_array( $row ) && is_string( $row['shoe'] ?? null ) ? trim( wp_strip_all_tags( $row['shoe'] ) ) : '';
			$width = is_array( $row ) && is_string( $row['width'] ?? null ) ? trim( wp_strip_all_tags( $row['width'] ) ) : '';
			if ( '' !== $shoe && '' !== $width ) {
				$kept[] = array(
					'shoe'  => $shoe,
					'width' => $width,
				);
			}
		}
		return array_slice( $kept, 0, self::MOST );
	}
}
