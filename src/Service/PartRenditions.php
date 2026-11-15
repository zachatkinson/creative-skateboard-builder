<?php
/**
 * Each part's layer image, made at the sizes the picture draws it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\LayerRenditions;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A part's renditions are made in its index job, in the background, once its specs are indexed: the specs give the
 * size the picture draws it at, and a save that gives it a layer image, or a new width, queues that job (D-194). The
 * sizes are those of every slot the picture draws that offers the part, in any kit. No page request makes one.
 *
 * @since 0.7.0
 */
final class PartRenditions {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param Kits            $kits       Which slots offer each part.
	 * @param SpecIndex       $index      The parts' specs.
	 * @param LayerImages     $layers     Each part's layer image.
	 * @param LayerRenditions $renditions Where the renditions are made.
	 */
	public function __construct(
		private Kits $kits,
		private SpecIndex $index,
		private LayerImages $layers,
		private LayerRenditions $renditions
	) {}

	/**
	 * Make what these parts' layer images lack.
	 *
	 * @since 0.7.0
	 *
	 * @param int ...$parts Product or variation IDs, as indexed.
	 */
	public function make( int ...$parts ): void {
		$kits  = array_map( static fn( array $kit ): Kit => $kit['kit'], array_values( $this->kits->all() ) );
		$specs = $this->index->specsOf( ...$parts );
		foreach ( $parts as $part ) {
			$image = $this->layers->attachment( $part );
			$facts = null === $image ? null : $this->layers->facts( $image );
			if ( null === $image || null === $facts || ! isset( $specs[ $part ] ) ) {
				continue;
			}
			$sizes = array();
			foreach ( array_intersect( Layout::DRAWN, $this->kits->slotsOf( $part, ...$kits ) ) as $slot ) {
				foreach ( Renditions::drawn( $slot, $specs[ $part ], $facts->width, $facts->height ) as $size ) {
					$sizes[ $size[0] . 'x' . $size[1] ] = $size;
				}
			}
			if ( array() !== $sizes ) {
				$this->renditions->make( $image, array_values( $sizes ) );
			}
		}
	}
}
