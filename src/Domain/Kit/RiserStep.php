<?php
/**
 * One row of the riser table.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

/**
 * A wheel up to so far over the truck's limit takes a riser so thick (R3): up to 2 mm over, ¼ in.
 */
final readonly class RiserStep {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param Measure $over  How far over the limit this row reaches, in millimetres.
	 * @param Measure $riser The riser it suggests, in inches.
	 */
	public function __construct( public Measure $over, public Measure $riser ) {}
}
