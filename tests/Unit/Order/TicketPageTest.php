<?php
/**
 * The assembly ticket's orders and pages.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Order;

use CreativeBranding\SkateboardBuilder\Order\TicketPage;
use CreativeBranding\SkateboardBuilder\Order\WorkshopWords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Pricing, stock, assembly and returns, Assembly: one build to a page, so a kit bought twice prints twice, and
 * the bulk print of a long list prints the first hundred orders and says how many it left.
 */
#[CoversClass( TicketPage::class )]
#[UsesClass( WorkshopWords::class )]
final class TicketPageTest extends TestCase {

	public function test_the_orders_asked_for_are_positive_ids_each_once_in_their_order(): void {
		self::assertSame( array( array( 12, 7, 30 ), 0 ), TicketPage::ids( '12, 7,12,0,-3,abc,30,,7.5' ) );
	}

	public function test_anything_but_a_string_asks_for_no_order(): void {
		self::assertSame( array( array(), 0 ), TicketPage::ids( array( '12' ) ) );
		self::assertSame( array( array(), 0 ), TicketPage::ids( null ) );
	}

	public function test_one_page_prints_the_first_hundred_orders_and_counts_the_rest(): void {
		[ $ids, $more ] = TicketPage::ids( implode( ',', range( 1, 103 ) ) );

		self::assertSame( range( 1, 100 ), $ids );
		self::assertSame( 3, $more );
	}

	public function test_the_bulk_actions_leftover_is_added_to_the_count(): void {
		self::assertSame( array( array( 4 ), 5 ), TicketPage::ids( '4', '5' ) );
		self::assertSame( array( array( 4 ), 0 ), TicketPage::ids( '4', '-5' ) );
		self::assertSame( array( array( 4 ), 0 ), TicketPage::ids( '4', 5 ) );
		self::assertSame( array( range( 1, 100 ), 2 ), TicketPage::ids( implode( ',', range( 1, 101 ) ), '1' ) );
	}

	public function test_each_board_gets_its_own_page_with_the_orders_and_the_builds_problems(): void {
		$one   = self::build( 'a', 'Complete', 1, array( 'The Grip line is no longer on the order.' ) );
		$two   = self::build( 'b', 'Street', 2, array() );
		$pages = TicketPage::pages(
			array(
				self::order( '1001', array( 'A loose kit.' ), array( $one, $two ) ),
				self::order( '1002', array(), array( $two ) ),
			)
		);

		self::assertSame( array( 'cbuild-ticket-1', 'cbuild-ticket-2', 'cbuild-ticket-3', 'cbuild-ticket-4', 'cbuild-ticket-5' ), array_column( $pages, 'id' ) );
		self::assertSame( array( '1001', '1001', '1001', '1002', '1002' ), array_map( static fn( array $page ): string => $page['order']['number'], $pages ) );
		self::assertSame( array( 'Complete', 'Street', 'Street', 'Street', 'Street' ), array_map( static fn( array $page ): string => $page['build']['title'], $pages ) );
		self::assertSame( array( '', 'Board 1 of 2', 'Board 2 of 2', 'Board 1 of 2', 'Board 2 of 2' ), array_column( $pages, 'which' ) );
		self::assertSame( array( 'A loose kit.', 'The Grip line is no longer on the order.' ), $pages[0]['problems'] );
		self::assertSame( array( 'A loose kit.' ), $pages[1]['problems'] );
		self::assertSame( array(), $pages[3]['problems'] );
	}

	public function test_an_order_with_no_build_has_no_page(): void {
		self::assertSame( array(), TicketPage::pages( array( self::order( '1003', array(), array() ) ) ) );
	}

	/**
	 * An order as the workshop reads it.
	 *
	 * @param string             $number   The order's number.
	 * @param list<string>       $problems The order's own problems.
	 * @param list<array<mixed>> $builds   Its builds.
	 * @return array<mixed>
	 */
	private static function order( string $number, array $problems, array $builds ): array {
		return array(
			'order'     => (int) $number,
			'number'    => $number,
			'customer'  => 'Sam Rider',
			'note'      => '',
			'problems'  => $problems,
			'builds'    => $builds,
			'assembles' => true,
		);
	}

	/**
	 * A build as the workshop reads it.
	 *
	 * @param string       $id       The build's ID.
	 * @param string       $title    Its heading.
	 * @param int          $boards   How many boards.
	 * @param list<string> $problems Its problems.
	 * @return array<mixed>
	 */
	private static function build( string $id, string $title, int $boards, array $problems ): array {
		return array(
			'id'          => $id,
			'title'       => $title,
			'boards'      => $boards,
			'assembly'    => true,
			'assembled'   => '',
			'rows'        => array(),
			'problems'    => $problems,
			'board'       => null,
			'description' => '',
			'steps'       => array(),
		);
	}
}
