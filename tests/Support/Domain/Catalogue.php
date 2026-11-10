<?php
/**
 * A generated catalogue for the domain's invariant and benchmark.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Domain;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Parts for every slot of the usual kit, made from a seed, so a run that fails can be made again. It is a shop's
 * catalogue with its faults left in: parts that fit, parts that warn, parts that are blocked, parts missing a spec a
 * rule needs, parts with no set size, and parts that are sold out.
 */
final class Catalogue {

	private const array WIDTHS    = array( '7.5', '7.75', '8.0', '8.125', '8.25', '8.375', '8.5', '8.75', '9.0', '9.5', '10.0' );
	private const array LENGTHS   = array( '31', '31.5', '32', '32.5', '33', '34' );
	private const array AXLES     = array( '7.6', '8.0', '8.25', '8.5', '8.75', '9.125' );
	private const array DIAMETERS = array( '50', '52', '53', '54', '55', '56', '58', '60', '63', '65' );
	private const array RISERS    = array( '1/8', '1/4', '1/2' );
	private const array BOLTS     = array( '7/8', '1', '1 1/8', '1 1/4', '1 1/2', '2' );
	private const array BRANDS    = array( 'alpha', 'bravo', 'charlie', 'delta' );

	/**
	 * A catalogue.
	 *
	 * @param int $seed    The seed.
	 * @param int $perSlot How many parts each slot gets.
	 * @return array<string, list<Part>> The parts by slot key.
	 */
	public static function generate( int $seed, int $perSlot = 40 ): array {
		$random    = new Randomizer( new Mt19937( $seed ) );
		$catalogue = array();
		foreach ( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ) as $index => $slot ) {
			$catalogue[ $slot ] = array();
			for ( $n = 1; $n <= $perSlot; $n++ ) {
				$specs = self::specs( $slot, $random );
				if ( ! self::chance( $random, 6 ) ) {
					$specs['set_size'] = self::setSize( $slot, $random );
				}
				$catalogue[ $slot ][] = Parts::of( $specs, ( $index + 1 ) * 100000 + $n, $random->getInt( 500, 12000 ), self::pick( $random, self::BRANDS ), ! self::chance( $random, 10 ) );
			}
		}
		return $catalogue;
	}

	/**
	 * The ids of a catalogue's parts by slot, as the validator takes them.
	 *
	 * @param array<string, list<Part>> $catalogue The catalogue.
	 * @return array<string, list<int>>
	 */
	public static function ids( array $catalogue ): array {
		return array_map( static fn( array $parts ): array => array_map( static fn( Part $part ): int => $part->id, $parts ), $catalogue );
	}

	/**
	 * A slot's part's specs, without its set size.
	 *
	 * @param string     $slot   The slot's key.
	 * @param Randomizer $random The run's randomness.
	 * @return array<string, string|bool|int>
	 */
	private static function specs( string $slot, Randomizer $random ): array {
		$specs = array();
		switch ( $slot ) {
			case SlotKey::DECK:
				if ( ! self::chance( $random, 5 ) ) {
					$specs['deck_width'] = self::pick( $random, self::WIDTHS );
				}
				if ( self::chance( $random, 70 ) ) {
					$specs['deck_length'] = self::pick( $random, self::LENGTHS );
				}
				if ( self::chance( $random, 75 ) ) {
					$specs['hole_pattern'] = self::pick( $random, array( 'new_school', 'new_school', 'new_school', 'new_school', 'old_school', 'both' ) );
				}
				break;
			case SlotKey::TRUCKS:
				$kind = $random->getInt( 1, 10 );
				if ( $kind <= 6 ) {
					[ $specs['axle_width'], $specs['deck_min'], $specs['deck_max'] ] = Parts::INDY[ self::pick( $random, array_map( 'strval', array_keys( Parts::INDY ) ) ) ];
				} elseif ( $kind <= 9 ) {
					$specs['axle_width'] = self::pick( $random, self::AXLES );
				}
				if ( ! self::chance( $random, 8 ) ) {
					$specs['baseplate'] = self::pick( $random, array( 'new_school', 'new_school', 'new_school', 'new_school', 'new_school', 'both', 'old_school' ) );
				}
				if ( self::chance( $random, 50 ) ) {
					$specs['max_wheel'] = self::pick( $random, array( '53', '56', '58' ) );
				} elseif ( self::chance( $random, 60 ) ) {
					$specs['height_class'] = self::pick( $random, array( 'low', 'mid', 'high' ) );
				}
				if ( self::chance( $random, 60 ) ) {
					$specs['axle_diameter'] = self::chance( $random, 8 ) ? '10' : '8';
				}
				break;
			case SlotKey::WHEELS:
				if ( ! self::chance( $random, 5 ) ) {
					$specs['wheel_diameter'] = self::pick( $random, self::DIAMETERS );
				}
				break;
			case SlotKey::RISERS:
				if ( ! self::chance( $random, 5 ) ) {
					$specs['riser_thickness'] = self::pick( $random, self::RISERS );
				}
				break;
			case SlotKey::BEARINGS:
				if ( ! self::chance( $random, 5 ) ) {
					$specs['bearing_bore'] = self::chance( $random, 10 ) ? '7' : '8';
				}
				if ( self::chance( $random, 20 ) ) {
					$specs['spacers_built_in'] = true;
				}
				break;
			case SlotKey::HARDWARE:
				if ( ! self::chance( $random, 5 ) ) {
					$specs['bolt_length'] = self::pick( $random, self::BOLTS );
				}
				break;
			default:
				if ( ! self::chance( $random, 5 ) ) {
					$specs['grip_width'] = self::pick( $random, array( '9', '9', '10', '11' ) );
				}
				if ( ! self::chance( $random, 10 ) ) {
					$specs['grip_length'] = self::pick( $random, array( '33', '33', '36', '45' ) );
				}
		}
		return $specs;
	}

	/**
	 * How many units a slot's part holds: the usual set, and now and then an odd one.
	 *
	 * @param string     $slot   The slot's key.
	 * @param Randomizer $random The run's randomness.
	 */
	private static function setSize( string $slot, Randomizer $random ): int {
		$usual = array(
			SlotKey::TRUCKS   => 2,
			SlotKey::WHEELS   => 4,
			SlotKey::RISERS   => 2,
			SlotKey::BEARINGS => 8,
			SlotKey::HARDWARE => 8,
		)[ $slot ] ?? 1;
		return self::chance( $random, 15 ) ? $random->getInt( 1, 2 * $usual ) : $usual;
	}

	/**
	 * True this many times in a hundred.
	 *
	 * @param Randomizer $random  The run's randomness.
	 * @param int        $percent The chance.
	 */
	public static function chance( Randomizer $random, int $percent ): bool {
		return $random->getInt( 1, 100 ) <= $percent;
	}

	/**
	 * One of some values.
	 *
	 * @template T
	 * @param Randomizer $random The run's randomness.
	 * @param list<T>    $values The values.
	 * @return T
	 */
	public static function pick( Randomizer $random, array $values ) {
		return $values[ $random->getInt( 0, count( $values ) - 1 ) ];
	}
}
