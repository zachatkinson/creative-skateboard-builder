<?php
/**
 * What a part's sources come to.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

use function array_map;

/**
 * One reading per spec, the winner by the shop's order, and every disagreement on the way there.
 *
 * @since 0.3.0
 */
final readonly class Resolution {

	/**
	 * Constructor.
	 *
	 * @param list<Reading>      $kept          The winning reading of each spec, in the order of the spec keys.
	 * @param list<Disagreement> $disagreements Where sources said different things, in the same order.
	 */
	private function __construct( public array $kept, public array $disagreements ) {}

	/**
	 * The winner of each spec among these readings. Of two readings the earlier source in the order wins; of two
	 * from one source, the first given.
	 *
	 * @since 0.3.0
	 *
	 * @param SourceOrder $order       Which source wins.
	 * @param Reading     ...$readings What every source says of the part.
	 */
	public static function of( SourceOrder $order, Reading ...$readings ): self {
		$kept          = array();
		$disagreements = array();
		foreach ( SpecKey::cases() as $key ) {
			$winner = null;
			foreach ( $readings as $reading ) {
				if ( $reading->key === $key && ( null === $winner || $order->rank( $reading->origin ) < $order->rank( $winner->origin ) ) ) {
					$winner = $reading;
				}
			}
			if ( null === $winner ) {
				continue;
			}
			$kept[] = $winner;
			$others = array();
			foreach ( $readings as $reading ) {
				if ( $reading->key === $key && $reading->text !== $winner->text ) {
					$others[] = $reading;
				}
			}
			if ( array() !== $others ) {
				$disagreements[] = new Disagreement( $winner, $others );
			}
		}
		return new self( $kept, $disagreements );
	}

	/**
	 * The rows the index holds for the part.
	 *
	 * @since 0.3.0
	 *
	 * @return list<IndexedSpec>
	 */
	public function rows(): array {
		return array_map( static fn( Reading $reading ): IndexedSpec => $reading->row(), $this->kept );
	}
}
