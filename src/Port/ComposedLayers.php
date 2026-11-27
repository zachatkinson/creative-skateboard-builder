<?php
/**
 * How each part is drawn in a build's composed pictures.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Another plugin's answer for a part's layer in one of a build's composed pictures, the cart's, the share card's or the
 * emails': drawn as it is, blurred, or left out (`cbuild_composed_layer`, D-309).
 *
 * @since 1.1.0
 */
interface ComposedLayers {

	/**
	 * Drawn as it is.
	 *
	 * @since 1.1.0
	 */
	public const string AS_IS = 'as_is';

	/**
	 * Drawn blurred, so its artwork can't be made out and its shape stays.
	 *
	 * @since 1.1.0
	 */
	public const string BLURRED = 'blurred';

	/**
	 * Not drawn.
	 *
	 * @since 1.1.0
	 */
	public const string LEFT_OUT = 'left_out';

	/**
	 * How a part is drawn in one use's picture: AS_IS, BLURRED or LEFT_OUT. Any other answer is handed on as it is,
	 * and `ComposedPictures` draws the part as it is.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $part The part's ID: a product's or a variation's.
	 * @param string $slot The slot's key.
	 * @param int    $kit  The kit product's ID.
	 * @param string $purpose The picture's use: cart, share or email, or from 1.4.0 3d, the 3D view's textures.
	 */
	public function answer( int $part, string $slot, int $kit, string $purpose ): string;
}
