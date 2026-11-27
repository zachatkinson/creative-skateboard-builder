/**
 * The picture's renderers behind one interface (SPEC: The picture, "Ready for 3D"). The 2D board is drawn by the
 * server's template and re-rendered by the store, so its renderer has nothing to do; the 3D view, loaded on the press
 * of "View in 3D", draws the same view's scene. The store holds one renderer at a time, and goes back to the board's
 * when the 3D view closes or fails.
 */
import type { Object3D } from 'three';
import type { Scene } from './three/scene';

/**
 * Where the camera can be sent: the step's side, the top, the underside, level with the deck's edge (1.4.0, #171), or
 * close to the deck's graphic.
 */
export type Place = 'reset' | 'top' | 'under' | 'side' | 'graphic';

/** The places, as the server's buttons name them. */
export const PLACES: readonly Place[] = [
	'reset',
	'top',
	'under',
	'side',
	'graphic',
];

/** The tricks, as the server's buttons name them, in their order (1.4.0, #139). */
export const TRICKS = [
	'ollie',
	'kickflip',
	'heelflip',
	'pop-shove-it',
	'360-flip',
] as const;

export type TrickName = ( typeof TRICKS )[ number ];

/**
 * A trick, as the tricks are given to `play()`: how long it lasts, in milliseconds, and the board's pose at each
 * moment of it, from 0 to 1. The pose moves the board's group from its rest, and the renderer puts it back when the
 * trick ends.
 */
export interface Trick {
	duration: number;
	pose: ( board: Object3D, progress: number ) => void;
}

/** A renderer of the picture. */
export interface Renderer {
	/** Draw a view's scene, on its step's side: the top, or the underside. */
	show: ( scene: Scene | null | undefined, top: boolean ) => void;
	/** Send the camera somewhere. */
	look: ( place: Place ) => void;
	/** Play a trick once any before it has ended: false where motion is reduced, or nothing plays. */
	play: ( trick: Trick ) => Promise< boolean >;
	/** Give everything back. */
	close: () => void;
}

/** The 2D board's renderer: the template draws it, so nothing here draws, moves or plays. */
export const flat: Renderer = {
	show: () => undefined,
	look: () => undefined,
	play: () => Promise.resolve( false ),
	close: () => undefined,
};

/**
 * Whether a button names one of the places.
 *
 * @param value The button's value.
 */
export function placeOf( value: string | undefined ): Place | null {
	return PLACES.find( ( one ) => one === value ) ?? null;
}

/**
 * Whether a button names one of the tricks.
 *
 * @param value The button's value.
 */
export function trickOf( value: string | undefined ): TrickName | null {
	return TRICKS.find( ( one ) => one === value ) ?? null;
}
