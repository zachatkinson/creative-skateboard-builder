<?php
/**
 * A kit's slots, in step order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use function array_keys;
use function array_search;
use function array_slice;
use function array_values;

/**
 * The slots in the order the builder steps through them (spec: Data model, `_cbuild_slots`). Keys are unique, and the
 * deck comes first: every later step is checked against it, and it is never picked for the shopper.
 */
final readonly class Slots {

	/**
	 * The most slots a kit has.
	 */
	public const int MAX = 20;

	/**
	 * The words no slot is keyed with: a build link carries its step, assembly and its preset beside its picks,
	 * under these names, and its step may be the review. By word, so that asking is one lookup.
	 *
	 * @since 0.6.0
	 */
	public const array KEPT = array(
		'step'     => true,
		'assembly' => true,
		'preset'   => true,
		'review'   => true,
	);

	/**
	 * Constructor.
	 *
	 * @param array<string, Slot> $slots The slots by key, in order.
	 */
	private function __construct( private array $slots ) {}

	/**
	 * From the slots document.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $document The decoded `_cbuild_slots` document.
	 *
	 * @throws InvalidDocument When the document, a slot or the set of them is not valid.
	 */
	public static function fromDocument( mixed $document ): self {
		$root  = Fields::document( $document );
		$slots = array();
		foreach ( $root->rowsFilled( 'slots', self::MAX ) as $at => $row ) {
			$slot = Slot::fromFields( $row );
			if ( isset( $slots[ $slot->key ] ) ) {
				$refused = InvalidDocument::because( DocumentError::Duplicate, $row->path( 'key' ) );
				throw $refused;
			}
			if ( 0 === $at && SlotKey::DECK !== $slot->key ) {
				$refused = InvalidDocument::because( DocumentError::DeckNotFirst, $row->path( 'key' ) );
				throw $refused;
			}
			if ( SlotKey::RISERS === $slot->key && isset( $slots[ SlotKey::HARDWARE ] ) ) {
				// The bolts are picked to reach through the riser (R4). With the bolts first, wheels that ask for a
				// riser would find every riser hidden by bolts already chosen (docs/UX-RESEARCH.md, W2).
				$refused = InvalidDocument::because( DocumentError::RisersAfterHardware, $row->path( 'key' ) );
				throw $refused;
			}
			$slots[ $slot->key ] = $slot;
		}
		return new self( $slots );
	}

	/**
	 * Every slot, in step order.
	 *
	 * @since 0.2.0
	 *
	 * @return list<Slot>
	 */
	public function all(): array {
		return array_values( $this->slots );
	}

	/**
	 * Every slot's key, in step order.
	 *
	 * @since 0.2.0
	 *
	 * @return list<string>
	 */
	public function keys(): array {
		return array_keys( $this->slots );
	}

	/**
	 * One slot, or null when the kit has none with this key.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The slot's key.
	 */
	public function get( string $key ): ?Slot {
		return $this->slots[ $key ] ?? null;
	}

	/**
	 * The keys of the slots that come before one, in step order; none when the kit has no such slot.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The slot's key.
	 *
	 * @return list<string>
	 */
	public function before( string $key ): array {
		$keys = $this->keys();
		return array_slice( $keys, 0, (int) array_search( $key, $keys, true ) );
	}

	/**
	 * The keys of the slots that come after one, in step order; none when the kit has no such slot.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The slot's key.
	 *
	 * @return list<string>
	 */
	public function after( string $key ): array {
		$keys = $this->keys();
		$at   = array_search( $key, $keys, true );
		return false === $at ? array() : array_slice( $keys, $at + 1 );
	}
}
