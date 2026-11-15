<?php
/**
 * The picture's layers, as the page stacks them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Service\LayerArt;
use CreativeBranding\SkateboardBuilder\Service\PictureRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each layer with art, from the bottom of the stack, placed by its top left corner from the layout's, with the nose at
 * the top, in the layout's units: the view turns them into shares of the board, so the page draws at any size. Its
 * image is the master, with each type's renditions as a `srcset` where they are made (D-194, D-195).
 *
 * @since 0.7.0
 * @internal
 *
 * @implements PictureRenderer<array{width: int, height: int, layers: list<array{slot: string, part: int, x: int, y: int, width: int, height: int, image: array{src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}}>}>
 */
final class PageRenderer implements PictureRenderer {

	/**
	 * Draw the layout.
	 *
	 * @since 0.7.0
	 *
	 * @param Layout               $layout The layout.
	 * @param array<int, LayerArt> $art    By part ID.
	 * @return array{width: int, height: int, layers: list<array{slot: string, part: int, x: int, y: int, width: int, height: int, image: array{src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}}>}
	 */
	public function render( Layout $layout, array $art ): array {
		$layers = array();
		foreach ( $layout->layers as $layer ) {
			$image = $art[ $layer->part ] ?? null;
			if ( null === $image ) {
				continue;
			}
			$layers[] = array(
				'slot'   => $layer->slot,
				'part'   => $layer->part,
				'x'      => intdiv( $layout->width - $layer->width, 2 ) + $layer->x,
				'y'      => intdiv( $layout->height - $layer->height, 2 ) - $layer->y,
				'width'  => $layer->width,
				'height' => $layer->height,
				'image'  => array(
					'src'     => $image->url,
					'width'   => $image->width,
					'height'  => $image->height,
					'sources' => self::sources( $image ),
				),
			);
		}
		return array(
			'width'  => $layout->width,
			'height' => $layout->height,
			'layers' => $layers,
		);
	}

	/**
	 * A `<source>` for each type with renditions made.
	 *
	 * @param LayerArt $image The art.
	 * @return list<array{type: string, srcset: string}>
	 */
	private static function sources( LayerArt $image ): array {
		$sources = array();
		foreach ( $image->sources as $type => $files ) {
			$sources[] = array(
				'type'   => $type,
				'srcset' => implode( ', ', array_map( static fn( array $file ): string => $file['url'] . ' ' . $file['width'] . 'w', $files ) ),
			);
		}
		return $sources;
	}
}
