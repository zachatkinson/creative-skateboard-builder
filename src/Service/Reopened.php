<?php
/**
 * A saved build as it reopens.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What changed in a saved build since it was saved, for "Your build has changed" (spec: A saved build that has
 * changed, D-254): the picks that changed and their closest parts, the build with those in their place, and each part
 * kept whose price changed, then and now, in the currency it was saved in.
 *
 * @since 1.0.0
 */
final readonly class Reopened {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SavedBuild                                                $build  The build as saved.
	 * @param SavedCheck                                                $check  What changed among its picks.
	 * @param State                                                     $state  The build with the closest parts in their place, at the review.
	 * @param list<array{slot: string, part: int, then: int, now: int}> $prices Each part kept whose line costs another amount now.
	 * @param int|null                                                  $then   The total when saved, or null where it was not kept in this currency.
	 */
	public function __construct( public SavedBuild $build, public SavedCheck $check, public State $state, public array $prices, public ?int $then ) {}
}
