<?php
/**
 * SavedCheck.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Saved;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedChange;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedCheck;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedReason;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use PHPUnit\Framework\TestCase;

/**
 * Spec (configurator): A saved build that has changed (D-254), "What's checked": a part sold out or gone from the kit,
 * or one that no longer fits, each with the closest part that fits in its place, found by `ClosestPart` against the
 * rest of the build.
 */
final class SavedCheckTest extends TestCase {

	private const array SAVED = array(
		SlotKey::DECK     => 103,
		SlotKey::TRUCKS   => 149,
		SlotKey::WHEELS   => 302,
		SlotKey::BEARINGS => 501,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

	/**
	 * The check of the saved picks against these parts.
	 *
	 * @param array<string, list<Part>> $parts The kit's parts now, by slot.
	 * @param array<string, Part>       $was   The parts no longer offered, as they read now.
	 * @param array<string, int>        $picks The picks saved.
	 */
	private static function check( array $parts, array $was = array(), array $picks = self::SAVED ): SavedCheck {
		return SavedCheck::of( Shop::kit(), FitEngine::standard(), $picks, $parts, $was );
	}

	/**
	 * The changes as slot, was, reason and closest.
	 *
	 * @return list<array{string, int, string, int|null}>
	 */
	private static function said( SavedCheck $check ): array {
		return array_map( static fn( SavedChange $change ): array => array( $change->slot, $change->was, $change->reason->value, $change->closest ), $check->changes );
	}

	public function test_a_build_the_shop_still_has_and_that_still_fits_has_no_change(): void {
		$check = self::check( Shop::parts() );
		self::assertSame( array(), $check->changes );
		self::assertSame( self::SAVED, $check->picks );
		self::assertFalse( $check->changed() );
	}

	public function test_a_sold_out_part_is_named_with_the_closest_part_that_fits_in_its_place(): void {
		$parts                    = Shop::parts();
		$parts[ SlotKey::WHEELS ] = array_map( static fn( Part $part ): Part => 302 === $part->id ? Shop::priced( $part, $part->price, false, 0 ) : $part, $parts[ SlotKey::WHEELS ] );
		$check                    = self::check( $parts );
		self::assertSame( array( array( SlotKey::WHEELS, 302, 'sold_out', 301 ) ), self::said( $check ), '52 mm is nearer 54 mm than 58 mm' );
		self::assertSame( 301, $check->picks[ SlotKey::WHEELS ], 'the replacement takes its place' );
		self::assertTrue( $check->changed() );
	}

	public function test_a_part_the_shop_holds_too_few_of_is_sold_out_for_the_build(): void {
		$parts                      = Shop::parts();
		$parts[ SlotKey::BEARINGS ] = array( Shop::priced( $parts[ SlotKey::BEARINGS ][0], 1500, true, 3 ) );
		self::assertSame( array( array( SlotKey::BEARINGS, 501, 'sold_out', null ) ), self::said( self::check( $parts ) ), 'the build takes 8, and no other bearing is offered' );
	}

	public function test_a_part_gone_from_the_kit_is_named_with_the_closest_to_it_as_it_reads_now(): void {
		$parts                  = Shop::parts();
		$gone                   = $parts[ SlotKey::DECK ][2];
		$parts[ SlotKey::DECK ] = array_slice( $parts[ SlotKey::DECK ], 0, 2 );
		$check                  = self::check( $parts, array( SlotKey::DECK => $gone ) );
		self::assertSame( array( SlotKey::DECK, 103, 'gone', 102 ), self::said( $check )[0], '8.25 is the nearest to 8.5' );
		self::assertSame( 102, $check->picks[ SlotKey::DECK ] );

		$unread = self::check( $parts );
		self::assertSame( array( SlotKey::DECK, 103, 'gone', null ), self::said( $unread )[0], 'a part that no longer reads has no closest' );
		self::assertArrayNotHasKey( SlotKey::DECK, $unread->picks, 'and, where the slot recommends none, it is left for the shopper' );

		$parts                    = Shop::parts();
		$parts[ SlotKey::WHEELS ] = array_values( array_filter( $parts[ SlotKey::WHEELS ], static fn( Part $part ): bool => 302 !== $part->id ) );
		[ , , $reason, $closest ] = self::said( self::check( $parts ) )[0];
		self::assertSame( 'gone', $reason );
		self::assertNotNull( $closest, 'the wheels the step recommends stand in' );
	}

	public function test_a_part_that_no_longer_fits_the_build_before_it_is_named_and_replaced(): void {
		$saved                   = array( SlotKey::TRUCKS => 139 ) + self::SAVED;
		$check                   = self::check( Shop::parts(), array(), $saved );
		[ $slot, $was, $reason ] = self::said( $check )[0];
		self::assertSame( array( SlotKey::TRUCKS, 139, 'no_fit' ), array( $slot, $was, $reason ), '139 trucks on an 8.5 deck' );
		self::assertSame( 149, $check->picks[ SlotKey::TRUCKS ], 'the trucks that fit the deck' );
	}

	public function test_a_pick_for_a_slot_the_kit_no_longer_has_is_left_out(): void {
		$check = self::check( Shop::parts(), array(), self::SAVED + array( 'spacers' => 9 ) );
		self::assertSame( array(), $check->changes );
		self::assertSame( self::SAVED, $check->picks );
	}
}
