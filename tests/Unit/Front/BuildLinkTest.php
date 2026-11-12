<?php
/**
 * The build link: a build as the query parameters of a page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Upgrades and the public API: `cbuild[<slot key>]=<part ID>`, with `cbuild[step]`, `cbuild[assembly]` and
 * `cbuild[preset]`; SEO and sharing: written in slot order, so one build has one URL; Security: a link carries IDs
 * only, and nothing of it is trusted.
 */
final class BuildLinkTest extends TestCase {

	private static function slots(): Slots {
		return Slots::fromDocument( KitDocuments::slots() );
	}

	public function test_a_page_with_no_build_in_its_address_is_the_clean_page(): void {
		foreach ( array( null, '', 'deck', 7, array(), array( 'step' => array( 'x' ) ), array( 7 => '12' ), array( 'Deck' => '12' ) ) as $sent ) {
			$link = BuildLink::read( $sent );
			self::assertTrue( $link->isClean() );
			self::assertSame( array(), $link->query( self::slots() ) );
		}
		self::assertTrue( ( new BuildLink() )->isClean() );
		self::assertSame( 0, ( new BuildLink() )->shown );
		self::assertFalse( BuildLink::read( array( 'step' => 'deck' ) )->isClean(), 'a step alone is a state' );
		foreach ( array( array( 'assembly' => '0' ), array( 'deck' => '1' ), array( '_was' => array( 'deck' => '1' ) ), array( 'preset' => '0' ), array( '_brand' => 'a' ), array( '_size' => '8' ), array( '_shown' => '48' ), array( '_replace' => 'a' ) ) as $sent ) {
			self::assertFalse( BuildLink::read( $sent )->isClean(), (string) key( $sent ) );
		}
	}

	public function test_a_link_is_read_as_picks_a_step_assembly_and_a_preset(): void {
		$link = BuildLink::read(
			array(
				'trucks'   => '21',
				'deck'     => '12',
				'step'     => 'wheels',
				'assembly' => '1',
				'preset'   => '2',
			)
		);
		self::assertSame( array( 'trucks' => 21, 'deck' => 12 ), $link->picks );
		self::assertSame( 'wheels', $link->step );
		self::assertTrue( $link->assembly );
		self::assertSame( 2, $link->preset );
		self::assertNull( $link->was );
		self::assertSame( array( '', '', 0, '' ), array( $link->brand, $link->size, $link->shown, $link->replace ) );

		self::assertFalse( BuildLink::read( array( 'assembly' => '0' ) )->assembly );
		self::assertSame( 'review', BuildLink::read( array( 'step' => 'review' ) )->step );
		self::assertSame( 0, BuildLink::read( array( 'preset' => '0' ) )->preset );
	}

	public function test_a_link_is_written_in_slot_order_so_one_build_has_one_address(): void {
		$sent = array(
			'_shown'   => '48',
			'preset'   => '3',
			'assembly' => '0',
			'wheels'   => '31',
			'step'     => 'bearings',
			'trucks'   => '21',
			'_was'     => array(
				'wheels' => '30',
				'deck'   => '12',
			),
			'deck'     => '12',
			'_brand'   => 'spitfire',
			'_size'    => '54',
			'_replace' => '0b9c4f6e-58a1-4d0e-9d57-3c1f2a6b7e10',
		);
		$want = array(
			'deck'     => '12',
			'trucks'   => '21',
			'wheels'   => '31',
			'step'     => 'bearings',
			'assembly' => '0',
			'preset'   => '3',
			'_was'     => array(
				'deck'   => '12',
				'wheels' => '30',
			),
			'_brand'   => 'spitfire',
			'_size'    => '54',
			'_shown'   => '48',
			'_replace' => '0b9c4f6e-58a1-4d0e-9d57-3c1f2a6b7e10',
		);
		self::assertSame( $want, BuildLink::read( $sent )->query( self::slots() ) );
		self::assertSame( $want, BuildLink::read( $want )->query( self::slots() ), 'and read back, it is itself' );
		self::assertSame( 'cbuild', BuildLink::PARAM );
	}

	public function test_a_slot_the_kit_does_not_have_is_left_out_of_what_is_written(): void {
		$link = BuildLink::read(
			array(
				'deck'  => '12',
				'bolts' => '9',
				'step'  => 'bolts',
				'_was'  => array( 'bolts' => '8' ),
			)
		);
		self::assertSame( array( 'deck' => 12, 'bolts' => 9 ), $link->picks, 'read as sent, for the state to refuse by name' );
		self::assertSame( array( 'deck' => '12' ), $link->query( self::slots() ) );
	}

