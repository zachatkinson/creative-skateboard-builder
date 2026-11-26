/**
 * The 3D view's tricks (M14, #139): ollie, kickflip, heelflip, pop shove-it and 360 flip, each a keyframed pose of the
 * whole board, in the scene's axes (+x to the nose, +y across, +z up, in millimetres). The parts stay assembled and
 * the board moves as one; nothing is simulated.
 *
 * - **Timed from the teardown** (docs/research/3d-teardown.md, "Tricks and other motion"): 930 ms for an ollie,
 *   1,200 for a kickflip or heelflip, 1,130 for a pop shove-it and 1,470 for a 360 flip.
 * - **Heights** are about two-thirds of the teardown's, so the board stays in the frame from the three-quarter view.
 * - **Each starts and ends at rest.** A flip or a spin turns whole turns. A pop shove-it turns half a turn in the
 *   air, as the trick does, then reverts the other half on its wheels in 300 ms, so the nose ends where it began.
 * - **The board turns about its middle,** PIVOT below the deck's top, so a flip spins it in place.
 */
import { Vector3, type Object3D } from 'three';
import type { Trick, TrickName } from '../picture';

/** A pose of the board from its rest: its rise in millimetres, and its turns in radians. */
export interface Pose {
	rise: number;
	/** About the board's own long axis; a kickflip's way is positive. */
	roll: number;
	/** The nose up. */
	pitch: number;
	/** About the vertical; the nose toward +y is positive. */
	yaw: number;
}

/** Keys of one value: each a time in milliseconds and the value then, the first at 0 and the last at the end. */
type Keys = readonly ( readonly [ number, number ] )[];

interface Moves {
	rise: Keys;
	/** In degrees, as the teardown measured them. */
	roll?: Keys;
	pitch?: Keys;
	yaw?: Keys;
}

/** How far below the deck's top the board turns, in millimetres: about halfway down to its wheels' axles. */
export const PIVOT = 45;

/**
 * A smooth step from 0 to 1, held outside.
 *
 * @param t The time, from 0 to 1.
 */
export function smooth( t: number ): number {
	const k = Math.min( Math.max( t, 0 ), 1 );
	return k * k * ( 3 - 2 * k );
}

/**
 * One value at a moment: each key's at its time, eased between two, and the first or last held past the ends.
 *
 * @param keys The keys.
 * @param ms   The moment, in milliseconds.
 */
export function sample( keys: Keys, ms: number ): number {
	for ( let index = 1; index < keys.length; index++ ) {
		const [ to, value ] = keys[ index ]!;
		// Stryker disable next-line EqualityOperator: at a key's own time either side gives its value.
		if ( to >= ms ) {
			const [ from, was ] = keys[ index - 1 ]!;
			return (
				was + ( value - was ) * smooth( ( ms - from ) / ( to - from ) )
			);
		}
	}
	return keys.at( -1 )?.[ 1 ] ?? 0;
}

const DEGREE = Math.PI / 180;

