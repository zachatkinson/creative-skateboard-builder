<?php
/**
 * Which source wins where two disagree.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use function array_column;
use function array_search;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * The shop's order of its three sources, first wins (spec: Admin, Settings; D-026). The maker's chart comes first
 * unless the shop says otherwise, then what was filled in on the part, then a mapped attribute.
 *
 * @since 0.3.0
 */
final readonly class SourceOrder {

	/**
	 * Constructor.
	 *
	 * @param list<Origin> $origins Every source once, first wins.
	 */
	private function __construct( public array $origins ) {}

	/**
	 * The order when a shop has set none: the chart, the field, the attribute.
	 *
	 * @since 0.3.0
	 */
	public static function standard(): self {
		return new self( array( Origin::Table, Origin::Field, Origin::Attribute ) );
	}

	/**
	 * The order a shop saved, by the sources' names. Anything that is not every source exactly once reads as the
	 * standard order: a setting from a version with another source must not leave one out.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $names What was stored.
	 */
	public static function read( mixed $names ): self {
		$given   = is_array( $names ) ? $names : array();
		$origins = array();
		foreach ( $given as $name ) {
			$origin = is_string( $name ) ? Origin::tryFrom( $name ) : null;
			if ( null !== $origin && ! in_array( $origin, $origins, true ) ) {
				$origins[] = $origin;
			}
		}
		$whole = count( $origins ) === count( Origin::cases() ) && count( $origins ) === count( $given );
		return $whole ? new self( $origins ) : self::standard();
	}

	/**
	 * A source's place, 0 first.
	 *
	 * @since 0.3.0
	 *
	 * @param Origin $origin The source.
	 */
	public function rank( Origin $origin ): int {
		return (int) array_search( $origin, $this->origins, true );
	}

	/**
	 * The sources' names in order, for the store.
	 *
	 * @since 0.3.0
	 *
	 * @return list<string>
	 */
	public function names(): array {
		return array_column( $this->origins, 'value' );
	}
}