	/**
	 * What is sent that is no part of a link, and what the link is without it.
	 *
	 * @return iterable<string, array{array<mixed>, array<string, mixed>}>
	 */
	public static function untrusted(): iterable {
		yield 'an ID that is no whole number' => array(
			array(
				'deck'     => '-0',
				'trucks'   => '-4',
				'wheels'   => '1e3',
				'bearings' => '012',
				'hardware' => '7.0',
				'grip'     => array( '7' ),
				'risers'   => ' 7',
			),
			array(),
		);
		yield 'an ID sent as a number' => array( array( 'deck' => 12 ), array( 'deck' => '12' ) );
		yield 'an ID past what a post can have' => array( array( 'deck' => '99999999999999999999' ), array() );
		yield 'a step that is no key' => array( array( 'step' => 'Wheels <b>' ), array() );
		yield 'assembly that is neither' => array( array( 'assembly' => 'yes' ), array() );
		yield 'a preset the kit cannot have' => array( array( 'preset' => '6' ), array() );
		yield 'a preset that is no number' => array( array( 'preset' => 'x' ), array() );
		yield 'what was picked, sent as text' => array( array( '_was' => 'deck' ), array() );
		yield 'what was picked, with an ID that is none' => array( array( '_was' => array( 'deck' => 'x' ) ), array() );
		yield 'a brand that is no slug' => array( array( '_brand' => 'Spit fire"' ), array() );
		yield 'a brand too long to be one' => array( array( '_brand' => str_repeat( 'a', 201 ) ), array() );
		yield 'a size that is no value' => array( array( '_size' => '<8.25>' ), array() );
		yield 'more shown than a slot can hold' => array( array( '_shown' => '99999' ), array( '_shown' => '500' ) );
		yield 'fewer shown than a step shows' => array( array( '_shown' => '3' ), array() );
		yield 'as many shown as a step shows' => array( array( '_shown' => '24' ), array() );
		yield 'one more shown than a step shows' => array( array( '_shown' => '25' ), array( '_shown' => '25' ) );
		yield 'a build to replace that is no identifier' => array( array( '_replace' => '../x' ), array() );
		yield 'a key of the plugin\'s own that it does not have' => array( array( '_other' => '1' ), array() );
	}

	/**
	 * @param array<mixed>         $sent  What was sent.
	 * @param array<string, mixed> $wrote What the link is.
	 */
	#[DataProvider( 'untrusted' )]
	public function test_what_is_no_part_of_a_link_is_left_out( array $sent, array $wrote ): void {
		self::assertSame( $wrote, BuildLink::read( $sent )->query( self::slots() ) );
	}

	public function test_no_more_picks_are_read_than_a_kit_can_have_slots(): void {
		$sent = array();
		for ( $n = 0; $n < 30; $n++ ) {
			$sent[ 'slot_' . $n ] = '5';
		}
		self::assertCount( Slots::MAX, BuildLink::read( $sent )->picks );
		self::assertCount( Slots::MAX, BuildLink::read( array( '_was' => $sent ) )->was ?? array() );
	}

	public function test_a_link_is_changed_by_making_another(): void {
		$link = BuildLink::read(
			array(
				'deck'   => '12',
				'trucks' => '21',
				'step'   => 'trucks',
				'_brand' => 'thunder',
				'_shown' => '48',
			)
		);
		$next = $link->pick( 'trucks', 22 )->at( 'wheels' );
		self::assertSame(
			array(
				'deck'   => '12',
				'trucks' => '22',
				'step'   => 'wheels',
			),
			$next->query( self::slots() ),
			'another step starts with no filter and the first options'
		);
		self::assertSame( 0, $next->shown );
		self::assertSame( 0, $link->filtered( '', '149' )->shown );
		self::assertSame( array( 'deck' => '12', 'trucks' => '21', 'step' => 'trucks', '_brand' => 'thunder', '_shown' => '48' ), $link->query( self::slots() ), 'and the first is as it was' );
		self::assertSame( array( 'deck' => '12', 'step' => 'trucks', '_brand' => 'thunder', '_shown' => '48' ), $link->pick( 'trucks', null )->query( self::slots() ) );
		self::assertSame( array( 'deck' => '12', 'trucks' => '21', 'step' => 'trucks', '_brand' => 'thunder', '_shown' => '72' ), $link->showing( 72 )->query( self::slots() ) );
		self::assertSame( array( 'deck' => '12', 'trucks' => '21', 'step' => 'trucks', '_size' => '149' ), $link->filtered( '', '149' )->query( self::slots() ), 'a filter starts from the first options' );
		self::assertSame( array( 'deck' => '12', 'trucks' => '21', 'step' => 'trucks', 'assembly' => '1', '_brand' => 'thunder', '_shown' => '48' ), $link->assembled( true )->query( self::slots() ) );
		self::assertSame( array( 'deck' => '12', 'trucks' => '21', 'step' => 'trucks', '_was' => array( 'deck' => '11' ), '_brand' => 'thunder', '_shown' => '48' ), $link->after( array( 'deck' => 11 ) )->query( self::slots() ) );
		self::assertSame( array( 'deck' => '12', 'trucks' => '21', 'step' => 'trucks', '_brand' => 'thunder', '_shown' => '48', '_replace' => 'abc-1' ), $link->replacing( 'abc-1' )->query( self::slots() ) );
		self::assertSame( array( 'deck' => '12', 'trucks' => '21', 'step' => 'trucks' ), $link->after( array( 'deck' => 11 ) )->replacing( 'abc-1' )->shared()->query( self::slots() ), 'what is shared is the build and its step, as the spec has it, and nothing of the visit' );
	}

