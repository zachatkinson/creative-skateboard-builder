<?php
/**
 * The shop's kits.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotSources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit is a product a shopper builds from parts (spec: Vocabulary): a product of the kit type, with four documents.
 *
 * @since 0.3.0
 */
interface Kits {

	/**
	 * Every kit whose documents read, by the kit product's ID.
	 *
	 * @since 0.3.0
	 *
	 * @return array<int, array{name: string, kit: Kit}>
	 */
	public function all(): array;

	/**
	 * One kit, or null when the product is no kit or its documents do not read. A request for one kit reads one.
	 *
	 * @since 0.4.0
	 *
	 * @param int $id The kit product's ID.
	 */
	public function kit( int $id ): ?Kit;

	/**
	 * Every kit product's ID, whatever its documents hold, for what must leave each one out of a listing.
	 *
	 * @since 0.4.0
	 *
	 * @return list<int>
	 */
	public function ids(): array;

	/**
	 * The parts that can fill one of a kit's slots, as the catalogue finds them, with their specs from the index: only parts
	 * a shopper could be shown and could buy. One that is sold out is among them, marked so.
	 *
	 * @since 0.3.0
	 *
	 * @param int    $kit  The kit product's ID.
	 * @param string $slot The slot's key.
	 * @return list<Part>
	 */
	public function candidates( int $kit, string $slot ): array;

	/**
	 * What was last kept of a slot's parts with keep(), as it was given, or null: nothing is kept, an hour has
	 * passed, something a part's place in a slot depends on moved since (forget()), or the visitor is shown other
	 * prices than the one it was kept for. What comes back is what a cache handed over, to be read as such.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $kit  The kit product's ID.
	 * @param string $slot The slot's key.
	 */
	public function kept( int $kit, string $slot ): mixed;

	/**
	 * Keep a slot's parts for an hour at most, for the prices this visitor is shown, as of the moment kept() was
	 * asked: when something moved since, what is kept is never read, since it may be older than the move.
	 *
	 * @since 0.4.0
	 *
	 * @param int                $kit   The kit product's ID.
	 * @param string             $slot  The slot's key.
	 * @param list<array<mixed>> $parts The parts, as scalars and arrays of them.
	 */
	public function keep( int $kit, string $slot, array $parts ): void;

	/**
	 * Forget every slot's kept parts: a product, a price, a stock count, a term or a spec moved.
	 *
	 * @since 0.4.0
	 */
	public function forget(): void;

	/**
	 * The parts these sources find, as candidates() finds a stored slot's: for sources that are not stored yet, as
	 * when a kit is being saved.
	 *
	 * @since 0.4.0
	 *
	 * @param SlotSources $sources A slot's sources.
	 * @return list<Part>
	 */
	public function parts( SlotSources $sources ): array;

	/**
	 * The keys of the slots, in these kits, whose sources take a product or variation, whether or not a shopper
	 * could buy it yet: a part's specs are filled in before it is published.
	 *
	 * @since 0.4.0
	 *
	 * @param int $product The product's or variation's ID.
	 * @param Kit ...$kits The kits, as all() gave them.
	 * @return list<string>
	 */
	public function slotsOf( int $product, Kit ...$kits ): array;

	/**
	 * Whether a visitor may see this kit: it is a kit, published, and has no password.
	 *
	 * @since 0.4.0
	 *
	 * @param int $kit A product's ID.
	 */
	public function isPublic( int $kit ): bool;

	/**
	 * How many decimals the shop's prices have: the minor units every part's price is in.
	 *
	 * @since 0.4.0
	 */
	public function decimals(): int;
}
