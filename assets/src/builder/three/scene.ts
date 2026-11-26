/**
 * The scene the server measured for a build (M14, the scene's contract): every length in whole micrometres, every
 * angle in tenths of a degree, the origin on the deck's top face midway between the trucks, +x toward the nose, +y
 * across the width and +z up. A slot with nothing in it is null, and its part is left out. The browser draws these
 * sizes and decides none of them.
 */

import type { CbuildState, ScenePoint } from '../../types/state';

/** A point on the deck's top face: x and y, in micrometres. */
export type Flat = Readonly< ScenePoint >;

/** The scene as `schema/state.json` gives `view.scene`, and `npm run types` writes it. */
type Measured = NonNullable< NonNullable< CbuildState[ 'view' ] >[ 'scene' ] >;

/** The slots a scene may leave out: the server gives each, as null when empty, but `cbuild_scene` may drop one. */
type Optional = 'assumed' | 'trucks' | 'wheels' | 'risers' | 'bolts' | 'grip';

/**
 * The scene the view draws: the server's own shape, so the view and the route can't drift apart, with a slot a
 * plugin's answer left out read as an empty one.
 */
export type Scene = Omit< Measured, Optional > &
	Partial< Pick< Measured, Optional > >;

/** A point in the scene: x, y and z, in micrometres. */
export type Spot = Readonly<
	NonNullable< Measured[ 'wheels' ] >[ 'places' ][ number ]
>;

export type Deck = Scene[ 'deck' ];
export type Trucks = NonNullable< Scene[ 'trucks' ] >;
export type Wheels = NonNullable< Scene[ 'wheels' ] >;
export type Risers = NonNullable< Scene[ 'risers' ] >;
export type Bolts = NonNullable< Scene[ 'bolts' ] >;
export type Grip = NonNullable< Scene[ 'grip' ] >;

/** The scene's parts, each drawn as a group of its own, so a change to one swaps that group alone. */
export const SLOTS = [ 'deck', 'trucks', 'wheels', 'risers', 'bolts' ] as const;

export type Slot = ( typeof SLOTS )[ number ];
