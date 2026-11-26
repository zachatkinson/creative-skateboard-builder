<?php
/**
 * The build link: a build as the query parameters of a page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Guide\RidingStyle;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\GuideAnswers;
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

	public function test_a_filter_sent_from_a_step_stays_on_that_step_and_goes_no_further(): void {
		$filtered = BuildLink::read(
			array(
				'deck'   => '12',
				'_on'    => 'trucks',
				'_brand' => 'independent',
				'_size'  => '8.5in',
				'_shown' => '48',
			)
		);
		self::assertSame( 'trucks', $filtered->step, 'a filter pressed keeps the shopper on the step its form is' );
		self::assertSame( array( 'independent', '8.5in', 48 ), array( $filtered->brand, $filtered->size, $filtered->shown ) );

		$next = BuildLink::read(
			array(
				'deck'   => '12',
				'_on'    => 'trucks',
				'_size'  => '8.5in',
				'_shown' => '48',
				'step'   => 'wheels',
			)
		);
		self::assertSame( 'wheels', $next->step );
		self::assertSame( array( '', '', 0 ), array( $next->brand, $next->size, $next->shown ), "the trucks' filters are not the wheels'" );

		$same = BuildLink::read( array( '_on' => 'trucks', '_size' => '8.5in', 'step' => 'trucks' ) );
		self::assertSame( '8.5in', $same->size );
		self::assertNull( BuildLink::read( array( '_on' => 'Trucks!' ) )->step, 'a step that is no key is no step' );
		self::assertSame( '8.5in', BuildLink::read( array( '_size' => '8.5in', 'step' => 'trucks' ) )->size, 'an address the server wrote' );
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

	public function test_the_shelf_travels_as_its_width_chips_search_and_sort(): void {
		$sent = array(
			'deck'   => '12',
			'step'   => 'wheels',
			'_width' => '8.25in',
			'_q'     => "  Retna\tmint ",
			'_sort'  => 'price-asc',
			'_chips' => array(
				'brand'          => array( 'spitfire', 'bones' ),
				'wheel_diameter' => array( '54mm' ),
				'pa_hardness'    => array( '99a' ),
			),
		);
		$link = BuildLink::read( $sent );
		self::assertSame( '8.25in', $link->shelf->width );
		self::assertSame( 'Retna mint', $link->shelf->query, 'trimmed, a control character as a space' );
		self::assertSame( 'price-asc', $link->shelf->sort );
		self::assertSame( $sent['_chips'], $link->shelf->chips );
		self::assertFalse( $link->isClean() );
		self::assertSame(
			array(
				'deck'   => '12',
				'step'   => 'wheels',
				'_width' => '8.25in',
				'_q'     => 'Retna mint',
				'_sort'  => 'price-asc',
				'_chips' => array(
					'brand'          => array( 'spitfire', 'bones' ),
					'wheel_diameter' => array( '54mm' ),
					'pa_hardness'    => array( '99a' ),
				),
			),
			$link->query( self::slots() )
		);
		self::assertSame( array( 'deck' => '12', 'step' => 'trucks', '_width' => '8.25in' ), $link->at( 'trucks' )->query( self::slots() ), 'another step keeps the width, remembered for the visit (D-242), and nothing else of the shelf' );
		self::assertSame( array( 'deck' => '12', 'step' => 'wheels' ), $link->shared()->query( self::slots() ), 'nothing of the shelf is shared' );
		self::assertSame( 'Retna mint', $link->showing( 60 )->assembled( true )->after( array() )->replacing( 'a' )->pick( 'deck', 13 )->filtered( '', '' )->shelf->query, 'every other change keeps it' );
		self::assertSame( 0, $link->showing( 60 )->shelved( $link->shelf )->shown, 'a new shelf starts from its first page' );
	}

	/**
	 * @return iterable<string, array{array<string, mixed>, string, string}>
	 */
	public static function shelvesNotRead(): iterable {
		yield 'a width with no unit' => array( array( '_width' => '8.25' ), 'width', '' );
		yield 'a width in feet' => array( array( '_width' => '8ft' ), 'width', '' );
		yield 'a width as a list' => array( array( '_width' => array( '8in' ) ), 'width', '' );
		yield 'a sort of its own' => array( array( '_sort' => 'cheapest' ), 'sort', 'recommended' );
		yield 'a query that is no text' => array( array( '_q' => "\xff\xfe" ), 'query', '' );
		yield 'a query as a list' => array( array( '_q' => array( 'x' ) ), 'query', '' );
	}

	/**
	 * @param array<string, mixed> $sent What was sent.
	 */
	#[DataProvider( 'shelvesNotRead' )]
	public function test_what_is_no_shelf_is_left_out( array $sent, string $field, string $read ): void {
		self::assertSame( $read, BuildLink::read( $sent )->shelf->{$field} );
	}

	public function test_a_query_is_100_characters_and_the_chips_four_groups_of_twenty_at_most(): void {
		self::assertSame( str_repeat( 'é', 100 ), BuildLink::read( array( '_q' => str_repeat( 'é', 150 ) ) )->shelf->query, 'characters, not bytes' );
		$chips = array();
		foreach ( array( 'brand', 'pa_a', 'pa_b', 'pa_c', 'pa_d' ) as $group ) {
			$chips[ $group ] = array_map( static fn( int $i ): string => 'v' . $i, range( 1, 25 ) );
		}
		$chips['Bad Key'] = array( 'x' );
		$chips['pa_e']    = array( 'BAD VALUE', array( 'x' ), 'ok' );
		$read             = BuildLink::read( array( '_chips' => $chips ) )->shelf->chips;
		self::assertSame( array( 'brand', 'pa_a', 'pa_b', 'pa_c' ), array_keys( $read ) );
		self::assertCount( 20, $read['brand'] );
		self::assertSame( array( 'ok' ), BuildLink::read( array( '_chips' => array( 'pa_e' => array( 'BAD VALUE', array( 'x' ), 'ok', '' ) ) ) )->shelf->chips['pa_e'] );
		self::assertSame( array(), BuildLink::read( array( '_chips' => 'brand' ) )->shelf->chips );
		self::assertSame( array(), BuildLink::read( array( '_chips' => array( 'brand' => array() ) ) )->shelf->chips, 'a group with nothing chosen is no group' );
	}

	public function test_a_shelf_sent_from_another_step_goes_no_further(): void {
		$link = BuildLink::read(
			array(
				'step'   => 'trucks',
				'_on'    => 'wheels',
				'_width' => '8in',
				'_q'     => 'retna',
				'_chips' => array( 'brand' => array( 'bones' ) ),
			)
		);
		self::assertSame( array( '8in', '', array() ), array( $link->shelf->width, $link->shelf->query, $link->shelf->chips ), 'Next from wheels leaves its search and chips behind; the width is the visit\'s' );
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

	// Spec (configurator): Risers, "Anyone can add them": a link that names a step asks for it; a step's form does not,
	// since its Next was made before the form's own pick.
	public function test_only_a_link_that_names_a_step_asks_for_it(): void {
		self::assertTrue( BuildLink::read( array( 'step' => 'risers' ) )->asked );
		self::assertFalse( BuildLink::read( array() )->asked, 'a link with no step asks for none' );
		self::assertFalse( BuildLink::read( array( '_on' => 'risers' ) )->asked, 'a step\'s form stays on its step without asking for it' );
		self::assertFalse( BuildLink::read( array( 'step' => 'hardware', '_on' => 'risers' ) )->asked, 'nor does its Next' );
	}

	public function test_a_chip_group_with_a_name_of_its_own_is_left_out_and_the_groups_after_it_are_read(): void {
		self::assertSame(
			array( 'brand' => array( 'bones' ) ),
			BuildLink::read(
				array(
					'_chips' => array(
						'Bad-Group'   => array( 'x' ),
						'pa_hardness' => '99a',
						'brand'       => array( 'bones' ),
					),
				)
			)->shelf->chips
		);
	}

	public function test_a_query_that_is_not_utf_8_is_no_query_even_with_a_control_character_in_it(): void {
		self::assertSame( '', BuildLink::read( array( '_q' => "Retna\t\xff" ) )->shelf->query );
	}

	public function test_the_guided_starts_answers_travel_under_guide_and_change_nothing_else(): void {
		$link = BuildLink::read(
			array(
				'_guide' => array(
					'shoe'   => '2',
					'style'  => 'bowl',
					'budget' => '180',
					'asked'  => '1',
				),
			)
		);
		self::assertSame( array( 2, RidingStyle::Bowl, 180, true ), array( $link->guide->shoe, $link->guide->style, $link->guide->budget, $link->guide->asked ) );
		self::assertFalse( $link->isClean(), 'answers are a state' );
		self::assertSame( array( '_guide' => array( 'shoe' => '2', 'style' => 'bowl', 'budget' => '180', 'asked' => '1' ) ), $link->query( self::slots() ) );
		self::assertSame( array(), ( new BuildLink() )->query( self::slots() ) );
		$asked = BuildLink::read( array( '_guide' => array( 'asked' => '1' ) ) );
		self::assertSame( array( '_guide' => array( 'asked' => '1' ) ), $asked->query( self::slots() ), 'every answer left blank is still an answer' );
		self::assertSame( array( 'deck' => '12', 'step' => 'review', '_guide' => array( 'style' => 'street' ) ), BuildLink::read( array( 'deck' => '12', 'step' => 'review', '_guide' => array( 'style' => 'street' ) ) )->query( self::slots() ), 'after the build' );
		self::assertTrue( $link->at( 'deck' )->guide->isEmpty(), 'a step taken leaves the answers behind' );
		self::assertTrue( $link->pick( 'deck', 12 )->guide->isEmpty() );
		self::assertTrue( $link->shared()->guide->isEmpty(), 'and a shared link carries none' );
		self::assertSame( $link->guide, ( new BuildLink() )->guided( $link->guide )->guide );
	}

	/**
	 * Answers that are none.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function notAnswers(): array {
		return array(
			'not a list'         => array( 'street' ),
			'a shoe row too far' => array( array( 'shoe' => '12' ) ),
			'a shoe not a row'   => array( array( 'shoe' => '-1' ) ),
			'a shoe as 02'       => array( array( 'shoe' => '02' ) ),
			'a shoe of words'    => array( array( 'shoe' => 'ten' ) ),
			'a style not asked'  => array( array( 'style' => 'vert' ) ),
			'a style in capitals' => array( array( 'style' => 'Street' ) ),
			'a budget of none'   => array( array( 'budget' => '0' ) ),
			'a budget too long'  => array( array( 'budget' => '1000000' ) ),
			'a budget below'     => array( array( 'budget' => '-5' ) ),
			'a budget as a list' => array( array( 'budget' => array( '5' ) ) ),
			'asked as anything'  => array( array( 'asked' => 'yes' ) ),
		);
	}

	/**
	 * @param mixed $sent What was sent under `_guide`.
	 */
	#[DataProvider( 'notAnswers' )]
	public function test_what_is_no_answer_is_left_out( mixed $sent ): void {
		self::assertTrue( BuildLink::read( array( '_guide' => $sent ) )->isClean() );
		self::assertTrue( GuideAnswers::read( $sent )->isEmpty() );
	}

	/**
	 * Budgets as a shopper types them, forgiven (the auditor's 1.3.0 should-fix 4, D-433).
	 *
	 * @return array<string, array{string, int}>
	 */
	public static function typedBudgets(): array {
		return array(
			'a decimal amount, rounded up'   => array( '150.50', 151 ),
			'a decimal amount, rounded down' => array( '150.49', 150 ),
			'one decimal'                    => array( '99.5', 100 ),
			'a currency sign'                => array( '$150', 150 ),
			'a sign after, with a space'     => array( '150 €', 150 ),
			'a comma for thousands'          => array( '1,000', 1000 ),
			'a point for thousands'          => array( '1.000', 1000 ),
			'a space for thousands'          => array( '1 000', 1000 ),
			'a no-break space for thousands' => array( "1\u{00A0}000", 1000 ),
			'thousands and decimals'         => array( '$1,234.56', 1235 ),
			'French thousands and decimals'  => array( '1 234,49 $', 1234 ),
			'a decimal comma'                => array( '150,50', 151 ),
			'spaces around'                  => array( ' 150 ', 150 ),
			'a leading zero'                 => array( '0150', 150 ),
			'the most'                       => array( '999,999.49', 999999 ),
		);
	}

	#[DataProvider( 'typedBudgets' )]
	public function test_a_budget_typed_with_a_sign_separators_or_decimals_is_read( string $typed, int $budget ): void {
		$guide = GuideAnswers::read( array( 'budget' => $typed ) );
		self::assertSame( $budget, $guide->budget );
		self::assertFalse( $guide->budgetLeftOut );
	}

	/**
	 * Budgets that are no amount, even forgiven.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unreadBudgets(): array {
		return array(
			'words'                 => array( 'ten' ),
			'below nothing'         => array( '-5' ),
			'two decimal points'    => array( '1.2.3' ),
			'three decimals'        => array( '12.345.6' ),
			'thousands mixed'       => array( '1,000.000,5' ),
			'a range'               => array( '100-200' ),
			'too much'              => array( '1000000' ),
			'too much, rounded'     => array( '999,999.50' ),
			'a sign alone'          => array( '$' ),
			'too long to be typed'  => array( str_repeat( '1', 40 ) ),
		);
	}

	#[DataProvider( 'unreadBudgets' )]
	public function test_a_budget_that_is_no_amount_is_left_out_and_said_so( string $typed ): void {
		$guide = GuideAnswers::read(
			array(
				'budget' => $typed,
				'asked'  => '1',
			)
		);
		self::assertNull( $guide->budget );
		self::assertTrue( $guide->budgetLeftOut );
		self::assertSame( array( 'asked' => '1' ), $guide->query(), 'what is no amount is not carried in the link' );
		self::assertTrue( GuideAnswers::read( array( 'budget' => $typed ) )->isEmpty(), 'and is no answer of its own' );
	}

	public function test_an_empty_budget_is_any_and_is_not_said_to_be_left_out(): void {
		foreach ( array( '', ' ', '0' ) as $typed ) {
			$guide = GuideAnswers::read( array( 'budget' => $typed ) );
			self::assertNull( $guide->budget, $typed );
			self::assertFalse( $guide->budgetLeftOut, $typed );
		}
	}

	public function test_the_largest_answers_are_kept(): void {
		$guide = GuideAnswers::read( array( 'shoe' => '11', 'budget' => '999999' ) );
		self::assertSame( array( 11, 999999 ), array( $guide->shoe, $guide->budget ) );
		self::assertSame( 0, GuideAnswers::read( array( 'shoe' => '0' ) )->shoe );
		self::assertSame( 1, GuideAnswers::read( array( 'budget' => '1' ) )->budget );
	}

	public function test_a_swaps_link_carries_the_picks_its_undo_puts_back_and_no_link_made_from_it_does(): void {
		$link    = BuildLink::read(
			array(
				'deck'     => '12',
				'wheels'   => '31',
				'hardware' => '61',
				'step'     => 'review',
			)
		);
		$swapped = $link->pick( 'wheels', 30 )->pick( 'hardware', 60 )->undoing( $link->picks );
		self::assertSame(
			array(
				'deck'     => '12',
				'wheels'   => '30',
				'hardware' => '60',
				'step'     => 'review',
				'_undo'    => array(
					'deck'     => '12',
					'wheels'   => '31',
					'hardware' => '61',
				),
			),
			$swapped->query( self::slots() ),
			'in slot order, after the rest'
		);
		$read = BuildLink::read( $swapped->query( self::slots() ) );
		self::assertSame( array( 'deck' => 12, 'wheels' => 31, 'hardware' => 61 ), $read->undo, 'read back' );
		self::assertFalse( BuildLink::read( array( '_undo' => array( 'deck' => '1' ) ) )->isClean(), 'an undo alone is a state' );
		foreach ( array( 'at' => $read->at( 'review' ), 'pick' => $read->pick( 'grip', 7 ), 'assembled' => $read->assembled( true ), 'shared' => $read->shared(), 'after' => $read->after( array() ), 'filtered' => $read->filtered( 'a', '' ), 'showing' => $read->showing( 48 ), 'replacing' => $read->replacing( 'abc' ), 'shelved' => $read->shelved( new \CreativeBranding\SkateboardBuilder\Service\ShelfQuery() ), 'leave' => $read->leave( 'grip' ) ) as $made => $other ) {
			self::assertSame( array(), $other->undo, $made . ' leaves the undo behind: it is the swap\'s alone' );
		}
		foreach ( array( array( '_undo' => 'deck' ), array( '_undo' => array( 'deck' => 'x', 'Deck' => '1', 'trucks' => '0' ) ) ) as $sent ) {
			self::assertSame( array(), BuildLink::read( $sent )->undo, 'IDs alone, each a slot\'s' );
		}
		self::assertSame( array( 'deck' => '12' ), ( new BuildLink( undo: array( 'deck' => 12, 'bolts' => 8 ) ) )->query( self::slots() )['_undo'], 'a slot the kit lacks is left out' );
	}
}
