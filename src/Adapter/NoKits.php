<?php
/**
 * The shop's kits, before there is a kit product type.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Kits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * None. Milestone 4 adds the kit product type and the adapter that reads its documents and its slots' parts, and
 * binds that in this one's place.
 *
 * @since 0.3.0
 */
final class NoKits implements Kits {

	/**
	 * Every kit.
	 *
	 * @since 0.3.0
	 *
	 * @return array<int, array{name: string, kit: \CreativeBranding\SkateboardBuilder\Domain\Kit\Kit}>
	 */
	public function all(): array {
		return array();
	}

	/**
	 * A slot's parts.
	 *
	 * @since 0.3.0
	 *
	 * @param int    $kit  The kit product's ID.
	 * @param string $slot The slot's key.
	 * @return list<\CreativeBranding\SkateboardBuilder\Domain\Kit\Part>
	 */
	public function candidates( int $kit, string $slot ): array {
		unset( $kit, $slot );
		return array();
	}
}
