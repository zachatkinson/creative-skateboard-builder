<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildAgain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Order again on an order with a build (D-137): what the snapshot asks for, and which lines make a whole build.
 */
#[CoversClass( BuildAgain::class )]
final class BuildAgainTest extends TestCase {

	private static function build( string $id, string ...$slots ): Build {
		return new Build( $id, 9, array_fill_keys( $slots, 5 ), array_fill_keys( $slots, 1 ), false );
	}

	/**
	 * @return array{build: Build|null, parent: string, slot: string}
	 */
	private static function part( string $parent, string $slot ): array {
		return array(
			'build'  => null,
			'parent' => $parent,
			'slot'   => $slot,
		);
	}

	/**
	 * @return array{build: Build|null, parent: string, slot: string}
	 */
	private static function kit( Build $build ): array {
		return array(
			'build'  => $build,
			'parent' => '',
			'slot'   => '',
		);
	}

	public function test_a_snapshot_asks_for_each_slots_part_and_its_assembly(): void {
		$snapshot = array(
			'schema_version' => 1,
			'kit'            => 9,
			'assembly'       => true,
			'picks'          => array(
				'deck'   => array( 'part' => 103, 'quantity' => 1, 'specs' => array() ),
				'trucks' => array( 'part' => 149, 'quantity' => 2 ),
				7        => array( 'part' => 5 ),
			),
		);
		self::assertSame(
			array(
				'picks'    => array( 'deck' => 103, 'trucks' => 149, 7 => 5 ),
				'assembly' => true,
			),
			BuildAgain::asked( $snapshot )
		);
		self::assertFalse( BuildAgain::asked( array( 'picks' => array( 'deck' => array( 'part' => 1 ) ) ) )['assembly'] ?? null, 'assembly not said is none' );
		self::assertFalse( BuildAgain::asked( array( 'assembly' => 1, 'picks' => array( 'deck' => array( 'part' => 1 ) ) ) )['assembly'] ?? null, 'and it is true or it is not' );
	}

	public function test_what_is_no_snapshot_asks_for_nothing(): void {
		foreach ( array(
			'nothing'           => null,
			'text'              => 'a:0:{}',
			'no picks'          => array( 'assembly' => true ),
			'picks of no kind'  => array( 'picks' => 'deck' ),
			'no pick'           => array( 'picks' => array() ),
			'a pick of no kind' => array( 'picks' => array( 'deck' => 103 ) ),
			'a pick with no ID' => array( 'picks' => array( 'deck' => array( 'quantity' => 1 ) ) ),
			'an ID as text'     => array( 'picks' => array( 'deck' => array( 'part' => '103' ) ) ),
			'an ID of nothing'  => array( 'picks' => array( 'deck' => array( 'part' => 0 ) ) ),
			'one good, one not' => array( 'picks' => array( 'deck' => array( 'part' => 103 ), 'grip' => array( 'part' => -1 ) ) ),
		) as $what => $snapshot ) {
			self::assertNull( BuildAgain::asked( $snapshot ), $what );
		}
		self::assertSame( array( 'deck' => 1 ), BuildAgain::asked( array( 'picks' => array( 'deck' => array( 'part' => 1 ) ) ) )['picks'] ?? null, 'the least ID there is' );
	}

	public function test_a_whole_build_has_a_line_for_each_slot_and_its_parts_are_named_in_the_carts_order(): void {
		$whole = BuildAgain::whole(
			array(
				'k' => self::kit( self::build( 'a', 'deck', 'trucks', 'grip' ) ),
				'g' => self::part( 'k', 'grip' ),
				'd' => self::part( 'k', 'deck' ),
				't' => self::part( 'k', 'trucks' ),
			)
		);
		self::assertSame( array( 'k' => array( 'g', 'd', 't' ) ), $whole['children'] );
		self::assertSame( array(), $whole['dropped'] );
	}

	public function test_a_build_with_a_line_missing_one_too_many_or_one_of_another_slot_is_dropped_whole(): void {
		$missing = BuildAgain::whole(
			array(
				'k' => self::kit( self::build( 'a', 'deck', 'trucks' ) ),
				'd' => self::part( 'k', 'deck' ),
			)
		);
		self::assertSame( array(), $missing['children'] );
		self::assertSame( array( 'k', 'd' ), $missing['dropped'] );

		$twice = BuildAgain::whole(
			array(
				'k'  => self::kit( self::build( 'a', 'deck', 'trucks' ) ),
				'd'  => self::part( 'k', 'deck' ),
				'd2' => self::part( 'k', 'deck' ),
				't'  => self::part( 'k', 'trucks' ),
			)
		);
		self::assertSame( array(), $twice['children'] );
		self::assertSame( array( 'k', 'd', 'd2', 't' ), $twice['dropped'] );

		$other = BuildAgain::whole(
			array(
				'k' => self::kit( self::build( 'a', 'deck', 'trucks' ) ),
				'd' => self::part( 'k', 'deck' ),
				'w' => self::part( 'k', 'wheels' ),
			)
		);
		self::assertSame( array( 'k', 'd', 'w' ), $other['dropped'] );

		$two = BuildAgain::whole(
			array(
				'k1' => self::kit( self::build( 'a', 'deck', 'grip' ) ),
				'd1' => self::part( 'k1', 'deck' ),
				'k2' => self::kit( self::build( 'b', 'deck', 'grip' ) ),
				'g2' => self::part( 'k2', 'grip' ),
			)
		);
		self::assertSame( array( 'k1', 'd1', 'k2', 'g2' ), $two['dropped'], 'two builds, each with a line missing' );

		$none = BuildAgain::whole( array( 'k' => self::kit( self::build( 'a', 'deck' ) ) ) );
		self::assertSame( array( 'k' ), $none['dropped'], 'a kit\'s line with no part at all' );
	}

	public function test_each_build_is_judged_by_itself_and_a_part_of_no_kit_is_dropped(): void {
		$whole = BuildAgain::whole(
			array(
				'o1' => self::part( '', 'deck' ),
				'k1' => self::kit( self::build( 'a', 'deck', 'grip' ) ),
				'd1' => self::part( 'k1', 'deck' ),
				'k2' => self::kit( self::build( 'b', 'grip', 'deck' ) ),
				'd2' => self::part( 'k2', 'deck' ),
				'g2' => self::part( 'k2', 'grip' ),
				'o2' => self::part( 'gone', 'grip' ),
				'o3' => self::part( 'gone', 'deck' ),
			)
		);
		self::assertSame( array( 'k2' => array( 'd2', 'g2' ) ), $whole['children'], 'slots in any order' );
		self::assertSame( array( 'k1', 'd1', 'o1', 'o2', 'o3' ), $whole['dropped'] );
		self::assertSame(
			array(
				'children' => array(),
				'dropped'  => array(),
			),
			BuildAgain::whole( array() )
		);
	}
}
