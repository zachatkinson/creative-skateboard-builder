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
 * A kit is a product a shopper builds from parts (spec: Vocabulary). The kit product type arrives in milestone 4;
 * until then the shop has none, and what reads kits is tested against kits made from documents.
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
	 * The parts that can fill one of a kit's slots, as the catalogue finds them, with their specs from the index.
	 *
	 * @since 0.3.0
	 *
	 * @param int    $kit  The kit product's ID.
	 * @param string $slot The slot's key.
	 * @return list<Part>
	 */
	public function candidates( int $kit, string $slot ): array;
}
