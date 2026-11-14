<?php
/**
 * Where a kit's builder is.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The address of the page a kit's builder is on, with no build in it (D-008): the kit's own page, unless the shop
 * answers `cbuild_builder_url` with the page its Builder block is on. The kit's page sends a visitor there, and a
 * deck's "Make it a complete" link and the cart's Edit build start from it.
 *
 * An answer must be an address on this site. Any other is no answer, so the filter can never send a shopper, or the
 * build in their address, to another host.
 *
 * @since 0.6.0
 */
final class BuilderUrl {

	/**
	 * The builder's address for a kit.
	 *
	 * @since 0.6.0
	 *
	 * @param int $kit The kit product's ID.
	 */
	public static function of( int $kit ): string {
		$own = (string) get_permalink( $kit );
		/**
		 * Filter where a kit's builder is: the address of the page its Builder block is on, with no build in it.
		 * An address on another host, or an answer that is no address, is ignored.
		 *
		 * @since 0.6.0
		 *
		 * @param string $url The kit's own page.
		 * @param int    $kit The kit product's ID.
		 */
		$answer = apply_filters( 'cbuild_builder_url', $own, $kit );
		if ( ! is_string( $answer ) || '' === $answer ) {
			return $own;
		}
		return wp_validate_redirect( $answer, $own );
	}
}
