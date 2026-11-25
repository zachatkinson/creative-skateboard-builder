<?php
/**
 * A swap a build is offered.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;

/**
 * One press that swaps parts into a build (#135): what it is for, the part each slot had and the option it takes in
 * its place, and the whole build after it as the validator found it, which is what gets priced. Only the swapper
 * makes one, after the engine has checked the build it makes.
 */
final readonly class Swap {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SwapKind              $kind  What it is for.
	 * @param array<string, Part>   $was   The part each slot it changes has now, by slot key, in step order.
	 * @param array<string, Option> $to    The option each of those slots takes, by slot key, in step order.
	 * @param Validation            $after The build with the swap made.
	 */
	public function __construct( public SwapKind $kind, public array $was, public array $to, public Validation $after ) {}
}
