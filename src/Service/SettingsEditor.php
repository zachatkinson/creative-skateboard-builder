<?php
/**
 * Checks and saves the shop's settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Assembly;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a person sends from WooCommerce > Settings > Builder is checked here, line by line and field by field, and
 * saved only when nothing is wrong with any of it: a mapping half saved would index every part against settings nobody chose. The
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
	public const DISCOUNT_INVALID  = 'discount_invalid';
	public const COUPONS_INVALID   = 'coupons_invalid';
	public const RATE_INVALID      = 'rate_invalid';
	public const HEADER_INVALID    = 'header_invalid';
	public const FEE_INVALID       = 'fee_invalid';
	public const LEAD_TIME_LONG    = 'lead_time_long';
	public const RETURNS_LONG      = 'returns_notice_long';

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
	 * @since 0.4.0 Takes the terms.
	 *
	 * @param array<mixed>      $lines     The mapping as typed: each line an `attribute`, a `spec` and a `unit`.
	 * @param array<mixed>      $order     The sources' names, first wins.
	 * @param array<mixed>|null $terms     The terms as typed: `discount`, `coupons`, `rate`, `header`, and a new kit's
	 *                                     `default_on`, `fee`, `lead_time` and `returns_notice`. Null leaves the
	 *                                     terms as they are.
	 * @param string            $separator The shop's decimal separator, which the discount and the fee may be typed
	 *                                     with.
	 */
	public function submit( array $lines, array $order, ?array $terms = null, string $separator = '.' ): SettingsDraft {
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
		$built = null;
		if ( null !== $terms ) {
			$stored = self::terms( $terms, $separator );
			$wrong  = self::wrong( $stored );
			$built  = array() === $wrong ? BuildTerms::read( $stored ) : null;
			foreach ( $wrong as $code ) {
				$problems[] = array(
					'code' => $code,
					'line' => 0,
				);
			}
		}
		$draft = new SettingsDraft( $mapping, $sources, $problems, $built );
		if ( null !== $sources && $draft->saved() ) {
			$this->settings->save( $mapping, $sources, $built );
		}
		return $draft;
	}

	/**
	 * The terms as the settings write them, from what was typed. A discount or a fee left empty is none, and a rate
	 * that is no whole number is no rate.
	 *
	 * @param array<mixed> $sent      The fields.
	 * @param string       $separator The shop's decimal separator.
	 * @return array{build_discount: string, fixed_coupons: string, rate_limit: int|null, trusted_proxy_header: string, kit_defaults: array{assembly: array<string, mixed>}}
	 */
	private static function terms( array $sent, string $separator ): array {
		$rate = self::text( $sent, 'rate' );
		return array(
			BuildTerms::DISCOUNT     => self::decimal( self::text( $sent, 'discount' ), $separator ),
			BuildTerms::COUPONS      => self::text( $sent, 'coupons' ),
			BuildTerms::RATE         => 1 === preg_match( '/\A\d{1,4}\z/', $rate ) ? (int) $rate : null,
			BuildTerms::PROXY        => self::text( $sent, 'header' ),
			BuildTerms::KIT_DEFAULTS => array(
				'assembly' => array(
					'schema_version' => 1,
					'default_on'     => '' !== self::text( $sent, 'default_on' ),
					'fee'            => self::decimal( self::text( $sent, 'fee' ), $separator ),
					'lead_time'      => self::text( $sent, 'lead_time' ),
					'returns_notice' => self::text( $sent, 'returns_notice' ),
				),
			),
		);
	}

	/**
	 * What is wrong with the terms, a code for each field the domain does not take.
	 *
	 * @param array{build_discount: string, fixed_coupons: string, rate_limit: int|null, trusted_proxy_header: string, kit_defaults: array{assembly: array<string, mixed>}} $stored The terms as the settings would write them.
	 * @return list<string>
	 */
	private static function wrong( array $stored ): array {
		$wrong = array();
		if ( null === BuildTerms::discountFrom( $stored[ BuildTerms::DISCOUNT ] ) ) {
			$wrong[] = self::DISCOUNT_INVALID;
		}
		if ( null === BuildTerms::couponsFrom( $stored[ BuildTerms::COUPONS ] ) ) {
			$wrong[] = self::COUPONS_INVALID;
		}
		if ( null === BuildTerms::rateFrom( $stored[ BuildTerms::RATE ] ) ) {
			$wrong[] = self::RATE_INVALID;
		}
		if ( null === BuildTerms::headerFrom( $stored[ BuildTerms::PROXY ] ) ) {
			$wrong[] = self::HEADER_INVALID;
		}
		try {
			Assembly::fromDocument( $stored[ BuildTerms::KIT_DEFAULTS ]['assembly'] );
		} catch ( InvalidDocument $refused ) {
			$wrong[] = match ( $refused->path ) {
				'lead_time'      => self::LEAD_TIME_LONG,
				'returns_notice' => self::RETURNS_LONG,
				default          => self::FEE_INVALID,
			};
		}
		return $wrong;
	}

	/**
	 * A decimal as the settings write one, with a point. Nothing typed is none.
	 *
	 * @param string $text      What was typed.
	 * @param string $separator The shop's decimal separator.
	 */
	private static function decimal( string $text, string $separator ): string {
		if ( '' === $text ) {
			return '0';
		}
		return '' === $separator ? $text : str_replace( $separator, '.', $text );
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
