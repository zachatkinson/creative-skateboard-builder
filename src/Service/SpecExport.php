<?php
/**
 * Writes the specs sheet.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Port\PartLayers;
use CreativeBranding\SkateboardBuilder\Port\PartSpecs;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every product and variation with what it holds itself, a page at a time, so a shop of any size is written without
 * being held whole. What a part takes from a chart or an attribute is not written: importing the sheet back must
 * change nothing. Each cell goes through `Csv::line()`, which keeps a spreadsheet from running it.
 *
 * @since 0.3.0
 */
final class SpecExport {

	/**
	 * Products in one page.
	 */
	public const PAGE = 100;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param PartSpecs  $parts  What each product holds.
	 * @param SpecTables $tables The charts, for their names.
	 */
	public function __construct( private PartSpecs $parts, private SpecTables $tables ) {}

	/**
	 * The parts' own layer images, for the `layer_image` column; without them it is written empty, as a 0.9.0
	 * container builds this class (D-208).
	 *
	 * @var PartLayers|null
	 */
	private ?PartLayers $layers = null;

	/**
	 * A copy with each part's own Layer image written by its file's name (Cowork's 1.0.0 request; D-287).
	 *
	 * @since 1.0.0
	 *
	 * @param PartLayers $layers The parts' own layer images.
	 */
	public function withLayers( PartLayers $layers ): self {
		$copy         = clone $this;
		$copy->layers = $layers;
		return $copy;
	}

	/**
	 * The heading row.
	 *
	 * @since 0.3.0
	 */
	public function header(): string {
		return Csv::line( SpecSheet::columns() );
	}

	/**
	 * One page of rows.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page The page, from 1.
	 * @return string|null The rows, or null when the page before was the last.
	 */
	public function page( int $page ): ?string {
		$ids = $this->parts->page( $page, self::PAGE );
		if ( array() === $ids ) {
			return null;
		}
		$charts = $this->tables->all();
		$held   = $this->parts->read( ...$ids );
		$owned  = $this->layers?->owned( ...$ids ) ?? array();
		$text   = '';
		foreach ( $ids as $id ) {
			$part = $held[ $id ] ?? null;
			if ( null === $part ) {
				continue;
			}
			$own   = $owned[ $id ]['own'] ?? 0;
			$cells = array( (string) $id, $part['sku'], $part['name'], $charts[ $part['table'] ]['name'] ?? '', $part['size'], $own > 0 ? (string) $this->layers?->fileName( $own ) : '' );
			foreach ( SpecSheet::specs() as $key ) {
				$cells[] = $part['fields'][ $key ] ?? '';
			}
			$text .= Csv::line( $cells );
		}
		return $text;
	}

	/**
	 * The whole sheet.
	 *
	 * @since 0.3.0
	 */
	public function text(): string {
		$text = $this->header();
		$page = 1;
		$rows = $this->page( $page );
		while ( null !== $rows ) {
			$text .= $rows;
			$rows  = $this->page( ++$page );
		}
		return $text;
	}
}
