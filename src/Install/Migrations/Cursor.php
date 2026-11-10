<?php
/**
 * Where a migration stopped.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A migration's position: a value the migration chooses (the last id handled, a page), and whether it is done.
 * Saved after every batch, so a run cut off halfway resumes from it.
 */
final class Cursor {

	/**
	 * Constructor.
	 *
	 * @param int|string|null $position Where the next batch starts; null before the first.
	 * @param bool            $done     Whether the migration has finished.
	 */
	private function __construct( public readonly int|string|null $position, public readonly bool $done ) {}

	/**
	 * Before the first batch.
	 *
	 * @since 0.1.0
	 */
	public static function start(): self {
		return new self( null, false );
	}

	/**
	 * After a batch, with where the next starts.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $position Where the next batch starts.
	 */
	public static function at( int|string $position ): self {
		return new self( $position, false );
	}

	/**
	 * Finished.
	 *
	 * @since 0.1.0
	 */
	public static function done(): self {
		return new self( null, true );
	}

	/**
	 * For the store.
	 *
	 * @since 0.1.0
	 *
	 * @return array{position: int|string|null, done: bool}
	 */
	public function toArray(): array {
		return array(
			'position' => $this->position,
			'done'     => $this->done,
		);
	}

	/**
	 * From the store; anything malformed reads as the start.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $data What was stored.
	 */
	public static function fromArray( mixed $data ): self {
		$data     = is_array( $data ) ? $data : array();
		$position = $data['position'] ?? null;
		if ( ! is_int( $position ) && ! is_string( $position ) ) {
			$position = null;
		}
		$done = isset( $data['done'] ) && true === $data['done'];
		if ( $done ) {
			return self::done();
		}
		return null === $position ? self::start() : self::at( $position );
	}
}
