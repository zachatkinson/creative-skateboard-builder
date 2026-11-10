<?php
/**
 * Hookable contract.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A class that attaches its thin callbacks to WordPress hooks. Hooks call services and hold no logic (CLAUDE.md).
 */
interface Hookable {

	/**
	 * Attach hooks.
	 *
	 * @since 0.1.0
	 */
	public function register(): void;
}
