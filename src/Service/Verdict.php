<?php
/**
 * What the cart says of a build offered to it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A build offered to the cart is taken, or refused with a code and, where the build itself is at fault, each
 * thing wrong with it.
 *
 * @since 0.5.0
 */
final readonly class Verdict {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Build|null         $build    The build as the cart will carry it, when it is taken.
	 * @param CartRefusal|null   $refusal  Why it is not, when it is refused.
	 * @param list<BuildProblem> $problems What is wrong with the build.
	 */
	private function __construct( public ?Build $build, public ?CartRefusal $refusal, public array $problems ) {}

	/**
	 * A build that is taken.
	 *
	 * @since 0.5.0
	 *
	 * @param Build $build The build.
	 */
	public static function taken( Build $build ): self {
		return new self( $build, null, array() );
	}

	/**
	 * A build that is refused.
	 *
	 * @since 0.5.0
	 *
	 * @param CartRefusal        $refusal  Why.
	 * @param list<BuildProblem> $problems What is wrong with the build.
	 */
	public static function refused( CartRefusal $refusal, array $problems = array() ): self {
		return new self( null, $refusal, $problems );
	}
}
