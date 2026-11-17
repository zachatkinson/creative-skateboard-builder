<?php
/**
 * An order's builds, read from its lines, and where the lines no longer match them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one reading of an order's builds the workshop's screens ask (spec: Admin, On orders): the Build box, the
 * assembly ticket and the orders list's Assembly column. It is worked out from the lines as they are each time it is
 * asked, so a change made by any route is caught, the order screen's own, another plugin's or a script's, and nothing
 * is written when an order's items are saved.
 *
 * A build is its lines with one `_cbuild_id` (D-131). Its slots are the snapshot's picks, as sold, each matched to
 * the line that holds it now by `_cbuild_slot`; the order item IDs in `_cbuild_parent` and `_cbuild_children` are not
 * read, since a copy of the lines carries them over stale. Where the snapshot does not read, the build is read from its
 * lines and says so.
 *
 * A line without the build's keys counts against a build only where the build's slot has lost its own line and the
 * loose line is of that slot's product: it is then the line copied without its keys. Otherwise it is another purchase
 * of the same part, and nothing is wrong.
 *
 * @since 0.8.0
 *
 * @phpstan-type Sold array{kit: int, assembly: bool, picks: array<string, array{label: string, name: string, sku: string, part: int, quantity: int, specs: array<string, string>}>}
 */
