<?php
/**
 * A PHP without mbstring, for the words the builder writes and the names the charts are told apart by (#72).
 *
 * Front\Words asks function_exists() for mb_strtoupper() and mb_strtolower() before it changes a letter's case, and
 * Service\SpecTableEditor for mb_strtolower() before it folds a chart's name; these functions answer in those
 * namespaces first, so a test can take mbstring away and read the fallback. Each answers as PHP does unless
 * $GLOBALS['cbuild_test_no_mbstring'] is true. Loaded by the bootstrap, before any call resolves to PHP's own function
 * and keeps it.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile

namespace CreativeBranding\SkateboardBuilder\Front;

/**
 * Whether a function exists, with mbstring's case changes gone while a test asks.
 *
 * @param string $name The function's name.
 */
function function_exists( string $name ): bool {
	if ( true === ( $GLOBALS['cbuild_test_no_mbstring'] ?? false ) && str_starts_with( $name, 'mb_strto' ) ) {
		return false;
	}
	return \function_exists( $name );
}

namespace CreativeBranding\SkateboardBuilder\Service;

/**
 * Whether a function exists, with mbstring's case changes gone while a test asks.
 *
 * @param string $name The function's name.
 */
function function_exists( string $name ): bool {
	return \CreativeBranding\SkateboardBuilder\Front\function_exists( $name );
}
