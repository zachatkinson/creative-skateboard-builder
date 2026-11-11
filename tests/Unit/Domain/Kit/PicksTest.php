<?php
/**
 * Part and Picks.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidPart;
use CreativeBranding\SkateboardBuilder\Domain\Kit\PartError;
use PHPUnit\Framework\TestCase;

/**
 * A part as the engine sees it, and the parts picked so far.
 */
final class PicksTest extends TestCase {

	private static function part( int $id ): Part {
		return new Part( $id, '', Specs::none(), 1000, true );
	}

	public function test_a_part_holds_what_it_was_given(): void {
		$specs = Specs::none()->with( SpecKey::DeckWidth, Measure::inches( 82500 ) );
		$part  = new Part( 812, 'rascals', $specs, 7999, false );
		self::assertSame( 812, $part->id );
		self::assertSame( 'rascals', $part->brand );
		self::assertSame( $specs, $part->specs );
		self::assertSame( 7999, $part->price );
		self::assertFalse( $part->inStock );
		self::assertTrue( self::part( 1 )->inStock );
		self::assertSame( 0, ( new Part( 1, '', Specs::none(), 0, true ) )->price, 'a free part is a part' );
	}

	public function test_a_parts_id_is_one_or_more(): void {
		self::assertSame( 1, self::part( 1 )->id );
		try {
			self::part( 0 );
			self::fail( 'accepted' );
		} catch ( InvalidPart $e ) {
			self::assertSame( PartError::IdNotPositive, $e->reason );
			self::assertSame( 'part_id_not_positive', $e->getMessage() );
		}
	}

	public function test_a_parts_price_is_never_negative(): void {
		try {
			new Part( 1, '', Specs::none(), -1, true );
			self::fail( 'accepted' );
		} catch ( InvalidPart $e ) {
			self::assertSame( PartError::PriceNegative, $e->reason );
			self::assertSame( 'part_price_negative', $e->getMessage() );
		}
	}

	public function test_nothing_picked(): void {
		$picks = Picks::none();
		self::assertSame( array(), $picks->slots() );
		self::assertNull( $picks->part( 'deck' ) );
	}

	public function test_a_pick_makes_new_picks(): void {
		$deck   = self::part( 1 );
		$trucks = self::part( 2 );
		$first  = Picks::none()->with( 'deck', $deck );
		$second = $first->with( 'trucks', $trucks );
		self::assertSame( array( 'deck' ), $first->slots() );
		self::assertNull( $first->part( 'trucks' ) );
		self::assertSame( $deck, $second->part( 'deck' ) );
		self::assertSame( $trucks, $second->part( 'trucks' ) );
		self::assertEqualsCanonicalizing( array( 'deck', 'trucks' ), $second->slots() );
	}

	public function test_a_slots_part_is_replaced(): void {
		$wider = self::part( 9 );
		$picks = Picks::none()->with( 'deck', self::part( 1 ) )->with( 'trucks', self::part( 2 ) )->with( 'deck', $wider );
		self::assertSame( $wider, $picks->part( 'deck' ) );
		self::assertCount( 2, $picks->slots() );
	}

	public function test_a_slot_is_emptied(): void {
		$picks   = Picks::none()->with( 'deck', self::part( 1 ) )->with( 'trucks', self::part( 2 ) );
		$without = $picks->without( 'trucks' );
		self::assertSame( array( 'deck' ), $without->slots() );
		self::assertNotNull( $picks->part( 'trucks' ), 'the picks it came from are as they were' );
		self::assertSame( array( 'deck' ), $without->without( 'grip' )->slots(), 'emptying an empty slot changes nothing' );
	}

	public function test_only_keeps_the_named_slots(): void {
		$picks = Picks::none()->with( 'deck', self::part( 1 ) )->with( 'trucks', self::part( 2 ) )->with( 'wheels', self::part( 3 ) );
		$some  = $picks->only( array( 'deck', 'wheels', 'grip' ) );
		self::assertEqualsCanonicalizing( array( 'deck', 'wheels' ), $some->slots() );
		self::assertNull( $some->part( 'trucks' ) );
		self::assertSame( 3, $some->part( 'wheels' )?->id );
		self::assertSame( array(), $picks->only( array() )->slots() );
		self::assertCount( 3, $picks->slots() );
	}

	public function test_a_part_covers_a_quantity_when_it_is_in_stock_and_the_shop_holds_as_many_or_does_not_count(): void {
		$part = static fn( bool $inStock, ?int $stock ): Part => new Part( 1, '', Specs::none(), 1000, $inStock, $stock );
		self::assertTrue( $part( true, null )->covers( 99 ), 'a shop that does not count holds enough' );
		self::assertTrue( $part( true, 2 )->covers( 2 ) );
		self::assertTrue( $part( true, 2 )->covers( 1 ) );
		self::assertFalse( $part( true, 1 )->covers( 2 ), 'one left, and the build takes two (issue #14)' );
		self::assertFalse( $part( false, null )->covers( 1 ), 'sold out covers nothing' );
		self::assertFalse( $part( false, 5 )->covers( 1 ), 'whatever its count says' );
	}
}
