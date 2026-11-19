<?php
/**
 * How search folds text.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text as the builder's own search compares it: in lower case, its accents taken off (D-248, D-262).
 *
 * @since 1.0.0
 */
interface TextFolding {

	/**
	 * The text folded.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 */
	public function fold( string $text ): string;
}
