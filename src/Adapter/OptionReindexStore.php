<?php
/**
 * The reindex's place, in an option.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `cbuild_reindex_state`, not autoloaded: only the reindex's job and Site Health read it.
 *
 * @since 0.3.0
 */
final class OptionReindexStore implements ReindexStore {

	/**
	 * The state as stored.
	 *
	 * @since 0.3.0
	 *
	 * @return array<mixed>|null
	 */
	public function load(): ?array {
		$stored = get_option( Options::REINDEX, null );
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Store the state.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $state The state.
	 */
	public function save( array $state ): void {
		update_option( Options::REINDEX, $state, false );
	}
}
