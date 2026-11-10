<?php
/**
 * Whole builds for the domain's tests.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Domain;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;

/**
 * Parts with every spec the rules and the engine need, and a complete that fits, for the tests that change one thing.
 */
final class Builds {

	/**
	 * A deck with every spec the rules and the engine need.
	 */
	public static function deck( string $width = '8.5', int $id = 100 ): Part {
		return Parts::of( array( 'deck_width' => $width, 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), $id );
	}

	/**
	 * A truck with every spec the rules and the engine need.
	 */
	public static function truck( string $size ): Part {
		return Parts::indy( $size, array( 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 2 ) );
	}

	/**
	 * A wheel with every spec the rules and the engine need.
	 */
	public static function wheel( string $diameter, int $id = 300 ): Part {
		return Parts::of( array( 'wheel_diameter' => $diameter, 'set_size' => 4 ), $id );
	}

	/**
	 * A riser with every spec the rules and the engine need.
	 */
	public static function riser( string $thickness, int $id = 400 ): Part {
		return Parts::of( array( 'riser_thickness' => $thickness, 'set_size' => 2 ), $id );
	}

	/**
	 * Bearings with every spec the rules and the engine need.
	 */
	public static function bearings( string $bore = '8', int $id = 500 ): Part {
		return Parts::of( array( 'bearing_bore' => $bore, 'set_size' => 8 ), $id );
	}

	/**
	 * Bolts with every spec the rules and the engine need.
	 */
	public static function bolts( string $length = '1', int $id = 600 ): Part {
		return Parts::of( array( 'bolt_length' => $length, 'set_size' => 8 ), $id );
	}

	/**
	 * A sheet of grip with every spec the rules and the engine need.
	 */
	public static function grip( string $width = '9', int $id = 700 ): Part {
		return Parts::of( array( 'grip_width' => $width, 'grip_length' => '33', 'set_size' => 1 ), $id );
	}

	/**
	 * A complete that fits.
	 *
	 * @param array<string, Part> $with Parts to add or replace.
	 */
	public static function complete( array $with = array() ): Picks {
		return Parts::picks(
			$with + array(
				SlotKey::DECK     => self::deck(),
				SlotKey::TRUCKS   => self::truck( '149' ),
				SlotKey::WHEELS   => self::wheel( '54' ),
				SlotKey::BEARINGS => self::bearings(),
				SlotKey::HARDWARE => self::bolts(),
				SlotKey::GRIP     => self::grip(),
			)
		);
	}
}