/** Each trick's keys. */
const MOVES: Record< TrickName, Moves > = {
	// Rises 200 mm; nose up 24° at the pop, level at the top, the tail down 7° before it lands.
	ollie: {
		rise: [
			[ 0, 0 ],
			[ 110, 0 ],
			[ 470, 200 ],
			[ 800, 0 ],
			[ 930, 0 ],
		],
		pitch: [
			[ 0, 0 ],
			[ 90, 0 ],
			[ 180, 24 ],
			[ 420, 0 ],
			[ 720, 7 ],
			[ 800, 0 ],
			[ 930, 0 ],
		],
	},
	// Rises 240 mm, with one full roll about the long axis.
	kickflip: {
		rise: [
			[ 0, 0 ],
			[ 120, 0 ],
			[ 560, 240 ],
			[ 1000, 0 ],
			[ 1200, 0 ],
		],
		pitch: [
			[ 0, 0 ],
			[ 100, 0 ],
			[ 200, 20 ],
			[ 420, 0 ],
			[ 1200, 0 ],
		],
		roll: [
			[ 0, 0 ],
			[ 200, 0 ],
			[ 820, 360 ],
			[ 1200, 360 ],
		],
	},
	// The kickflip's roll, the other way.
	heelflip: {
		rise: [
			[ 0, 0 ],
			[ 120, 0 ],
			[ 560, 240 ],
			[ 1000, 0 ],
			[ 1200, 0 ],
		],
		pitch: [
			[ 0, 0 ],
			[ 100, 0 ],
			[ 200, 20 ],
			[ 420, 0 ],
			[ 1200, 0 ],
		],
		roll: [
			[ 0, 0 ],
			[ 200, 0 ],
			[ 820, -360 ],
			[ 1200, -360 ],
		],
	},
	// Rises 200 mm and turns 180° about the vertical, lands by 900 ms, then reverts the other 180° on its wheels.
	'pop-shove-it': {
		rise: [
			[ 0, 0 ],
			[ 110, 0 ],
			[ 520, 200 ],
			[ 900, 0 ],
			[ 1430, 0 ],
		],
		pitch: [
			[ 0, 0 ],
			[ 90, 0 ],
			[ 180, 14 ],
			[ 400, 0 ],
			[ 1430, 0 ],
		],
		yaw: [
			[ 0, 0 ],
			[ 160, 0 ],
			[ 780, 180 ],
			[ 1130, 180 ],
			[ 1430, 360 ],
		],
	},
	// Rises 280 mm and turns 360° about the vertical, with a kickflip's roll.
	'360-flip': {
		rise: [
			[ 0, 0 ],
			[ 120, 0 ],
			[ 650, 280 ],
			[ 1250, 0 ],
			[ 1470, 0 ],
		],
		pitch: [
			[ 0, 0 ],
			[ 100, 0 ],
			[ 200, 20 ],
			[ 450, 0 ],
			[ 1470, 0 ],
		],
		roll: [
			[ 0, 0 ],
			[ 220, 0 ],
			[ 1050, 360 ],
			[ 1470, 360 ],
		],
		yaw: [
			[ 0, 0 ],
			[ 180, 0 ],
			[ 1100, 360 ],
			[ 1470, 360 ],
		],
	},
};

/**
 * How long a trick lasts: its last key's time.
 *
 * @param moves The trick's keys.
 */
function length( moves: Moves ): number {
	return moves.rise.at( -1 )?.[ 0 ] ?? 0;
}

/**
 * A trick's pose at a moment.
 *
 * @param name     The trick.
 * @param progress From 0 to 1.
 */
export function at( name: TrickName, progress: number ): Pose {
	const moves = MOVES[ name ];
	const ms = progress * length( moves );
	const turn = ( keys: Keys | undefined ): number =>
		keys ? sample( keys, ms ) * DEGREE : 0;
	return {
		rise: sample( moves.rise, ms ),
		roll: turn( moves.roll ),
		pitch: turn( moves.pitch ),
		yaw: turn( moves.yaw ),
	};
}

/**
 * Put the board in a pose: turned about the vertical, then pitched, then rolled about its own long axis, all about
 * its middle, and risen.
 *
 * @param board The board's group.
 * @param pose  The pose.
 */
export function place( board: Object3D, pose: Pose ): void {
	// three's y turns +x toward −z, so the nose goes up for a negative turn.
	board.rotation.set( pose.roll, 0 - pose.pitch, pose.yaw, 'ZYX' );
	const middle = new Vector3( 0, 0, -PIVOT );
	const turned = middle.clone().applyEuler( board.rotation );
	board.position.set( 0, 0, pose.rise ).add( middle ).sub( turned );
}

/** The tricks, as the stage plays them. */
export const TRICKS: Record< TrickName, Trick > = Object.fromEntries(
	( Object.keys( MOVES ) as TrickName[] ).map( ( name ) => [
		name,
		{
			duration: length( MOVES[ name ] ),
			pose: ( board: Object3D, progress: number ) =>
				place( board, at( name, progress ) ),
		},
	] )
) as Record< TrickName, Trick >;
