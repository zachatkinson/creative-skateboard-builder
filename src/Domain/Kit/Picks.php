<?php
/**
 * The parts picked so far.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use function array_flip;
use function array_intersect_key;
use function array_keys;

/**
 * One part per slot, by the slot's key (spec: Vocabulary, Build). Never changed in place: a pick makes new picks, so
 * a change list can hand back the build as it was.
 */
final readonly class Picks {

	/**
	 * Constructor.
	 *
	 * @param array<string, Part> $parts The parts by slot key.
	 */
	private function __construct( private array $parts ) {}

	/**
	 * Nothing picked.
	 *
	 * @since 0.2.0
	 */
	public static function none(): self {
		return new self( array() );
	}

	/**
	 * These picks with a slot's part set or replaced.
	 *
	 * @since 0.2.0
	 *
	 * @param string $slot The slot's key.
	 * @param Part   $part The part.
	 */
	public function with( string $slot, Part $part ): self {
		return new self( array( $slot => $part ) + $this->parts );
	}

	/**
	 * These picks with a slot emptied.
	 *
	 * @since 0.2.0
	 *
	 * @param string $slot The slot's key.
	 */
	public function without( string $slot ): self {
		$parts = $this->parts;
		unset( $parts[ $slot ] );
		return new self( $parts );
	}

	/**
	 * Only the picks in these slots.
	 *
	 * @since 0.2.0
	 *
	 * @param list<string> $slots The slots' keys.
	 */
	public function only( array $slots ): self {
		return new self( array_intersect_key( $this->parts, array_flip( $slots ) ) );
	}

	/**
	 * The part in a slot, or null when it is empty.
	 *
	 * @since 0.2.0
	 *
	 * @param string $slot The slot's key.
	 */
	public function part( string $slot ): ?Part {
		return $this->parts[ $slot ] ?? null;
	}

	/**
	 * The keys of the filled slots.
	 *
	 * @since 0.2.0
	 *
	 * @return list<string>
	 */
	public function slots(): array {
		return array_keys( $this->parts );
	}
}
