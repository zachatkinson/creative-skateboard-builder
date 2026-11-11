<?php
/**
 * A build's lines, kept together.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Port\CartLine;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\BuildCart;
use CreativeBranding\SkateboardBuilder\Service\BuildLines;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;
use CreativeBranding\SkateboardBuilder\Service\Verdict;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\CountingUuids;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryCart;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Spec, Cart, checkout and orders, Keeping it together. Roadmap, milestone 5, Done when: no route through any
 * cart can split, change or orphan a build.
 */
final class BuildLinesTest extends TestCase {

	private const KIT = 77;

	/**
	 * A build that fits, from the fixture shop: slot key to part ID.
	 */
	private const PICKS = array(
		'deck'     => 103,
		'trucks'   => 149,
		'wheels'   => 302,
		'bearings' => 501,
		'hardware' => 601,
		'grip'     => 701,
	);

	private InMemoryKits $kits;

	private InMemoryCart $cart;

	private BuildCart $builds;

	private BuildLines $lines;

	protected function setUp(): void {
		$this->kits   = new InMemoryKits();
		$this->cart   = new InMemoryCart();
		$this->builds = new BuildCart( $this->kits, $this->cart, new InMemoryShopSettings(), new CountingUuids() );
		$this->lines  = new BuildLines( $this->cart, $this->kits, $this->builds );
		$this->kits->add( self::KIT, Shop::kit(), Shop::parts() );
		// The cart tells the service of each change, as WooCommerce's hooks do, the service's own among them.
		$this->cart->hears = $this->lines;
	}

	/**
	 * Put a build in the cart as an add does, and give its kit's line's key.
	 *
	 * @param int                $quantity How many of it.
	 * @param array<string, int> $picks    Its picks.
	 */
	private function build( int $quantity = 1, array $picks = self::PICKS ): string {
		$build = $this->builds->consider( self::KIT, array( 'picks' => $picks ), $quantity )->build;
		self::assertNotNull( $build );
		$key = $this->cart->put( self::KIT, $quantity, $build->toArray() );
		self::assertNull( $this->builds->attach( $key ) );
		return $key;
	}

	/**
	 * The quantities of a build's parts, by slot.
	 *
	 * @return array<string, int>
	 */
	private function quantities( string $key ): array {
		$lines = $this->cart->lines();
		$out   = array();
		foreach ( $lines[ $key ]->children as $child ) {
			if ( isset( $lines[ $child ] ) ) {
				$out[ $lines[ $child ]->slot ] = $lines[ $child ]->quantity;
			}
		}
		return $out;
	}

	/**
	 * A refusal's problems, as code, slot and part.
	 *
	 * @return list<array{string, string, int|null}>
	 */
	private static function problems( ?Verdict $verdict ): array {
		return array_map( static fn( BuildProblem $problem ): array => array( $problem->code->value, $problem->slot, $problem->part ), $verdict->problems ?? array() );
	}

	public function test_the_kits_quantity_sets_its_parts_and_a_part_changed_alone_goes_back(): void {
		$loose = $this->cart->put( 103, 4 );
		$key   = $this->build();
		$one   = $this->quantities( $key );

		$this->cart->setQuantity( $key, 3 );
		self::assertSame( array_map( static fn( int $each ): int => 3 * $each, $one ), $this->quantities( $key ) );
		self::assertSame( 4, $this->cart->lines()[ $loose ]->quantity, 'a line of the same part outside the build is left alone' );

		$deck = $this->cart->lines()[ $key ]->children[0];
		$this->cart->setQuantity( $deck, 9 );
		self::assertSame( 3 * $one['deck'], $this->cart->lines()[ $deck ]->quantity, 'a part follows its kit, whatever set it' );

		$this->cart->resized = array();
		$this->cart->setQuantity( $loose, 2 );
		$this->cart->setQuantity( $key, 3 );
		$this->lines->resized( 'line-none' );
		self::assertSame( array( $loose => 2, $key => 3 ), $this->cart->resized, 'nothing is set that is already right' );

		// A kit's line that names a line the cart does not hold, and a part in a slot the build has not: left for
		// the look over the cart, and the rest still follow.
		$trucks                       = $this->cart->lines()[ $key ]->children[1];
		$this->cart->lines[ $trucks ] = new CartLine( $trucks, 149, 3, null, array(), $key, 'rails' );
		unset( $this->cart->lines[ $deck ] );
		$this->cart->setQuantity( $key, 2 );
		self::assertSame( 3, $this->cart->lines()[ $trucks ]->quantity );
		self::assertSame( 2 * $one['wheels'], $this->quantities( $key )['wheels'] );
	}

