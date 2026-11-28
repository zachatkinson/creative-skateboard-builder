<?php
/**
 * A stand-in for the wheels' faces.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\WheelFaces;

/**
 * Each part's face, as set, and the parts asked for.
 */
final class FakeWheelFaces implements WheelFaces {

	/**
	 * The faces, by part.
	 *
	 * @var array<int, array{url: string, x: int, y: int, size: int, tread: string}>
	 */
	public array $faces = array();

	/**
	 * The parts asked for, in order.
	 *
	 * @var list<int>
	 */
	public array $asked = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param int $part The wheel part's product ID.
	 * @return array{url: string, x: int, y: int, size: int, tread: string}|null
	 */
	public function of( int $part ): ?array {
		$this->asked[] = $part;
		return $this->faces[ $part ] ?? null;
	}
}
