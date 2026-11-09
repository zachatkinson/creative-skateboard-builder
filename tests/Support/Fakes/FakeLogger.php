<?php
/**
 * A log the tests read.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Logger;

/**
 * Keeps every line.
 */
final class FakeLogger implements Logger {

	/**
	 * The lines, each level, message and context.
	 *
	 * @var list<array{level: string, message: string, context: array<string, mixed>}>
	 */
	public array $lines = array();

	/**
	 * Write one line.
	 *
	 * @param string               $level   Level.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$this->lines[] = array(
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		);
	}
}