	public function test_removing_any_line_of_a_build_removes_all_of_it_and_nothing_else(): void {
		$first  = $this->build();
		$second = $this->build();
		$loose  = $this->cart->put( 103 );

		$this->cart->remove( $first );
		self::assertSame( array( $second, ...$this->cart->lines()[ $second ]->children, $loose ), array_keys( $this->cart->lines() ) );

		$this->cart->remove( $this->cart->lines()[ $second ]->children[2] );
		self::assertSame( array( $loose ), array_keys( $this->cart->lines() ), 'from a part, the kit and the other parts go' );
		self::assertCount( 14, $this->cart->removed );
		self::assertCount( 14, array_unique( $this->cart->removed ), 'each line removed once' );

		$other = $this->cart->put( 103 );
		$this->lines->removing( 'line-none' );
		$this->cart->remove( $loose );
		self::assertSame( array( $other ), array_keys( $this->cart->lines() ), 'a line of no build takes nothing with it' );
	}

	public function test_a_change_that_fails_part_way_does_not_stop_the_next(): void {
		$key               = $this->build();
		$children          = $this->cart->lines()[ $key ]->children;
		$this->cart->stuck = array( $children[1] );
		try {
			$this->cart->remove( $key );
			self::fail( 'the cart threw' );
		} catch ( RuntimeException ) {
			$this->cart->stuck = array();
		}
		$this->cart->remove( $children[5] );
		self::assertSame( array(), $this->cart->lines(), 'the build still goes as one' );
	}

	public function test_an_undo_brings_the_whole_build_back_or_none_of_it(): void {
		$key      = $this->build( 2 );
		$children = $this->cart->lines()[ $key ]->children;
		$all      = array( $key, ...$children );

		foreach ( array( $key, $children[3] ) as $undone ) {
			$this->cart->remove( $undone );
			self::assertSame( array(), $this->cart->lines() );
			$this->cart->restore( $undone );
			self::assertEqualsCanonicalizing( $all, array_keys( $this->cart->lines() ), 'the kit and every part, from either end' );
			self::assertSame( 2, $this->cart->lines()[ $key ]->quantity );
			self::assertSame( array(), $this->cart->bin );
		}

		// One of the parts cannot come back: the cart has forgotten it.
		$this->cart->remove( $key );
		unset( $this->cart->bin[ $children[1] ] );
		$this->cart->restore( $key );
		self::assertSame( array(), $this->cart->lines(), 'a build is never left in part' );

		// Nor the kit, from a part.
		$key  = $this->build();
		$deck = $this->cart->lines()[ $key ]->children[0];
		$this->cart->remove( $deck );
		unset( $this->cart->bin[ $key ] );
		$this->cart->restore( $deck );
		self::assertSame( array(), $this->cart->lines() );

		$loose = $this->cart->put( 103 );
		$this->cart->remove( $loose );
		$this->cart->restore( $loose );
		$this->lines->restored( 'line-none' );
		self::assertSame( array( $loose ), array_keys( $this->cart->lines() ) );
	}

	public function test_a_parts_quantity_is_not_the_shoppers_to_set(): void {
		$key   = $this->build( 2 );
		$deck  = $this->cart->lines()[ $key ]->children[0];
		$loose = $this->cart->put( 103 );

		self::assertTrue( $this->lines->editable( $key ) );
		self::assertTrue( $this->lines->editable( $loose ) );
		self::assertTrue( $this->lines->editable( 'line-none' ) );
		self::assertFalse( $this->lines->editable( $deck ) );

		self::assertSame( CartRefusal::PartLocked, $this->lines->mayResize( $deck, 5 )?->refusal );
		self::assertNull( $this->lines->mayResize( $deck, 2 ), 'set to what it already must be, as the cart does when it saves every line' );
		self::assertNull( $this->lines->mayResize( $loose, 50 ) );
		self::assertNull( $this->lines->mayResize( 'line-none', 1 ) );
		self::assertNull( $this->lines->mayResize( $key, 50 ), 'nothing here has a stock count' );
		self::assertNull( $this->lines->limit( $key ) );
		self::assertNull( $this->lines->limit( $deck ) );
		self::assertNull( $this->lines->limit( 'line-none' ) );

		// A part whose kit's line is gone, or in a slot its build has not, may be set to nothing.
		$this->cart->lines[ $deck ] = new CartLine( $deck, 103, 2, null, array(), $key, 'rails' );
		self::assertSame( CartRefusal::PartLocked, $this->lines->mayResize( $deck, 2 )?->refusal );
		$wheels                    = $this->cart->lines()[ $key ]->children[2];
		$this->cart->lines[ $key ] = new CartLine( $key, self::KIT, 2, array( 'id' => 'x' ) );
		self::assertSame( CartRefusal::PartLocked, $this->lines->mayResize( $wheels, 2 )?->refusal, 'nor one whose kit\'s line no longer reads' );
		$this->cart->setQuantity( $key, 3 );
		self::assertSame( 2, $this->cart->lines()[ $wheels ]->quantity, 'and it follows nothing' );
		unset( $this->cart->lines[ $key ] );
		self::assertSame( CartRefusal::PartLocked, $this->lines->mayResize( $wheels, 2 )?->refusal );
		self::assertSame( CartRefusal::PartLocked, $this->lines->mayResize( $wheels, 0 )?->refusal );
	}

