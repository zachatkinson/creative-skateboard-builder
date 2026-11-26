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
		// A unit test answers a filter by putting a callable under the hook's name.
		$answer = $GLOBALS['cbuild_test_filters'][ $hook ] ?? null;
		return is_callable( $answer ) ? $answer( $value, ...$args ) : $value;
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

// The translation and number functions Admin\Labels and Admin\Sentences reach for. A test sets the decimal point to
// read a number as another locale writes it.
if ( ! function_exists( '_x' ) ) {
	function _x( string $text, string $context, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return $GLOBALS['cbuild_test_user'] ?? 0;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, int $decimals = 0 ): string {
		return number_format( (float) $number, $decimals, $GLOBALS['cbuild_test_decimal'] ?? '.', ',' );
	}
}
if ( ! function_exists( 'wc_get_price_decimal_separator' ) ) {
	function wc_get_price_decimal_separator(): string {
		// The shop's own for prices, which a test may set apart from the language's.
		return $GLOBALS['cbuild_test_price_decimal'] ?? $GLOBALS['cbuild_test_decimal'] ?? '.';
	}
}

// What the builder's answer reaches for: a text with its markup taken out. A list is joined by Words::joined() in the
// plugin's own words, so no wp_sprintf() here (D-460).
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return trim( strip_tags( $text ) );
	}
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
	// WordPress's: percent-encoded octets out, then anything but A-Z, a-z, 0-9, _ and -.
	function sanitize_html_class( string $classname, string $fallback = '' ): string {
		$sanitized = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', $classname ) );
		return '' === $sanitized && '' !== $fallback ? sanitize_html_class( $fallback ) : $sanitized;
	}
}
if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url( string $redirect = '', bool $force_reauth = false ): string {
		return 'https://shop.test/wp-login.php' . ( '' === $redirect ? '' : '?redirect_to=' . rawurlencode( $redirect ) );
	}
}
