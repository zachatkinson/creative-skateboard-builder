<?php
/**
 * The shop's settings, from their option.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads `cbuild_settings`. Whatever the option holds, the answer is a valid setting: a key that is missing or
 * malformed reads as its default, and a line of the mapping that is not one is left out. The settings screen checks
 * what a person saves; this guards what another plugin, an import or an older version left there.
 *
 * @since 0.3.0
 */
final class OptionShopSettings implements ShopSettings {

	/**
	 * Which source wins where two disagree.
	 *
	 * @since 0.3.0
	 */
	public function sourceOrder(): SourceOrder {
		return SourceOrder::read( $this->stored()[ Options::SETTING_SOURCE_ORDER ] ?? null );
	}

	/**
	 * Which attribute feeds which spec.
	 *
	 * @since 0.3.0
	 *
	 * @return list<AttributeMap>
	 */
	public function attributeMapping(): array {
		$lines   = $this->stored()[ Options::SETTING_ATTRIBUTE_MAPPING ] ?? null;
		$mapping = array();
		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$map = AttributeMap::read( $line );
			if ( null !== $map ) {
				$mapping[] = $map;
			}
		}
		return $mapping;
	}

	/**
	 * The option, as an array.
	 *
	 * @return array<mixed>
	 */
	private function stored(): array {
		$stored = get_option( Options::SETTINGS, array() );
		return is_array( $stored ) ? $stored : array();
	}
}
