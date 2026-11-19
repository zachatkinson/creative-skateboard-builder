<?php
/**
 * A signed-in customer's deck width, from their saved builds.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The width a signed-in customer rides, for the deck step's question (spec: The deck's width first, "Remembered"):
 * the width of the deck in the build they saved last, where this kit offers that deck. Nothing is stored for it, on
 * the server or the device (D-242): it is read from the saved build each time.
 *
 * @since 1.0.0
 */
final class RememberedWidth {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SavedBuilds $saved The customers' saved builds.
	 * @param Kits        $kits  The kits and their parts.
	 */
	public function __construct( private SavedBuilds $saved, private Kits $kits ) {}

	/**
	 * The width, as a size is named (`8.25in`), or empty: for a visitor signed out, one with no saved build, or one
	 * whose last saved build holds no deck this kit offers.
	 *
	 * @since 1.0.0
	 *
	 * @param int $kit The kit product's ID.
	 */
	public function of( int $kit ): string {
		$last = $this->saved->of( get_current_user_id() )[0] ?? null;
		$deck = $last?->picks[ SlotKey::DECK ] ?? null;
		$slot = null === $deck ? null : $this->kits->kit( $kit )?->slots->get( SlotKey::DECK );
		if ( null === $deck || null === $slot ) {
			return '';
		}
		$width = ( $this->kits->picked( array( SlotKey::DECK => array( $slot, $deck ) ) )[ SlotKey::DECK ] ?? null )?->specs->measure( SpecKey::DeckWidth );
		return null === $width ? '' : Shelf::named( $width );
	}
}
