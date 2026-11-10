<?php
/**
 * Slot, SlotSources and Slots.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A kit's slots, from the slots document.
 */
final class SlotsTest extends TestCase {

	public function test_the_slot_keys_the_rules_know(): void {
		self::assertSame( 'deck', SlotKey::DECK );
		self::assertSame( 'trucks', SlotKey::TRUCKS );
		self::assertSame( 'wheels', SlotKey::WHEELS );
		self::assertSame( 'risers', SlotKey::RISERS );
		self::assertSame( 'bearings', SlotKey::BEARINGS );
		self::assertSame( 'hardware', SlotKey::HARDWARE );
		self::assertSame( 'grip', SlotKey::GRIP );
		self::assertSame( 'spacers', SlotKey::SPACERS );
	}

	public function test_the_complete_kits_slots_in_step_order(): void {
		$slots = Slots::fromDocument( KitDocuments::slots() );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'risers', 'bearings', 'hardware', 'grip' ), $slots->keys() );
		self::assertCount( 7, $slots->all() );
		self::assertSame( 'deck', $slots->all()[0]->key );
		self::assertSame( 'grip', $slots->all()[6]->key );
		$trucks = $slots->get( 'trucks' );
		self::assertInstanceOf( Slot::class, $trucks );
		self::assertSame( 'trucks', $trucks->key );
		self::assertSame( 'Trucks', $trucks->label );
		self::assertSame( '', $trucks->help );
		self::assertTrue( $trucks->required );
		self::assertSame( 2, $trucks->units );
		self::assertNull( $trucks->defaultPart );
		self::assertSame( array(), $trucks->sources->categories );
		self::assertSame( array(), $trucks->sources->brands );
		self::assertSame( array(), $trucks->sources->products );
		self::assertSame( array(), $trucks->sources->attributes );
		self::assertFalse( $slots->get( 'risers' )?->required );
		self::assertNull( $slots->get( 'spacers' ) );
	}

	public function test_a_slot_carries_everything_the_owner_set(): void {
		$document          = KitDocuments::slots();
		$document['slots'] = array(
			array(
				'key'      => 'deck',
				'label'    => 'Your deck',
				'help'     => 'Start with a width',
				'required' => true,
				'units'    => 1,
				'sources'  => array(
					'categories' => array( 14, 15 ),
					'brands'     => array( 3 ),
					'products'   => array( 812, 813, 814 ),
					'attributes' => array(
						array(
							'taxonomy' => 'pa_width',
							'terms'    => array( 21, 22 ),
						),
						array(
							'taxonomy' => 'pa_shape',
							'terms'    => array(),
						),
					),
				),
				'default'  => 812,
			),
		);
		$deck              = Slots::fromDocument( $document )->get( 'deck' );
		self::assertSame( 'Your deck', $deck?->label );
		self::assertSame( 'Start with a width', $deck->help );
		self::assertSame( 812, $deck->defaultPart );
		self::assertSame( array( 14, 15 ), $deck->sources->categories );
		self::assertSame( array( 3 ), $deck->sources->brands );
		self::assertSame( array( 812, 813, 814 ), $deck->sources->products );
		self::assertSame(
			array(
				array(
					'taxonomy' => 'pa_width',
					'terms'    => array( 21, 22 ),
				),
				array(
					'taxonomy' => 'pa_shape',
					'terms'    => array(),
				),
			),
			$deck->sources->attributes
		);
	}

	public function test_units_run_from_one_to_ninety_nine(): void {
		self::assertSame( 99, Slot::MAX_UNITS );
		foreach ( array( 1, 99 ) as $units ) {
			$document                      = KitDocuments::slots();
			$document['slots'][1]['units'] = $units;
			self::assertSame( $units, Slots::fromDocument( $document )->get( 'trucks' )?->units );
		}
	}

	public function test_a_kit_has_up_to_twenty_slots(): void {
		self::assertSame( 20, Slots::MAX );
		$document = KitDocuments::slots();
		for ( $i = count( $document['slots'] ); $i < 20; $i++ ) {
			$document['slots'][] = array(
				'key'      => 'extra_' . $i,
				'label'    => 'Extra',
				'required' => false,
				'units'    => 1,
			);
		}
		self::assertCount( 20, Slots::fromDocument( $document )->all() );
		$document['slots'][] = array(
			'key'      => 'one_too_many',
			'label'    => 'Extra',
			'required' => false,
			'units'    => 1,
		);
		try {
			Slots::fromDocument( $document );
			self::fail( 'accepted 21 slots' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( DocumentError::OutOfRange, $e->reason );
			self::assertSame( 'slots', $e->path );
		}
	}

	public function test_before_and_after_follow_step_order(): void {
		$slots = Slots::fromDocument( KitDocuments::slots() );
		self::assertSame( array(), $slots->before( 'deck' ) );
		self::assertSame( array( 'deck' ), $slots->before( 'trucks' ) );
		self::assertSame( array( 'deck', 'trucks', 'wheels' ), $slots->before( 'risers' ) );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'risers', 'bearings', 'hardware' ), $slots->before( 'grip' ) );
		self::assertSame( array(), $slots->before( 'spacers' ), 'a slot the kit lacks has nothing before it' );
		self::assertSame( array( 'trucks', 'wheels', 'risers', 'bearings', 'hardware', 'grip' ), $slots->after( 'deck' ) );
		self::assertSame( array( 'grip' ), $slots->after( 'hardware' ) );
		self::assertSame( array(), $slots->after( 'grip' ) );
		self::assertSame( array(), $slots->after( 'spacers' ), 'nor anything after it' );
	}

	/**
	 * @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, DocumentError, string}>
	 */
	public static function refused(): iterable {
		$bend = static fn( int $at, string $field, mixed $value ): callable => static function ( array $d ) use ( $at, $field, $value ): array {
			$d['slots'][ $at ][ $field ] = $value;
			return $d;
		};
		$drop = static fn( int $at, string $field ): callable => static function ( array $d ) use ( $at, $field ): array {
			unset( $d['slots'][ $at ][ $field ] );
			return $d;
		};
		yield 'no slots' => array( static fn( array $d ): array => array( 'slots' => array() ) + $d, DocumentError::OutOfRange, 'slots' );
		yield 'slots left out' => array( static fn( array $d ): array => array( 'schema_version' => 1 ), DocumentError::OutOfRange, 'slots' );
		yield 'another version' => array( static fn( array $d ): array => array( 'schema_version' => 2 ) + $d, DocumentError::UnknownVersion, 'schema_version' );
		yield 'a key repeated' => array( $bend( 3, 'key', 'trucks' ), DocumentError::Duplicate, 'slots[3].key' );
		yield 'the deck not first' => array( static fn( array $d ): array => array( 'slots' => array_reverse( $d['slots'] ) ) + $d, DocumentError::DeckNotFirst, 'slots[0].key' );
		yield 'no deck at all' => array( $bend( 0, 'key', 'board' ), DocumentError::DeckNotFirst, 'slots[0].key' );
		yield 'a key that is not one' => array( $bend( 2, 'key', 'Wheels' ), DocumentError::BadKey, 'slots[2].key' );
		yield 'no key' => array( $drop( 2, 'key' ), DocumentError::Missing, 'slots[2].key' );
		yield 'no label' => array( $drop( 1, 'label' ), DocumentError::Missing, 'slots[1].label' );
		yield 'a label that is a number' => array( $bend( 1, 'label', 7 ), DocumentError::WrongType, 'slots[1].label' );
		yield 'help that is a number' => array( $bend( 1, 'help', 7 ), DocumentError::WrongType, 'slots[1].help' );
		yield 'required left out' => array( $drop( 4, 'required' ), DocumentError::Missing, 'slots[4].required' );
		yield 'required as a word' => array( $bend( 4, 'required', 'yes' ), DocumentError::WrongType, 'slots[4].required' );
		yield 'no units' => array( $drop( 1, 'units' ), DocumentError::Missing, 'slots[1].units' );
		yield 'no units of anything' => array( $bend( 1, 'units', 0 ), DocumentError::OutOfRange, 'slots[1].units' );
		yield 'a hundred units' => array( $bend( 1, 'units', 100 ), DocumentError::OutOfRange, 'slots[1].units' );
		yield 'units as text' => array( $bend( 1, 'units', '2' ), DocumentError::WrongType, 'slots[1].units' );
		yield 'a default that is not an id' => array( $bend( 1, 'default', 0 ), DocumentError::OutOfRange, 'slots[1].default' );
		yield 'sources that are a list' => array( $bend( 1, 'sources', array( 1 ) ), DocumentError::NotAnObject, 'slots[1].sources' );
		yield 'a category that is not an id' => array( $bend( 1, 'sources', array( 'categories' => array( 'trucks' ) ) ), DocumentError::WrongType, 'slots[1].sources.categories[0]' );
		yield 'a brand that is not an id' => array( $bend( 1, 'sources', array( 'brands' => array( 0 ) ) ), DocumentError::OutOfRange, 'slots[1].sources.brands[0]' );
		yield 'a product that is not an id' => array( $bend( 1, 'sources', array( 'products' => array( 5, -5 ) ) ), DocumentError::OutOfRange, 'slots[1].sources.products[1]' );
		yield 'an attribute with no taxonomy' => array( $bend( 1, 'sources', array( 'attributes' => array( array( 'terms' => array( 1 ) ) ) ) ), DocumentError::Missing, 'slots[1].sources.attributes[0].taxonomy' );
		yield 'an attribute term that is not an id' => array( $bend( 1, 'sources', array( 'attributes' => array( array( 'taxonomy' => 'pa_size', 'terms' => array( 'x' ) ) ) ) ), DocumentError::WrongType, 'slots[1].sources.attributes[0].terms[0]' );
		yield 'twenty-one attributes' => array( $bend( 1, 'sources', array( 'attributes' => array_fill( 0, 21, array( 'taxonomy' => 'pa_size' ) ) ) ), DocumentError::OutOfRange, 'slots[1].sources.attributes' );
	}

	/**
	 * @param callable(array<string, mixed>): array<string, mixed> $bend
	 */
	#[DataProvider( 'refused' )]
	public function test_a_slots_document_that_breaks_an_invariant_is_refused( callable $bend, DocumentError $reason, string $path ): void {
		try {
			Slots::fromDocument( $bend( KitDocuments::slots() ) );
			self::fail( 'accepted' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $path, $e->path );
		}
	}

	public function test_twenty_attributes_are_allowed(): void {
		self::assertSame( 20, \CreativeBranding\SkateboardBuilder\Domain\Kit\SlotSources::MAX_ATTRIBUTES );
		$document                        = KitDocuments::slots();
		$document['slots'][1]['sources'] = array( 'attributes' => array_fill( 0, 20, array( 'taxonomy' => 'pa_size' ) ) );
		self::assertCount( 20, Slots::fromDocument( $document )->get( 'trucks' )?->sources->attributes ?? array() );
	}

	public function test_a_duplicate_is_refused_before_the_deck_rule_is_read_for_it(): void {
		// The second row repeats the first: it is a duplicate, and only the first row is held to "deck first".
		$document             = KitDocuments::slots();
		$document['slots'][1] = $document['slots'][0];
		try {
			Slots::fromDocument( $document );
			self::fail( 'accepted' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( DocumentError::Duplicate, $e->reason );
			self::assertSame( 'slots[1].key', $e->path );
		}
	}

	public function test_only_the_first_slot_must_be_the_deck(): void {
		$slots = Slots::fromDocument( KitDocuments::slots() );
		self::assertSame( 'trucks', $slots->all()[1]->key );
	}
}
