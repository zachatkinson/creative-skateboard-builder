<?php
/**
 * Minimal WordPress function shims for the unit suite (no WordPress loaded).
 *
 * Only what the classes under test reach for. src/Domain must need none of these: a shim added for a Domain class is
 * a WordPress import in the domain layer, which the spec forbids. Anything needing real WordPress belongs in
 * tests/Integration/.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $html ): string {
		return $html;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) {
		return $value;
	}
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// A network's sites, for Install\Sites: the unit test sets the ids and reads the walk back.
if ( ! function_exists( 'get_sites' ) ) {
	function get_sites( array $args = array() ): array {
		$GLOBALS['cbuild_test_walk'][] = 'get_sites:' . ( $args['fields'] ?? '' ) . ':' . ( $args['number'] ?? '' );
		return $GLOBALS['cbuild_test_sites'] ?? array();
	}
}
if ( ! function_exists( 'switch_to_blog' ) ) {
	function switch_to_blog( int $site ): bool {
		$GLOBALS['cbuild_test_walk'][] = 'switch:' . $site;
		return true;
	}
}
if ( ! function_exists( 'restore_current_blog' ) ) {
	function restore_current_blog(): bool {
		$GLOBALS['cbuild_test_walk'][] = 'restore';
		return true;
	}
}
