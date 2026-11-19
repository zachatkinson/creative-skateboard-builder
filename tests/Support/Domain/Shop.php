<?php
/**
 * A small shop and its kit.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Domain;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;

/**
 * The kit and the parts the state's tests build with: decks 101 to 103 (8.0, 8.25, 8.5), trucks 139, 144, 149 and
 * 169, wheels 301 to 303 (52, 54, 58), risers 401 and 402, bearings 501, bolts 601 and grip 701, each with a price.
 */
final class Shop {

	/**
	 * The usual kit, with a fee of 15.00 for assembly, off by default.
	 *
	 * @param array<string, int>                     $defaults The default part by slot key.
	 * @param bool                                   $on       Whether the shop assembles by default.
	 * @param list<array<string, mixed>>             $presets  Its presets: name, description and picks.
	 */
	public static function kit( array $defaults = array(), bool $on = false, array $presets = array() ): Kit {
		$slots = KitDocuments::slots();
		foreach ( $slots['slots'] as $at => $slot ) {
			if ( isset( $defaults[ $slot['key'] ] ) ) {
				$slots['slots'][ $at ]['default'] = $defaults[ $slot['key'] ];
			}
		}
		return KitDocuments::kit(
			$slots,
			null,
			array(
				'schema_version' => 1,
				'presets'        => $presets,
			),
			array(
				'schema_version' => 1,
				'default_on'     => $on,
				'fee'            => '15',
				'lead_time'      => '',
				'returns_notice' => '',
			)
		);
	}

	/**
	 * A part of the usual kind with a price of its own.
	 */
	public static function priced( Part $part, int $price, bool $inStock = true, ?int $stock = null ): Part {
		return new Part( $part->id, $part->brand, $part->specs, $price, $inStock, $stock );
	}

	/**
	 * A small shop: three decks, four trucks, three wheels, two risers, and one each of the rest.
	 *
	 * @return array<string, list<Part>>
	 */
	public static function parts(): array {
		return array(
			SlotKey::DECK     => array( self::priced( Builds::deck( '8.0', 101 ), 6000 ), self::priced( Builds::deck( '8.25', 102 ), 6500 ), self::priced( Builds::deck( '8.5', 103 ), 7000 ) ),
			SlotKey::TRUCKS   => array( self::priced( Builds::truck( '139' ), 5000 ), self::priced( Builds::truck( '144' ), 5200 ), self::priced( Builds::truck( '149' ), 5400 ), self::priced( Builds::truck( '169' ), 5600 ) ),
			SlotKey::WHEELS   => array( self::priced( Builds::wheel( '52', 301 ), 3000 ), self::priced( Builds::wheel( '54', 302 ), 3200 ), self::priced( Builds::wheel( '58', 303 ), 3400 ) ),
			SlotKey::RISERS   => array( self::priced( Builds::riser( '0.125', 401 ), 500 ), self::priced( Builds::riser( '0.25', 402 ), 600 ) ),
			SlotKey::BEARINGS => array( self::priced( Builds::bearings( '8', 501 ), 1500 ) ),
			SlotKey::HARDWARE => array( self::priced( Builds::bolts( '1', 601 ), 400 ), self::priced( Builds::bolts( '1.25', 602 ), 450 ) ),
			SlotKey::GRIP     => array( self::priced( Builds::grip( '9', 701 ), 800 ) ),
		);
	}
}
