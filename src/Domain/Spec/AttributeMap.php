<?php
/**
 * One line of the shop's attribute mapping.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;

use function is_array;
use function is_string;
use function strlen;

/**
 * Which product attribute feeds which spec, and the unit its values are written in: the deck's width comes from the
 * `pa_width` attribute, in inches (spec: Data model, Part specs; D-026).
 *
 * @since 0.3.0
 */
final readonly class AttributeMap {

	/**
	 * The longest attribute name kept: a taxonomy's name is at most 32 characters, and a product's own attribute is
	 * a label a shop typed.
	 */
	public const int MAX_NAME = 190;

	/**
	 * Constructor.
	 *
	 * @param string    $attribute The attribute's name: a taxonomy such as `pa_width`, or a product's own attribute.
	 * @param SpecKey   $spec      The spec it feeds.
	 * @param Unit|null $unit      The unit an unmarked value is in, for a measure. Null is the spec's own.
	 */
	private function __construct( public string $attribute, public SpecKey $spec, public ?Unit $unit ) {}

	/**
	 * A line as it was saved, or null when it is not one: no attribute, a name too long, a spec this version does
	 * not know. A unit is kept only for a measure, and one that is not a unit reads as the spec's own.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $line What was stored: `attribute`, `spec` and `unit`.
	 */
	public static function read( mixed $line ): ?self {
		if ( ! is_array( $line ) ) {
			return null;
		}
		$attribute = $line['attribute'] ?? null;
		$name      = $line['spec'] ?? null;
		$spec      = is_string( $name ) ? SpecKey::tryFrom( $name ) : null;
		if ( ! is_string( $attribute ) || '' === $attribute || strlen( $attribute ) > self::MAX_NAME || null === $spec ) {
			return null;
		}
		$given = $line['unit'] ?? null;
		$unit  = is_string( $given ) && null !== $spec->unit() ? Unit::tryFrom( $given ) : null;
		return new self( $attribute, $spec, $unit );
	}

	/**
	 * The line for the store.
	 *
	 * @since 0.3.0
	 *
	 * @return array{attribute: string, spec: string, unit: string}
	 */
	public function toArray(): array {
		return array(
			'attribute' => $this->attribute,
			'spec'      => $this->spec->value,
			'unit'      => null === $this->unit ? '' : $this->unit->value,
		);
	}
}
