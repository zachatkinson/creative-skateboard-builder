<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\OrderBuilds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Cart, checkout and orders, Orders; Data model, Order items: the snapshot the kit's item keeps, and the tie
 * between an order's items. The auditor's findings 1 and 6 on milestone 5 (D-138).
 */
#[CoversClass( OrderBuilds::class )]
final class OrderBuildsTest extends TestCase {

	private const KIT = 77;

	private InMemoryKits $kits;

	private OrderBuilds $orders;

	protected function setUp(): void {
		$this->kits   = new InMemoryKits();
		$this->orders = new OrderBuilds( $this->kits );
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
	}

	/**
	 * @return array{build: string, kit: bool, parent: int, children: mixed}
	 */
	private static function item( string $build, bool $kit, int $parent = 0, mixed $children = '' ): array {
		return array(
			'build'    => $build,
			'kit'      => $kit,
			'parent'   => $parent,
			'children' => $children,
		);
	}

	public function test_the_snapshot_keeps_each_part_as_sold_in_the_builds_order(): void {
		$build    = new Build( 'a', self::KIT, array( 'deck' => 103, 'trucks' => 149 ), array( 'deck' => 1, 'trucks' => 2 ), true );
		$snapshot = $this->orders->snapshot( $build, array( 'deck' => 'Deck', 'trucks' => 'Trucks' ), array( 'trucks' => 'Stage 11', 'deck' => 'Eight and a half' ), array( 'trucks' => 'IND-149' ), 'Two days', 'No returns once gripped' );
		self::assertSame( 1, $this->kits->readings, 'the parts are read in one reading of the shop' );
		self::assertSame( array( 'schema_version', 'kit', 'assembly', 'picks', 'lead_time', 'returns_notice' ), array_keys( $snapshot ) );
		self::assertSame( array( OrderBuilds::SNAPSHOT_VERSION, self::KIT, true ), array( $snapshot['schema_version'], $snapshot['kit'], $snapshot['assembly'] ) );
		self::assertSame( 1, OrderBuilds::SNAPSHOT_VERSION );
		self::assertSame( array( 'Two days', 'No returns once gripped' ), array( $snapshot['lead_time'], $snapshot['returns_notice'] ) );
		self::assertSame( array( 'deck', 'trucks' ), array_keys( $snapshot['picks'] ) );
		self::assertSame( array( 'label', 'name', 'sku', 'part', 'quantity', 'specs' ), array_keys( $snapshot['picks']['trucks'] ) );
		self::assertSame( array( 'Trucks', 'Stage 11', 'IND-149', 149, 2 ), array_slice( array_values( $snapshot['picks']['trucks'] ), 0, 5 ) );
		self::assertSame( array( 'Deck', 'Eight and a half', '', 103, 1 ), array_slice( array_values( $snapshot['picks']['deck'] ), 0, 5 ) );
		self::assertSame( Shop::parts()['deck'][2]->specs->toIndex(), $snapshot['picks']['deck']['specs'] );
		self::assertNotSame( array(), $snapshot['picks']['deck']['specs'] );
	}

	public function test_the_owners_words_are_kept_for_an_assembled_build_alone(): void {
		$loose    = new Build( 'a', self::KIT, array( 'deck' => 103 ), array( 'deck' => 1 ), false );
		$snapshot = $this->orders->snapshot( $loose, array(), array(), array(), 'Two days', 'No returns once gripped' );
		self::assertFalse( $snapshot['assembly'] );
		self::assertSame( array( '', '' ), array( $snapshot['lead_time'], $snapshot['returns_notice'] ) );
		self::assertSame( array( 'deck', '' ), array( $snapshot['picks']['deck']['label'], $snapshot['picks']['deck']['name'] ), 'a slot nobody labelled is kept by its key, and a part nobody named with no name' );
	}

	public function test_a_part_or_a_kit_the_shop_no_longer_reads_is_kept_with_no_specs(): void {
		$build                = new Build( 'a', self::KIT, array( 'deck' => 103, 'trucks' => 149 ), array( 'deck' => 1, 'trucks' => 2 ), true );
		$this->kits->now[149] = false;
		$snapshot             = $this->orders->snapshot( $build, array(), array(), array(), '', '' );
		self::assertSame( array( 149, 2, array() ), array( $snapshot['picks']['trucks']['part'], $snapshot['picks']['trucks']['quantity'], $snapshot['picks']['trucks']['specs'] ), 'a part the shop no longer sells' );
		self::assertNotSame( array(), $snapshot['picks']['deck']['specs'] );

		unset( $this->kits->kits[ self::KIT ] );
		$picked   = count( $this->kits->picked );
		$snapshot = $this->orders->snapshot( $build, array(), array(), array(), '', '' );
		self::assertSame( array( 103, 1, array() ), array( $snapshot['picks']['deck']['part'], $snapshot['picks']['deck']['quantity'], $snapshot['picks']['deck']['specs'] ), 'a kit that no longer reads' );
		self::assertCount( $picked, $this->kits->picked, 'and no part is asked of the shop for a slot nobody knows' );
	}

	public function test_an_orders_items_are_tied_by_their_builds(): void {
		$ties = OrderBuilds::ties(
			array(
				11 => self::item( 'a', true ),
				12 => self::item( 'a', false ),
				13 => self::item( 'a', false ),
				21 => self::item( 'b', true ),
				22 => self::item( 'b', false ),
			)
		);
		self::assertSame(
			array(
				'parents'  => array(
					12 => 11,
					13 => 11,
					22 => 21,
				),
				'children' => array(
					11 => array( 12, 13 ),
					21 => array( 22 ),
				),
			),
			$ties
		);
	}

	public function test_what_is_tied_already_is_not_tied_again_and_what_changed_is(): void {
		$tied = array(
			11 => self::item( 'a', true, 0, array( 12, 13 ) ),
			12 => self::item( 'a', false, 11 ),
			13 => self::item( 'a', false, 11 ),
		);
		self::assertSame(
			array(
				'parents'  => array(),
				'children' => array(),
			),
			OrderBuilds::ties( $tied )
		);

		// The Store API makes a draft's items again: the kit has a new ID and one part does.
		$again = array(
			31 => self::item( 'a', true, 0, array( 12, 13 ) ),
			12 => self::item( 'a', false, 11 ),
			33 => self::item( 'a', false ),
		);
		self::assertSame(
			array(
				'parents'  => array(
					12 => 31,
					33 => 31,
				),
				'children' => array( 31 => array( 12, 33 ) ),
			),
			OrderBuilds::ties( $again )
		);
		self::assertSame( array( 11 => array() ), OrderBuilds::ties( array( 11 => self::item( 'a', true ) ) )['children'], 'a kit with no part is written as having none' );
		self::assertSame( array(), OrderBuilds::ties( array( 11 => self::item( 'a', true, 0, array() ) ) )['children'] );
	}

	public function test_a_part_whose_kit_is_not_on_the_order_is_left_as_it_is(): void {
		self::assertSame(
			array(
				'parents'  => array(),
				'children' => array(),
			),
			OrderBuilds::ties( array( 12 => self::item( 'a', false ), 21 => self::item( 'b', false, 5 ) ) )
		);
		self::assertSame(
			array(
				'parents'  => array(),
				'children' => array(),
			),
			OrderBuilds::ties( array() )
		);
	}
}