final readonly class OrderReading {

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param list<OrderedBuild> $builds     The order's builds, in the order of their kits' lines.
	 * @param list<Mismatch>     $mismatches Where the lines no longer match: those of no build first.
	 */
	private function __construct( public array $builds, public array $mismatches ) {}

	/**
	 * Read an order's builds from its lines.
	 *
	 * @since 0.8.0
	 *
	 * @param list<OrderLine> $lines The order's product lines, in the order's order.
	 */
	public static function of( array $lines ): self {
		$kits       = array();
		$parts      = array();
		$loose      = array();
		$mismatches = array();
		foreach ( $lines as $line ) {
			if ( '' === $line->build ) {
				if ( $line->kit ) {
					$mismatches[] = new Mismatch( Mismatch::KIT_WITHOUT_BUILD, '', '', $line->id, 0, 0, $line->name );
				} else {
					$loose[ $line->id ] = $line;
				}
			} elseif ( 'kit' !== $line->role ) {
				$parts[ $line->build ][] = $line;
			} elseif ( isset( $kits[ $line->build ] ) ) {
				$mismatches[] = new Mismatch( Mismatch::PART_TWICE, $line->build, '', $line->id, 0, 0, $line->name );
			} else {
				$kits[ $line->build ] = $line;
			}
		}
		$builds = array();
		foreach ( array_keys( $kits + $parts ) as $id ) {
			$id       = (string) $id;
			$kit      = $kits[ $id ] ?? null;
			$own      = $parts[ $id ] ?? array();
			$snapshot = null === $kit ? null : self::snapshot( $kit->snapshot );
			if ( null === $kit ) {
				$mismatches[] = new Mismatch( Mismatch::KIT_REMOVED, $id );
			} elseif ( null === $snapshot ) {
				$mismatches[] = new Mismatch( Mismatch::SNAPSHOT_UNREAD, $id, '', $kit->id, 0, 0, $kit->name );
			}
			if ( null === $kit || null === $snapshot ) {
				$builds[] = self::fromLines( $id, null === $kit ? 0 : $kit->id, $own );
				continue;
			}
			$builds[] = self::fromSnapshot( $id, $kit, $snapshot, $own, $loose, $mismatches );
		}
		return new self( $builds, $mismatches );
	}

	/**
	 * Whether the order has anything the workshop reads: a build, or a line that should have been one.
	 *
	 * @since 0.8.0
	 */
	public function has(): bool {
		return array() !== $this->builds || array() !== $this->mismatches;
	}

	/**
	 * The mismatches of one build, or with an empty identifier those of no build.
	 *
	 * @since 0.8.0
	 *
	 * @param string $build The build's `_cbuild_id`.
	 * @return list<Mismatch>
	 */
	public function about( string $build ): array {
		return array_values( array_filter( $this->mismatches, static fn( Mismatch $mismatch ): bool => $mismatch->build === $build ) );
	}

	/**
	 * How many boards on the order the shop assembles.
	 *
	 * @since 0.8.0
	 */
	public function toAssemble(): int {
		$boards = 0;
		foreach ( $this->builds as $build ) {
			$boards += $build->assembly ? $build->boards : 0;
		}
		return $boards;
	}

	/**
	 * A build read from its snapshot, each slot matched to its line.
	 *
	 * @param string                $id         The build's identifier.
	 * @param OrderLine             $kit        The kit's line.
	 * @param Sold                  $snapshot   The snapshot, read.
	 * @param list<OrderLine>       $own        The build's part lines.
	 * @param array<int, OrderLine> $loose      The order's lines of no build, by item; one copied without its keys is taken out.
	 * @param list<Mismatch>        $mismatches Where the lines no longer match, added to.
	 */
	private static function fromSnapshot( string $id, OrderLine $kit, array $snapshot, array $own, array &$loose, array &$mismatches ): OrderedBuild {
		$boards = $kit->quantity;
		$slots  = array();
		foreach ( $own as $line ) {
			$slots[ $line->slot ][] = $line;
		}
		$picks = array();
		foreach ( $snapshot['picks'] as $slot => $sold ) {
			$held = $slots[ $slot ] ?? array();
			$line = $held[0] ?? self::copied( $sold['part'], $loose );
			if ( null === $line ) {
				$mismatches[] = new Mismatch( Mismatch::PART_REMOVED, $id, $slot );
			} elseif ( array() === $held ) {
				unset( $loose[ $line->id ] );
				$mismatches[] = new Mismatch( Mismatch::COPIED_WITHOUT_KEYS, $id, $slot, $line->id, 0, 0, $line->name );
			} else {
				foreach ( array_slice( $held, 1 ) as $twice ) {
					$mismatches[] = new Mismatch( Mismatch::PART_TWICE, $id, $slot, $twice->id, 0, 0, $twice->name );
				}
				if ( $line->product !== $sold['part'] ) {
					$mismatches[] = new Mismatch( Mismatch::PART_CHANGED, $id, $slot, $line->id, $sold['part'], $line->product, $line->name );
				}
				if ( $line->quantity !== $sold['quantity'] * $boards ) {
					$mismatches[] = new Mismatch( Mismatch::QUANTITY_CHANGED, $id, $slot, $line->id, $sold['quantity'] * $boards, $line->quantity, $line->name );
				}
			}
			$picks[ $slot ] = new OrderedPick( $slot, $sold['label'], $sold['name'], $sold['sku'], $sold['part'], $sold['quantity'], $sold['specs'], $line->id ?? 0 );
		}
		foreach ( array_diff_key( $slots, $snapshot['picks'] ) as $slot => $held ) {
			$mismatches[] = new Mismatch( Mismatch::PART_NOT_IN_BUILD, $id, (string) $slot, $held[0]->id, 0, 0, $held[0]->name );
		}
		return new OrderedBuild( $id, $kit->id, $snapshot['kit'], $boards, $snapshot['assembly'], $picks, true );
	}

	/**
	 * A build read from its lines alone: each line's slot, product and quantity, with no words or specs, as one board
	 * the shop is not known to assemble.
	 *
	 * @param string          $id   The build's identifier.
	 * @param int             $item The kit's order item, 0 where there is none.
	 * @param list<OrderLine> $own  The build's part lines.
	 */
	private static function fromLines( string $id, int $item, array $own ): OrderedBuild {
		$picks = array();
		foreach ( $own as $line ) {
			$picks[ $line->slot ] ??= new OrderedPick( $line->slot, $line->slot, $line->name, '', $line->product, $line->quantity, array(), $line->id );
		}
		return new OrderedBuild( $id, $item, 0, 1, false, $picks, false );
	}

	/**
	 * The first line of no build that is of this part.
	 *
	 * @param int                   $part  The part sold.
	 * @param array<int, OrderLine> $loose The order's lines of no build.
	 */
	private static function copied( int $part, array $loose ): ?OrderLine {
		foreach ( $loose as $line ) {
			if ( $line->product === $part ) {
				return $line;
			}
		}
		return null;
	}

	/**
	 * A snapshot as the workshop needs it, or null where it does not read: it needs at least one pick, each under a
	 * slot's key with its part and how many one board takes. Words, a SKU, specs, the kit and assembly it may lack: a
	 * slot with no label is called by its key, and a value that is not text is left out of the specs.
	 *
	 * @param mixed $stored The snapshot as stored.
	 * @return Sold|null
	 */
	private static function snapshot( mixed $stored ): ?array {
		$picks = is_array( $stored ) ? ( $stored['picks'] ?? null ) : null;
		if ( ! is_array( $picks ) || array() === $picks ) {
			return null;
		}
		$read = array();
		foreach ( $picks as $slot => $pick ) {
			$part     = is_array( $pick ) ? ( $pick['part'] ?? null ) : null;
			$quantity = is_array( $pick ) ? ( $pick['quantity'] ?? null ) : null;
			if ( ! is_string( $slot ) || ! is_int( $part ) || $part < 1 || ! is_int( $quantity ) || $quantity < 1 ) {
				return null;
			}
			$specs = array();
			foreach ( is_array( $pick['specs'] ?? null ) ? $pick['specs'] : array() as $key => $value ) {
				if ( is_string( $key ) && is_string( $value ) ) {
					$specs[ $key ] = $value;
				}
			}
			$read[ $slot ] = array(
				'label'    => is_string( $pick['label'] ?? null ) && '' !== $pick['label'] ? $pick['label'] : $slot,
				'name'     => is_string( $pick['name'] ?? null ) ? $pick['name'] : '',
				'sku'      => is_string( $pick['sku'] ?? null ) ? $pick['sku'] : '',
				'part'     => $part,
				'quantity' => $quantity,
				'specs'    => $specs,
			);
		}
		return array(
			'kit'      => is_array( $stored ) && is_int( $stored['kit'] ?? null ) ? $stored['kit'] : 0,
			'assembly' => is_array( $stored ) && true === ( $stored['assembly'] ?? null ),
			'picks'    => $read,
		);
	}
}
