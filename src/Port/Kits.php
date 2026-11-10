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
}
