<?php
/**
 * One pick that no longer fits.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;

/**
 * A later pick an earlier change left not fitting: the part it was, why it no longer fits, and the closest part that
 * does, or null when nothing in stock fits and the slot would be left to pick again.
 */
final readonly class Change {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param string        $slot     The slot's key.
	 * @param Part          $was      The part picked.
	 * @param list<Finding> $findings Why it no longer fits: its blocking findings.
	 * @param Option|null   $closest  The closest part that fits and is in stock, as the slot would offer it.
	 */
	public function __construct( public string $slot, public Part $was, public array $findings, public ?Option $closest ) {}
}
