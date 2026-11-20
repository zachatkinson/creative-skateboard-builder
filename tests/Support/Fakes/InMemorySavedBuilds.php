<?php
/**
 * Saved builds held in memory, for unit tests.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Port\SavedBuildStore;

/**
 * The table as a list of rows, by UUID, with each read and change named for its owner as the repository's are.
 */
final class InMemorySavedBuilds implements SavedBuildStore {

	/**
	 * The rows, by UUID.
	 *
	 * @var array<string, SavedBuild>
	 */
	public array $rows = array();

	public function of( int $user ): array {
		$mine = array_values( array_filter( $this->rows, static fn( SavedBuild $build ): bool => $build->user === $user ) );
		usort( $mine, static fn( SavedBuild $a, SavedBuild $b ): int => $b->updated <=> $a->updated );
		return array_slice( $mine, 0, self::MOST );
	}

	public function find( int $user, string $uuid ): ?SavedBuild {
		$build = $this->rows[ $uuid ] ?? null;
		return null !== $build && $build->user === $user ? $build : null;
	}

	public function count( int $user ): int {
		return count( $this->of( $user ) );
	}

	/**
	 * Whether the table refuses every write, as a database that has gone away does.
	 */
	public bool $refuse = false;

	public function put( SavedBuild $build ): bool {
		$held = $this->rows[ $build->uuid ] ?? null;
		if ( $this->refuse || ( null !== $held && $held->user !== $build->user ) || ( null === $held && $this->count( $build->user ) >= self::MOST ) ) {
			return false;
		}
		$this->rows[ $build->uuid ] = $build;
		return true;
	}

	public function delete( int $user, string $uuid ): bool {
		if ( null === $this->find( $user, $uuid ) ) {
			return false;
		}
		unset( $this->rows[ $uuid ] );
		return true;
	}

	public function forget( int $user ): int {
		$mine = $this->of( $user );
		foreach ( $mine as $build ) {
			unset( $this->rows[ $build->uuid ] );
		}
		return count( $mine );
	}
}
