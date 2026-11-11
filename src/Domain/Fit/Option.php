<?php
/**
 * One part as an option in a slot.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;

/**
 * A candidate part with what the rules found about it against the picks before its slot, and how many of it a build
 * takes. Its severity is the worst of its findings: fits and warn are offered, block is hidden with its reason.
 */
final readonly class Option {

	/**
	 * How it fits.
	 *
	 * @since 0.2.0
	 *
	 * @var Severity
	 */
	public Severity $severity;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param Part          $part     The part.
	 * @param list<Finding> $findings What the rules found that reads its slot, notes included.
	 * @param int|null      $quantity How many of it cover the slot (R7), or null when it gives no set size.
	 */
	public function __construct( public Part $part, public array $findings, public ?int $quantity ) {
		$this->severity = Finding::worst( $findings );
	}

	/**
	 * Why it is hidden: its first blocking finding, or null when it is offered.
	 *
	 * @since 0.2.0
	 */
	public function reason(): ?Finding {
		return Finding::blocking( $this->findings )[0] ?? null;
	}

	/**
	 * Whether the shop holds what a build takes of it: its quantity, or one where it gives no set size.
	 *
	 * @since 0.4.0
	 */
	public function available(): bool {
		return $this->part->covers( $this->quantity ?? 1 );
	}
}
