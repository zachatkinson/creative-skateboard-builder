<?php
/**
 * The 3D view's scene, for the builder's view.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerArt;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Scene\Scene;
use CreativeBranding\SkateboardBuilder\Port\ComposedLayers;
use CreativeBranding\SkateboardBuilder\Port\WheelFaces;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Service\State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The board in three dimensions for the 3D view (1.4.0, #139, D-470): the domain's scene of the build's picks, with each
 * drawn part's texture, through `cbuild_scene`. Every size is the domain's; the edge adds the textures alone. A texture
 * is the plugin's own rendition of the part's layer image, asked of `cbuild_composed_layer` with the use `3d`: a part
 * answered other than `as_is` has none, so the view draws its colour, and no sharp texture shows what the answer hid
 * (D-475).
 *
 * @since 1.4.0
 */
final class SceneView {

	/**
	 * The 3D view's use, for `cbuild_composed_layer`.
	 *
	 * @since 1.4.0
	 */
	public const string USE = '3d';

	/**
	 * The rendition's type a texture is: WebP, which every browser with WebGL 2 decodes into a texture.
	 */
	private const string TYPE = 'image/webp';

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 *
	 * @param LayerArtwork   $artwork The drawn parts' layer images.
	 * @param ComposedLayers $layers  Another plugin's answer for each part's layer, by use.
	 */
	public function __construct( private LayerArtwork $artwork, private ComposedLayers $layers ) {}

	/**
	 * The wheels' faces, from their photos (M15). Without them the wheels have none.
	 *
	 * @var WheelFaces|null
	 */
	private ?WheelFaces $faces = null;

	/**
	 * This view, with the wheels' faces read from their photos.
	 *
	 * @since 1.5.0
	 *
	 * @param WheelFaces $faces The wheels' faces.
	 */
	public function withFaces( WheelFaces $faces ): self {
		$view        = clone $this;
		$view->faces = $faces;
		return $view;
	}

	/**
	 * The scene of a build, or null where the kit's 3D view is off or no deck with a width is picked.
	 *
	 * @since 1.4.0
	 *
	 * @param State $state The build's state.
	 * @return array<string, mixed>|null The shape `schema/state.json` gives `view.scene`.
	 */
	public function of( State $state ): ?array {
		if ( ! $state->kit->settings->view3d ) {
			return null;
		}
		$counted = $state->counted();
		$picks   = Picks::none();
		foreach ( $counted as $slot => $part ) {
			$picks = $picks->with( $slot, $part );
		}
		$scene = Scene::of( $picks, $state->kit->picture );
		if ( null === $scene ) {
			return null;
		}
		$art     = $this->artwork->of( Layout::of( $picks, $state->kit->picture ), $counted );
		$texture = function ( string $slot ) use ( $counted, $art, $state ): ?string {
			$part  = $counted[ $slot ] ?? null;
			$drawn = null === $part ? null : ( $art[ $part->id ] ?? null );
			if ( null === $part || null === $drawn || ComposedLayers::AS_IS !== $this->layers->answer( $part->id, $slot, $state->id, self::USE ) ) {
				return null;
			}
			return self::url( $drawn );
		};

		$grip                     = $texture( SlotKey::GRIP );
		$scene['deck']['texture'] = array(
			'top'    => $grip,
			'bottom' => $texture( SlotKey::DECK ),
		);
		if ( null !== $scene['wheels'] ) {
			$scene['wheels']['texture'] = $texture( SlotKey::WHEELS );
			// The photo is the wheel's picture too: held back with its layer where cbuild_composed_layer says so.
			$wheels                  = $counted[ SlotKey::WHEELS ] ?? null;
			$face                    = null === $wheels || null === $this->faces || ComposedLayers::AS_IS !== $this->layers->answer( $wheels->id, SlotKey::WHEELS, $state->id, self::USE ) ? null : $this->faces->of( $wheels->id );
			$scene['wheels']['face'] = null === $face ? null : array(
				'url'  => $face['url'],
				'x'    => $face['x'],
				'y'    => $face['y'],
				'size' => $face['size'],
			);
			// The domain gives no colour; cbuild_scene, after this, may give its own.
			if ( null !== $face ) {
				$scene['wheels']['color'] = $face['tread'];
			}
		}
		if ( null !== $scene['grip'] ) {
			$scene['grip']['texture'] = $grip;
		}
		/**
		 * Filters the 3D view's scene of a build: every part's size and place, in whole micrometres from the trucks'
		 * midpoint on the deck's top face, and its textures. It answers after the domain, so a plugin may change a
		 * texture or a part's color; an answer that is no scene with a deck is passed over, and the domain's is kept.
		 *
		 * @since 1.4.0
		 *
		 * @param array<string, mixed> $scene The scene, as `schema/state.json` gives `view.scene`.
		 * @param int                  $kit   The kit product's ID.
		 * @param array<string, int>   $parts The parts it was measured from, slot key to part ID.
		 */
		$said = apply_filters( 'cbuild_scene', $scene, $state->id, array_map( static fn( $part ): int => $part->id, $counted ) );
		return is_array( $said ) && is_array( $said['deck'] ?? null ) ? $said : $scene;
	}

	/**
	 * A texture's address: the largest WebP rendition made, two pixels to the millimetre where the image has them, or
	 * the image's own file before its renditions are made.
	 *
	 * @param LayerArt $art The part's layer image.
	 */
	private static function url( LayerArt $art ): string {
		$made = $art->sources[ self::TYPE ] ?? array();
		return array() === $made ? $art->url : $made[ count( $made ) - 1 ]['url'];
	}
}
