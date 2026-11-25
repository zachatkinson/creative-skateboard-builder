<?php
/**
 * The deck width guide by shoe size.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SizeGuide;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The rows of "Not sure of your width?" under the deck step's width question (spec: The deck's width first): a shoe
 * size and the deck width it usually rides, after Skate Warehouse's deck buying guide, in the plugin's own words.
 * From 1.2.0 a kit's Build tab gives its own rows or hides the guide (#114); a plugin's answer to `cbuild_width_guide`
 * comes last.
 *
 * @since 1.0.0
 */
final class WidthGuide {

	/**
	 * The most rows the guide shows.
	 */
	private const int MOST = 12;

	/**
	 * The field a row's shoe size is asked of `cbuild_kit_text` as (1.2.0).
	 *
	 * @since 1.2.0
	 */
	public const string SHOE = 'width_guide_shoe';

	/**
	 * The guide's rows for a kit, as plain text, each with a shoe size and a width. A row without both is left out.
	 * From 1.2.0 the kit's own setting comes first (#114, D-350): the plugin's rows, the shop's own, or none for a
	 * guide the kit hides. `cbuild_width_guide` answers last, over any of them.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 The kit's width guide.
	 *
	 * @param int            $kit   The kit product's ID.
	 * @param SizeGuide|null $guide The kit's width guide; none, as for a kit saved before 1.2.0, is the plugin's rows.
	 * @return list<array{shoe: string, width: string}>
	 */
	public static function rows( int $kit, ?SizeGuide $guide = null ): array {
		$rows = array_map(
			static fn( array $row ): array => array(
				'shoe'  => $row['shoe'],
				'width' => self::widths( $row['from'], $row['to'] ),
			),
			self::structured( $kit, $guide )
		);
		/**
		 * Filters the rows of the deck width guide by shoe size, "Not sure of your width?", under the deck step's width
		 * question. Each row is an array with `shoe` (the shoe size, as the shop writes it) and `width` (the deck width
		 * it rides). Each is shown as plain text; a row without both is left out, and twelve rows are shown at most. An
		 * empty list takes the guide away.
		 *
		 * @since 1.0.0
		 * @since 1.2.0 `$rows` starts as the kit's: the plugin's, the shop's own from its Build tab, or none where the
		 *              kit hides the guide.
		 *
		 * @param list<array{shoe: string, width: string}> $rows The kit's rows: the plugin's, after Skate Warehouse's deck
		 *                                                       buying guide, unless the kit has its own or hides it.
		 * @param int                                      $kit  The kit product's ID.
		 */
		$said = apply_filters( 'cbuild_width_guide', $rows, $kit );
		$kept = array();
		foreach ( Cast::items( $said ) as $row ) {
			$row = is_array( $row ) ? $row : array();
			// wp_strip_all_tags() trims what it leaves, so a row of spaces or of markup alone is empty.
			$shoe  = is_string( $row['shoe'] ?? null ) ? wp_strip_all_tags( $row['shoe'] ) : '';
			$width = is_string( $row['width'] ?? null ) ? wp_strip_all_tags( $row['width'] ) : '';
			if ( '' !== $shoe && '' !== $width ) {
				$kept[] = array(
					'shoe'  => $shoe,
					'width' => $width,
				);
			}
		}
		return array_slice( $kept, 0, self::MOST );
	}

	/**
	 * The kit's rows as widths, before any plugin answers: the plugin's, the shop's own from its Build tab, or none
	 * where the kit hides the guide. The guided start asks its shoe size question from these (#134), since
	 * `cbuild_width_guide` answers in words, not widths.
	 *
	 * @since 1.3.0
	 *
	 * @param int            $kit   The kit product's ID.
	 * @param SizeGuide|null $guide The kit's width guide; none is the plugin's rows.
	 * @return list<array{shoe: string, from: Measure, to: Measure|null}>
	 */
	public static function structured( int $kit, ?SizeGuide $guide = null ): array {
		$guide ??= SizeGuide::plugins();
		if ( ! $guide->shown ) {
			return array();
		}
		if ( null === $guide->rows ) {
			return self::defaults();
		}
		$rows = array();
		foreach ( $guide->rows as $at => $row ) {
			$rows[] = array(
				// The owner's words, which no translation file holds: a translation plugin answers them, as it does the
				// kit's other texts.
				'shoe' => StateAnswer::text( $row->shoe, self::SHOE, $kit, (string) $at ),
				'from' => $row->from,
				'to'   => $row->to,
			);
		}
		return $rows;
	}

	/**
	 * The plugin's rows, after Skate Warehouse's deck buying guide, in its own translatable words: each shoe size as
	 * the shopper's language writes it, and the deck widths it rides. The Build tab starts a kit's own rows from these.
	 *
	 * @since 1.2.0
	 *
	 * @return list<array{shoe: string, from: Measure, to: Measure|null}>
	 */
	public static function defaults(): array {
		return array(
			array(
				/* translators: %s: a US shoe size, such as 7. */
				'shoe' => sprintf( __( '%s or smaller', 'creative-skateboard-builder' ), number_format_i18n( 6.5, 1 ) ),
				'from' => Measure::inches( 70000 ),
				'to'   => Measure::inches( 77500 ),
			),
			array(
				'shoe' => self::between( number_format_i18n( 7 ), number_format_i18n( 8 ) ),
				'from' => Measure::inches( 77500 ),
				'to'   => Measure::inches( 80000 ),
			),
			array(
				'shoe' => self::between( number_format_i18n( 8.5, 1 ), number_format_i18n( 9 ) ),
				'from' => Measure::inches( 80000 ),
				'to'   => Measure::inches( 82500 ),
			),
			array(
				/* translators: %s: a US shoe size, such as 9. */
				'shoe' => sprintf( __( '%s and larger', 'creative-skateboard-builder' ), number_format_i18n( 9.5, 1 ) ),
				'from' => Measure::inches( 82500 ),
				'to'   => null,
			),
		);
	}

	/**
	 * The widths a row rides, in the shopper's units: from one to another, one alone, or one and wider.
	 *
	 * @param Measure      $from The narrowest.
	 * @param Measure|null $to   The widest, or none for "and wider".
	 */
	private static function widths( Measure $from, ?Measure $to ): string {
		if ( null === $to ) {
			/* translators: %s: a deck width, such as 8.25″. */
			return sprintf( __( '%s and wider', 'creative-skateboard-builder' ), Words::measure( $from ) );
		}
		return $from->equals( $to ) ? Words::measure( $from ) : self::between( Words::measure( $from ), Words::measure( $to ) );
	}

	/**
	 * A range of two numbers or widths.
	 *
	 * @param string $from The smaller.
	 * @param string $to   The larger.
	 */
	private static function between( string $from, string $to ): string {
		/* translators: 1: the smaller of a range, such as a deck width of 7″ or a US shoe size of 7. 2: the larger, such as 7.75″ or 8.5. */
		return sprintf( __( '%1$s to %2$s', 'creative-skateboard-builder' ), $from, $to );
	}
}