	public function test_a_slot_sent_as_nothing_is_left_empty_by_choice_and_written_back_in_its_place(): void {
		$link = BuildLink::read(
			array(
				'risers' => '0',
				'deck'   => '12',
				'wheels' => 0,
				'step'   => '0',
				'Deck'   => '0',
				'_was'   => array( 'trucks' => '0' ),
			)
		);
		self::assertSame( array( 'risers', 'wheels' ), $link->none, 'A slot\'s key alone, and never one of the words the address keeps.' );
		self::assertSame( array( 'deck' => 12 ), $link->picks );
		self::assertNull( $link->was );
		self::assertFalse( BuildLink::read( array( 'risers' => '0' ) )->isClean() );
		self::assertSame( array(), BuildLink::read( array( 'risers' => '00' ) )->none );
		self::assertSame(
			array(
				'deck'   => '12',
				'wheels' => '0',
				'risers' => '0',
			),
			$link->query( self::slots() ),
			'In the slots\' order, among the picks.'
		);
		self::assertSame( array(), ( new BuildLink( none: array( 'stickers' ) ) )->query( self::slots() ), 'A slot the kit lacks is left out.' );
		self::assertSame( array( 'risers' => '7' ), ( new BuildLink( array( 'risers' => 7 ), none: array( 'risers' ) ) )->query( self::slots() ), 'A pick stands before a leave.' );
	}

	public function test_leaving_a_slot_takes_its_pick_out_and_picking_takes_it_back(): void {
		$link = ( new BuildLink( array( 'deck' => 12, 'risers' => 41 ), step: 'risers', assembly: true, preset: 1, brand: 'a', size: '8in', shown: 48, replace: 'ab' ) )->leave( 'risers' );
		self::assertSame( array( 'deck' => 12 ), $link->picks );
		self::assertSame( array( 'risers' ), $link->none );
		self::assertSame( array( 'risers', true, 1, 'a', '8in', 48, 'ab' ), array( $link->step, $link->assembly, $link->preset, $link->brand, $link->size, $link->shown, $link->replace ) );
		self::assertSame( array( 'risers', 'wheels' ), $link->leave( 'wheels' )->none );
		self::assertSame( array( 'risers' ), $link->leave( 'risers' )->none, 'Once.' );

		$both = $link->leave( 'wheels' );
		self::assertSame( array( 'wheels' ), $both->pick( 'risers', 42 )->none );
		self::assertSame( array( 'wheels' ), $both->pick( 'risers', null )->none, 'Taken out is not left by choice.' );
		self::assertSame( array( 0 ), array_keys( $both->pick( 'risers', null )->none ) );

		// Every other change keeps what was left, a shared link too: it is part of the build.
		foreach ( array( $both->at( 'deck' ), $both->filtered( 'b', '' ), $both->showing( 72 ), $both->assembled( false ), $both->after( array( 'deck' => 11 ) ), $both->replacing( 'cd' ), $both->shared() ) as $made ) {
			self::assertSame( array( 'risers', 'wheels' ), $made->none );
		}
	}

	public function test_no_more_slots_are_left_than_a_kit_can_have(): void {
		$sent = array();
		for ( $n = 0; $n < Slots::MAX + 5; $n++ ) {
			$sent[ 'slot_' . $n ] = '0';
		}
		self::assertCount( Slots::MAX, BuildLink::read( $sent )->none );
	}
}
