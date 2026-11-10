<?php
/**
 * What a rule says of a pair of parts.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use function max;

/**
 * Fits, warn (the build can go ahead, with a note) or block (it can't) (spec: Compatibility rules). The value is what
 * the state route and the templates carry.
 */
enum Severity: string {

	case Fits  = 'fits';
	case Warn  = 'warn';
	case Block = 'block';

	/**
	 * Each severity's rank. A table, not a match, so the branch floor counts no case that cannot happen (D-052).
	 */
	private const RANKS = array(
		'fits'  => 0,
		'warn'  => 1,
		'block' => 2,
	);

	/**
	 * The severities by rank.
	 */
	private const BY_RANK = array( self::Fits, self::Warn, self::Block );

	/**
	 * The worse of this and another.
	 *
	 * @since 0.2.0
	 *
	 * @param Severity $other The other severity. Named in full: PHPCompatibility reads `self` in an enum as outside a class.
	 */
	public function worst( Severity $other ): Severity {
		return self::BY_RANK[ max( self::RANKS[ $this->value ], self::RANKS[ $other->value ] ) ];
	}
}
