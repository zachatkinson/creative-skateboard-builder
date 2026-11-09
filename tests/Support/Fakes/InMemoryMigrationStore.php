<?php
/**
 * The migration store, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationState;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;

/**
 * Versions, states and a lock with the same TTL rule as the repository's, plus counters the tests read.
 */
class InMemoryMigrationStore implements MigrationStore {

	public string $version = '0';

	/**
	 * Stored states by key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $saved = array();

	public int $saves = 0;

	public ?int $lock = null;

	public int $refreshes = 0;

	public int $releases = 0;

	public int $freshReads = 0;

	/**
	 * The version.
	 */
	public function schemaVersion(): string {
		return $this->version;
	}

	/**
	 * The version, counted as a fresh read.
	 */
	public function freshSchemaVersion(): string {
		++$this->freshReads;
		return $this->version;
	}

	/**
	 * Record.
	 *
	 * @param string $version Version.
	 */
	public function recordSchemaVersion( string $version ): void {
		$this->version = $version;
	}

	/**
	 * One stored state.
	 *
	 * @param string $key Key.
	 * @return array<string, mixed>|null
	 */
	public function state( string $key ): ?array {
		return $this->saved[ $key ] ?? null;
	}

	/**
	 * All stored states.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function states(): array {
		return $this->saved;
	}

	/**
	 * Save.
	 *
	 * @param string               $key  Key.
	 * @param array<string, mixed> $data State.
	 */
	public function save( string $key, array $data ): void {
		$this->saved[ $key ] = $data;
		++$this->saves;
	}

	/**
	 * One state, typed, for assertions.
	 *
	 * @param string $key Key.
	 */
	public function stateOf( string $key ): ?MigrationState {
		return isset( $this->saved[ $key ] ) ? MigrationState::fromArray( $key, $this->saved[ $key ] ) : null;
	}

	/**
	 * Seed a state.
	 *
	 * @param MigrationState $state State.
	 */
	public function seed( MigrationState $state ): void {
		$this->save( $state->key, $state->toArray() );
	}

	/**
	 * Whether the lock is this store's own: false once another request has taken it over.
	 *
	 * @var bool
	 */
	public bool $mine = false;

	/**
	 * Lock, or take over an expired one.
	 *
	 * @param int $now Now.
	 * @param int $ttl TTL.
	 */
	public function acquireLock( int $now, int $ttl ): bool {
		if ( null === $this->lock || $now - $this->lock > $ttl ) {
			$this->lock = $now;
			$this->mine = true;
			return true;
		}
		return false;
	}

	/**
	 * Another request decides this one crashed and takes the lock.
	 *
	 * @param int $at When.
	 */
	public function takenOver( int $at ): void {
		$this->lock = $at;
		$this->mine = false;
	}

	/**
	 * Refresh, if the lock is still this store's.
	 *
	 * @param int $now Now.
	 */
	public function refreshLock( int $now ): bool {
		++$this->refreshes;
		if ( ! $this->mine ) {
			return false;
		}
		$this->lock = $now;
		return true;
	}

	/**
	 * Release, if the lock is this store's.
	 */
	public function releaseLock(): void {
		if ( ! $this->mine ) {
			return;
		}
		++$this->releases;
		$this->lock = null;
		$this->mine = false;
	}

	/**
	 * Remove the lock whoever holds it.
	 */
	public function clearLock(): void {
		$this->lock = null;
		$this->mine = false;
	}
}
