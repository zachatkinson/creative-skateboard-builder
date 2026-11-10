<?php
/**
 * Spec tables a test sets.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;

/**
 * Holds the tables made, each with its name and seed.
 */
final class InMemorySpecTables implements SpecTables {

	/**
	 * The tables, by ID.
	 *
	 * @var array<int, array{name: string, table: SpecTable, seed: string}>
	 */
	public array $made = array();

	/**
	 * When set, create() throws it once this many tables exist.
	 */
	public ?\Throwable $fails = null;

	public int $failsAfter = 0;

	public function find( int ...$ids ): array {
		$found = array();
		foreach ( $ids as $id ) {
			if ( isset( $this->made[ $id ] ) ) {
				$found[ $id ] = $this->made[ $id ]['table'];
			}
		}
		return $found;
	}

	public function create( string $name, SpecTable $table, string $seed = '' ): int {
		if ( null !== $this->fails && count( $this->made ) >= $this->failsAfter ) {
			throw $this->fails;
		}
		$id                = count( $this->made ) + 1;
		$this->made[ $id ] = array(
			'name'  => $name,
			'table' => $table,
			'seed'  => $seed,
		);
		return $id;
	}

	public function hasSeed( string $seed ): bool {
		return in_array( $seed, array_column( $this->made, 'seed' ), true );
	}

	/**
	 * Every table, by ID, in the order of their names.
	 *
	 * @return array<int, array{name: string, table: SpecTable}>
	 */
	public function all(): array {
		$all = array();
		foreach ( $this->made as $id => $made ) {
			$all[ $id ] = array(
				'name'  => $made['name'],
				'table' => $made['table'],
			);
		}
		uasort( $all, static fn( array $a, array $b ): int => $a['name'] <=> $b['name'] );
		return $all;
	}
}
