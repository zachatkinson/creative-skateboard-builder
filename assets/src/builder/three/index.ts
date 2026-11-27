/**
 * The 3D view's chunk (M14, #139): three.js and the stage, loaded only once the shopper presses "View in 3D", so the
 * builder's own script stays as small as before. It opens the stage on the server's canvas with a WebGL 2 renderer,
 * the room's lighting and the scene's own textures, and hands the store the picture's renderer interface.
 *
 * A browser without WebGL 2 throws from here, and the store goes back to the 2D board.
 */
import {
	NeutralToneMapping,
	PMREMGenerator,
	SRGBColorSpace,
	TextureLoader,
	WebGLRenderer,
	type Texture,
} from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import type { Renderer } from '../picture';
import { stage, type Drawing } from './stage';
import { TRICKS } from './tricks';

export { TRICKS as tricks };

/** What the store gives the view. */
export interface Opening {
	canvas: HTMLCanvasElement;
	/** Whether the device asks for reduced motion, read at each move. */
	reduced: () => boolean;
	/** Told once, when the view fails and has closed itself. */
	failed: ( why: unknown ) => void;
}

/**
 * Whether a texture's address is the shop's own: the scene's are the plugin's renditions, on the same origin, and
 * nothing else is loaded.
 *
 * @param url  The address.
 * @param page The page's own address.
 */
export function own( url: string | null | undefined, page: string ): boolean {
	if ( ! url ) {
		return false;
	}
	try {
		return new URL( url, page ).origin === new URL( page ).origin;
	} catch {
		return false;
	}
}

/**
 * Open the 3D view on a canvas.
 *
 * @param  opening The canvas, and what the view asks the store.
 * @throws {Error} Where the browser cannot draw WebGL 2.
 */
export function open( opening: Opening ): Renderer {
	const { canvas } = opening;
	const view = canvas.ownerDocument.defaultView ?? window;
	const renderer = new WebGLRenderer( {
		canvas,
		antialias: true,
		// Transparent, so the frame's ground shows through: the builder's own colour, which a theme sets (D-500).
		alpha: true,
		powerPreference: 'low-power',
	} );
	renderer.setClearColor( 0x000000, 0 );
	renderer.outputColorSpace = SRGBColorSpace;
	// Khronos's neutral tone mapping keeps a graphic's colours as they are, where a filmic one would shift them.
	renderer.toneMapping = NeutralToneMapping;
	const loader = new TextureLoader();
	const page = view.location.href;
	const shown = stage( {
		renderer: renderer as unknown as Drawing,
		reduced: opening.reduced,
		failed: opening.failed,
		memory: ( view.navigator as Navigator & { deviceMemory?: number } )
			.deviceMemory,
		texture( url, arrived ) {
			if ( ! own( url, page ) ) {
				return null;
			}
			// Only an image that loads is put on its part; one that fails leaves the part in its colour (F1).
			const texture: Texture = loader.load( url, arrived );
			texture.colorSpace = SRGBColorSpace;
			texture.anisotropy = 4;
			return texture;
		},
		environment( drawing ) {
			const generator = new PMREMGenerator(
				drawing as unknown as WebGLRenderer
			);
			const room = new RoomEnvironment();
			const target = generator.fromScene( room, 0.04 );
			room.dispose();
			return {
				texture: target.texture,
				dispose: () => {
					target.dispose();
					generator.dispose();
				},
			};
		},
	} );
	// A test reads the frames and the memory through the page's probe, which only a browser test turns on.
	if (
		canvas.ownerDocument.documentElement.hasAttribute( 'data-cbuild-probe' )
	) {
		( view as Window & { cbuildProbe?: unknown } ).cbuildProbe =
			Object.assign( shown, { tricks: TRICKS } );
	}
	return shown;
}
