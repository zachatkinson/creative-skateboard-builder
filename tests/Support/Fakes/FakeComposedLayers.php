<?php
/**
 * A test double: answers for the parts' layers in the composed pictures.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\ComposedLayers;

/**
 * Answers AS_IS but where told otherwise, by part and use, and keeps what it was asked.
 */
final class FakeComposedLayers implements ComposedLayers {

	/**
	 * The answers, by part ID and then use.
	 *
	 * @var array<int, array<string, string>>
	 */
	public array $answers = array();

	/**
	 * What it was asked: part, slot, kit and use.
	 *
	 * @var list<array{int, string, int, string}>
	 */
	public array $asked = array();

	public function answer( int $part, string $slot, int $kit, string $purpose ): string {
		$this->asked[] = array( $part, $slot, $kit, $purpose );
		return $this->answers[ $part ][ $purpose ] ?? self::AS_IS;
	}
}
