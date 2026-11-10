<?php
/**
 * One value a source gives for one spec of a part.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;

use function ctype_digit;
use function strtolower;
use function trim;

/**
 * What a part's field, a mapped attribute or a spec table says of one spec, already of the key's kind and in the
 * key's unit. Every edge that takes a value from a person or a shop's data reads it here, so 8.25, 8,25 and 8 1/4
 * mean one thing wherever they are typed (spec: Admin, Part specs).
 *
 * @since 0.3.0
 */
final readonly class Reading {

	/**
	 * What a flag may be written as.
	 */
	private const array FLAGS = array(
		'1'     => true,
		'yes'   => true,
		'true'  => true,
		'0'     => false,
		'no'    => false,
		'false' => false,
	);

	/**
	 * Constructor.
	 *
	 * @param SpecKey $key    The spec.
	 * @param string  $text   The value as the index stores it.
	 * @param Origin  $origin The source.
	 * @param string  $ref    What in the source: an attribute's name, a table and size, '' for the part's own field.
	 */
	private function __construct(
		public SpecKey $key,
		public string $text,
		public Origin $origin,
		public string $ref
	) {}

	/**
	 * A value of the key's kind, from a source.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey                 $key    The spec.
	 * @param Measure|string|bool|int $value  Its value. A measure in another unit is converted.
	 * @param Origin                  $origin The source.
	 * @param string                  $ref    What in the source.
	 *
	 * @throws InvalidSpec When the value is not one the key can hold.
	 */
	public static function of( SpecKey $key, Measure|string|bool|int $value, Origin $origin, string $ref = '' ): self {
		return new self( $key, Specs::none()->with( $key, $value )->toIndex()[ $key->value ], $origin, $ref );
	}

	/**
	 * A value a person typed: read with the shop's decimal separator, and with a point when that does not read, so
	 * 8,25 and 8.25 are one value in a shop that writes a comma (spec: Admin, Part specs).
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey   $key       The spec.
	 * @param string    $text      What was typed.
	 * @param Origin    $origin    The source.
	 * @param string    $ref       What in the source.
	 * @param string    $separator The shop's decimal separator.
	 * @param Unit|null $unit      The unit an unmarked measure is in, when it is not the key's own.
	 */
	public static function typed( SpecKey $key, string $text, Origin $origin, string $ref = '', string $separator = '.', ?Unit $unit = null ): self|Unread {
		$read = self::from( $key, $text, $origin, $ref, $separator, $unit );
		return $read instanceof self ? $read : self::from( $key, $text, $origin, $ref, '.', $unit );
	}

	/**
	 * A value as it was typed or stored, or why it cannot be read.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey   $key       The spec.
	 * @param string    $text      What was typed or stored.
	 * @param Origin    $origin    The source.
	 * @param string    $ref       What in the source.
	 * @param string    $separator The decimal separator a measure was typed with.
	 * @param Unit|null $unit      The unit an unmarked measure is in, when it is not the key's own.
	 */
	public static function from( SpecKey $key, string $text, Origin $origin, string $ref = '', string $separator = '.', ?Unit $unit = null ): self|Unread {
		try {
			return self::of( $key, self::value( $key, trim( $text ), $separator, $unit ), $origin, $ref );
		} catch ( InvalidSpec $refused ) {
			return new Unread( $key->value, $text, $origin, $ref, $refused->reason->value );
		} catch ( InvalidMeasure $refused ) {
			return new Unread( $key->value, $text, $origin, $ref, $refused->reason->value );
		}
	}

	/**
	 * The text as a value for `Specs::with()` to check: a measure parsed, a flag's word as a flag, digits as a
	 * count, and anything else as it stands, which is right for a choice and refused for the rest.
	 *
	 * @param SpecKey   $key       The spec.
	 * @param string    $text      What was typed, trimmed.
	 * @param string    $separator The decimal separator.
	 * @param Unit|null $unit      The unit an unmarked measure is in.
	 *
	 * @throws InvalidMeasure When the key is a measure and the text is not one.
	 */
	private static function value( SpecKey $key, string $text, string $separator, ?Unit $unit ): Measure|string|bool|int {
		$own = $key->unit();
		if ( null !== $own ) {
			return MeasureParser::parse( $text, $unit ?? $own, $separator );
		}
		$text = strtolower( $text );
		if ( SpecKind::Flag === $key->kind() ) {
			return self::FLAGS[ $text ] ?? $text;
		}
		if ( SpecKind::Count === $key->kind() && ctype_digit( $text ) ) {
			return (int) $text;
		}
		return $text;
	}

	/**
	 * The row the index holds for this reading.
	 *
	 * @since 0.3.0
	 */
	public function row(): IndexedSpec {
		return new IndexedSpec( $this->key, $this->text, $this->origin, $this->ref );
	}
}
