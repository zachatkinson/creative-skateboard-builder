<?php
/**
 * The shop-wide terms a build is sold on.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use function intdiv;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function rtrim;
use function str_pad;
use function substr;

/**
 * What a shop sets once for every kit (spec: Admin, Settings): the build discount, where a fixed-product coupon
 * lands, how often one address may ask the state route and which header names that address behind a proxy, and the
 * assembly settings a new kit starts from. Each reads from what the settings hold, and whatever they hold, the
 * answer is a valid setting: one that is missing or malformed is its default.
 *
 * @since 0.4.0
 */
final readonly class BuildTerms {

	/**
	 * The settings' keys.
	 *
	 * @since 0.4.0
	 */
	public const string DISCOUNT     = 'build_discount';
	public const string COUPONS      = 'fixed_coupons';
	public const string RATE         = 'rate_limit';
	public const string PROXY        = 'trusted_proxy_header';
	public const string KIT_DEFAULTS = 'kit_defaults';

	/**
	 * Where a fixed-product coupon lands: on the kit's line alone, or on every part as well.
	 *
	 * @since 0.4.0
	 */
	public const string ON_KIT   = 'kit';
	public const string ON_PARTS = 'parts';

	/**
	 * How many times a minute one address may ask the state route: the default, and the least and the most a shop
	 * may set.
	 *
	 * @since 0.4.0
	 */
	public const int RATE_DEFAULT = 120;
	public const int RATE_MIN     = 10;
	public const int RATE_MAX     = 6000;

	/**
	 * A percentage below 100 with up to two decimals, and a request header's name of up to 64 characters.
	 */
	private const string PERCENT = '/\A(?<whole>\d{1,2})(?:\.(?<places>\d{1,2}))?\z/D';
	private const string HEADER  = '/\A[A-Za-z][A-Za-z0-9-]{0,63}\z/D';

	/**
	 * Constructor.
	 *
	 * @param int                  $discount     The build discount in basis points: 500 is 5 %. 0 is none.
	 * @param string               $fixedCoupons `ON_KIT` or `ON_PARTS`.
	 * @param int                  $rateLimit    Requests a minute from one address.
	 * @param string               $proxyHeader  The header a trusted proxy names the visitor's address in, '' for none.
	 * @param array<string, mixed> $assembly     The assembly document a new kit starts from.
	 */
	private function __construct( public int $discount, public string $fixedCoupons, public int $rateLimit, public string $proxyHeader, public array $assembly ) {}

	/**
	 * From the settings. Anything that is not a setting reads as its default.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $stored The settings, by key.
	 */
	public static function read( mixed $stored ): self {
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = is_array( $stored[ self::KIT_DEFAULTS ] ?? null ) ? $stored[ self::KIT_DEFAULTS ] : array();
		return new self(
			self::discountFrom( $stored[ self::DISCOUNT ] ?? null ) ?? 0,
			self::couponsFrom( $stored[ self::COUPONS ] ?? null ) ?? self::ON_KIT,
			self::rateFrom( $stored[ self::RATE ] ?? null ) ?? self::RATE_DEFAULT,
			self::headerFrom( $stored[ self::PROXY ] ?? null ) ?? '',
			self::assemblyFrom( $defaults['assembly'] ?? null ) ?? self::noAssembly()
		);
	}

	/**
	 * As the settings hold them.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			self::DISCOUNT     => self::decimal( $this->discount, 100 ),
			self::COUPONS      => $this->fixedCoupons,
			self::RATE         => $this->rateLimit,
			self::PROXY        => $this->proxyHeader,
			self::KIT_DEFAULTS => array( 'assembly' => $this->assembly ),
		);
	}

	/**
	 * A discount in basis points from a percentage written with a point, `7.5`; null when it is not one below 100.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $value The setting.
	 */
	public static function discountFrom( mixed $value ): ?int {
		if ( ! is_string( $value ) || 1 !== preg_match( self::PERCENT, $value, $found ) ) {
			return null;
		}
		return (int) ( $found['whole'] . str_pad( $found['places'] ?? '', 2, '0' ) );
	}

	/**
	 * Where a fixed-product coupon lands, or null when it is neither place.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $value The setting.
	 */
	public static function couponsFrom( mixed $value ): ?string {
		return self::ON_KIT === $value || self::ON_PARTS === $value ? $value : null;
	}

	/**
	 * The rate limit, or null when it is not a whole number the shop may set.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $value The setting.
	 */
	public static function rateFrom( mixed $value ): ?int {
		return is_int( $value ) && $value >= self::RATE_MIN && $value <= self::RATE_MAX ? $value : null;
	}

	/**
	 * The proxy's header, '' for none, or null when it is not a header's name.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $value The setting.
	 */
	public static function headerFrom( mixed $value ): ?string {
		if ( '' === $value ) {
			return '';
		}
		return is_string( $value ) && 1 === preg_match( self::HEADER, $value ) ? $value : null;
	}

	/**
	 * The assembly document a new kit starts from, with the fields an assembly document has and no others; null
	 * when the domain does not read it.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $value The setting.
	 * @return array<string, mixed>|null
	 */
	public static function assemblyFrom( mixed $value ): ?array {
		try {
			$assembly = Assembly::fromDocument( $value );
		} catch ( InvalidDocument ) {
			return null;
		}
		return array(
			'schema_version' => 1,
			'default_on'     => $assembly->defaultOn,
			'fee'            => self::decimal( $assembly->fee( Assembly::MAX_DECIMALS ), 10000 ),
			'lead_time'      => $assembly->leadTime,
			'returns_notice' => $assembly->returnsNotice,
		);
	}

	/**
	 * No assembly by default, and no fee.
	 *
	 * @return array<string, mixed>
	 */
	private static function noAssembly(): array {
		return array(
			'schema_version' => 1,
			'default_on'     => false,
			'fee'            => '0',
			'lead_time'      => '',
			'returns_notice' => '',
		);
	}

	/**
	 * A whole number of hundredths or ten-thousandths as a decimal with a point and no zeros it does not need: 750
	 * hundredths is `7.5`.
	 *
	 * @param int $amount The number.
	 * @param int $unit   How many of them make one: 100 or 10000.
	 */
	private static function decimal( int $amount, int $unit ): string {
		$places = rtrim( substr( (string) ( $unit + $amount % $unit ), 1 ), '0' );
		return intdiv( $amount, $unit ) . ( '' === $places ? '' : '.' . $places );
	}
}
