<?php
/**
 * Where the migration runner keeps its state.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The schema version, each migration's state and cursor, and the lock (spec: Upgrades and the public API; D-021).
 * Repository\MigrationRepository keeps them in the options table; tests keep them in memory. A state is stored as the
 * array Install\Migrations\MigrationState writes and reads, so the port knows nothing of the framework above it
 * (D-020: ports depend on the domain alone).
 */
interface MigrationStore {

	/**
	 * The installed schema version, '0' when none. May come from the object cache.
	 *
	 * @since 0.1.0
	 */
	public function schemaVersion(): string;

	/**
	 * The installed schema version read from the database now, past any cache: what the runner trusts once it holds
	 * the lock, since another request may have finished in between.
	 *
	 * @since 0.1.0
	 */
	public function freshSchemaVersion(): string;

	/**
	 * Record the schema version, once every migration is done.
	 *
	 * @since 0.1.0
	 *
	 * @param string $version The version.
	 */
	public function recordSchemaVersion( string $version ): void;

	/**
	 * One migration's stored state, or null when it has never started.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key The migration's key.
	 * @return array<string, mixed>|null
	 */
	public function state( string $key ): ?array;

	/**
	 * Every migration's stored state, keyed by migration. May come from the object cache.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function states(): array;

	/**
	 * Every migration's stored state read from the database now, past any cache, and the copy every later read and
	 * save in this request starts from: what the runner trusts once it holds the lock, since another process may
	 * have moved a cursor on or recorded a failure in between (D-043).
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function freshStates(): array;

	/**
	 * Save one migration's state.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $key  The migration's key.
	 * @param array<string, mixed> $data The state, as MigrationState::toArray() writes it.
	 */
	public function save( string $key, array $data ): void;

	/**
	 * Take the lock in one atomic step, or take over one older than the TTL by compare-and-swap (D-021).
	 *
	 * @since 0.1.0
	 *
	 * @param int $now The moment, as a UTC timestamp.
	 * @param int $ttl Seconds after which a held lock counts as abandoned.
	 * @return bool Whether this caller holds the lock.
	 */
	public function acquireLock( int $now, int $ttl ): bool;

	/**
	 * Keep a held lock fresh during a long run.
	 *
	 * @since 0.1.0
	 *
	 * @param int $now The moment, as a UTC timestamp.
	 * @return bool Whether the lock is still this caller's. False means another request took it over, and this run
	 *              must stop without touching it.
	 */
	public function refreshLock( int $now ): bool;

	/**
	 * Release the lock, if this caller holds it. A lock another request has taken over is left alone.
	 *
	 * @since 0.1.0
	 */
	public function releaseLock(): void;

	/**
	 * Remove the lock whoever holds it: deactivation, where no run should outlive the plugin.
	 *
	 * @since 0.1.0
	 */
	public function clearLock(): void;
}
