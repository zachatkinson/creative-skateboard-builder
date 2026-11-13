<?php
/**
 * Slot keys a build link keeps for itself.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit saved by 0.4.0 or 0.5.0 may key a slot `step`, `assembly`, `preset` or `review`, which 0.6.0's build link
 * uses for itself and so refuses (D-142). Each such key is renamed `<key>_slot`, counted on (`_2`, `_3`) where that
 * is taken, in the slots and in each preset's picks. The label the shopper reads is kept (D-169).
 *
 * @since 0.6.0
 */
final class ReservedSlotKeys {

	/**
	 * A kit's slots and presets with each reserved key renamed, and what became what; null where none is.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string, mixed>|null $slots   The slots document as stored, decoded.
	 * @param array<string, mixed>|null $presets The presets document as stored, decoded.
	 * @return array{slots: array<string, mixed>, presets: array<string, mixed>|null, renamed: array<string, string>}|null
	 */
	public static function renamed( ?array $slots, ?array $presets ): ?array {
		$list = $slots['slots'] ?? null;
		if ( ! is_array( $list ) ) {
			return null;
		}
		$taken = array();
		foreach ( $list as $slot ) {
			if ( is_array( $slot ) && is_string( $slot['key'] ?? null ) ) {
				$taken[] = $slot['key'];
			}
		}
		$renamed = array();
		foreach ( $list as $at => $slot ) {
			$key = is_array( $slot ) ? ( $slot['key'] ?? null ) : null;
			if ( ! is_array( $slot ) || ! is_string( $key ) || ! isset( Slots::KEPT[ $key ] ) ) {
				continue;
			}
			$new   = $key . '_slot';
			$count = 2;
			while ( in_array( $new, $taken, true ) ) {
				$new = $key . '_slot_' . $count;
				++$count;
			}
			$taken[]         = $new;
			$renamed[ $key ] = $new;
			$slot['key']     = $new;
			$list[ $at ]     = $slot;
		}
		if ( array() === $renamed ) {
			return null;
		}
		$slots['slots'] = $list;
		$kept           = $presets['presets'] ?? null;
		if ( is_array( $kept ) ) {
			foreach ( $kept as $at => $preset ) {
				$was = is_array( $preset ) ? ( $preset['picks'] ?? null ) : null;
				if ( ! is_array( $preset ) || ! is_array( $was ) ) {
					continue;
				}
				$picks = array();
				foreach ( $was as $key => $part ) {
					$picks[ $renamed[ $key ] ?? $key ] = $part;
				}
				$preset['picks'] = $picks;
				$kept[ $at ]     = $preset;
			}
			$presets['presets'] = $kept;
		}
		return array(
			'slots'   => $slots,
			'presets' => $presets,
			'renamed' => $renamed,
		);
	}
}
