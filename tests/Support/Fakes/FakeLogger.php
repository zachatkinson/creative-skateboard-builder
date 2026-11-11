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

	/**
	 * The keys a line was written for this hour.
	 *
	 * @var array<string, true>
	 */
	public array $keys = array();

	public function hourly( string $key, string $level, string $message, array $context = array() ): void {
		if ( ! isset( $this->keys[ $key ] ) ) {
			$this->keys[ $key ] = true;
			$this->log( $level, $message, $context );
		}
	}
}
