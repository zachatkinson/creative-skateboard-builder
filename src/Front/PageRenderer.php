<?php
/**
 * The picture's layers, as the page stacks them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerArt;
use CreativeBranding\SkateboardBuilder\Port\PictureRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each layer with art, from the bottom of the stack, placed by its top left corner from the layout's, with the nose at
 * the top, in the layout's units: the view turns them into shares of the board, so the page draws at any size. Its
 * image is the master, with each type's renditions as a `srcset` where they are made (D-194, D-195).
 *
 * From 0.8.0 the board's top as well (#46), which the page alone draws: the same, from the layout's top. The deck's
 * image is the top's outline, so a deck with no art has no top.
 *
 * @since 0.7.0
 * @internal
 *
 * @phpstan-type Drawn array{slot: string, part: int, x: int, y: int, width: int, height: int, image: array{src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}}
 * @implements PictureRenderer<array{width: int, height: int, layers: list<Drawn>, top: list<Drawn>}>
 */
final class PageRenderer implements PictureRenderer {

	/**
	 * Draw the layout.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 The top.
	 *
	 * @param Layout               $layout The layout.
	 * @param array<int, LayerArt> $art    By part ID.
	 * @return array{width: int, height: int, layers: list<Drawn>, top: list<Drawn>}
	 */
	public function render( Layout $layout, array $art ): array {
		$top = self::drawn( $layout, $layout->top, $art );
		return array(
			'width'  => $layout->width,
			'height' => $layout->height,
			'layers' => self::drawn( $layout, $layout->layers, $art ),
			'top'    => in_array( SlotKey::DECK, array_column( $top, 'slot' ), true ) ? $top : array(),
		);
	}

	/**
	 * The layers with art, placed.
	 *
	 * @param Layout               $layout The layout.
	 * @param list<Layer>          $stack  Its layers, one side's.
	 * @param array<int, LayerArt> $art    By part ID.
	 * @return list<Drawn>
	 */
	private static function drawn( Layout $layout, array $stack, array $art ): array {
		$layers = array();
		foreach ( $stack as $layer ) {
			$image = $art[ $layer->part ] ?? null;
			if ( null === $image ) {
				continue;
			}
			$layers[] = array(
				'slot'   => $layer->slot,
				'part'   => $layer->part,
				'x'      => $layout->corner( $layer )[0],
				'y'      => $layout->corner( $layer )[1],
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
		return $layers;
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
