<?php
/**
 * The columns of the specs sheet.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per product or variation. The headings are the same in every language, as the spec keys are, so a sheet
 * exported from one shop reads in another: `id`, `sku`, `name` (for the reader; an import passes over it),
 * `spec_table` (a chart's name), `spec_size`, then a column per spec key.
 *
 * @since 0.3.0
 */
final class SpecSheet {

	/**
	 * The columns that are not spec keys.
	 */
	public const ID    = 'id';
	public const SKU   = 'sku';
	public const NAME  = 'name';
	public const TABLE = 'spec_table';
	public const SIZE  = 'spec_size';

	/**
	 * Every column, in the order an export writes them.
	 *
	 * @since 0.3.0
	 *
	 * @return list<string>
	 */
	public static function columns(): array {
		return array( self::ID, self::SKU, self::NAME, self::TABLE, self::SIZE, ...self::specs() );
	}

	/**
	 * The spec keys.
	 *
	 * @since 0.3.0
	 *
	 * @return list<string>
	 */
	public static function specs(): array {
		return array_map( static fn( SpecKey $key ): string => $key->value, SpecKey::cases() );
	}

	/**
	 * Where each known column sits in a heading row, and the known ones that are there twice. A heading is matched
	 * whatever its case; one this version does not know is passed over.
	 *
	 * @since 0.3.0
	 *
	 * @param list<string> $header The heading row.
	 * @return array{columns: array<string, int>, twice: list<string>}
	 */
	public static function map( array $header ): array {
		$columns = array();
		$twice   = array();
		foreach ( $header as $at => $heading ) {
			$name = strtolower( $heading );
			if ( ! in_array( $name, self::columns(), true ) ) {
				continue;
			}
			if ( isset( $columns[ $name ] ) ) {
				$twice[] = $name;
				continue;
			}
			$columns[ $name ] = $at;
		}
		return array(
			'columns' => $columns,
			'twice'   => array_values( array_unique( $twice ) ),
		);
	}
}
