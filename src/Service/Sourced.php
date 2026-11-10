<?php
/**
 * What a part's sources say, resolved.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Resolution;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One part after every source was asked: the winner of each spec with the disagreements, and what a source holds
 * that could not be read. The index takes the first; the Catalogue check lists the rest.
 *
 * @since 0.3.0
 */
final readonly class Sourced {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Resolution   $resolution The winner of each spec, and the disagreements.
	 * @param list<Unread> $unread     What a source holds and could not be read.
	 */
	public function __construct( public Resolution $resolution, public array $unread ) {}
}
