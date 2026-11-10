<?php
/**
 * The import in hand, in an option.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `cbuild_import_state`, not autoloaded: only the import's screen, its job and the command line read it. An option
 * and not a transient, so a shop with a persistent object cache does not lose a waiting import when the cache is
 * flushed or an entry is too large for it.
 *
 * @since 0.3.0
 */
final class OptionImportStore implements ImportStore {

	/**
	 * The import as stored.
	 *
	 * @since 0.3.0
	 *
	 * @return array<mixed>|null
	 */
	public function load(): ?array {
		$stored = get_option( Options::IMPORT, null );
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Store the import.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $state The import.
	 */
	public function save( array $state ): void {
		update_option( Options::IMPORT, $state, false );
	}

	/**
	 * Forget the import.
	 *
	 * @since 0.3.0
	 */
	public function clear(): void {
		delete_option( Options::IMPORT );
	}
}
