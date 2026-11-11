<?php
/**
 * What looking a cart over found.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What `BuildLines::tidy()` did and found: how many broken builds it took out, and each whole build the shop
 * would no longer sell as it is.
 *
 * @since 0.5.0
 */
final readonly class Tidied {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param int                    $dropped How many builds were removed for having lost a line.
	 * @param array<string, Verdict> $refused Why each build still in the cart cannot be bought, by its kit's
	 *                                        line's cart key.
	 */
	public function __construct( public int $dropped, public array $refused ) {}
}
