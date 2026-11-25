<?php
/**
 * What the builder shows, as the kit's switches and a plugin's answer say.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\KitSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Build tab's switches for what the builder shows (1.3.0, D-409): "Help me choose" at its start, and on the cards
 * the ready builds that pick a part and the part's rating. Each starts as the kit's setting, on for a kit that has
 * none, and a plugin's answer to its filter comes last, as `cbuild_width_guide` and `cbuild_start_button` do for the
 * 1.2.0 settings. Anything but `true` is off. A switch turns its feature off or on and never changes what it shows.
 *
 * @since 1.3.0
 */
final class Shown {

	/**
	 * Whether the builder offers "Help me choose" at its start.
	 *
	 * @since 1.3.0
	 *
	 * @param int         $kit      The kit product's ID.
	 * @param KitSettings $settings The kit's settings.
	 */
	public static function guide( int $kit, KitSettings $settings ): bool {
		/**
		 * Filters whether the builder offers "Help me choose", the guided start's questions, at its start. False: the
		 * page draws no disclosure, form or builds, and answers in a link recommend nothing.
		 *
		 * @since 1.3.0
		 *
		 * @param bool $offered The kit's Help me choose (`guide` in `_cbuild_settings`): true where the kit has none.
		 * @param int  $kit     The kit product's ID.
		 */
		return true === apply_filters( 'cbuild_help_me_choose', $settings->guided, $kit );
	}

	/**
	 * Whether a card says the ready builds that pick its part, "Picked for Street" (#137).
	 *
	 * @since 1.3.0
	 *
	 * @param int         $kit      The kit product's ID.
	 * @param KitSettings $settings The kit's settings.
	 */
	public static function picks( int $kit, KitSettings $settings ): bool {
		/**
		 * Filters whether a card in the builder says which ready builds pick its part. False: no card says it.
		 *
		 * @since 1.3.0
		 *
		 * @param bool $shown The kit's Ready-build picks on cards (`picks` in `_cbuild_settings`): true where the kit
		 *                    has none.
		 * @param int  $kit   The kit product's ID.
		 */
		return true === apply_filters( 'cbuild_card_picks', $settings->cardPicks, $kit );
	}

	/**
	 * Whether a card shows its part's rating, a star and its score (#137). Never while WooCommerce's star ratings are
	 * off, which the card asks itself, as before 1.3.0.
	 *
	 * @since 1.3.0
	 *
	 * @param int         $kit      The kit product's ID.
	 * @param KitSettings $settings The kit's settings.
	 */
	public static function ratings( int $kit, KitSettings $settings ): bool {
		/**
		 * Filters whether a card in the builder shows its part's rating. False: no card shows a star or a score. True
		 * still shows none while WooCommerce's star ratings are off.
		 *
		 * @since 1.3.0
		 *
		 * @param bool $shown The kit's Ratings on cards (`ratings` in `_cbuild_settings`): true where the kit has none.
		 * @param int  $kit   The kit product's ID.
		 */
		return true === apply_filters( 'cbuild_card_ratings', $settings->cardRatings, $kit );
	}
}
