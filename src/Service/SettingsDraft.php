<?php
/**
 * What the settings screen was sent, checked.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The mapping and the order a person submitted, with everything wrong with them. Each problem is a code and the
 * line of the mapping it is on, counted from 1 as the screen shows them; 0 is the form as a whole.
 *
 * @since 0.3.0
 */
final readonly class SettingsDraft {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param list<AttributeMap>                   $mapping  The lines that read.
	 * @param SourceOrder|null                     $order    The order, null when it is not one.
	 * @param list<array{code: string, line: int}> $problems What is wrong.
	 */
	public function __construct( public array $mapping, public ?SourceOrder $order, public array $problems ) {}

	/**
	 * Whether it was saved: nothing was wrong with it.
	 *
	 * @since 0.3.0
	 */
	public function saved(): bool {
		return array() === $this->problems;
	}
}
