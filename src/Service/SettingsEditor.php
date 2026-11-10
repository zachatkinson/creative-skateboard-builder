<?php
/**
 * Checks and saves the shop's settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a person sends from WooCommerce > Settings > Builder is checked here, line by line, and saved only when
 * nothing is wrong with any of it: a mapping half saved would index every part against settings nobody chose. The
 * screen, and anything else that edits the settings, calls this one service.
 *
 * @since 0.3.0
 */
final class SettingsEditor {

	/**
	 * The most lines a mapping holds. A shop maps a handful; the cap bounds what one request can store.
	 */
	public const MAX_LINES = 50;

	public const ATTRIBUTE_MISSING = 'mapping_attribute_missing';
	public const SPEC_MISSING      = 'mapping_spec_missing';
	public const ATTRIBUTE_LONG    = 'mapping_attribute_long';
	public const SPEC_UNKNOWN      = 'mapping_spec_unknown';
	public const UNIT_UNKNOWN      = 'mapping_unit_unknown';
	public const UNIT_NOT_MEASURE  = 'mapping_unit_not_measure';
	public const DUPLICATE         = 'mapping_duplicate';
	public const TOO_MANY          = 'mapping_too_many';
	public const ORDER_INVALID     = 'order_invalid';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ShopSettings $settings The shop's settings.
	 */
	public function __construct( private ShopSettings $settings ) {}

	/**
	 * Check what was sent, and save it when nothing is wrong. A line left wholly blank is no line.
	 *
	 * @since 0.3.0
	 *
	 * @param array<mixed> $lines The mapping as typed: each line an `attribute`, a `spec` and a `unit`.
	 * @param array<mixed> $order The sources' names, first wins.
	 */
	public function submit( array $lines, array $order ): SettingsDraft {
		$mapping  = array();
		$problems = array();
		$seen     = array();
		$number   = 0;
		foreach ( $lines as $line ) {
			++$number;
			$attribute = self::text( $line, 'attribute' );
			$spec      = self::text( $line, 'spec' );
			$unit      = self::text( $line, 'unit' );
			if ( '' === $attribute && '' === $spec ) {
				continue;
			}
			$problem = self::problem( $attribute, $spec, $unit );
			if ( null === $problem && isset( $seen[ $spec ][ $attribute ] ) ) {
				$problem = self::DUPLICATE;
			}
			$map = null === $problem ? AttributeMap::read( compact( 'attribute', 'spec', 'unit' ) ) : null;
			if ( null === $map ) {
				$problems[] = array(
					'code' => $problem ?? self::SPEC_UNKNOWN,
					'line' => $number,
				);
				continue;
			}
			$seen[ $spec ][ $attribute ] = true;
			$mapping[]                   = $map;
		}
		if ( count( $mapping ) > self::MAX_LINES ) {
			$problems[] = array(
				'code' => self::TOO_MANY,
				'line' => 0,
			);
		}
		$sources = SourceOrder::of( $order );
		if ( null === $sources ) {
			$problems[] = array(
				'code' => self::ORDER_INVALID,
				'line' => 0,
			);
		}
		$draft = new SettingsDraft( $mapping, $sources, $problems );
		if ( null !== $sources && $draft->saved() ) {
			$this->settings->save( $mapping, $sources );
		}
		return $draft;
	}

	/**
	 * What is wrong with a line that is not blank, or null.
	 *
	 * @param string $attribute The attribute's name.
	 * @param string $spec      The spec's key.
	 * @param string $unit      The unit, '' for the spec's own.
	 */
	private static function problem( string $attribute, string $spec, string $unit ): ?string {
		if ( '' === $attribute ) {
			return self::ATTRIBUTE_MISSING;
		}
		if ( '' === $spec ) {
			return self::SPEC_MISSING;
		}
		if ( strlen( $attribute ) > AttributeMap::MAX_NAME ) {
			return self::ATTRIBUTE_LONG;
		}
		$key = SpecKey::tryFrom( $spec );
		if ( null === $key ) {
			return self::SPEC_UNKNOWN;
		}
		if ( '' === $unit ) {
			return null;
		}
		if ( null === Unit::tryFrom( $unit ) ) {
			return self::UNIT_UNKNOWN;
		}
		return null === $key->unit() ? self::UNIT_NOT_MEASURE : null;
	}

	/**
	 * One field of a line, trimmed; '' when the line or the field is not text.
	 *
	 * @param mixed  $line  The line.
	 * @param string $field The field's name.
	 */
	private static function text( mixed $line, string $field ): string {
		$value = is_array( $line ) ? ( $line[ $field ] ?? null ) : null;
		return is_string( $value ) ? trim( $value ) : '';
	}
}
