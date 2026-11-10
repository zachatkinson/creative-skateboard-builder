<?php
/**
 * Reads and writes CSV text.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV as spreadsheets write it (RFC 4180): cells between commas, a cell with a comma, a quote or a line break
 * inside double quotes, a quote inside a quoted cell doubled. Read from text already in memory, so no file is
 * opened here, and written a line at a time.
 *
 * A cell that starts with `=`, `+`, `-`, `@`, a tab or a carriage return is written with an apostrophe before it:
 * a spreadsheet would otherwise run it as a formula (spec: Security, Admin). Read back, the apostrophe is taken off.
 *
 * @since 0.3.0
 */
final class Csv {

	/**
	 * What a spreadsheet takes for the start of a formula.
	 */
	private const FORMULA = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * The rows of a text, each a list of cells. A row with nothing in any cell is left out.
	 *
	 * @since 0.3.0
	 *
	 * @param string $text      The CSV.
	 * @param string $delimiter What parts the cells: a comma, or a semicolon as some spreadsheets write.
	 * @return list<list<string>>
	 */
	public static function parse( string $text, string $delimiter = ',' ): array {
		$rows   = array();
		$row    = array();
		$cell   = '';
		$quoted = false;
		$length = strlen( $text );
		for ( $at = 0; $at < $length; $at++ ) {
			$char = $text[ $at ];
			if ( $quoted ) {
				if ( '"' !== $char ) {
					$cell .= $char;
				} elseif ( '"' === ( $text[ $at + 1 ] ?? '' ) ) {
					$cell .= '"';
					++$at;
				} else {
					$quoted = false;
				}
				continue;
			}
			if ( '"' === $char && '' === $cell ) {
				$quoted = true;
			} elseif ( $delimiter === $char ) {
				$row[] = $cell;
				$cell  = '';
			} elseif ( "\n" === $char || "\r" === $char ) {
				// A carriage return and a line feed together end one row and then a blank one, which is left out.
				$row[] = $cell;
				$cell  = '';
				$rows  = self::with( $rows, $row );
				$row   = array();
			} else {
				$cell .= $char;
			}
		}
		$row[] = $cell;
		return self::with( $rows, $row );
	}

	/**
	 * The rows with one more, unless it is blank.
	 *
	 * @param list<list<string>> $rows The rows so far.
	 * @param list<string>       $row  The next.
	 * @return list<list<string>>
	 */
	private static function with( array $rows, array $row ): array {
		$cells = array();
		foreach ( $row as $cell ) {
			$cell    = trim( $cell );
			$cells[] = strlen( $cell ) > 1 && "'" === $cell[0] && in_array( $cell[1], self::FORMULA, true ) ? trim( substr( $cell, 1 ) ) : $cell;
		}
		if ( '' !== implode( '', $cells ) ) {
			$rows[] = $cells;
		}
		return $rows;
	}

	/**
	 * What parts the cells of a text: a semicolon when its first line has more of them than commas.
	 *
	 * @since 0.3.0
	 *
	 * @param string $text The CSV.
	 */
	public static function delimiter( string $text ): string {
		$first = strtok( $text, "\r\n" );
		$first = false === $first ? '' : $first;
		return substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
	}

	/**
	 * One row as a line of CSV, ending in a line break, safe to open in a spreadsheet.
	 *
	 * @since 0.3.0
	 *
	 * @param list<string> $cells The cells.
	 */
	public static function line( array $cells ): string {
		$written = array();
		foreach ( $cells as $cell ) {
			if ( '' !== $cell && in_array( $cell[0], self::FORMULA, true ) ) {
				$cell = "'" . $cell;
			}
			$written[] = 1 === preg_match( '/[",\r\n;]/', $cell ) ? '"' . str_replace( '"', '""', $cell ) . '"' : $cell;
		}
		return implode( ',', $written ) . "\r\n";
	}
}
