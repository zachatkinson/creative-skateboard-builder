<?php
/**
 * The art each drawn part has.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\LayerRenditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * For each part a layout draws, its layer image and the renditions its index job made of it at the size its slot draws
 * it (D-194, D-195). Nothing is made here: a part whose renditions are not made yet is drawn from its master. A part
 * with no layer image, or one no longer in the Media Library, has no art, and the picture shows its labelled photo.
 *
 * @since 0.7.0
 */
final class LayerArtwork {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param LayerImages     $layers     The parts' layer images.
	 * @param LayerRenditions $renditions What is made of them.
	 */
	public function __construct( private LayerImages $layers, private LayerRenditions $renditions ) {}

	/**
	 * The art of the parts a layout draws.
	 *
	 * @since 0.7.0
	 *
	 * @param Layout              $layout The layout.
	 * @param array<string, Part> $parts  The parts it was laid out from, by slot.
	 * @return array<int, LayerArt> By part ID, in the order the layout first draws each.
	 */
	public function of( Layout $layout, array $parts ): array {
		$art = array();
		foreach ( $layout->layers as $layer ) {
			$part = $parts[ $layer->slot ] ?? null;
			if ( array_key_exists( $layer->part, $art ) || null === $part || $part->id !== $layer->part ) {
				continue;
			}
			$attachment = $this->layers->attachment( $part->id );
			$image      = null === $attachment ? null : $this->layers->image( $attachment );
			if ( null === $attachment || null === $image ) {
				$art[ $layer->part ] = null;
				continue;
			}
			$art[ $layer->part ] = new LayerArt(
				$attachment,
				$image['url'],
				$image['width'],
				$image['height'],
				$this->renditions->sources( $attachment, Renditions::drawn( $layer->slot, $part->specs, $image['width'], $image['height'] ) )
			);
		}
		return array_filter( $art );
	}
}
