<?php
/**
 * Where the reindex keeps its place.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The reindex's state between its batches, as the array `Service\ReindexState` writes and reads (spec: Options and
 * jobs: everything, 100 parts at a time, from a saved cursor).
 *
 * @since 0.3.0
 */
interface ReindexStore {

	/**
	 * The state as stored, read from the database now, or null when no reindex has ever been asked for.
	 *
	 * @since 0.3.0
	 *
	 * @return array<mixed>|null
	 */
	public function load(): ?array;

	/**
	 * Store the state.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $state The state.
	 */
	public function save( array $state ): void;
}
