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
 * The address of the page a kit's builder is on, with no build in it (D-008): the kit's own page, or from 1.2.0 the
 * Builder page its Build tab chose (`BuilderAddress`, D-348), unless the shop answers `cbuild_builder_url` with the
 * page its Builder block is on. `of()` is the kit's own page before the filter, as a copy an earlier release's
 * container built asks it. The kit's page sends a visitor there, and a deck's "Build a complete with this deck" and
 * the cart's Edit build start from it.
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
		return self::from( $kit, (string) get_permalink( $kit ) );
	}

	/**
	 * The builder's address for a kit, from where its settings put it: the shop's filter answers last (1.2.0, #113).
	 *
	 * @since 1.2.0
	 *
	 * @param int    $kit   The kit product's ID.
	 * @param string $start Where the kit's settings put its builder: its Builder page, or its own page.
	 */
	public static function from( int $kit, string $start ): string {
		/**
		 * Filter where a kit's builder is: the address of the page its Builder block is on, with no build in it.
		 * An address on another host, or an answer that is no address, is ignored, and the starting value kept.
		 *
		 * @since 0.6.0
		 * @since 1.2.0 `$url` is the Builder page the kit's Build tab chose, where that is a published page holding
		 *              the kit's Builder block, and the kit's own page otherwise.
		 *
		 * @param string $url Where the kit's settings put its builder.
		 * @param int    $kit The kit product's ID.
		 */
		$answer = apply_filters( 'cbuild_builder_url', $start, $kit );
		if ( ! is_string( $answer ) || '' === $answer ) {
			return $start;
		}
		return wp_validate_redirect( $answer, $start );
	}
}
