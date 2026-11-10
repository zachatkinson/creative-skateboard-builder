<?php
/**
 * Where an import waits and keeps its place.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one import a shop has in hand, as the array `Service\ImportState` writes and reads: the rows read from the
 * file while it waits to be confirmed and while it runs, and what it did once it has.
 *
 * @since 0.3.0
 */
interface ImportStore {

	/**
	 * The import as stored, read from the database now, or null when there is none.
	 *
	 * @since 0.3.0
	 *
	 * @return array<mixed>|null
	 */
	public function load(): ?array;

	/**
	 * Store the import.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $state The import.
	 */
	public function save( array $state ): void;

	/**
	 * Forget the import.
	 *
	 * @since 0.3.0
	 */
	public function clear(): void;
}