	public function test_a_kits_line_holds_what_the_scarcest_part_allows_beside_the_rest_of_the_cart(): void {
		$this->kits->now[103] = Shop::priced( Shop::parts()['deck'][2], 7000, true, 5 );
		$this->kits->now[302] = Shop::priced( Shop::parts()['wheels'][1], 3200, true, 9 );
		$key                  = $this->build( 2 );
		self::assertSame( 5, $this->lines->limit( $key ), 'five decks' );

		$loose = $this->cart->put( 103, 1 );
		$this->cart->put( 103, 1 );
		self::assertSame( 3, $this->lines->limit( $key ), 'two of the decks are in lines of their own' );
		self::assertNull( $this->lines->mayResize( $key, 3 ) );
		$refused = $this->lines->mayResize( $key, 4 );
		self::assertSame( CartRefusal::BuildRefused, $refused?->refusal );
		self::assertSame( array( array( 'part_short', 'deck', 103 ) ), self::problems( $refused ) );

		$this->cart->setQuantity( $loose, 8 );
		self::assertSame( 0, $this->lines->limit( $key ), 'more in the cart than the shop has is none, not fewer than none' );
		unset( $this->cart->lines[ $loose ] );

		$this->kits->now[302] = Shop::priced( Shop::parts()['wheels'][1], 3200, true, 1 );
		self::assertSame( 1, $this->lines->limit( $key ) );
		self::assertSame( array( array( 'part_short', 'wheels', 302 ) ), self::problems( $this->lines->mayResize( $key, 2 ) ) );
		self::assertSame( array( array( 'part_short', 'deck', 103 ), array( 'part_short', 'wheels', 302 ) ), self::problems( $this->lines->mayResize( $key, 5 ) ) );

		$this->kits->now[302] = false;
		self::assertSame( 4, $this->lines->limit( $key ), 'a part the shop no longer sells limits nothing, and the look over the cart says so: five decks, one in a line of its own' );

		unset( $this->kits->kits[ self::KIT ] );
		self::assertNull( $this->lines->limit( $key ), 'nor does a kit that no longer reads' );
		self::assertNull( $this->lines->mayResize( $key, 99 ) );
	}

	public function test_a_part_in_two_slots_is_counted_in_both(): void {
		// The fixture shop's grip slot is given the bolts as well, and a build takes them in both.
		$bolts                                      = Shop::priced( Shop::parts()['hardware'][0], 400, true, 7 );
		$this->kits->parts[ self::KIT ]['grip'][]   = $bolts;
		$this->kits->now[601]                       = $bolts;
		$build                                      = new Build( 'b', self::KIT, array( 'hardware' => 601, 'grip' => 601 ), array( 'hardware' => 2, 'grip' => 1 ), false );
		$key                                        = $this->cart->put( self::KIT, 1, $build->toArray() );
		$this->cart->add( 601, 2, $key, 'hardware' );
		$this->cart->add( 601, 1, $key, 'grip' );
		self::assertSame( 2, $this->lines->limit( $key ), 'seven bolts, three a build' );
	}

