<?php
/**
 * The specs index, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKind;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use DateTimeImmutable;

/**
 * Holds what the repository's table would, and passes the same cases (tests/Support/SpecIndexCases.php).
 */
final class InMemorySpecIndex implements SpecIndex {

	/**
	 * Rows by part, then by key.
	 *
	 * @var array<int, array<string, IndexedSpec>>
	 */
	public array $rows = array();

	/**
	 * When each part was last written.
	 *
	 * @var array<int, DateTimeImmutable>
	 */
	public array $writtenAt = array();

	public function replace( int $partId, array $rows, DateTimeImmutable $at ): void {
		unset( $this->rows[ $partId ], $this->writtenAt[ $partId ] );
		foreach ( $rows as $row ) {
			$this->rows[ $partId ][ $row->key->value ] = $row;
			$this->writtenAt[ $partId ]                = $at;
		}
	}

	public function forget( int ...$partIds ): void {
		foreach ( $partIds as $partId ) {
			unset( $this->rows[ $partId ], $this->writtenAt[ $partId ] );
		}
	}

	public function specsOf( int ...$partIds ): array {
		$specs = array();
		foreach ( $partIds as $partId ) {
			if ( isset( $this->rows[ $partId ] ) ) {
				$specs[ $partId ] = Specs::fromIndex( array_map( static fn( IndexedSpec $row ): string => $row->text, $this->rows[ $partId ] ) );
			}
		}
		return $specs;
	}

	public function rowsOf( int $partId ): array {
		$ordered = array();
		foreach ( SpecKey::cases() as $key ) {
			if ( isset( $this->rows[ $partId ][ $key->value ] ) ) {
				$ordered[] = $this->rows[ $partId ][ $key->value ];
			}
		}
		return $ordered;
	}

	public function partsBetween( SpecKey $key, Measure $from, Measure $to, int $limit ): array {
		$unit = $key->unit();
		if ( null === $unit ) {
			return array();
		}
		$found = array();
		foreach ( $this->rows as $partId => $rows ) {
			$value = isset( $rows[ $key->value ] ) ? MeasureParser::read( $rows[ $key->value ]->text, $unit ) : null;
			if ( null !== $value && $value->atLeast( $from ) && $value->atMost( $to ) ) {
				$found[ $partId ] = $value;
			}
		}
		uksort( $found, static fn( int $a, int $b ): int => array( $found[ $a ]->compare( $found[ $b ] ), $a ) <=> array( 0, $b ) );
		return array_slice( array_keys( $found ), 0, $limit );
	}

	public function partsWith( SpecKey $key, string $choice, int $limit ): array {
		if ( SpecKind::Choice !== $key->kind() ) {
			return array();
		}
		$found = array();
		foreach ( $this->rows as $partId => $rows ) {
			if ( isset( $rows[ $key->value ] ) && $rows[ $key->value ]->text === $choice ) {
				$found[] = $partId;
			}
		}
		sort( $found );
		return array_slice( $found, 0, $limit );
	}

	public function partsAfter( int $after, int $limit ): array {
		$found = array_values( array_filter( array_keys( $this->rows ), static fn( int $partId ): bool => $partId > $after ) );
		sort( $found );
		return array_slice( $found, 0, $limit );
	}
}
