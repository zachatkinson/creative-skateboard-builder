<?php
/**
 * LeadDays.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\LeadDays;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #142: the kit's assembly time in business days, "2" or "2-4", 0 to 60. It is read as an owner types it, "2-4",
 * "2 - 4", "2–4" or "2 to 4", and kept as "2-4", the form Creative Checkout's "Delivery time (business days)" has.
 */
final class LeadDaysTest extends TestCase {

	/**
	 * @return iterable<string, array{string, int, int, string}>
	 */
	public static function accepted(): iterable {
		yield 'a day count' => array( '2', 2, 2, '2' );
		yield 'a range' => array( '2-4', 2, 4, '2-4' );
		yield 'a range spaced' => array( '2 - 4', 2, 4, '2-4' );
		yield 'a range with an en dash' => array( '2–4', 2, 4, '2-4' );
		yield 'a range in words' => array( '2 to 4', 2, 4, '2-4' );
		yield 'in words, capitalised' => array( '2 To 4', 2, 4, '2-4' );
		yield 'spaces around' => array( '  3  ', 3, 3, '3' );
		// A browser can send a no-break space, which PHP's own number reading does not pass over.
		yield 'a no-break space before' => array( "\u{00A0}3", 3, 3, '3' );
		yield 'a no-break space in a range' => array( "\u{00A0}2\u{00A0}to\u{00A0}4", 2, 4, '2-4' );
		yield 'none' => array( '0', 0, 0, '0' );
		yield 'the most' => array( '60', 60, 60, '60' );
		yield 'the whole range' => array( '0-60', 0, 60, '0-60' );
		yield 'a range of one day is that day' => array( '5-5', 5, 5, '5' );
		yield 'a zero in front' => array( '02-04', 2, 4, '2-4' );
	}

	#[DataProvider( 'accepted' )]
	public function test_each_form_an_owner_types_is_read_and_kept_as_one( string $typed, int $from, int $to, string $kept ): void {
		$days = LeadDays::read( $typed );
		self::assertSame( array( $from, $to ), array( $days->from, $days->to ) );
		self::assertSame( $kept, $days->stored() );
		self::assertEquals( $days, LeadDays::read( $days->stored() ), 'and what is kept reads the same' );
	}

	/**
	 * @return iterable<string, array{string, DocumentError}>
	 */
	public static function refused(): iterable {
		yield 'one day too many' => array( '61', DocumentError::OutOfRange );
		yield 'a range that ends past 60' => array( '2-61', DocumentError::OutOfRange );
		yield 'a range that starts past 60' => array( '61-70', DocumentError::OutOfRange );
		yield 'a count no int holds' => array( '99999999999999999999', DocumentError::OutOfRange );
		yield 'the most first' => array( '4-2', DocumentError::OutOfOrder );
		yield 'nothing' => array( '', DocumentError::BadMeasure );
		yield 'spaces' => array( '   ', DocumentError::BadMeasure );
		yield 'words' => array( 'two days', DocumentError::BadMeasure );
		yield 'a unit after' => array( '2 days', DocumentError::BadMeasure );
		yield 'a fraction' => array( '2.5', DocumentError::BadMeasure );
		yield 'a decimal comma' => array( '2,5', DocumentError::BadMeasure );
		yield 'a minus' => array( '-2', DocumentError::BadMeasure );
		yield 'a range with no end' => array( '2-', DocumentError::BadMeasure );
		yield 'a range with no start' => array( 'to 4', DocumentError::BadMeasure );
		yield 'three numbers' => array( '2-4-6', DocumentError::BadMeasure );
		yield 'an em dash' => array( '2—4', DocumentError::BadMeasure );
		yield 'a line after' => array( "2\n", DocumentError::BadMeasure );
	}

	#[DataProvider( 'refused' )]
	public function test_what_is_not_business_days_from_0_to_60_is_refused_with_its_code( string $typed, DocumentError $reason ): void {
		try {
			LeadDays::read( $typed );
			self::fail( 'read: ' . $typed );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( $reason, $refused->reason );
			self::assertSame( 'lead_days', $refused->path, 'named at its field' );
		}
	}

	// What a document keeps is "2" or "2-4" alone, as its schema says; typed forms are for the Build tab to read.
	public function test_a_document_keeps_the_days_in_one_form_alone(): void {
		self::assertSame( '2-4', LeadDays::kept( '2-4' )->stored() );
		self::assertSame( '0', LeadDays::kept( '0' )->stored() );
		foreach ( array( '2 to 4', '2 - 4', '2–4', ' 2', '02', '3-3' ) as $other ) {
			try {
				LeadDays::kept( $other, 'settings.lead_days' );
				self::fail( 'kept: ' . $other );
			} catch ( InvalidDocument $refused ) {
				self::assertSame( array( DocumentError::BadMeasure, 'settings.lead_days' ), array( $refused->reason, $refused->path ), $other );
			}
		}
		try {
			LeadDays::kept( '61' );
			self::fail( 'kept: 61' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( array( DocumentError::OutOfRange, 'lead_days' ), array( $refused->reason, $refused->path ), 'past 60, as typed' );
		}
	}

	public function test_a_refusal_is_named_at_the_path_given(): void {
		try {
			LeadDays::read( '61', 'settings.lead_days' );
			self::fail( 'read: 61' );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( 'settings.lead_days', $refused->path );
			self::assertSame( 'document_out_of_range', $refused->getMessage() );
		}
	}
}
