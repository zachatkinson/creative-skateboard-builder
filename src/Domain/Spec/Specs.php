<?php
/**
 * A part's specs.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
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
		$unit    = $key->unit();
		$choices = $key->choices();
		$problem = null;
		if ( null !== $unit ) {
			$problem = $value instanceof Measure ? null : SpecError::WrongKind;
			$value   = $value instanceof Measure ? $value->to( $unit ) : $value;
			$problem = $value instanceof Measure && 0 === $value->tenThousandths ? SpecError::NotPositive : $problem;
		} elseif ( array() !== $choices ) {
			$problem = is_string( $value ) ? null : SpecError::WrongKind;
			$problem = null === $problem && ! in_array( $value, $choices, true ) ? SpecError::UnknownChoice : $problem;
		} elseif ( SpecKind::Flag === $key->kind() ) {
			$problem = is_bool( $value ) ? null : SpecError::WrongKind;
		} else {
			$problem = is_int( $value ) ? null : SpecError::WrongKind;
			$problem = null === $problem && $value < 1 ? SpecError::NotPositive : $problem;
			$problem = null === $problem && $value > self::MAX_COUNT ? SpecError::TooMany : $problem;
		}
		if ( null !== $problem ) {
			$refused = InvalidSpec::because( $problem, $key->value );
			throw $refused;
		}
		return new self( array( $key->value => $value ) + $this->values );
	}

	/**
	 * A part's specs from the index: each value as the text the index stores. A measure is a decimal in the key's
	 * unit, a flag is `1` or `0`, and a count is digits.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, string> $rows The values by key.
	 *
	 * @throws InvalidSpec    When a value is not of its key's kind. A key this version does not know is passed over.
	 * @throws InvalidMeasure When a measure cannot be read.
	 */
	public static function fromIndex( array $rows ): self {
		$specs = self::none();
		foreach ( $rows as $name => $text ) {
			$key = SpecKey::tryFrom( $name );
			if ( null === $key ) {
				// A row a newer version wrote. Refusing it would cost the part every spec it has after a rollback.
				continue;
			}
			$unit  = $key->unit();
			$value = $text;
			if ( null !== $unit ) {
				$value = MeasureParser::parse( $text, $unit );
			} elseif ( SpecKind::Flag === $key->kind() ) {
				$value = in_array( $text, array( '0', '1' ), true ) ? '1' === $text : $text;
			} elseif ( SpecKind::Count === $key->kind() ) {
				$value = ctype_digit( $text ) ? (int) $text : $text;
			}
			$specs = $specs->with( $key, $value );
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
