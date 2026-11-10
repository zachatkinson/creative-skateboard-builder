<?php
/**
 * Builds the specs index from the shop's sources.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Resolution;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Port\SpecSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asks every source what it says of a part, keeps the winner of each spec by the shop's order, and writes that to
 * the index (spec: Data model, the specs index). Indexing a part twice leaves the index as indexing it once: a part
 * no source says anything of, a deleted product among them, ends with no row.
 *
 * @since 0.3.0
 */
final class SpecIndexer {

	/**
	 * The sources.
	 *
	 * @var array<SpecSource>
	 */
	private array $sources;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecIndex    $index      The index.
	 * @param ShopSettings $settings   Which source wins.
	 * @param Clock        $clock      When the parts were read.
	 * @param SpecSource   ...$sources The sources, each asked once per batch.
	 */
	public function __construct( private SpecIndex $index, private ShopSettings $settings, private Clock $clock, SpecSource ...$sources ) {
		$this->sources = $sources;
	}

	/**
	 * What the sources come to for each of these parts, without writing anything: what the index would hold, the
	 * disagreements, and what could not be read.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds Products' and variations' IDs.
	 * @return array<int, Sourced> By part, every part asked for.
	 */
	public function resolve( int ...$partIds ): array {
		$readings = array_fill_keys( $partIds, array() );
		$unread   = $readings;
		foreach ( $this->sources as $source ) {
			foreach ( $source->read( ...$partIds ) as $partId => $found ) {
				if ( ! isset( $readings[ $partId ] ) ) {
					// A source may only answer for what it was asked.
					continue;
				}
				foreach ( $found as $item ) {
					if ( $item instanceof Reading ) {
						$readings[ $partId ][] = $item;
					} else {
						$unread[ $partId ][] = $item;
					}
				}
			}
		}
		$order   = $this->settings->sourceOrder();
		$sourced = array();
		foreach ( $readings as $partId => $ofPart ) {
			$sourced[ $partId ] = new Sourced( Resolution::of( $order, ...$ofPart ), $unread[ $partId ] );
		}
		return $sourced;
	}

	/**
	 * Read these parts and write what they come to into the index.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds Products' and variations' IDs.
	 * @return array<int, Sourced> What each came to.
	 */
	public function index( int ...$partIds ): array {
		$sourced = $this->resolve( ...$partIds );
		$now     = $this->clock->now();
		foreach ( $sourced as $partId => $part ) {
			$this->index->replace( $partId, $part->resolution->rows(), $now );
		}
		return $sourced;
	}
}
