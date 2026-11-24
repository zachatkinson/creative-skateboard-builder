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
 * day.
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
	 * A count, or two joined by a hyphen, an en dash or "to", with spaces between and around, and no line.
	 */
	private const string FORM = '/\A\h*(\d+)\h*(?:(?:-|\x{2013}|to)\h*(\d+)\h*)?\z/iu';

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
		if ( 1 !== preg_match( self::FORM, $typed, $found ) ) {
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
	 * As it is kept: "2", or "2-4".
	 *
	 * @since 1.2.0
	 */
	public function stored(): string {
		return $this->from === $this->to ? (string) $this->from : $this->from . '-' . $this->to;
	}
}
