<?php
/**
 * A part's specs.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;

use function ctype_digit;
use function in_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * One value per spec key, each of its key's kind: a measure in the key's unit, one of a choice's values, a flag or a
 * count of one or more (spec: Data model, Part specs). Built key by key, or from the index's rows.
 */
final readonly class Specs {

	/**
	 * The most a count may be: a set of a thousand. Past it the engine's sums would leave whole numbers.
	 */
	public const int MAX_COUNT = 1000;

	/**
	 * Constructor.
	 *
	 * @param array<string, Measure|string|bool|int> $values The values by key.
	 */
	private function __construct( private array $values ) {}

	/**
	 * No specs.
	 *
	 * @since 0.2.0
	 */
	public static function none(): self {
		return new self( array() );
	}

	/**
	 * These specs and one more, or one changed.
	 *
	 * @since 0.2.0
	 *
	 * @param SpecKey                 $key   The spec.
	 * @param Measure|string|bool|int $value Its value, of the key's kind. A measure in another unit is converted.
	 *
	 * @throws InvalidSpec When the value is not of the key's kind, not one of its choices, or a count below one.
	 */
	public function with( SpecKey $key, Measure|string|bool|int $value ): self {
		$fitted = self::fitted( $key, $value );
		if ( $fitted instanceof SpecError ) {
			$refused = InvalidSpec::because( $fitted, $key->value );
			throw $refused;
		}
		return new self( array( $key->value => $fitted ) + $this->values );
	}

	/**
	 * A value as its key holds it (a measure in the key's unit), or why it cannot be this key's.
	 *
	 * @param SpecKey                 $key   The spec.
	 * @param Measure|string|bool|int $value The value.
	 */
	private static function fitted( SpecKey $key, Measure|string|bool|int $value ): Measure|string|bool|int|SpecError {
		$unit    = $key->unit();
		$choices = $key->choices();
		if ( null !== $unit ) {
			if ( ! $value instanceof Measure ) {
				return SpecError::WrongKind;
			}
			$value = $value->to( $unit );
			return 0 === $value->tenThousandths ? SpecError::NotPositive : $value;
		}
		if ( array() !== $choices ) {
			if ( ! is_string( $value ) ) {
				return SpecError::WrongKind;
			}
			return in_array( $value, $choices, true ) ? $value : SpecError::UnknownChoice;
		}
		if ( SpecKind::Flag === $key->kind() ) {
			return is_bool( $value ) ? $value : SpecError::WrongKind;
		}
		if ( ! is_int( $value ) ) {
			return SpecError::WrongKind;
		}
		if ( $value < 1 ) {
			return SpecError::NotPositive;
		}
		return $value > self::MAX_COUNT ? SpecError::TooMany : $value;
	}

	/**
	 * A part's specs from the index: each value as the text the index stores. A measure is a decimal in the key's
	 * unit, a flag is `1` or `0`, and a count is digits. A row this version cannot read is passed over: a key it does
	 * not know, a choice it does not offer, a value out of range or in another form. A newer version may have written
	 * it, and refusing the part would cost it every spec it has after a rollback. A rule that needs the spec then
	 * says it is missing, and the Catalogue check lists it. What a person types is checked at the edge, with `with()`.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, string> $rows The values by key.
	 */
	public static function fromIndex( array $rows ): self {
		$specs = self::none();
		foreach ( $rows as $name => $text ) {
			$key = SpecKey::tryFrom( $name );
			if ( null === $key ) {
				continue;
			}
			$unit  = $key->unit();
			$value = $text;
			if ( null !== $unit ) {
				$value = MeasureParser::read( $text, $unit );
			} elseif ( SpecKind::Flag === $key->kind() ) {
				$value = in_array( $text, array( '0', '1' ), true ) ? '1' === $text : $text;
			} elseif ( SpecKind::Count === $key->kind() ) {
				$value = ctype_digit( $text ) ? (int) $text : $text;
			}
			$fitted = null === $value ? SpecError::WrongKind : self::fitted( $key, $value );
			if ( ! $fitted instanceof SpecError ) {
				$specs = new self( array( $key->value => $fitted ) + $specs->values );
			}
		}
		return $specs;
	}

	/**
	 * Whether the part has this spec.
	 *
	 * @since 0.2.0
	 *
	 * @param SpecKey $key The spec.
	 */
	public function has( SpecKey $key ): bool {
		return isset( $this->values[ $key->value ] );
	}

	/**
	 * A measure, or null when the part has none for this key.
	 *
	 * @since 0.2.0
	 *
	 * @param SpecKey $key The spec.
	 */
	public function measure( SpecKey $key ): ?Measure {
		$value = $this->values[ $key->value ] ?? null;
		return $value instanceof Measure ? $value : null;
	}

	/**
	 * A choice's value, or null when the part has none for this key.
	 *
	 * @since 0.2.0
	 *
	 * @param SpecKey $key The spec.
	 */
	public function choice( SpecKey $key ): ?string {
		$value = $this->values[ $key->value ] ?? null;
		return is_string( $value ) ? $value : null;
	}

	/**
	 * A flag: true only when the part says so.
	 *
	 * @since 0.2.0
	 *
	 * @param SpecKey $key The spec.
	 */
	public function flag( SpecKey $key ): bool {
		return true === ( $this->values[ $key->value ] ?? null );
	}

	/**
	 * A count, or null when the part has none for this key.
	 *
	 * @since 0.2.0
	 *
	 * @param SpecKey $key The spec.
	 */
	public function count( SpecKey $key ): ?int {
		$value = $this->values[ $key->value ] ?? null;
		return is_int( $value ) ? $value : null;
	}
}
