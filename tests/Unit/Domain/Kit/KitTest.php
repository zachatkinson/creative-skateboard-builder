<?php
/**
 * Kit, Preset and Assembly.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Assembly;
use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A kit from its four documents.
 */
final class KitTest extends TestCase {

	/**
	 * A preset that fills every required slot of the complete kit.
	 *
	 * @return array<string, mixed>
	 */
	private static function preset(): array {
		return array(
			'name'        => 'Street',
			'description' => 'A first board for the park and the street',
			'picks'       => array(
				'deck'     => 11,
				'trucks'   => 22,
				'wheels'   => 33,
				'bearings' => 55,
				'hardware' => 66,
				'grip'     => 77,
			),
		);
	}

	/**
	 * @param list<array<string, mixed>> $presets
	 * @return array<string, mixed>
	 */
	private static function presets( array $presets ): array {
		return array(
			'schema_version' => 1,
			'presets'        => $presets,
		);
	}

	public function test_a_kit_holds_its_four_documents(): void {
		$rules                  = KitDocuments::rules();
		$rules['r2']['enabled'] = false;
		$assembly               = array( 'default_on' => false, 'fee' => '15', 'lead_time' => 'Two working days', 'returns_notice' => 'Assembled boards are final sale' ) + KitDocuments::assembly();
		$kit                    = KitDocuments::kit( null, $rules, self::presets( array( self::preset() ) ), $assembly );
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'risers', 'bearings', 'hardware', 'grip' ), $kit->slots->keys() );
		self::assertFalse( $kit->rules->enabled( RuleId::R2 ) );
		self::assertTrue( $kit->rules->enabled( RuleId::R1 ) );
		self::assertCount( 1, $kit->presets );
		self::assertSame( 'Street', $kit->presets[0]->name );
		self::assertSame( 'A first board for the park and the street', $kit->presets[0]->description );
		self::assertSame( array( 'deck' => 11, 'trucks' => 22, 'wheels' => 33, 'bearings' => 55, 'hardware' => 66, 'grip' => 77 ), $kit->presets[0]->picks );
		self::assertFalse( $kit->assembly->defaultOn );
		self::assertSame( 1500, $kit->assembly->fee( 2 ) );
		self::assertSame( 'Two working days', $kit->assembly->leadTime );
		self::assertSame( 'Assembled boards are final sale', $kit->assembly->returnsNotice );
	}

	public function test_a_kit_needs_no_presets_and_holds_up_to_six(): void {
		self::assertSame( 6, Kit::MAX_PRESETS );
		self::assertSame( array(), KitDocuments::kit()->presets );
		self::assertSame( array(), KitDocuments::kit( null, null, array( 'schema_version' => 1 ) )->presets, 'the list may be left out' );
		self::assertCount( 6, KitDocuments::kit( null, null, self::presets( array_fill( 0, 6, self::preset() ) ) )->presets );
	}

	public function test_a_preset_may_fill_an_optional_slot_and_may_leave_out_its_description(): void {
		$preset                    = self::preset();
		$preset['picks']['risers'] = 44;
		$preset['picks']['grip']   = 1;
		unset( $preset['description'] );
		$kit = KitDocuments::kit( null, null, self::presets( array( $preset ) ) );
		self::assertSame( 44, $kit->presets[0]->picks['risers'] );
		self::assertSame( 1, $kit->presets[0]->picks['grip'], 'the first id there is' );
		self::assertSame( '', $kit->presets[0]->description );
	}

	/**
	 * @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, DocumentError, string}>
	 */
	public static function refusedPresets(): iterable {
		yield 'no name' => array(
			static function ( array $p ): array {
				unset( $p['name'] );
				return $p;
			},
			DocumentError::Missing,
			'presets[1].name',
		);
		yield 'a name that is a number' => array( static fn( array $p ): array => array( 'name' => 4 ) + $p, DocumentError::WrongType, 'presets[1].name' );
		yield 'a description that is a number' => array( static fn( array $p ): array => array( 'description' => 4 ) + $p, DocumentError::WrongType, 'presets[1].description' );
		yield 'no picks' => array(
			static function ( array $p ): array {
				unset( $p['picks'] );
				return $p;
			},
			DocumentError::Missing,
			'presets[1].picks',
		);
		yield 'picks as a list' => array( static fn( array $p ): array => array( 'picks' => array( 11, 22 ) ) + $p, DocumentError::NotAnObject, 'presets[1].picks' );
		yield 'a slot key that is not one' => array( static fn( array $p ): array => array( 'picks' => $p['picks'] + array( 'Grip Tape' => 5 ) ) + $p, DocumentError::BadKey, 'presets[1].picks.Grip Tape' );
		yield 'a part that is not an id' => array( static fn( array $p ): array => array( 'picks' => array( 'trucks' => '22' ) + $p['picks'] ) + $p, DocumentError::WrongType, 'presets[1].picks.trucks' );
		yield 'a part of none' => array( static fn( array $p ): array => array( 'picks' => array( 'trucks' => 0 ) + $p['picks'] ) + $p, DocumentError::OutOfRange, 'presets[1].picks.trucks' );
		yield 'a slot the kit lacks' => array( static fn( array $p ): array => array( 'picks' => $p['picks'] + array( 'spacers' => 5 ) ) + $p, DocumentError::UnknownSlot, 'presets[1].picks.spacers' );
		yield 'a required slot left empty' => array(
			static function ( array $p ): array {
				unset( $p['picks']['hardware'] );
				return $p;
			},
			DocumentError::SlotNotFilled,
			'presets[1].picks.hardware',
		);
		yield 'no deck' => array(
			static function ( array $p ): array {
				unset( $p['picks']['deck'] );
				return $p;
			},
			DocumentError::SlotNotFilled,
			'presets[1].picks.deck',
		);
	}

	/**
	 * @param callable(array<string, mixed>): array<string, mixed> $bend
	 */
	#[DataProvider( 'refusedPresets' )]
	public function test_a_preset_that_breaks_an_invariant_is_refused( callable $bend, DocumentError $reason, string $path ): void {
		try {
			KitDocuments::kit( null, null, self::presets( array( self::preset(), $bend( self::preset() ) ) ) );
			self::fail( 'accepted' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $path, $e->path );
		}
	}

	public function test_seven_presets_are_one_too_many(): void {
		try {
			KitDocuments::kit( null, null, self::presets( array_fill( 0, 7, self::preset() ) ) );
			self::fail( 'accepted' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( DocumentError::OutOfRange, $e->reason );
			self::assertSame( 'presets', $e->path );
		}
	}

	/**
	 * @return iterable<string, array{int, mixed, DocumentError, string}>
	 */
	public static function badDocuments(): iterable {
		yield 'slots of another version' => array( 0, array( 'schema_version' => 2 ) + KitDocuments::slots(), DocumentError::UnknownVersion, 'schema_version' );
		yield 'rules that are not a document' => array( 1, 'x', DocumentError::NotAnObject, '' );
		yield 'presets of another version' => array( 2, array( 'schema_version' => 2 ), DocumentError::UnknownVersion, 'schema_version' );
		yield 'assembly that is not a document' => array( 3, null, DocumentError::NotAnObject, '' );
	}

	#[DataProvider( 'badDocuments' )]
	public function test_each_document_is_checked( int $which, mixed $document, DocumentError $reason, string $path ): void {
		$documents           = array( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly() );
		$documents[ $which ] = $document;
		try {
			Kit::fromDocuments( ...$documents );
			self::fail( 'accepted' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $path, $e->path );
		}
	}

	/**
	 * @return iterable<string, array{string, int, int}>
	 */
	public static function fees(): iterable {
		yield 'free' => array( '0', 2, 0 );
		yield 'dollars' => array( '15', 2, 1500 );
		yield 'dollars and cents' => array( '15.5', 2, 1550 );
		yield 'yen' => array( '1500', 0, 1500 );
		yield 'yen with a stray half rounds up' => array( '1500.5', 0, 1501 );
		yield 'yen just under a half rounds down' => array( '1500.4999', 0, 1500 );
		yield 'a third decimal of half rounds up' => array( '15.555', 2, 1556 );
		yield 'a third decimal under half rounds down' => array( '15.5549', 2, 1555 );
		yield 'one decimal' => array( '15.55', 1, 156 );
		yield 'dinars' => array( '15.5555', 3, 15556 );
		yield 'four decimals' => array( '15.5555', 4, 155555 );
		yield 'more decimals than are kept reads as four' => array( '15.5555', 9, 155555 );
		yield 'fewer than none reads as none' => array( '15.5', -1, 16 );
	}

	#[DataProvider( 'fees' )]
	public function test_the_fee_in_minor_units( string $fee, int $decimals, int $minor ): void {
		self::assertSame( 4, Assembly::MAX_DECIMALS );
		self::assertSame( $minor, Assembly::fromDocument( array( 'fee' => $fee ) + KitDocuments::assembly() )->fee( $decimals ) );
	}

	public function test_assembly_reads_each_field(): void {
		$on = Assembly::fromDocument( KitDocuments::assembly() );
		self::assertTrue( $on->defaultOn );
		self::assertSame( '', $on->leadTime );
		self::assertSame( '', $on->returnsNotice );
		$bare = Assembly::fromDocument( array( 'schema_version' => 1, 'default_on' => false, 'fee' => '0' ) );
		self::assertFalse( $bare->defaultOn );
		self::assertSame( '', $bare->leadTime );
		self::assertSame( '', $bare->returnsNotice );
	}

	/**
	 * @return iterable<string, array{array<string, mixed>, DocumentError, string}>
	 */
	public static function refusedAssembly(): iterable {
		yield 'another version' => array( array( 'schema_version' => 2 ), DocumentError::UnknownVersion, 'schema_version' );
		yield 'the default as a word' => array( array( 'default_on' => 'yes' ), DocumentError::WrongType, 'default_on' );
		yield 'the fee as a number' => array( array( 'fee' => 15 ), DocumentError::WrongType, 'fee' );
		yield 'the fee with a sign' => array( array( 'fee' => '$15' ), DocumentError::BadMeasure, 'fee' );
		yield 'a negative fee' => array( array( 'fee' => '-15' ), DocumentError::BadMeasure, 'fee' );
		yield 'a lead time that is a number' => array( array( 'lead_time' => 2 ), DocumentError::WrongType, 'lead_time' );
		yield 'a notice that is a list' => array( array( 'returns_notice' => array() ), DocumentError::WrongType, 'returns_notice' );
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	#[DataProvider( 'refusedAssembly' )]
	public function test_an_assembly_document_that_breaks_an_invariant_is_refused( array $fields, DocumentError $reason, string $path ): void {
		try {
			Assembly::fromDocument( $fields + KitDocuments::assembly() );
			self::fail( 'accepted' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $path, $e->path );
		}
	}

	public function test_assembly_needs_its_default_and_its_fee(): void {
		foreach ( array( 'default_on', 'fee' ) as $field ) {
			$document = KitDocuments::assembly();
			unset( $document[ $field ] );
			try {
				Assembly::fromDocument( $document );
				self::fail( 'accepted without ' . $field );
			} catch ( InvalidDocument $e ) {
				self::assertSame( DocumentError::Missing, $e->reason );
				self::assertSame( $field, $e->path );
			}
		}
	}
}
