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
	 *
	 * @since 0.1.0
	 */
	public function schemaVersion(): string {
		$stored = get_option( Options::DB_VERSION, '0' );
		return is_scalar( $stored ) ? (string) $stored : '0';
	}

	/**
	 * The installed schema version read from the table now.
	 *
	 * @since 0.1.0
	 */
	public function freshSchemaVersion(): string {
		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, Options::DB_VERSION ) );
		return is_string( $stored ) && '' !== $stored ? $stored : '0';
	}

	/**
	 * Record the schema version, autoloaded.
	 *
	 * @since 0.1.0
	 *
	 * @param string $version The version.
	 */
	public function recordSchemaVersion( string $version ): void {
		update_option( Options::DB_VERSION, $version, true );
	}

	/**
	 * One migration's stored state, or null when it has never started.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * Every migration's stored state read from the table now. The options cache's copy, and its note that the option
	 * did not exist, are dropped first, so this read and every read and save after it in this request start from the
	 * row (D-043).
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function freshStates(): array {
		$this->forgetCached( Options::MIGRATIONS );
		return $this->states();
	}

	/**
	 * Save one migration's state, not autoloaded.
	 *
	 * @since 0.1.0
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
	 * The lock's value while this instance holds it: the moment it was taken or last refreshed, a colon, and a token
	 * of this process's own, so a lock is only ever refreshed or released by the run that holds it (D-033).
	 *
	 * @var string|null
	 */
	private ?string $held = null;

	/**
	 * This process's token.
	 *
	 * @var string|null
	 */
	private ?string $token = null;

	/**
	 * Take the lock in one statement, or take over one older than the TTL by compare-and-swap.
	 *
	 * @since 0.1.0
	 *
	 * @param int $now The moment, as a UTC timestamp.
	 * @param int $ttl Seconds after which a held lock counts as abandoned.
	 */
	public function acquireLock( int $now, int $ttl ): bool {
		global $wpdb;
		$mine     = $this->lockFor( $now );
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, Options::MIGRATION_LOCK, $mine, 'off' ) );
		if ( 1 === $inserted ) {
			$this->forgetLockCache();
			$this->held = $mine;
			return true;
		}
		$held = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, Options::MIGRATION_LOCK ) );
		if ( ! is_string( $held ) ) {
			return false;
		}
		$since = explode( ':', $held, 2 )[0];
		if ( ! ctype_digit( $since ) || $now - (int) $since <= $ttl ) {
			return false;
		}
		$taken = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s', $wpdb->options, $mine, Options::MIGRATION_LOCK, $held ) );
		if ( 1 === $taken ) {
			$this->forgetLockCache();
			$this->held = $mine;
			return true;
		}
		return false;
	}

	/**
	 * Keep a held lock fresh, if it is still this instance's.
	 *
	 * @since 0.1.0
	 *
	 * @param int $now The moment, as a UTC timestamp.
	 */
	public function refreshLock( int $now ): bool {
		global $wpdb;
		if ( null === $this->held ) {
			return false;
		}
		$mine = $this->lockFor( $now );
		if ( $mine === $this->held ) {
			// The same second: nothing to write, and an UPDATE that changes nothing reports no row either way.
			return $this->lockValue() === $this->held;
		}
		$kept = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s', $wpdb->options, $mine, Options::MIGRATION_LOCK, $this->held ) );
		$this->forgetLockCache();
		if ( 1 !== $kept ) {
			$this->held = null;
			return false;
		}
		$this->held = $mine;
		return true;
	}

	/**
	 * Release the lock, if this instance holds it.
	 *
	 * @since 0.1.0
	 */
	public function releaseLock(): void {
		global $wpdb;
		if ( null === $this->held ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, Options::MIGRATION_LOCK, $this->held ) );
		$this->forgetLockCache();
		$this->held = null;
	}

	/**
	 * Remove the lock whoever holds it.
	 *
	 * @since 0.1.0
	 */
	public function clearLock(): void {
		delete_option( Options::MIGRATION_LOCK );
		$this->forgetLockCache();
		$this->held = null;
	}

	/**
	 * The value this process writes for a moment.
	 *
	 * @param int $now The moment, as a UTC timestamp.
	 */
	private function lockFor( int $now ): string {
		$this->token ??= bin2hex( random_bytes( 8 ) );
		return $now . ':' . $this->token;
	}

	/**
	 * The lock's value, read from the table, or null when none is held. For the tests and Site Health.
	 *
	 * @since 0.1.0
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
		$this->forgetCached( Options::MIGRATION_LOCK );
	}

	/**
	 * Drop an option's cached copy, and the note that it did not exist, so the next read is the table's.
	 *
	 * @param string $option The option.
	 */
	private function forgetCached( string $option ): void {
		wp_cache_delete( $option, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
			unset( $notoptions[ $option ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
