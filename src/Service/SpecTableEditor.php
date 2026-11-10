<?php
/**
 * Adds, saves and deletes the makers' charts.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Domain\Spec\TableRow;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A chart is saved whole or not at all: a row half right would give some trucks their figures and leave the rest
 * with last year's. Each figure is read as the index reads it and stored as the index writes it, so a chart typed
 * with a comma or a fraction reads the same in every locale.
 *
 * @since 0.3.0
 */
final class SpecTableEditor {

	/**
	 * The longest name a chart takes, and the most rows.
	 */
	public const MAX_NAME = 190;
	public const MAX_ROWS = 100;

	/**
	 * The fields of a row that are not spec keys, and of the table.
	 */
	public const NAME   = 'name';
	public const SIZE   = 'size';
	public const SOURCE = 'source';
	public const READ   = 'read_on';

	/**
	 * What can be wrong. A figure's problem carries the domain's own code.
	 */
	public const NAME_MISSING   = 'name_missing';
	public const NAME_LONG      = 'name_long';
	public const TABLE_MISSING  = 'table_missing';
	public const SIZE_MISSING   = 'size_missing';
	public const SIZE_LONG      = 'size_long';
	public const SIZE_DUPLICATE = 'size_duplicate';
	public const SOURCE_LONG    = 'source_long';
	public const DATE_INVALID   = 'date_invalid';
	public const TOO_MANY       = 'rows_too_many';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecTables $tables The charts.
	 */
	public function __construct( private SpecTables $tables ) {}

	/**
	 * Add a chart with no rows yet.
	 *
	 * @since 0.3.0
	 *
	 * @param string $name The maker's name.
	 */
	public function add( string $name ): TableDraft {
		$problem = self::nameRefused( trim( $name ) );
		if ( null !== $problem ) {
			return new TableDraft( 0, array( $problem ) );
		}
		return new TableDraft( $this->tables->create( trim( $name ), SpecTable::read( array() ) ), array() );
	}

	/**
	 * Save a chart's name and rows, all or none.
	 *
	 * @since 0.3.0
	 *
	 * @param int          $id        The chart's ID.
	 * @param string       $name      The maker's name.
	 * @param array<mixed> $rows      The rows as typed, each with `size`, `source`, `read_on` and `specs`, a figure
	 *                                per spec key. A row with nothing in it is no row.
	 * @param string       $separator The shop's decimal separator.
	 */
	public function save( int $id, string $name, array $rows, string $separator = '.' ): TableDraft {
		if ( ! isset( $this->tables->all()[ $id ] ) ) {
			return new TableDraft( $id, array( self::problem( 0, self::NAME, self::TABLE_MISSING ) ) );
		}
		$problems = array();
		$problem  = self::nameRefused( trim( $name ) );
		if ( null !== $problem ) {
			$problems[] = $problem;
		}
		$kept  = array();
		$sizes = array();
		$line  = 0;
		foreach ( $rows as $row ) {
			++$line;
			$row   = is_array( $row ) ? $row : array();
			$specs = is_array( $row['specs'] ?? null ) ? $row['specs'] : array();
			$text  = static fn( mixed $value ): string => is_string( $value ) ? trim( $value ) : '';
			$size  = $text( $row[ self::SIZE ] ?? null );
			$from  = $text( $row[ self::SOURCE ] ?? null );
			$read  = $text( $row[ self::READ ] ?? null );

			$values = array();
			foreach ( SpecKey::cases() as $key ) {
				$typed = $text( $specs[ $key->value ] ?? null );
				if ( '' === $typed ) {
					continue;
				}
				$reading = Reading::typed( $key, $typed, Origin::Table, '', $separator );
				if ( $reading instanceof Reading ) {
					$values[ $key->value ] = $reading->text;
				} else {
					$values[ $key->value ] = $typed;
					$problems[]            = self::problem( $line, $key->value, $reading->reason );
				}
			}
			if ( '' === $size && '' === $from && '' === $read && array() === $values ) {
				continue;
			}

			if ( '' === $size ) {
				$problems[] = self::problem( $line, self::SIZE, self::SIZE_MISSING );
			} elseif ( strlen( $size ) > TableRow::MAX_SIZE ) {
				$problems[] = self::problem( $line, self::SIZE, self::SIZE_LONG );
			} elseif ( in_array( $size, $sizes, true ) ) {
				$problems[] = self::problem( $line, self::SIZE, self::SIZE_DUPLICATE );
			}
			$sizes[] = $size;
			if ( strlen( $from ) > TableRow::MAX_SOURCE ) {
				$problems[] = self::problem( $line, self::SOURCE, self::SOURCE_LONG );
			}
			if ( '' !== $read && ! self::isDay( $read ) ) {
				$problems[] = self::problem( $line, self::READ, self::DATE_INVALID );
			}
			$kept[] = array(
				'size'    => $size,
				'specs'   => $values,
				'source'  => $from,
				'read_on' => $read,
			);
		}
		if ( count( $kept ) > self::MAX_ROWS ) {
			$problems[] = self::problem( 0, self::SIZE, self::TOO_MANY );
		}
		if ( array() === $problems ) {
			$this->tables->update( $id, trim( $name ), SpecTable::read( $kept ) );
		}
		return new TableDraft( $id, $problems );
	}

	/**
	 * Delete a chart for good. Parts that name it keep the name, and the Catalogue check lists them.
	 *
	 * @since 0.3.0
	 *
	 * @param int $id The chart's ID.
	 * @return bool Whether there was one to delete.
	 */
	public function delete( int $id ): bool {
		if ( ! isset( $this->tables->all()[ $id ] ) ) {
			return false;
		}
		$this->tables->delete( $id );
		return true;
	}

	/**
	 * Why a name cannot be a chart's, or null when it can.
	 *
	 * @param string $name The name, trimmed.
	 * @return array{row: int, field: string, code: string}|null
	 */
	private static function nameRefused( string $name ): ?array {
		if ( '' === $name ) {
			return self::problem( 0, self::NAME, self::NAME_MISSING );
		}
		return strlen( $name ) > self::MAX_NAME ? self::problem( 0, self::NAME, self::NAME_LONG ) : null;
	}

	/**
	 * Whether a text is a day of the calendar, written year-month-day.
	 *
	 * @param string $text The text.
	 */
	private static function isDay( string $text ): bool {
		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $text );
		return false !== $day && $day->format( 'Y-m-d' ) === $text;
	}

	/**
	 * A problem.
	 *
	 * @param int    $row   The row, from 1; 0 for the table.
	 * @param string $field The field.
	 * @param string $code  The code.
	 * @return array{row: int, field: string, code: string}
	 */
	private static function problem( int $row, string $field, string $code ): array {
		return array(
			'row'   => $row,
			'field' => $field,
			'code'  => $code,
		);
	}
}
