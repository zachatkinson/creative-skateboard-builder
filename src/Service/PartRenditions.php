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
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
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
	 * An image was deleted from the Media Library: remove its renditions, whichever parts it was the layer of.
	 *
	 * @since 0.7.0
	 *
	 * @param int $attachment The image's attachment ID.
	 */
	public function forget( int $attachment ): void {
		$this->renditions->forget( $attachment );
	}

	/**
	 * Make what these parts' layer images lack.
	 *
	 * @since 0.7.0
	 *
	 * @param int ...$parts Product or variation IDs, as indexed.
	 */
	public function make( int ...$parts ): void {
		$kits  = $this->kitsAll();
		$specs = $this->index->specsOf( ...$parts );
		foreach ( $parts as $part ) {
			$drawn = $this->drawn( $part, $kits, $specs );
			if ( null !== $drawn ) {
				$this->renditions->make( ...$drawn );
			}
		}
	}

	/**
	 * How many parts a kit offers in a slot the picture draws have a layer image and not one rendition of it: the
	 * host's editors write neither AVIF nor WebP, the uploads folder cannot be written, or the index jobs have not run.
	 * The picture then draws the part from its full-size image, and Site Health says so (Cowork's M7 review, F2; D-209).
	 * Site Health runs it as a direct test, so it reads each image's stored pixels and opens no file.
	 *
	 * @since 0.7.0
	 */
	public function unrendered(): int {
		$parts = array();
		foreach ( $this->kits->all() as $id => $entry ) {
			foreach ( $entry['kit']->slots->all() as $slot ) {
				if ( in_array( $slot->key, Layout::DRAWN, true ) ) {
					foreach ( $this->kits->candidates( $id, $slot->key ) as $part ) {
						$parts[ $part->id ] = $part->id;
					}
				}
			}
		}
		$parts = $this->layers->layered( ...$parts );
		if ( array() === $parts ) {
			return 0;
		}
		$kits    = $this->kitsAll();
		$specs   = $this->index->specsOf( ...$parts );
		$missing = 0;
		foreach ( $parts as $part ) {
			$drawn = $this->drawn( $part, $kits, $specs, true );
			if ( null !== $drawn && array() === $this->renditions->sources( ...$drawn ) ) {
				++$missing;
			}
		}
		return $missing;
	}

	/**
	 * Every kit.
	 *
	 * @return array<int, Kit>
	 */
	private function kitsAll(): array {
		return array_map( static fn( array $kit ): Kit => $kit['kit'], $this->kits->all() );
	}

	/**
	 * A part's layer image and the sizes the picture draws it at: those of every slot that offers it, in any kit. A
	 * slot the picture does not draw gives no size, and two kits' decks give the same sizes, listed once. Null where
	 * there is nothing to make: no layer image, no specs, or no slot that draws it.
	 *
	 * @param int               $part   The part's ID.
	 * @param array<int, Kit>   $kits   Every kit.
	 * @param array<int, Specs> $specs  The parts' specs.
	 * @param bool              $stored Whether to take the image's pixels from what the Media Library stored, opening
	 *                                  no file, where making the renditions reads the file itself.
	 * @return array{0: int, 1: non-empty-list<array{int, int}>}|null
	 */
	private function drawn( int $part, array $kits, array $specs, bool $stored = false ): ?array {
		$image = $this->layers->attachment( $part );
		$facts = null === $image ? null : ( $stored ? $this->layers->image( $image ) : $this->layers->facts( $image ) );
		if ( null === $image || null === $facts || ! isset( $specs[ $part ] ) ) {
			return null;
		}
		[ $width, $height ] = is_array( $facts ) ? array( $facts['width'], $facts['height'] ) : array( $facts->width, $facts->height );
		$sizes              = array();
		foreach ( $this->kits->slotsOf( $part, ...$kits ) as $slot ) {
			array_push( $sizes, ...Renditions::drawn( $slot, $specs[ $part ], $width, $height ) );
		}
		$sizes = array_values( array_unique( $sizes, SORT_REGULAR ) );
		return array() === $sizes ? null : array( $image, $sizes );
	}
}
