<?php
/**
 * A build in the cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a kit's cart line carries under the key `cbuild` (spec: Data model, Cart items): the build's identifier,
 * the kit, the pick and the quantity for each slot filled, and whether the shop assembles it. It is stored as a
 * plain array, so a session written by one version is read, or passed over, by another, and nothing in a session
 * needs this class to be loaded.
 *
 * @since 0.5.0
 */
final readonly class Build {

	/**
	 * The version of the stored shape.
	 */
	public const int VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param string             $id         The build's UUID.
	 * @param int                $kit        The kit product's ID.
	 * @param array<string, int> $picks      The part's ID for each slot filled, in step order.
	 * @param array<string, int> $quantities How many of that part one build takes, by the same slots.
	 * @param bool               $assembly   Whether the shop assembles it.
	 */
	public function __construct( public string $id, public int $kit, public array $picks, public array $quantities, public bool $assembly ) {}

	/**
	 * The build as the cart stores it.
	 *
	 * @since 0.5.0
	 *
	 * @return array{schema_version: int, id: string, kit: int, picks: array<string, int>, quantities: array<string, int>, assembly: bool}
	 */
	public function toArray(): array {
		return array(
			'schema_version' => self::VERSION,
			'id'             => $this->id,
			'kit'            => $this->kit,
			'picks'          => $this->picks,
			'quantities'     => $this->quantities,
			'assembly'       => $this->assembly,
		);
	}

	/**
	 * The build a cart line stored, or null when what is there is not one this version reads: another version's,
	 * or anything else's.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $stored The key `cbuild` as the session gave it.
	 */
	public static function read( mixed $stored ): ?self {
		if ( ! is_array( $stored ) || self::VERSION !== ( $stored['schema_version'] ?? null ) ) {
			return null;
		}
		$id         = $stored['id'] ?? null;
		$kit        = $stored['kit'] ?? null;
		$assembly   = $stored['assembly'] ?? null;
		$picks      = self::counts( $stored['picks'] ?? null );
		$quantities = self::counts( $stored['quantities'] ?? null );
		if ( ! is_string( $id ) || '' === $id || ! is_int( $kit ) || $kit < 1 || ! is_bool( $assembly ) || null === $picks || null === $quantities ) {
			return null;
		}
		if ( array() === $picks || array_keys( $picks ) !== array_keys( $quantities ) ) {
			return null;
		}
		return new self( $id, $kit, $picks, $quantities, $assembly );
	}

	/**
	 * A map of slot keys to whole numbers of one or more, or null when it is anything else.
	 *
	 * @param mixed $stored What was stored.
	 * @return array<string, int>|null
	 */
	private static function counts( mixed $stored ): ?array {
		if ( ! is_array( $stored ) ) {
			return null;
		}
		$counts = array();
		foreach ( $stored as $slot => $count ) {
			if ( ! is_string( $slot ) || ! is_int( $count ) || $count < 1 ) {
				return null;
			}
			$counts[ $slot ] = $count;
		}
		return $counts;
	}
}
