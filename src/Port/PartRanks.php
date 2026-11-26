<?php
/**
 * The shop's ranks of its parts.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How the shop ranks a part in its slot, for smart swaps' "better" (#135, D-395): a whole number, higher being better,
 * from the part's Part specs or another plugin's answer to `cbuild_part_rank`.
 *
 * @since 1.3.0
 */
interface PartRanks {

	/**
	 * The rank of each of these parts that has one, by ID. A part with none is left out.
	 *
	 * @since 1.3.0
	 *
	 * @param int ...$parts Products' and variations' IDs.
	 * @return array<int, int>
	 */
	public function ranks( int ...$parts ): array;

	/**
	 * The rank of each of these parts that has one, by ID, as `ranks()` gives it, from the rank each part was read with
	 * (`Part::$rank`): no product is loaded for it (D-434). A part with none is left out.
	 *
	 * @since 1.3.0
	 *
	 * @param Part ...$parts The parts, as the shop's kits read them.
	 * @return array<int, int>
	 */
	public function of( Part ...$parts ): array;
}
