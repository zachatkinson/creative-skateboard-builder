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
 * Two options, neither autoloaded: only the import's screen, its job and the command line read them. Options and not
 * transients, so a shop with a persistent object cache does not lose a waiting import when the cache is flushed or an
 * entry is too large for it.
 *
 * `cbuild_import_rows` holds the rows of the file and the products its SKUs named, which do not change once the file
 * is read: up to 5,000 rows, written once. `cbuild_import_state` holds the rest, which every batch changes: the
 * status, the place, the counts and the rows refused. A batch so writes a small option, not the whole file again
 * (D-090). The rows carry the import's token, and an import whose rows are gone or are another import's is none.
 *
 * @since 0.3.0
 */
final class OptionImportStore implements ImportStore {

	/**
	 * What of an import is kept with its rows.
	 */
	private const HELD = array( 'rows', 'found' );

	/**
	 * The token of the import whose rows this request has read or written, so it does not write them again.
	 *
	 * @var string|null
	 */
	private ?string $held = null;

	/**
	 * The import as stored.
	 *
	 * @since 0.3.0
	 *
	 * @return array<mixed>|null
	 */
	public function load(): ?array {
		// From the table: a second manager, the queue or the command line may have changed it (FreshOption).
		$state = FreshOption::read( Options::IMPORT );
		if ( null === $state || true !== ( $state['has_rows'] ?? false ) ) {
			return $state;
		}
		$rows = FreshOption::read( Options::IMPORT_ROWS );
		if ( null === $rows || ( $rows['token'] ?? null ) !== ( $state['token'] ?? '' ) ) {
			return null;
		}
		$this->held = is_string( $rows['token'] ) ? $rows['token'] : null;
		unset( $state['has_rows'] );
		foreach ( self::HELD as $part ) {
			$state[ $part ] = $rows[ $part ] ?? array();
		}
		return $state;
	}

	/**
	 * Store the import.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $state The import.
	 */
	public function save( array $state ): void {
		$token = is_string( $state['token'] ?? null ) ? $state['token'] : '';
		$rows  = array( 'token' => $token );
		foreach ( self::HELD as $part ) {
			$rows[ $part ] = $state[ $part ] ?? array();
			unset( $state[ $part ] );
		}
		$has = array() !== $rows['rows'] && '' !== $token;
		if ( ! $has ) {
			delete_option( Options::IMPORT_ROWS );
			$this->held = null;
		} elseif ( $token !== $this->held ) {
			// The rows first: a state that says it has rows never waits on rows not yet written.
			update_option( Options::IMPORT_ROWS, $rows, false );
			$this->held = $token;
		}
		update_option( Options::IMPORT, $has ? $state + array( 'has_rows' => true ) : $state, false );
	}

	/**
	 * Forget the import.
	 *
	 * @since 0.3.0
	 */
	public function clear(): void {
		delete_option( Options::IMPORT );
		delete_option( Options::IMPORT_ROWS );
		$this->held = null;
	}
}
