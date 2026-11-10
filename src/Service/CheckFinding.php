<?php
/**
 * One thing the Catalogue check found.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A code and what it is about, never words: the screen and the command line add those, in the reader's language.
 *
 * @since 0.3.0
 */
final readonly class CheckFinding {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string                $code   One of `CatalogueCheck`'s codes.
	 * @param int                   $part   The product's or variation's ID, 0 when it is not about a part.
	 * @param int                   $kit    The kit product's ID, 0 when it is not about a kit.
	 * @param string                $slot   The slot's key, '' when it is not about a slot.
	 * @param string                $spec   The spec key, '' when it is not about a spec.
	 * @param array<string, string> $values What else the words need, by name.
	 */
	public function __construct(
		public string $code,
		public int $part = 0,
		public int $kit = 0,
		public string $slot = '',
		public string $spec = '',
		public array $values = array()
	) {}
}