	public function test_the_cart_is_looked_over_before_it_is_paid_for(): void {
		$loose = $this->cart->put( 103 );
		$whole = $this->build( 2 );
		$kept  = array( $loose, $whole, ...$this->cart->lines()[ $whole ]->children );
		self::assertSame( 0, $this->lines->tidy()->dropped );
		self::assertSame( array(), $this->lines->tidy()->refused );
		self::assertSame( $kept, array_keys( $this->cart->lines() ) );

		// Each way a build comes apart: its kit's line gone; a part gone; a part that names another kit's line; a
		// part that is not the build's pick; a line that names the kit and is not one of its parts; a kit's line
		// that names a part twice.
		$broken   = array();
		$broken[] = $this->build();
		unset( $this->cart->lines[ $broken[0] ] );
		$broken[] = $this->build();
		unset( $this->cart->lines[ $this->cart->lines[ $broken[1] ]->children[4] ] );
		$broken[] = $this->build();
		$child    = $this->cart->lines[ $broken[2] ]->children[0];
		$this->cart->lines[ $child ] = new CartLine( $child, 103, 1, null, array(), $whole, 'deck' );
		$broken[] = $this->build();
		$child    = $this->cart->lines[ $broken[3] ]->children[0];
		$this->cart->lines[ $child ] = new CartLine( $child, 101, 1, null, array(), $broken[3], 'deck' );
		$broken[] = $this->build();
		$this->cart->add( 999, 1, $broken[4], 'deck' );
		$broken[] = $this->build();
		$children = $this->cart->lines[ $broken[5] ]->children;
		$extra    = $this->cart->add( 701, 1, $broken[5], 'grip' );
		$this->cart->setChildren( $broken[5], array( ...$children, (string) $extra ) );

		$tidied = $this->lines->tidy();
		self::assertSame( 7, $tidied->dropped, 'the six, and the build the stray part named' );
		self::assertSame( array( $loose ), array_keys( $this->cart->lines() ) );
	}

	public function test_a_whole_build_is_judged_again_as_the_shop_is_now(): void {
		$loose = $this->cart->put( 999 );
		$whole = $this->build( 2 );

		// A part's quantity that drifted is put right without a word.
		$deck                       = $this->cart->lines()[ $whole ]->children[0];
		$this->cart->lines[ $deck ] = new CartLine( $deck, 103, 7, null, array(), $whole, 'deck' );
		self::assertSame( array(), $this->lines->tidy()->refused );
		self::assertSame( 2, $this->cart->lines()[ $deck ]->quantity );

		// The shop changed since: the wheels sold out, and then the kit was taken down.
		$this->kits->now[302] = Shop::priced( Shop::parts()['wheels'][1], 3200, false );
		$tidied               = $this->lines->tidy();
		self::assertSame( 0, $tidied->dropped );
		self::assertSame( array( $whole ), array_keys( $tidied->refused ) );
		self::assertSame( CartRefusal::BuildRefused, $tidied->refused[ $whole ]->refusal );
		self::assertSame( array( array( 'part_sold_out', 'wheels', 302 ) ), self::problems( $tidied->refused[ $whole ] ) );
		self::assertCount( 8, $this->cart->lines(), 'the build stays for the shopper to change' );

		$this->kits->hidden = array( self::KIT );
		self::assertSame( CartRefusal::KitNotFound, $this->lines->tidy()->refused[ $whole ]->refusal );
		self::assertArrayHasKey( $loose, $this->cart->lines() );
	}

	public function test_a_build_in_the_cart_is_counted_once_when_it_is_judged_again(): void {
		$this->kits->now[103] = Shop::priced( Shop::parts()['deck'][2], 7000, true, 2 );
		$key                  = $this->build( 2 );
		self::assertSame( array(), $this->lines->tidy()->refused, 'two decks, and the cart holds two' );
		$this->cart->put( 103 );
		self::assertSame( array( array( 'part_short', 'deck', 103 ) ), self::problems( $this->lines->tidy()->refused[ $key ] ?? null ) );
		self::assertFalse( $this->builds->admits( self::KIT ), 'judging it again takes no new build' );
	}

	public function test_a_kits_line_whose_build_does_not_read_or_has_no_parts_is_dropped(): void {
		$this->cart->put( self::KIT, 1, array( 'id' => 'x' ) );
		self::assertSame( 1, $this->lines->tidy()->dropped );

		$forged = ( new Build( 'b', self::KIT, self::PICKS, array_fill_keys( array_keys( self::PICKS ), 1 ), false ) )->toArray();
		$this->cart->put( self::KIT, 1, $forged );
		$loose = $this->cart->put( 103 );
		self::assertSame( 1, $this->lines->tidy()->dropped, 'a build with no parts beside it' );
		self::assertSame( array( $loose ), array_keys( $this->cart->lines() ) );
	}
}
