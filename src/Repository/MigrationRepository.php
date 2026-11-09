<?php
/**
 * The migration runner's state, in the options table.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Repository;

use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A repository: SQL lives here (docs/STANDARDS.md). The lock and the fresh version are read past the object cache on purpose (D-021).

/**
 * `cbuild_db_version` (autoloaded, read on every request), `cbuild_migrations` (not autoloaded) and the lock
 * `cbuild_migration_lock`, written with one atomic statement and taken over by compare-and-swap, as
 * `WP_Upgrader::create_lock()` and `ActionScheduler_OptionLock` do (D-021). The lock and the fresh version are read
 * from the table, never from the object cache.
 */
final class MigrationRepository implements MigrationStore {

	/**
	 * The installed schema version, '0' when none.
	 */
	public function schemaVersion(): string {
		$stored = get_option( Options::DB_VERSION, '0' );
		return is_scalar( $stored ) ? (string) $stored : '0';
	}

	/**
	 * The installed schema version read from the table now.
	 */
	public function freshSchemaVersion(): string {
		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, Options::DB_VERSION ) );
		return is_string( $stored ) && '' !== $stored ? $stored : '0';
	}

	/**
	 * Record the schema version, autoloaded.
	 *
	 * @param string $version The version.
	 */
	public function recordSchemaVersion( string $version ): void {
		update_option( Options::DB_VERSION, $version, true );
	}

	/**
	 * One migration's stored state, or null when it has never started.
	 *
	 * @param string $key The migration's key.
	 * @return array<string, mixed>|null
	 */
	public function state( string $key ): ?array {
		return $this->states()[ $key ] ?? null;
	}

	/**
	 * Every migration's stored state, keyed by migration.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function states(): array {
		$stored = get_option( Options::MIGRATIONS, array() );
		$states = array();
		foreach ( is_array( $stored ) ? $stored : array() as $key => $data ) {
			if ( is_string( $key ) && is_array( $data ) ) {
				/** @var array<string, mixed> $data */
				$states[ $key ] = $data;
			}
		}
		return $states;
	}

	/**
	 * Save one migration's state, not autoloaded.
	 *
	 * @param string               $key  The migration's key.
	 * @param array<string, mixed> $data The state.
	 */
	public function save( string $key, array $data ): void {
		$stored         = $this->states();
		$stored[ $key ] = $data;
		update_option( Options::MIGRATIONS, $stored, false );
	}

	/**
	 * Take the lock in one statement, or take over one older than the TTL by compare-and-swap.
	 *
	 * @param int $now The moment, as a UTC timestamp.
	 * @param int $ttl Seconds after which a held lock counts as abandoned.
	 */
	public function acquireLock( int $now, int $ttl ): bool {
		global $wpdb;
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, Options::MIGRATION_LOCK, (string) $now, 'off' ) );
		if ( 1 === $inserted ) {
			$this->forgetLockCache();
			return true;
		}
		$held = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, Options::MIGRATION_LOCK ) );
		if ( ! is_string( $held ) || ! is_numeric( $held ) || $now - (int) $held <= $ttl ) {
			return false;
		}
		$taken = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s', $wpdb->options, (string) $now, Options::MIGRATION_LOCK, $held ) );
		if ( 1 === $taken ) {
			$this->forgetLockCache();
			return true;
		}
		return false;
	}

	/**
	 * Keep a held lock fresh.
	 *
	 * @param int $now The moment, as a UTC timestamp.
	 */
	public function refreshLock( int $now ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s', $wpdb->options, (string) $now, Options::MIGRATION_LOCK ) );
		$this->forgetLockCache();
	}

	/**
	 * Release the lock.
	 */
	public function releaseLock(): void {
		delete_option( Options::MIGRATION_LOCK );
	}

	/**
	 * The lock's value, read from the table, or null when none is held. For the tests and Site Health.
	 */
	public function lockValue(): ?string {
		global $wpdb;
		$held = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, Options::MIGRATION_LOCK ) );
		return is_string( $held ) ? $held : null;
	}

	/**
	 * The lock is written around the options API, so its cached copy, and the note that it did not exist, go.
	 */
	private function forgetLockCache(): void {
		wp_cache_delete( Options::MIGRATION_LOCK, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ Options::MIGRATION_LOCK ] ) ) {
			unset( $notoptions[ Options::MIGRATION_LOCK ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
