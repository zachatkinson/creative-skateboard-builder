<?php
/**
 * The shop's settings, from their option.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\PluginException;

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
		return self::mapping( $this->stored() );
	}

	/**
	 * The terms every build is sold on.
	 *
	 * @since 0.4.0
	 */
	public function terms(): BuildTerms {
		return BuildTerms::read( $this->stored() );
	}

	/**
	 * What of a stored settings value the index is built from.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $stored The option's value, or anything else when there was none.
	 * @return array{0: list<array<string, string>>, 1: list<string>}
	 */
	public function indexedFrom( mixed $stored ): array {
		return self::indexed( $stored );
	}

	/**
	 * The same, without a shop: the unit tests' settings read a stored value through it.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $stored The option's value.
	 * @return array{0: list<array<string, string>>, 1: list<string>}
	 */
	public static function indexed( mixed $stored ): array {
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			array_map( static fn( AttributeMap $map ): array => $map->toArray(), self::mapping( $stored ) ),
			SourceOrder::read( $stored[ Options::SETTING_SOURCE_ORDER ] ?? null )->names(),
		);
	}

	/**
	 * The mapping's lines that read, from the option.
	 *
	 * @param array<mixed> $stored The option.
	 * @return list<AttributeMap>
	 */
	private static function mapping( array $stored ): array {
		$lines   = $stored[ Options::SETTING_ATTRIBUTE_MAPPING ] ?? null;
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
	 * Save the mapping and the order, and the terms where they are given, leaving the option's other keys as they
	 * are. What is stored is checked against `schema/settings.json` first: the screen's own checks should have
	 * caught anything it refuses.
	 *
	 * @since 0.3.0
	 * @since 0.4.0 Takes the terms.
	 *
	 * @param list<AttributeMap> $mapping The mapping.
	 * @param SourceOrder        $order   Which source wins.
	 * @param BuildTerms|null    $terms   The terms, or null to leave them as they are.
	 * @throws PluginException When the schema refuses the document.
	 */
	public function save( array $mapping, SourceOrder $order, ?BuildTerms $terms = null ): void {
		$stored                                       = $this->stored();
		$stored[ Options::SETTING_ATTRIBUTE_MAPPING ] = array_map( static fn( AttributeMap $map ): array => $map->toArray(), $mapping );
		$stored[ Options::SETTING_SOURCE_ORDER ]      = $order->names();
		if ( null !== $terms ) {
			$stored = $terms->toArray() + $stored;
		}

		$schema = wp_json_file_decode( CBUILD_PATH . 'schema/settings.json', array( 'associative' => true ) );
		$valid  = is_array( $schema ) ? rest_validate_value_from_schema( $stored, $schema, Options::SETTINGS ) : false;
		if ( true !== $valid ) {
			throw new PluginException( esc_html( 'The settings do not match schema/settings.json' . ( is_wp_error( $valid ) ? ': ' . $valid->get_error_message() : '.' ) ) );
		}
		update_option( Options::SETTINGS, $stored );
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
