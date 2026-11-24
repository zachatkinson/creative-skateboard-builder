<?php
/**
 * How each part is drawn in a build's composed pictures, as another plugin answers.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\ComposedLayers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The answer of `cbuild_composed_layer`, which another plugin, such as one that flags mature artwork, gives for a
 * part's layer in each use's picture (Cowork's review of 1.0.0, F19; D-309).
 *
 * @since 1.1.0
 */
final class WpComposedLayers implements ComposedLayers {

	/**
	 * How a part is drawn in one use's picture, as the filter answers it.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $part The part's ID.
	 * @param string $slot The slot's key.
	 * @param int    $kit  The kit product's ID.
	 * @param string $purpose The picture's use.
	 */
	public function answer( int $part, string $slot, int $kit, string $purpose ): string {
		/**
		 * Filter how a part's layer is drawn in a build's composed picture: as it is, blurred so its artwork can't be
		 * made out and its shape stays, or left out. Each use is asked on its own, so a plugin can blur a part on the
		 * share card a link preview shows to anyone and leave the shopper's own cart and emails as they are. The answer
		 * is in the picture's name: a change draws a new picture, and the one drawn before is no longer shown.
		 *
		 * @since 1.1.0
		 *
		 * @param string $answer `as_is`, `blurred` or `left_out`: `as_is`.
		 * @param int    $part   The part's ID: a product's or a variation's.
		 * @param string $slot   The slot's key, such as deck.
		 * @param int    $kit    The kit product's ID.
		 * @param string $purpose  The picture's use: `cart`, `share` or `email`.
		 */
		$answer = apply_filters( 'cbuild_composed_layer', self::AS_IS, $part, $slot, $kit, $purpose );
		// Handed on as it is: `ComposedPictures::answer()` alone reads it, and draws any other answer as it is.
		return is_string( $answer ) ? $answer : self::AS_IS;
	}
}
