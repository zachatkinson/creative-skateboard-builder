<?php
/**
 * The mounting patterns.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * What a deck is drilled for and what a baseplate takes (spec: R2). The values are what the specs `hole_pattern` and
 * `baseplate` hold, so they are public API and never renamed in place.
 */
enum HolePattern: string {

	case NewSchool = 'new_school';
	case OldSchool = 'old_school';
	case Both      = 'both';

	/**
	 * The pattern a deck is taken to have when it gives none.
	 */
	public const USUAL = self::NewSchool;
}
