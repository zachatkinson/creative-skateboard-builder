<?php
/**
 * A kit's assembly time in business days.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * How many business days putting a build together takes (#142): one count, "2", or a range, "2-4", from 0 to 60. It
 * is read as an owner types it, "2-4", "2 - 4", "2–4" or "2 to 4", and kept as "2-4", the form Creative Checkout's
 * "Delivery time (business days)" takes, so a plugin that dates a delivery can count it. A range of one day is that
 * day. Digits of any script are read as their ASCII digits: "٢-٤" is kept as "2-4".
 *
 * @since 1.2.0
 */
final readonly class LeadDays {

	/**
	 * The most business days it may be.
	 *
	 * @since 1.2.0
	 */
	public const int MAX = 60;

	/**
	 * A count in ASCII digits, or two joined by a hyphen, an en dash or "to", with spaces between and around, and no
	 * line. `\d` would take any script's digit, which `(int)` reads as nought: other scripts' are made ASCII first.
	 */
	private const string FORM = '/\A\h*([0-9]+)\h*(?:(?:-|\x{2013}|to)\h*([0-9]+)\h*)?\z/iu';

	/**
	 * A decimal digit of any script but ASCII's: Arabic-Indic, Devanagari, full-width and the rest.
	 */
	private const string DIGIT = '/(?![0-9])\p{Nd}/u';

	/**
	 * One decimal digit, of any script, and nothing else.
	 */
	private const string ONE_DIGIT = '/\A\p{Nd}\z/u';

	/**
	 * Constructor.
	 *
	 * @param int $from The fewest business days.
	 * @param int $to   The most, no fewer than `$from`.
	 */
	private function __construct( public int $from, public int $to ) {}

	/**
	 * The business days typed, or as stored.
	 *
	 * @since 1.2.0
	 *
	 * @param string $typed The days: "2", "2-4", "2 - 4", "2–4" or "2 to 4".
	 * @param string $path  Where the field is, for the refusal.
	 *
	 * @throws InvalidDocument When it is not a count or a range (`document_bad_measure`), the most comes first
	 *                         (`rules_out_of_order`), or a count is over 60 (`document_out_of_range`).
	 */
	public static function read( string $typed, string $path = 'lead_days' ): self {
		$found = array();
		if ( 1 !== preg_match( self::FORM, self::ascii( $typed ), $found ) ) {
			$refused = InvalidDocument::because( DocumentError::BadMeasure, $path );
			throw $refused;
		}
		$from = (int) $found[1];
		$to   = (int) ( $found[2] ?? $found[1] );
		if ( $from > self::MAX || $to > self::MAX ) {
			$refused = InvalidDocument::because( DocumentError::OutOfRange, $path );
			throw $refused;
		}
		if ( $to < $from ) {
			$refused = InvalidDocument::because( DocumentError::OutOfOrder, $path );
			throw $refused;
		}
		return new self( $from, $to );
	}

	/**
	 * The business days as a document keeps them, "2" or "2-4", and in no other form, so the domain refuses what
	 * `schema/kit-settings.json` refuses.
	 *
	 * @since 1.2.0
	 *
	 * @param string $kept The days as stored.
	 * @param string $path Where the field is, for the refusal.
	 *
	 * @throws InvalidDocument As `read()` does, and when they are business days in any other form
	 *                         (`document_bad_measure`).
	 */
	public static function kept( string $kept, string $path = 'lead_days' ): self {
		$days = self::read( $kept, $path );
		if ( $days->stored() !== $kept ) {
			$refused = InvalidDocument::because( DocumentError::BadMeasure, $path );
			throw $refused;
		}
		return $days;
	}

	/**
	 * The days typed with each decimal digit of another script as its ASCII digit, "٢-٤" as "2-4", so they are
	 * counted as typed, not read as no days. Empty for text that is not UTF-8, which is no count.
	 *
	 * @param string $typed The days typed.
	 */
	private static function ascii( string $typed ): string {
		return (string) preg_replace_callback( self::DIGIT, static fn ( array $digit ): string => self::value( $digit[0] ), $typed );
	}

	/**
	 * A decimal digit's value. Unicode assigns decimal digits only in unbroken runs of ten, 0 to 9 (its stability
	 * policy for Numeric_Type=Decimal), so a digit is worth how many digits run before it, counted in tens. Read
	 * without mbstring or intl, which a host may lack.
	 *
	 * @param string $digit One decimal digit, of two to four bytes in UTF-8.
	 */
	private static function value( string $digit ): string {
		$bytes = array_map( ord( ... ), str_split( $digit ) );
		// The lead byte of a character of n bytes starts with n ones and a nought: take them away, then add six bits
		// from each byte after it, past its leading 10.
		$point = $bytes[0] - 256 + 2 ** ( 8 - strlen( $digit ) );
		foreach ( array_slice( $bytes, 1 ) as $byte ) {
			$point = $point * 64 + $byte - 128;
		}
		// The digits in a row that end at this one, itself among them.
		$run = 0;
		while ( 1 === preg_match( self::ONE_DIGIT, html_entity_decode( '&#' . ( $point - $run ) . ';', ENT_HTML5, 'UTF-8' ) ) ) {
			++$run;
		}
		return (string) ( ( $run - 1 ) % 10 );
	}

	/**
	 * As it is kept: "2", or "2-4".
	 *
	 * @since 1.2.0
	 */
	public function stored(): string {
		return $this->from === $this->to ? (string) $this->from : $this->from . '-' . $this->to;
	}
}
