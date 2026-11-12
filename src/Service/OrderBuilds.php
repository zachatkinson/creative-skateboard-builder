<?php
/**
 * A build on an order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Port\Kits;

/**
 * What an order keeps of a build (spec: Cart, checkout and orders, Orders): the snapshot on the kit's item, which is
 * kept for as long as the order is, and which of an order's items belong to which. The hooks hand over what
 * WooCommerce's objects say and write what this answers (D-138).
 *
 * @since 0.5.0
 */
final class OrderBuilds {

	/**
	 * The snapshot's own version, in its `schema_version`: `schema/order-snapshot.json`. It moves when the
	 * snapshot's shape does, and not when the cart's build does.
	 *
	 * @since 0.5.0
	 */
	public const int SNAPSHOT_VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Kits $kits The shop's kits and each slot's parts.
	 */
	public function __construct( private Kits $kits ) {}

	/**
	 * A build as it is sold, for the order to keep: each slot's label and part, the part's name, how many of it one
	 * build takes and its specs as the shop has them at that moment, and the owner's words on an assembled build
	 * as the shopper read them. A part the shop can no longer read is kept with no specs, and a slot nobody gave a
	 * label or a name is kept by its key and with no name.
	 *
	 * @since 0.5.0
	 *
	 * @param Build                 $build  The build.
	 * @param array<string, string> $labels Each slot's label, by its key.
	 * @param array<string, string> $names  Each slot's part's name, by the slot's key.
	 * @param string                $lead   The kit's lead time, as the shopper read it.
	 * @param string                $notice The kit's returns notice, as the shopper read it.
	 * @return array{schema_version: int, kit: int, assembly: bool, picks: array<string, array{label: string, name: string, part: int, quantity: int, specs: array<string, string>}>, lead_time: string, returns_notice: string}
	 */
	public function snapshot( Build $build, array $labels, array $names, string $lead, string $notice ): array {
		$slots = $this->kits->kit( $build->kit )?->slots;
		$asked = array();
		foreach ( $build->picks as $key => $part ) {
			$slot = $slots?->get( $key );
			if ( null !== $slot ) {
				$asked[ $key ] = array( $slot, $part );
			}
		}
		$read  = $this->kits->picked( $asked );
		$picks = array();
		foreach ( $build->picks as $key => $part ) {
			$picks[ $key ] = array(
				'label'    => $labels[ $key ] ?? $key,
				'name'     => $names[ $key ] ?? '',
				'part'     => $part,
				'quantity' => $build->quantities[ $key ],
				'specs'    => isset( $read[ $key ] ) ? $read[ $key ]->specs->toIndex() : array(),
			);
		}
		return array(
			'schema_version' => self::SNAPSHOT_VERSION,
			'kit'            => $build->kit,
			'assembly'       => $build->assembly,
			'picks'          => $picks,
			// Both are about assembly, and are kept for a build the shop assembles alone.
			'lead_time'      => $build->assembly ? $lead : '',
			'returns_notice' => $build->assembly ? $notice : '',
		);
	}

	/**
	 * How an order's builds are tied by their items' IDs: each part's kit's item, and each kit's parts' items in
	 * the order's own order. Only what is not already so is answered, so asking again of an order that is tied
	 * answers nothing.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int, array{build: string, kit: bool, parent: int, children: mixed}> $items By item ID, the items
	 *                                      of a build: its identifier, whether it is the kit's, and the tie it has.
	 * @return array{parents: array<int, int>, children: array<int, list<int>>} A part's item to its kit's item; a
	 *                                      kit's item to its parts' items.
	 */
	public static function ties( array $items ): array {
		$kits  = array();
		$parts = array();
		foreach ( $items as $id => $item ) {
			if ( $item['kit'] ) {
				$kits[ $item['build'] ] = $id;
			} else {
				$parts[ $item['build'] ][] = $id;
			}
		}
		$parents  = array();
		$children = array();
		foreach ( $kits as $build => $kit ) {
			$own = $parts[ $build ] ?? array();
			foreach ( $own as $part ) {
				if ( $items[ $part ]['parent'] !== $kit ) {
					$parents[ $part ] = $kit;
				}
			}
			if ( $items[ $kit ]['children'] !== $own ) {
				$children[ $kit ] = $own;
			}
		}
		return array(
			'parents'  => $parents,
			'children' => $children,
		);
	}
}
