<?php
/**
 * One look: one card in a step.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function array_keys;
use function array_map;
use function count;
use function in_array;
use function usort;

/**
 * What the shopper can see: a product, or the variations of one product that share a photo and a layer image. Its
 * sizes never get a card of their own, and the other attributes that differ between its variations are the choices
 * you can't see, made after the card is picked (SPEC-CONFIGURATOR, One card per look; D-240).
 */
final readonly class Look {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string        $key      What tells it from the step's other looks: the product, photo and layer image.
	 * @param int           $product  The product it belongs to.
	 * @param list<Variant> $variants Its parts, in the order given, never none.
	 */
	public function __construct( public string $key, public int $product, public array $variants ) {}

	/**
	 * Its parts, in the order given.
	 *
	 * @since 1.0.0
	 *
	 * @return list<Part>
	 */
	public function parts(): array {
		return array_map( static fn( Variant $variant ): Part => $variant->part, $this->variants );
	}

	/**
	 * The choices you can't see: each attribute whose values differ between its variations, with the values they
	 * come in, in the order first given. A variation without the attribute adds no value.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, list<string>>
	 */
	public function choices(): array {
		$values = array();
		foreach ( $this->variants as $variant ) {
			foreach ( $variant->choices as $name => $value ) {
				if ( ! in_array( $value, $values[ $name ] ?? array(), true ) ) {
					$values[ $name ][] = $value;
				}
			}
		}
		$choices = array();
		foreach ( $values as $name => $list ) {
			if ( count( $list ) > 1 ) {
				$choices[ $name ] = $list;
			}
		}
		return $choices;
	}

	/**
	 * Its parts with one of the values chosen in each of its choices: values in one choice widen, and choices narrow
	 * each other. A choice it doesn't have, or one with nothing chosen, narrows nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, list<string>> $chosen The values chosen, by attribute.
	 * @return list<Part>
	 */
	public function narrowed( array $chosen ): array {
		$names = array_keys( $this->choices() );
		$parts = array();
		foreach ( $this->variants as $variant ) {
			foreach ( $chosen as $name => $values ) {
				if ( array() !== $values && in_array( $name, $names, true ) && ! in_array( $variant->choices[ $name ] ?? null, $values, true ) ) {
					continue 2;
				}
			}
			$parts[] = $variant->part;
		}
		return $parts;
	}

	/**
	 * The sizes it comes in on a spec: each distinct figure once, smallest first. A part with no figure adds none.
	 *
	 * @since 1.0.0
	 *
	 * @param SpecKey $spec The slot's size spec.
	 * @return list<Measure>
	 */
	public function sizes( SpecKey $spec ): array {
		$sizes = array();
		foreach ( $this->variants as $variant ) {
			$size = $variant->part->specs->measure( $spec );
			if ( null === $size ) {
				continue;
			}
			foreach ( $sizes as $held ) {
				if ( $held->equals( $size ) ) {
					continue 2;
				}
			}
			$sizes[] = $size;
		}
		usort( $sizes, static fn( Measure $a, Measure $b ): int => $a->compare( $b ) );
		return $sizes;
	}
}
