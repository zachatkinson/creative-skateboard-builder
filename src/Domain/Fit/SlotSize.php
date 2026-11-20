<?php
/**
 * The spec a slot's parts are sized by.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

/**
 * The spec a shopper picks each slot's parts by: the size a look comes in, and the one "nearest" is measured on.
 */
final class SlotSize {

	/**
	 * The size spec by slot.
	 */
	private const array SPECS = array(
		SlotKey::DECK     => SpecKey::DeckWidth,
		SlotKey::TRUCKS   => SpecKey::AxleWidth,
		SlotKey::WHEELS   => SpecKey::WheelDiameter,
		SlotKey::RISERS   => SpecKey::RiserThickness,
		SlotKey::BEARINGS => SpecKey::BearingBore,
		SlotKey::HARDWARE => SpecKey::BoltLength,
		SlotKey::GRIP     => SpecKey::GripWidth,
	);

	/**
	 * A slot's size spec, or null for a slot the rules don't know.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot The slot's key.
	 */
	public static function spec( string $slot ): ?SpecKey {
		return self::SPECS[ $slot ] ?? null;
	}
}
