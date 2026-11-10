<?php
/**
 * Fields.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Fields;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Typed reads from a decoded document, each refusal naming its field.
 */
final class FieldsTest extends TestCase {

	/**
	 * @param array<mixed> $fields
	 */
	private static function root( array $fields = array() ): Fields {
		return Fields::document( array( 'schema_version' => 1 ) + $fields );
	}

	private static function refusal( Closure $read ): InvalidDocument {
		try {
			$read();
		} catch ( InvalidDocument $e ) {
			self::assertSame( $e->reason->value, $e->getMessage() );
			return $e;
		}
		self::fail( 'accepted' );
	}

	/**
	 * @return iterable<string, array{mixed, DocumentError, string}>
	 */
	public static function notDocuments(): iterable {
		yield 'null' => array( null, DocumentError::NotAnObject, '' );
		yield 'a string' => array( '{}', DocumentError::NotAnObject, '' );
		yield 'a number' => array( 1, DocumentError::NotAnObject, '' );
		yield 'a list' => array( array( 1, 2 ), DocumentError::NotAnObject, '' );
		yield 'no version' => array( array( 'slots' => array() ), DocumentError::UnknownVersion, 'schema_version' );
		yield 'an empty object' => array( array(), DocumentError::UnknownVersion, 'schema_version' );
		yield 'a later version' => array( array( 'schema_version' => 2 ), DocumentError::UnknownVersion, 'schema_version' );
		yield 'version none' => array( array( 'schema_version' => 0 ), DocumentError::UnknownVersion, 'schema_version' );
		yield 'the version as text' => array( array( 'schema_version' => '1' ), DocumentError::UnknownVersion, 'schema_version' );
		yield 'the version as a float' => array( array( 'schema_version' => 1.0 ), DocumentError::UnknownVersion, 'schema_version' );
	}

	#[DataProvider( 'notDocuments' )]
	public function test_a_document_must_be_an_object_of_a_known_version( mixed $data, DocumentError $reason, string $path ): void {
		$e = self::refusal( static fn() => Fields::document( $data ) );
		self::assertSame( $reason, $e->reason );
		self::assertSame( $path, $e->path );
	}

	public function test_the_version_this_code_reads_is_one(): void {
		self::assertSame( 1, Fields::VERSION );
		self::assertTrue( self::root()->has( 'schema_version' ) );
	}

	public function test_paths_run_from_the_root(): void {
		$root = self::root(
			array(
				'slots' => array(
					array( 'key' => 'deck' ),
					array( 'sources' => array( 'products' => array( 1 ) ) ),
				),
				'r1'    => array( 'enabled' => true ),
			)
		);
		self::assertSame( 'slots', $root->path( 'slots' ) );
		$rows = $root->rows( 'slots', 9 );
		self::assertCount( 2, $rows );
		self::assertSame( 'slots[0].key', $rows[0]->path( 'key' ) );
		self::assertSame( 'slots[1].sources.products', $rows[1]->object( 'sources' )->path( 'products' ) );
		self::assertSame( 'r1.enabled', $root->object( 'r1' )->path( 'enabled' ) );
		self::assertSame( 'slots[1].extra.x', $rows[1]->objectOrEmpty( 'extra' )->path( 'x' ) );
	}

	public function test_has_tells_a_null_field_from_a_missing_one(): void {
		$root = self::root( array( 'default' => null ) );
		self::assertTrue( $root->has( 'default' ) );
		self::assertFalse( $root->has( 'label' ) );
	}

