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
	 * The attributes' names, by taxonomy.
	 *
	 * @var array<string, string>
	 */
	public array $names = array();

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

	/**
	 * The attributes' names set, in the order asked.
	 *
	 * @param list<string> $taxonomies Attributes' taxonomies.
	 * @return array<string, string>
	 */
	public function attributes( array $taxonomies ): array {
		$found = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( isset( $this->names[ $taxonomy ] ) ) {
				$found[ $taxonomy ] = $this->names[ $taxonomy ];
			}
		}
		return $found;
	}
}
