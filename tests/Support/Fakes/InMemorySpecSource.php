<?php
/**
 * A source of specs, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;
use CreativeBranding\SkateboardBuilder\Port\SpecSource;

/**
 * Says of each part what the test gave it, and counts how often it was asked.
 */
final class InMemorySpecSource implements SpecSource {

	/**
	 * Each call's parts.
	 *
	 * @var list<list<int>>
	 */
	public array $asked = array();

	/**
	 * Constructor.
	 *
	 * @param array<int, list<Reading|Unread>> $says What it holds, by part.
	 */
	public function __construct( public array $says = array() ) {}

	public function read( int ...$partIds ): array {
		$this->asked[] = array_values( $partIds );
		$found         = array();
		foreach ( $partIds as $partId ) {
			$found[ $partId ] = $this->says[ $partId ] ?? array();
		}
		return $found;
	}
}
