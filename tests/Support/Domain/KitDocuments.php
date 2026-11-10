<?php
/**
 * A complete skateboard's four documents, for the domain's tests.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Domain;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;

/**
 * The kit the spec describes: deck, trucks, wheels, risers, bearings, hardware and grip, in that order, with R7's
 * units. Each method returns a fresh decoded document a test can bend before building from it.
 */
final class KitDocuments {

	/**
	 * @return array<string, mixed>
	 */
	public static function slots(): array {
		$slot = static fn( string $key, string $label, bool $required, int $units ): array => array(
			'key'      => $key,
			'label'    => $label,
			'required' => $required,
			'units'    => $units,
		);
		return array(
			'schema_version' => 1,
			'slots'          => array(
				$slot( 'deck', 'Deck', true, 1 ),
				$slot( 'trucks', 'Trucks', true, 2 ),
				$slot( 'wheels', 'Wheels', true, 4 ),
				$slot( 'risers', 'Risers', false, 2 ),
				$slot( 'bearings', 'Bearings', true, 8 ),
				$slot( 'hardware', 'Hardware', true, 8 ),
				$slot( 'grip', 'Grip', true, 1 ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function rules(): array {
		return RuleSettings::DEFAULTS;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function presets(): array {
		return array(
			'schema_version' => 1,
			'presets'        => array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function assembly(): array {
		return array(
			'schema_version' => 1,
			'default_on'     => true,
			'fee'            => '0',
			'lead_time'      => '',
			'returns_notice' => '',
		);
	}

	/**
	 * The kit, with any document replaced.
	 *
	 * @param array<string, mixed>|null $slots    Slots.
	 * @param array<string, mixed>|null $rules    Rules.
	 * @param array<string, mixed>|null $presets  Presets.
	 * @param array<string, mixed>|null $assembly Assembly.
	 */
	public static function kit( ?array $slots = null, ?array $rules = null, ?array $presets = null, ?array $assembly = null ): Kit {
		return Kit::fromDocuments( $slots ?? self::slots(), $rules ?? self::rules(), $presets ?? self::presets(), $assembly ?? self::assembly() );
	}
}
