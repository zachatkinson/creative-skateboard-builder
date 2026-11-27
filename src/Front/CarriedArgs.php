<?php
/**
 * The arguments a kit's page carries to the Builder page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The request's other arguments, carried to the Builder page as WordPress's own redirects keep them: a newsletter's
 * `utm_*` tags and an ad's `gclid` or `fbclid` (F38, #161). Not the build, which is the builder's own, nor the query
 * variables that found the kit's page. Each is plain text, encoded for the address; a value that is no text, or a name
 * that is no tag's, is left. Moved out of `PageHooks` so the hook reads the request and holds no rule (M14's audit, F10).
 *
 * @since 1.4.0
 */
final class CarriedArgs {

	/**
	 * The arguments to carry.
	 *
	 * @since 1.4.0
	 *
	 * @param array<mixed> $sent    The request's query arguments, unslashed.
	 * @param mixed        $request WordPress's request (`WP`), whose `query_vars` found the page; anything else has none.
	 * @return array<string, string>
	 */
	public static function of( array $sent, mixed $request ): array {
		$vars = is_object( $request ) && property_exists( $request, 'query_vars' ) ? $request->query_vars : null;
		$own  = is_array( $vars ) ? array_keys( $vars ) : array();
		$args = array();
		foreach ( $sent as $key => $value ) {
			// A name as tags and click IDs are written; any other is left, as is a value that is no text.
			if ( ! is_string( $key ) || 1 !== preg_match( '/\A[A-Za-z0-9_.-]{1,64}\z/', $key ) || ! is_string( $value ) || BuildLink::PARAM === $key || in_array( $key, $own, true ) ) {
				continue;
			}
			$args[ $key ] = rawurlencode( sanitize_text_field( $value ) );
		}
		return $args;
	}
}
