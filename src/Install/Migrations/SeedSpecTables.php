<?php
/**
 * The spec tables every shop starts with.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Independent's, Thunder's and Venture's size charts, from the table under R1 in the spec: each size's axle width
 * and the deck widths its maker gives, in inches. Each row says where it was read and on which day; a row with no
 * day is the spec's own figure, not read again since (D-072).
 *
 * @since 0.3.0
 */
final class SeedSpecTables {

	private const INDEPENDENT  = 'Independent, https://independenttrucks.com/trucks';
	private const TACTICS      = 'Tactics, https://www.tactics.com/thunder/hollow-lights-skateboard-trucks';
	private const WAREHOUSE    = 'Warehouse Skateboards, https://www.warehouseskateboards.com/thunder-skateboard-trucks';
	private const ROLLERSNAKES = 'Rollersnakes, https://us.rollersnakes.com/pages/venture-skateboard-trucks-size-guide-chart';

	/**
	 * Each seed's rows: the size, the axle, the narrowest deck, the widest deck, the source, the day read. '' where
	 * the maker gives none.
	 *
	 * @var array<string, array{name: string, rows: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>}>
	 */
	private const SEEDS = array(
		'independent' => array(
			'name' => 'Independent',
			'rows' => array(
				array( '109', '6.9000', '6.2500', '7.6000', self::INDEPENDENT, '2026-11-10' ),
				array( '129', '7.6000', '7.4000', '7.8000', self::INDEPENDENT, '2026-11-10' ),
				array( '139', '8.0000', '7.8000', '8.2000', self::INDEPENDENT, '2026-11-10' ),
				array( '144', '8.2500', '8.2000', '8.3750', self::INDEPENDENT, '2026-11-10' ),
				array( '146', '8.3750', '8.1250', '8.6250', self::INDEPENDENT, '2026-11-10' ),
				array( '149', '8.5000', '8.3750', '8.6000', self::INDEPENDENT, '2026-11-10' ),
				array( '151', '8.6250', '8.3750', '8.8750', self::INDEPENDENT, '2026-11-10' ),
				array( '159', '8.7500', '8.6000', '9.0000', self::INDEPENDENT, '2026-11-10' ),
				array( '166', '9.0000', '8.5000', '9.5000', self::INDEPENDENT, '2026-11-10' ),
				array( '169', '9.1250', '9.0000', '9.5000', self::INDEPENDENT, '2026-11-10' ),
				array( '215', '10.0000', '9.5000', '10.5000', self::INDEPENDENT, '2026-11-10' ),
			),
		),
		'thunder'     => array(
			'name' => 'Thunder',
			'rows' => array(
				array( '143', '7.0000', '', '', self::WAREHOUSE, '' ),
				array( '145', '7.6000', '7.3800', '8.0000', self::TACTICS, '2026-11-10' ),
				array( '147', '8.0000', '7.7500', '8.2500', self::TACTICS, '2026-11-10' ),
				array( '148', '8.2500', '8.0000', '8.5000', self::WAREHOUSE, '' ),
				array( '149', '8.5000', '8.2500', '8.7500', self::TACTICS, '2026-11-10' ),
				array( '151', '8.7500', '8.5000', '9.0000', self::TACTICS, '2026-11-10' ),
				array( '161', '9.1250', '', '', self::WAREHOUSE, '' ),
			),
		),
		'venture'     => array(
			'name' => 'Venture',
			'rows' => array(
				array( '5.0', '7.6200', '', '7.9000', self::ROLLERSNAKES, '2026-11-10' ),
				array( '5.2', '8.0000', '7.9000', '8.1000', self::ROLLERSNAKES, '2026-11-10' ),
				array( '5.6', '8.2500', '8.1000', '8.3000', self::ROLLERSNAKES, '2026-11-10' ),
				array( '5.8', '8.5000', '8.4000', '8.6000', self::ROLLERSNAKES, '2026-11-10' ),
				array( '6.1', '8.7500', '8.6200', '', self::ROLLERSNAKES, '2026-11-10' ),
			),
		),
	);

	/**
	 * The seeds, by name: the maker's name and the table.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, array{name: string, table: SpecTable}>
	 */
	public static function all(): array {
		$seeds = array();
		foreach ( self::SEEDS as $seed => $chart ) {
			$rows = array();
			foreach ( $chart['rows'] as [ $size, $axle, $min, $max, $source, $readOn ] ) {
				$rows[] = array(
					'size'    => $size,
					'specs'   => array(
						'axle_width' => $axle,
						'deck_min'   => $min,
						'deck_max'   => $max,
					),
					'source'  => $source,
					'read_on' => $readOn,
				);
			}
			$seeds[ $seed ] = array(
				'name'  => $chart['name'],
				'table' => SpecTable::read( $rows ),
			);
		}
		return $seeds;
	}
}
