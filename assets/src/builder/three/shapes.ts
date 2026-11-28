/**
 * The shapes the 3D view draws, from the scene's sizes alone (M14, #139): plain arithmetic, in millimetres, with no
 * three.js in it, so every number is tested. The server measured each size; this only turns sizes into points. The
 * shapes are simplified, as the view's copy says, and the sizes are the scene's.
 *
 * - **The deck:** its outline from width, length and the holes, with the nose and the tail rounded; its top face
 *   lifted by the concave across its width and by the kick past each truck's outer holes; its bottom face the
 *   thickness under the top.
 * - **A wheel:** a lathe profile, the face to the contact band and back, with the 608 bearing's seat and the axle's
 *   hole.
 * - **The trucks:** drawn at the axle centres the scene gives, so their height is the server's alone (F9).
 */
import type { Deck, Flat, Spot, Wheels } from './scene';

/** Millimetres in a micrometre: the scene sends micrometres, and the view draws in millimetres. */
export const MM = 0.001;

/** A 608 bearing, which every skateboard wheel takes: 22 mm across and 7 mm deep. */
export const BEARING = { radius: 11, depth: 7 } as const;

/** The 8 mm axle's hole through the wheel. */
export const AXLE_RADIUS = 4;

/** How far past the outer holes the kick starts to rise, and over how long it bends, in millimetres. */
export const KICK = { start: 15, bend: 40 } as const;

/** The points along the deck's length, and across its width, its faces are drawn with. */
export const GRID = { along: 72, across: 16 } as const;

/**
 * A size from the scene in millimetres: a missing or broken one is nothing.
 *
 * @param micrometres The scene's size.
 */
export function mm( micrometres: number | undefined ): number {
	return Number.isFinite( micrometres ) ? ( micrometres as number ) * MM : 0;
}

/**
 * The deck's ends along its length, in millimetres: the nose its own length past the outermost hole on its side, and
 * the tail the deck's length behind the nose, so the length is exact whatever the holes' rounding.
 *
 * @param deck The scene's deck.
 */
export function ends( deck: Deck ): { nose: number; tail: number } {
	const xs = deck.holes.points.map( ( point ) => mm( point[ 0 ] ) );
	const nose =
		xs.length > 0
			? Math.max( ...xs ) + mm( deck.nose )
			: mm( deck.length ) / 2;
	return { nose, tail: nose - mm( deck.length ) };
}

/**
 * Half the deck's width at a point along its length: the full half-width between the ends' curves, and a quarter
 * ellipse to nothing at each tip, its length along the deck no more than the half-width, so a round nose is round.
 *
 * @param deck The scene's deck.
 * @param x    The point along the deck, in millimetres from the origin.
 */
export function halfWidth( deck: Deck, x: number ): number {
	const half = mm( deck.width ) / 2;
	const { nose, tail } = ends( deck );
	if ( x >= nose || x <= tail ) {
		return 0;
	}
	const round = Math.min( half, ( nose - tail ) / 2 );
	const past = Math.max( x - ( nose - round ), tail + round - x, 0 );
	return half * Math.sqrt( 1 - ( past / round ) ** 2 );
}

/**
 * How far a kick has risen a distance past its start: a curve over its bend, then straight at its angle.
 *
 * @param past  How far past the kick's start, in millimetres; nothing before it.
 * @param slope The kick's rise per millimetre, the tangent of its angle.
 */
export function rise( past: number, slope: number ): number {
	if ( past <= 0 ) {
		return 0;
	}
	const bend = KICK.bend;
	return past < bend
		? ( ( past * past ) / ( 2 * bend ) ) * slope
		: ( bend / 2 + ( past - bend ) ) * slope;
}

/**
 * The deck's top face's height at a point, in millimetres: the concave's depth at the edges, rising from nothing at
 * the centre line as a parabola, and each kick past its truck's outer holes. A deck with no holes has no kick.
 *
 * @param deck The scene's deck.
 * @param x    Along the deck, in millimetres.
 * @param y    Across it, in millimetres.
 */
export function surface( deck: Deck, x: number, y: number ): number {
	const half = mm( deck.width ) / 2;
	const concave =
		half > 0 ? mm( deck.concave ) * Math.min( 1, ( y / half ) ** 2 ) : 0;
	const xs = deck.holes.points.map( ( point ) => mm( point[ 0 ] ) );
	if ( 0 === xs.length ) {
		// With no holes there are no trucks to kick up past.
		return concave;
	}
	const front = Math.max( ...xs );
	const back = Math.min( ...xs );
	const slope = Math.tan( ( ( deck.kick / 10 ) * Math.PI ) / 180 );
	return (
		concave +
		rise( x - ( front + KICK.start ), slope ) +
		rise( back - KICK.start - x, slope )
	);
}

/**
 * The points along the deck its faces are drawn at, closer together toward each tip, where the outline turns, and
 * the tips themselves first and last.
 *
 * @param deck The scene's deck.
 */
export function stations( deck: Deck ): number[] {
	const { nose, tail } = ends( deck );
	const out: number[] = [];
	for ( let at = 0; at <= GRID.along; at++ ) {
		// A cosine's spacing: even in the middle, close at the ends.
		const share = ( 1 - Math.cos( ( Math.PI * at ) / GRID.along ) ) / 2;
		out.push( tail + share * ( nose - tail ) );
	}
	return out;
}

