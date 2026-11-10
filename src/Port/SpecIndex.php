<?php
/**
 * The specs index.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One value per part and spec, with where it came from and when (spec: Data model, the specs index). Derived: the
 * indexer writes it from the shop's three sources and nothing else does. The fit engine reads a part's specs from
 * here alone, so deciding fit never loads a product. Repository\SpecIndexRepository keeps it in
 * `{prefix}cbuild_specs`; tests keep it in memory.
 *
 * @since 0.3.0
 */
interface SpecIndex {

	/**
	 * Make the index hold exactly these specs for a part: each row written, and any spec the part had before and no
	 * longer has removed. The same rows twice leave the same index. No rows forgets the part.
	 *
	 * @since 0.3.0
	 *
	 * @param int               $partId The part: a product's or a variation's ID.
	 * @param list<IndexedSpec> $rows   Its specs, one per key.
	 * @param DateTimeImmutable $at     When they were read, UTC.
	 */
	public function replace( int $partId, array $rows, DateTimeImmutable $at ): void;

	/**
	 * Remove everything the index holds for these parts.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds The parts.
	 */
	public function forget( int ...$partIds ): void;

	/**
	 * The specs of each of these parts, in one query. A part the index has nothing for is not in the answer.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds The parts.
	 * @return array<int, Specs> By part.
	 */
	public function specsOf( int ...$partIds ): array;

	/**
	 * One part's rows, with their sources, in the order of the spec keys.
	 *
	 * @since 0.3.0
	 *
	 * @param int $partId The part.
	 * @return list<IndexedSpec>
	 */
	public function rowsOf( int $partId ): array;

	/**
	 * The parts whose measure for a spec is from one value to another, both included, smallest first.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key   A measure's key. Any other key has no answer.
	 * @param Measure $from  The least.
	 * @param Measure $to    The most.
	 * @param int     $limit How many at most.
	 * @return list<int> Part IDs.
	 */
	public function partsBetween( SpecKey $key, Measure $from, Measure $to, int $limit ): array;

	/**
	 * The parts whose choice for a spec is this one, by ID.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecKey $key    A choice's key. Any other key has no answer.
	 * @param string  $choice The value.
	 * @param int     $limit  How many at most.
	 * @return list<int> Part IDs.
	 */
	public function partsWith( SpecKey $key, string $choice, int $limit ): array;

	/**
	 * The parts the index holds anything for, after a part ID, by ID: how a reindex walks what is already there.
	 *
	 * @since 0.3.0
	 *
	 * @param int $after The last part ID seen, 0 to start.
	 * @param int $limit How many at most.
	 * @return list<int> Part IDs, each once.
	 */
	public function partsAfter( int $after, int $limit ): array;
}
