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
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotSources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit is a product a shopper builds from parts (spec: Vocabulary): a product of the kit type, with its documents: four, and from 0.7.0 the picture's.
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
	 * The parts that can fill one of a kit's slots, as candidates() finds them, from what is kept of each part: a product is read from
	 * the shop only when nothing is kept of it, so a request with a budget can ask (D-116). What is kept is for the
	 * prices this visitor is shown, and is at most two hours old where the shop was not told of a change.
	 *
	 * @since 0.4.0
	 *
	 * @param int  $kit  The kit product's ID.
	 * @param Slot $slot One of its slots, as kit() gave it: its sources are not read again.
	 * @return list<Part>
	 */
	public function offered( int $kit, Slot $slot ): array;

	/**
	 * A build's parts as the shop has them now, read from WooCommerce itself and not from what is kept of them,
	 * for a build on its way to the cart or in it (issue #26): each one's price, stock and specs as they are at
	 * this moment. They are read together, so a build of seven parts costs what one part does and not seven times
	 * that (D-134). A slot is left out of the answer when its ID is not one of the slot's parts, or not one a
	 * shopper could be shown and could buy.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int|string, array{Slot, int}> $asked By any key: one of a kit's slots, as kit() gave it, and a
	 *                                               product's or variation's ID.
	 * @return array<int|string, Part> By the same keys.
	 */
	public function picked( array $asked ): array;

	/**
	 * Forget what is kept of these products and variations, or of every one when none is named.
	 *
	 * @since 0.4.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function forget( int ...$ids ): void;

	/**
	 * Read these products and variations from the shop now and keep what a build is told of them, for the prices
	 * a visitor nothing is known of is shown: the background job's work, so that no visitor's request does it
	 * (issue #28). A product no kit's slot could offer is passed over.
	 *
	 * @since 0.6.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function warm( int ...$ids ): void;

	/**
	 * A term of this taxonomy was edited or deleted: forget what is kept that it is written into.
	 *
	 * @since 0.4.0
	 *
	 * @param string $taxonomy The taxonomy.
	 */
	public function termMoved( string $taxonomy ): void;

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

	/**
	 * Do a piece of work that reads many builds of the shop's kits, such as a customer's saved builds, holding each
	 * slot's offered parts for its length, so the parts are read once however many builds share a slot. Nothing is
	 * held past it.
	 *
	 * @since 0.8.0
	 *
	 * @template T
	 * @param \Closure(): T $work The work.
	 * @return T
	 */
	public function together( \Closure $work ): mixed;
}
