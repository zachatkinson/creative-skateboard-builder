<?php
/**
 * Parts' looks, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Port\PartLooks;

/**
 * Looks set by the test, by part ID.
 */
final class InMemoryPartLooks implements PartLooks {

	/**
	 * The looks.
	 *
	 * @var array<int, LookFact>
	 */
	public array $facts = array();

	/**
	 * The IDs each call asked for.
	 *
	 * @var list<list<int>>
	 */
	public array $asked = array();

	/**
	 * The facts of these parts, by ID, in the order asked.
	 *
	 * @param list<int> $ids Parts' IDs.
	 * @return array<int, LookFact>
	 */
	public function looks( array $ids ): array {
		$this->asked[] = $ids;
		$found         = array();
		foreach ( $ids as $id ) {
			if ( isset( $this->facts[ $id ] ) ) {
				$found[ $id ] = $this->facts[ $id ];
			}
		}
		return $found;
	}
}