	public function test_text(): void {
		$root = self::root( array( 'label' => 'Deck', 'empty' => '', 'n' => 4, 'none' => null ) );
		self::assertSame( 'Deck', $root->text( 'label', 80 ) );
		self::assertSame( 'Deck', $root->text( 'label', 4 ), 'as long as it may be' );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->text( 'label', 3 ) )->reason );
		self::assertSame( 'label', self::refusal( static fn() => $root->text( 'label', 3 ) )->path );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->text( 'empty', 80 ) )->reason, 'required text is a character or more' );
		self::assertSame( DocumentError::Missing, self::refusal( static fn() => $root->text( 'help', 80 ) )->reason );
		self::assertSame( 'help', self::refusal( static fn() => $root->text( 'help', 80 ) )->path );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->text( 'n', 80 ) )->reason );
		self::assertSame( 'n', self::refusal( static fn() => $root->text( 'n', 80 ) )->path );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->text( 'none', 80 ) )->reason );
	}

	public function test_text_is_counted_in_characters_and_must_be_utf_8(): void {
		$root = self::root( array( 'accents' => 'éééé', 'lines' => "a\nb", 'broken' => "ab\xC3", 'one' => 'é' ) );
		self::assertSame( 'éééé', $root->text( 'accents', 4 ), 'four characters, eight bytes' );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->text( 'accents', 3 ) )->reason );
		self::assertSame( "a\nb", $root->text( 'lines', 3 ), 'a line break is a character' );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->text( 'lines', 2 ) )->reason );
		self::assertSame( 'é', $root->text( 'one', 1 ) );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->text( 'broken', 80 ) )->reason );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->textOrEmpty( 'broken', 80 ) )->reason );
	}

	public function test_text_that_may_be_left_out(): void {
		$root = self::root( array( 'help' => 'Pick a width', 'empty' => '', 'n' => 4 ) );
		self::assertSame( 'Pick a width', $root->textOrEmpty( 'help', 300 ) );
		self::assertSame( 'Pick a width', $root->textOrEmpty( 'help', 12 ) );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->textOrEmpty( 'help', 11 ) )->reason );
		self::assertSame( 'help', self::refusal( static fn() => $root->textOrEmpty( 'help', 11 ) )->path );
		self::assertSame( '', $root->textOrEmpty( 'label', 300 ) );
		self::assertSame( '', $root->textOrEmpty( 'empty', 300 ), 'left empty is as good as left out' );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->textOrEmpty( 'n', 300 ) )->reason );
	}

	public function test_text_in_a_form(): void {
		$root = self::root( array( 'taxonomy' => 'pa_size', 'other' => 'size', 'n' => 4 ) );
		self::assertSame( 'pa_size', $root->matching( 'taxonomy', '/\\Apa_[a-z]+\\z/D' ) );
		self::assertSame( DocumentError::BadKey, self::refusal( static fn() => $root->matching( 'other', '/\\Apa_[a-z]+\\z/D' ) )->reason );
		self::assertSame( 'other', self::refusal( static fn() => $root->matching( 'other', '/\\Apa_[a-z]+\\z/D' ) )->path );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->matching( 'n', '/\\A.+\\z/D' ) )->reason );
		self::assertSame( DocumentError::Missing, self::refusal( static fn() => $root->matching( 'gone', '/\\A.+\\z/D' ) )->reason );
	}


	/**
	 * @return iterable<string, array{string, bool}>
	 */
	public static function keys(): iterable {
		yield 'a word' => array( 'deck', true );
		yield 'one letter' => array( 'd', true );
		yield 'digits and underscores after the first' => array( 'slot_2_b', true );
		yield '32 characters' => array( 'a' . str_repeat( 'b', 31 ), true );
		yield '33 characters' => array( 'a' . str_repeat( 'b', 32 ), false );
		yield 'nothing' => array( '', false );
		yield 'a capital' => array( 'Deck', false );
		yield 'a capital inside' => array( 'dEck', false );
		yield 'a leading digit' => array( '2nd', false );
		yield 'a leading underscore' => array( '_deck', false );
		yield 'a hyphen' => array( 'grip-tape', false );
		yield 'a space' => array( 'grip tape', false );
		yield 'a trailing line break' => array( "deck\n", false );
		yield 'a leading line break' => array( "\ndeck", false );
		yield 'an accent' => array( 'décor', false );
	}

	#[DataProvider( 'keys' )]
	public function test_a_key_is_a_lower_case_letter_then_letters_digits_or_underscores( string $value, bool $is ): void {
		self::assertSame( $is, Fields::isKey( $value ) );
		$root = self::root( array( 'key' => $value ) );
		if ( $is ) {
			self::assertSame( $value, $root->key( 'key' ) );
			return;
		}
		$e = self::refusal( static fn() => $root->key( 'key' ) );
		self::assertSame( DocumentError::BadKey, $e->reason );
		self::assertSame( 'key', $e->path );
	}

	public function test_a_key_must_be_there_and_be_text(): void {
		$root = self::root( array( 'key' => 7 ) );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->key( 'key' ) )->reason );
		self::assertSame( DocumentError::Missing, self::refusal( static fn() => $root->key( 'other' ) )->reason );
	}

	public function test_flag(): void {
		$root = self::root( array( 'on' => true, 'off' => false, 'one' => 1, 'word' => 'true', 'none' => null ) );
		self::assertTrue( $root->flag( 'on' ) );
		self::assertFalse( $root->flag( 'off' ) );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->flag( 'one' ) )->reason );
		self::assertSame( 'one', self::refusal( static fn() => $root->flag( 'one' ) )->path );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->flag( 'word' ) )->reason );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->flag( 'none' ) )->reason );
		self::assertSame( DocumentError::Missing, self::refusal( static fn() => $root->flag( 'missing' ) )->reason );
	}

	public function test_a_whole_number_within_its_range(): void {
		$root = self::root( array( 'units' => 4, 'low' => 1, 'high' => 99, 'under' => 0, 'over' => 100, 'text' => '4', 'float' => 4.0, 'none' => null ) );
		self::assertSame( 4, $root->whole( 'units', 1, 99 ) );
		self::assertSame( 1, $root->whole( 'low', 1, 99 ) );
		self::assertSame( 99, $root->whole( 'high', 1, 99 ) );
		$under = self::refusal( static fn() => $root->whole( 'under', 1, 99 ) );
		self::assertSame( DocumentError::OutOfRange, $under->reason );
		self::assertSame( 'under', $under->path );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->whole( 'over', 1, 99 ) )->reason );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->whole( 'text', 1, 99 ) )->reason );
		self::assertSame( 'text', self::refusal( static fn() => $root->whole( 'text', 1, 99 ) )->path );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->whole( 'float', 1, 99 ) )->reason );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->whole( 'none', 1, 99 ) )->reason );
		self::assertSame( DocumentError::Missing, self::refusal( static fn() => $root->whole( 'missing', 1, 99 ) )->reason );
	}

	public function test_an_id_or_null(): void {
		$root = self::root( array( 'default' => 412, 'one' => 1, 'big' => PHP_INT_MAX, 'none' => null, 'zero' => 0, 'minus' => -3, 'text' => '412' ) );
		self::assertSame( 412, $root->idOrNull( 'default' ) );
		self::assertSame( 1, $root->idOrNull( 'one' ) );
		self::assertSame( PHP_INT_MAX, $root->idOrNull( 'big' ) );
		self::assertNull( $root->idOrNull( 'none' ) );
		self::assertNull( $root->idOrNull( 'missing' ) );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->idOrNull( 'zero' ) )->reason );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->idOrNull( 'minus' ) )->reason );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->idOrNull( 'text' ) )->reason );
	}

	public function test_a_list_of_ids(): void {
		$root = self::root(
			array(
				'products' => array( 12, 1, 12 ),
				'empty'    => array(),
				'map'      => array( 'a' => 1 ),
				'gappy'    => array( 1 => 5 ),
				'text'     => array( 12, '13' ),
				'zero'     => array( 12, 13, 0 ),
				'minus'    => array( -1 ),
				'scalar'   => 12,
			)
		);
		self::assertSame( array( 12, 1, 12 ), $root->ids( 'products' ) );
		self::assertSame( array(), $root->ids( 'empty' ) );
		self::assertSame( array(), $root->ids( 'missing' ) );
		$map = self::refusal( static fn() => $root->ids( 'map' ) );
		self::assertSame( DocumentError::WrongType, $map->reason );
		self::assertSame( 'map', $map->path );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->ids( 'gappy' ) )->reason );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->ids( 'scalar' ) )->reason );
		$text = self::refusal( static fn() => $root->ids( 'text' ) );
		self::assertSame( DocumentError::WrongType, $text->reason );
		self::assertSame( 'text[1]', $text->path );
		$zero = self::refusal( static fn() => $root->ids( 'zero' ) );
		self::assertSame( DocumentError::OutOfRange, $zero->reason );
		self::assertSame( 'zero[2]', $zero->path );
		self::assertSame( 'minus[0]', self::refusal( static fn() => $root->ids( 'minus' ) )->path );
	}

	public function test_a_nested_lists_paths_carry_the_parents(): void {
		$sources = self::root( array( 'sources' => array( 'brands' => array( 4, 'x' ), 'products' => array( 0 ) ) ) )->object( 'sources' );
		self::assertSame( 'sources.brands[1]', self::refusal( static fn() => $sources->ids( 'brands' ) )->path );
		self::assertSame( 'sources.products[0]', self::refusal( static fn() => $sources->ids( 'products' ) )->path );
		self::assertSame( 'sources.categories', self::refusal( static fn() => self::root( array( 'sources' => array( 'categories' => 3 ) ) )->object( 'sources' )->ids( 'categories' ) )->path );
	}

	public function test_rows_are_objects_within_a_count(): void {
		$root = self::root(
			array(
				'slots'  => array( array( 'key' => 'deck' ), array( 'key' => 'trucks' ) ),
				'mixed'  => array( array( 'key' => 'deck' ), 'trucks' ),
				'lists'  => array( array( 1, 2 ) ),
				'empty'  => array(),
				'scalar' => 'deck',
				'map'    => array( 'deck' => array() ),
			)
		);
		$rows = $root->rows( 'slots', 2 );
		self::assertSame( 'deck', $rows[0]->key( 'key' ) );
		self::assertSame( 'trucks', $rows[1]->key( 'key' ) );
		self::assertSame( array(), $root->rows( 'empty', 5 ) );
		self::assertSame( array(), $root->rows( 'missing', 5 ) );
		self::assertSame( array(), $root->rows( 'empty', 0 ) );
		$many = self::refusal( static fn() => $root->rows( 'slots', 1 ) );
		self::assertSame( DocumentError::OutOfRange, $many->reason );
		self::assertSame( 'slots', $many->path );
		$mixed = self::refusal( static fn() => $root->rows( 'mixed', 5 ) );
		self::assertSame( DocumentError::NotAnObject, $mixed->reason );
		self::assertSame( 'mixed[1]', $mixed->path );
		self::assertSame( 'lists[0]', self::refusal( static fn() => $root->rows( 'lists', 5 ) )->path );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->rows( 'scalar', 5 ) )->reason );
		self::assertSame( DocumentError::WrongType, self::refusal( static fn() => $root->rows( 'map', 5 ) )->reason );
	}

	public function test_rows_that_must_hold_one(): void {
		$root = self::root(
			array(
				'slots' => array( array( 'key' => 'deck' ), array( 'key' => 'trucks' ) ),
				'one'   => array( array( 'key' => 'deck' ) ),
				'empty' => array(),
			)
		);
		self::assertCount( 2, $root->rowsFilled( 'slots', 2 ) );
		self::assertSame( 'deck', $root->rowsFilled( 'one', 1 )[0]->key( 'key' ) );
		$empty = self::refusal( static fn() => $root->rowsFilled( 'empty', 5 ) );
		self::assertSame( DocumentError::OutOfRange, $empty->reason );
		self::assertSame( 'empty', $empty->path );
		self::assertSame( 'missing', self::refusal( static fn() => $root->rowsFilled( 'missing', 5 ) )->path );
		self::assertSame( DocumentError::OutOfRange, self::refusal( static fn() => $root->rowsFilled( 'slots', 1 ) )->reason );
	}

	public function test_an_empty_row_is_an_object(): void {
		$rows = self::root( array( 'rows' => array( array() ) ) )->rows( 'rows', 1 );
		self::assertFalse( $rows[0]->has( 'key' ) );
	}

	public function test_an_object(): void {
		$root = self::root( array( 'r1' => array( 'enabled' => true ), 'list' => array( 1 ), 'text' => 'x', 'empty' => array() ) );
		self::assertTrue( $root->object( 'r1' )->flag( 'enabled' ) );
		self::assertFalse( $root->object( 'empty' )->has( 'enabled' ) );
		$missing = self::refusal( static fn() => $root->object( 'r2' ) );
		self::assertSame( DocumentError::Missing, $missing->reason );
		self::assertSame( 'r2', $missing->path );
		$list = self::refusal( static fn() => $root->object( 'list' ) );
		self::assertSame( DocumentError::NotAnObject, $list->reason );
		self::assertSame( 'list', $list->path );
		self::assertSame( DocumentError::NotAnObject, self::refusal( static fn() => $root->object( 'text' ) )->reason );
	}

	public function test_an_object_that_may_be_left_out(): void {
		$root = self::root( array( 'sources' => array( 'products' => array( 3 ) ), 'text' => 'x', 'none' => null ) );
		self::assertSame( array( 3 ), $root->objectOrEmpty( 'sources' )->ids( 'products' ) );
		self::assertSame( array(), $root->objectOrEmpty( 'missing' )->ids( 'products' ) );
		self::assertSame( array(), $root->objectOrEmpty( 'none' )->names() );
		$text = self::refusal( static fn() => $root->objectOrEmpty( 'text' ) );
		self::assertSame( DocumentError::NotAnObject, $text->reason );
		self::assertSame( 'text', $text->path );
	}

	public function test_an_objects_names(): void {
		$picks = self::root( array( 'picks' => array( 'deck' => 1, 'trucks' => 2, 7 => 3 ) ) )->object( 'picks' );
		self::assertSame( array( 'deck', 'trucks', '7' ), $picks->names() );
	}

	/**
	 * @return iterable<string, array{mixed, int|DocumentError}>
	 */
	public static function amounts(): iterable {
		yield 'a whole number' => array( '56', 560000 );
		yield 'zero' => array( '0', 0 );
		yield 'one place' => array( '0.5', 5000 );
		yield 'two places' => array( '0.25', 2500 );
		yield 'four places' => array( '8.1234', 81234 );
		yield 'trailing zeros' => array( '8.2500', 82500 );
		yield 'a small fourth place' => array( '0.0001', 1 );
		yield 'nine whole digits' => array( '123456789', 1234567890000 );
		yield 'ten whole digits' => array( '1234567890', DocumentError::BadMeasure );
		yield 'five places' => array( '8.12345', DocumentError::BadMeasure );
		yield 'a comma' => array( '8,25', DocumentError::BadMeasure );
		yield 'a fraction' => array( '1/4', DocumentError::BadMeasure );
		yield 'a unit mark' => array( '56 mm', DocumentError::BadMeasure );
		yield 'a word' => array( 'wide', DocumentError::BadMeasure );
		yield 'nothing' => array( '', DocumentError::BadMeasure );
		yield 'a negative' => array( '-1', DocumentError::BadMeasure );
		yield 'a point with nothing after' => array( '8.', DocumentError::BadMeasure );
		yield 'a point with nothing before' => array( '.5', DocumentError::BadMeasure );
		yield 'a space before' => array( ' 8', DocumentError::BadMeasure );
		yield 'a line break after' => array( "8\n", DocumentError::BadMeasure );
		yield 'a number, not text' => array( 0.25, DocumentError::WrongType );
		yield 'an integer, not text' => array( 56, DocumentError::WrongType );
		yield 'null' => array( null, DocumentError::WrongType );
	}

	#[DataProvider( 'amounts' )]
	public function test_an_amount_is_a_decimal_in_text( mixed $value, int|DocumentError $expected ): void {
		$root = self::root( array( 'r3' => array( 'limit' => $value ) ) )->object( 'r3' );
		if ( is_int( $expected ) ) {
			self::assertSame( $expected, $root->amount( 'limit' ) );
			return;
		}
		$e = self::refusal( static fn() => $root->amount( 'limit' ) );
		self::assertSame( $expected, $e->reason );
		self::assertSame( 'r3.limit', $e->path );
	}

	public function test_a_missing_amount_is_missing(): void {
		self::assertSame( DocumentError::Missing, self::refusal( static fn() => self::root()->amount( 'fee' ) )->reason );
	}

	public function test_a_measure_is_an_amount_in_a_unit(): void {
		$root = self::root( array( 'fit' => '0.25', 'limit' => '56', 'word' => '1/4' ) );
		$fit  = $root->measure( 'fit', Unit::Inch );
		self::assertSame( 2500, $fit->tenThousandths );
		self::assertSame( Unit::Inch, $fit->unit );
		$limit = $root->measure( 'limit', Unit::Millimetre );
		self::assertSame( 560000, $limit->tenThousandths );
		self::assertSame( Unit::Millimetre, $limit->unit );
		$word = self::refusal( static fn() => $root->measure( 'word', Unit::Inch ) );
		self::assertSame( DocumentError::BadMeasure, $word->reason );
		self::assertSame( 'word', $word->path );
	}
}