/** A face of the deck as a grid: its points, each with its texture's place, and the triangles between them. */
export interface Face {
	/** x, y, z of each point, in millimetres. */
	points: number[];
	/** u, v of each point: across the width, then from the tail to the nose. */
	uvs: number[];
	/** Three points' indices a triangle, wound so the face looks out. */
	triangles: number[];
}

/**
 * The deck's top or bottom face, as a grid over its outline. Its texture lies across the width and from the tail to
 * the nose, as a layer image is drawn nose up, and reads the right way round from the side it faces. The stage turns
 * these axes, z up, into three's, y up, so with the nose up the deck's +y side is on the left seen from above and on
 * the right seen from under: the top's u runs against y and the bottom's with it (D-568).
 *
 * @param deck The scene's deck.
 * @param side Which face.
 */
export function face( deck: Deck, side: 'top' | 'bottom' ): Face {
	const along = stations( deck );
	const { nose, tail } = ends( deck );
	const half = mm( deck.width ) / 2;
	const drop = 'top' === side ? 0 : mm( deck.thickness );
	const points: number[] = [];
	const uvs: number[] = [];
	for ( const x of along ) {
		const reach = halfWidth( deck, x );
		for ( let at = 0; at <= GRID.across; at++ ) {
			const y = -reach + ( 2 * reach * at ) / GRID.across;
			points.push( x, y, surface( deck, x, y ) - drop );
			const u = half > 0 ? ( y + half ) / ( 2 * half ) : 0.5;
			uvs.push(
				'top' === side ? 1 - u : u,
				( x - tail ) / ( nose - tail )
			);
		}
	}
	const row = GRID.across + 1;
	const triangles: number[] = [];
	for ( let i = 0; i < along.length - 1; i++ ) {
		for ( let j = 0; j < GRID.across; j++ ) {
			const a = i * row + j;
			const b = a + row;
			// Counter-clockwise seen from above for the top, from below for the bottom.
			if ( 'top' === side ) {
				triangles.push( a, b, a + 1, a + 1, b, b + 1 );
			} else {
				triangles.push( a, a + 1, b, a + 1, b + 1, b );
			}
		}
	}
	return { points, uvs, triangles };
}

/**
 * The deck's edge, all the way round: a band from the top face's rim to the bottom's, its texture's v across the
 * thickness, so the plies run along it as stripes.
 *
 * @param deck The scene's deck.
 */
export function edge( deck: Deck ): Face {
	const along = stations( deck );
	// Round the outline: along one side from the tail to the nose, then back along the other.
	const rim: [ number, number ][] = [
		...along.map( ( x ): [ number, number ] => [
			x,
			halfWidth( deck, x ),
		] ),
		...along
			.slice( 1, -1 )
			.reverse()
			.map( ( x ): [ number, number ] => [ x, -halfWidth( deck, x ) ] ),
	];
	const thick = mm( deck.thickness );
	const points: number[] = [];
	const uvs: number[] = [];
	rim.forEach( ( [ x, y ], at ) => {
		const z = surface( deck, x, y );
		points.push( x, y, z, x, y, z - thick );
		uvs.push( at / rim.length, 1, at / rim.length, 0 );
	} );
	const triangles: number[] = [];
	for ( let at = 0; at < rim.length; at++ ) {
		const a = 2 * at;
		const b = 2 * ( ( at + 1 ) % rim.length );
		// The rim runs clockwise seen from above, so top, next top, bottom is counter-clockwise seen from outside
		// (D-505): wound the other way, three.js culled the band and the deck looked hollow from the side.
		triangles.push( a, b, a + 1, b, b + 1, a + 1 );
	}
	return { points, uvs, triangles };
}

/**
 * A wheel's profile for a lathe, as radius and width pairs in millimetres, from one face round to the other: the
 * bearing's seat and the axle's hole inside, the face out to its lip, a shoulder curving to the flat contact band, and
 * the same back. The width and the band are the wheel's own; where the scene has no band, half the width is.
 *
 * @param wheels The scene's wheels.
 */
export function profile( wheels: Wheels ): [ number, number ][] {
	const radius = mm( wheels.diameter ) / 2;
	const half = mm( wheels.width ) / 2;
	const contact = mm( wheels.contact );
	const band = Math.min( contact > 0 ? contact : half, 2 * half ) / 2;
	const seat = Math.min( BEARING.radius, radius * 0.6 );
	const depth = Math.min( BEARING.depth, half * 0.8 );
	const hole = Math.min( AXLE_RADIUS, seat * 0.5 );
	// The lip where the face meets the shoulder: about half the way out from the seat.
	const lip = seat + ( radius - seat ) * 0.55;
	const shoulder: [ number, number ][] = [];
	const steps = 8;
	for ( let at = 0; at <= steps; at++ ) {
		const angle = ( Math.PI / 2 ) * ( at / steps );
		shoulder.push( [
			lip + ( radius - lip ) * Math.sin( angle ),
			-half + ( half - band ) * ( 1 - Math.cos( angle ) ),
		] );
	}
	const one: [ number, number ][] = [
		[ hole, -half + depth ],
		[ seat, -half + depth ],
		[ seat, -half ],
		...shoulder,
	];
	const other = one
		.slice()
		.reverse()
		.map( ( [ r, y ] ): [ number, number ] => [ r, -y ] );
	return [ ...one, ...other, one[ 0 ]! ];
}

/**
 * A point from the scene in millimetres.
 *
 * @param point The scene's point.
 */
export function spot( point: Spot | Flat ): [ number, number, number ] {
	return [ mm( point[ 0 ] ), mm( point[ 1 ] ), mm( point[ 2 ] ) ];
}
