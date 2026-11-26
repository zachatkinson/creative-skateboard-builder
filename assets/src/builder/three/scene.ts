/**
 * The scene the server measured for a build (M14, the scene's contract): every length in whole micrometres, every
 * angle in tenths of a degree, the origin at the centre of the deck's top face, +x toward the nose, +y across the
 * width and +z up. A slot with nothing in it is null, and its part is left out. The browser draws these sizes and
 * decides none of them.
 */

/** A point on the deck's top face: x and y, in micrometres. */
export type Flat = readonly number[];

/** A point in the scene: x, y and z, in micrometres. */
export type Spot = readonly number[];

export interface Deck {
	part: number;
	width: number;
	length: number;
	wheelbase: number;
	nose: number;
	tail: number;
	concave: number;
	kick: number;
	thickness: number;
	plies: number;
	holes: {
		pattern: string;
		spacing: number;
		columns: number;
		points: readonly Flat[];
	};
	texture: { top: string | null; bottom: string | null };
}

export interface Trucks {
	part: number;
	axle: number;
	height: number;
	hanger: number;
	baseplate: { length: number; width: number };
	holes: readonly Flat[];
	color: string | null;
}

export interface Wheels {
	part: number;
	diameter: number;
	width: number;
	contact: number;
	texture: string | null;
	color: string | null;
	places: readonly Spot[];
}

export interface Risers {
	part: number;
	thickness: number;
}

export interface Bolts {
	part: number;
	length: number;
	points: readonly Flat[];
}

export interface Grip {
	part: number;
	texture: string | null;
}

export interface Scene {
	assumed?: readonly string[];
	deck: Deck;
	trucks?: Trucks | null;
	wheels?: Wheels | null;
	risers?: Risers | null;
	bolts?: Bolts | null;
	grip?: Grip | null;
}

/** The scene's parts, each drawn as a group of its own, so a change to one swaps that group alone. */
export const SLOTS = [ 'deck', 'trucks', 'wheels', 'risers', 'bolts' ] as const;

export type Slot = ( typeof SLOTS )[ number ];
