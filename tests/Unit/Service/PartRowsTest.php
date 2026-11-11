<?php
/**
 * A slot's parts as a cache keeps them.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Service\PartRows;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * D-116: what is kept is scalars and arrays of them, reads back to the same parts, and is no list at all when a
 * cache hands back anything else.
 */
final class PartRowsTest extends TestCase {

	/**
	 * A good row.
	 *
	 * @return array{int, string, int, bool, int|null, array<string, string>}
	 */
	private static function row(): array {
		return array( 7, 'indy', 2599, true, 3, array( 'axle_width' => '8.0000' ) );
	}

	public function test_parts_read_back_as_they_were_written_and_in_their_order(): void {
		$shop  = Shop::parts();
		$parts = array( ...$shop['trucks'], ...$shop['deck'], Shop::priced( $shop['wheels'][0], 0, false, 0 ), Shop::priced( $shop['wheels'][1], 4500, true, 2 ) );
		$rows  = PartRows::of( $parts );
		self::assertSame( $rows, unserialize( serialize( $rows ) ), 'scalars and arrays of them' );
		self::assertSame( $rows, json_decode( (string) json_encode( $rows ), true ), 'and nothing a cache that writes JSON would change' );
		$read = PartRows::read( $rows );
		self::assertNotNull( $read );
		self::assertEquals( $parts, $read );
		self::assertSame( array_map( static fn( Part $part ): int => $part->id, $parts ), array_map( static fn( Part $part ): int => $part->id, $read ) );
	}

	public function test_a_row_is_the_id_the_brand_the_price_the_stock_and_the_specs_as_the_index_holds_them(): void {
		$part = PartRows::read( array( self::row() ) )[0] ?? null;
		self::assertInstanceOf( Part::class, $part );
		self::assertSame( array( self::row() ), PartRows::of( array( $part ) ) );
		self::assertSame( array( 7, 'indy', 2599, true, 3 ), array( $part->id, $part->brand, $part->price, $part->inStock, $part->stock ) );
		self::assertSame( array(), PartRows::of( array() ) );
		self::assertSame( array(), PartRows::read( array() ), 'a slot with no parts is kept as one' );
	}

	public function test_a_count_the_shop_does_not_keep_stays_unknown(): void {
		$row    = self::row();
		$row[4] = null;
		$part   = PartRows::read( array( $row ) )[0] ?? null;
		self::assertInstanceOf( Part::class, $part );
		self::assertNull( $part->stock );
	}

	/**
	 * What a cache might hand back in place of the rows.
	 *
	 * @return iterable<string, array{mixed}>
	 */
	public static function notRows(): iterable {
		$with = static function ( int $at, mixed $value ): array {
			$row        = self::row();
			$row[ $at ] = $value;
			return array( self::row(), $row );
		};
		yield 'nothing kept' => array( null );
		yield 'a miss' => array( false );
		yield 'text' => array( 'a:0:{}' );
		yield 'a map' => array( array( 'parts' => array( self::row() ) ) );
		yield 'a list with a gap' => array( array( 1 => self::row() ) );
		yield 'a row that is no array' => array( array( self::row(), 7 ) );
		yield 'a row too short' => array( array( array_slice( self::row(), 0, 5 ) ) );
		yield 'a row too long' => array( array( array( ...self::row(), 1 ) ) );
		yield 'a row with names' => array( array( array_combine( array( 'a', 'b', 'c', 'd', 'e', 'f' ), self::row() ) ) );
		yield 'an id as text' => array( $with( 0, '7' ) );
		yield 'an id of nothing' => array( $with( 0, 0 ) );
		yield 'a brand that is no text' => array( $with( 1, null ) );
		yield 'a price as text' => array( $with( 2, '2599' ) );
		yield 'a price below nothing' => array( $with( 2, -1 ) );
		yield 'stock as a number' => array( $with( 3, 1 ) );
		yield 'a count as text' => array( $with( 4, '3' ) );
		yield 'specs that are no array' => array( $with( 5, 'axle_width' ) );
		yield 'specs in a list' => array( $with( 5, array( '8.0000' ) ) );
		yield 'a spec that is no text' => array( $with( 5, array( 'axle_width' => 8.0 ) ) );
	}

	#[DataProvider( 'notRows' )]
	public function test_anything_else_is_no_list_at_all( mixed $kept ): void {
		self::assertNull( PartRows::read( $kept ) );
	}
}
