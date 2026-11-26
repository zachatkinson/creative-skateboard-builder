/**
 * The 3D view's tricks (M14, #139): each a keyframed pose of the whole board, in the scene's axes, timed from the
 * teardown. Each starts and ends at rest, and turns as the trick does: a kickflip once about the long axis, a heelflip
 * once the other way, a pop shove-it half a turn in the air and the rest on its wheels, and a 360 flip a full turn
 * about the vertical with a flip.
 */
import { describe, expect, it } from 'vitest';
import { Group, Vector3 } from 'three';
import { TRICKS as NAMES } from '../../assets/src/builder/picture';
import {
	PIVOT,
	TRICKS,
	at,
	place,
	sample,
	smooth,
	type Pose,
} from '../../assets/src/builder/three/tricks';

const TURN = 2 * Math.PI;
const DEG = Math.PI / 180;

/**
 * The board's group at a moment of a trick.
 *
 * @param name     The trick.
 * @param progress From 0 to 1.
 */
function posed( name: ( typeof NAMES )[ number ], progress: number ): Group {
	const board = new Group();
	TRICKS[ name ].pose( board, progress );
	board.updateMatrix();
	return board;
}

/**
 * Whether a group is at rest: where it started, turned by nothing, to within a micrometre.
 *
 * @param board The group.
 */
function resting( board: Group ): void {
	const rest = new Group();
	rest.updateMatrix();
	board.matrix.elements.forEach( ( value, index ) =>
		expect( value ).toBeCloseTo( rest.matrix.elements[ index ]!, 6 )
	);
}

/**
 * The highest a trick takes the board, sampled each 10 ms.
 *
 * @param name The trick.
 */
function peak( name: ( typeof NAMES )[ number ] ): Pose {
	let most: Pose = at( name, 0 );
	for ( let ms = 0; ms <= TRICKS[ name ].duration; ms += 10 ) {
		const now = at( name, ms / TRICKS[ name ].duration );
		if ( now.rise > most.rise ) {
			most = now;
		}
	}
	return most;
}

describe( 'smooth', () => {
	it( 'eases from 0 to 1 and holds outside', () => {
		expect( smooth( 0 ) ).toBe( 0 );
		expect( smooth( 0.5 ) ).toBe( 0.5 );
		expect( smooth( 0.25 ) ).toBe( 0.15625 );
		expect( smooth( 1 ) ).toBe( 1 );
		expect( smooth( -1 ) ).toBe( 0 );
		expect( smooth( 2 ) ).toBe( 1 );
	} );
} );

describe( 'sample', () => {
	const keys = [
		[ 0, 0 ],
		[ 100, 10 ],
		[ 300, 10 ],
		[ 400, 30 ],
	] as const;

	it( 'is each key at its time, eased between, and held past the ends', () => {
		expect( sample( keys, -5 ) ).toBe( 0 );
		expect( sample( keys, 0 ) ).toBe( 0 );
		expect( sample( keys, 50 ) ).toBe( 5 );
		expect( sample( keys, 25 ) ).toBeCloseTo( 1.5625, 9 );
		expect( sample( keys, 100 ) ).toBe( 10 );
		expect( sample( keys, 200 ) ).toBe( 10 );
		expect( sample( keys, 350 ) ).toBe( 20 );
		expect( sample( keys, 400 ) ).toBe( 30 );
		expect( sample( keys, 900 ) ).toBe( 30 );
	} );

	it( 'is its one key’s value at every moment, where it has one', () => {
		expect( sample( [ [ 0, 7 ] ], -5 ) ).toBe( 7 );
		expect( sample( [ [ 0, 7 ] ], 10 ) ).toBe( 7 );
	} );
} );

describe( 'the tricks', () => {
	it( 'are the five the server’s buttons name, in its order', () => {
		expect( Object.keys( TRICKS ) ).toEqual( [ ...NAMES ] );
		expect( NAMES ).toEqual( [
			'ollie',
			'kickflip',
			'heelflip',
			'pop-shove-it',
			'360-flip',
		] );
	} );

	it( 'last as long as the teardown timed them', () => {
		expect( TRICKS.ollie.duration ).toBe( 930 );
		expect( TRICKS.kickflip.duration ).toBe( 1200 );
		expect( TRICKS.heelflip.duration ).toBe( 1200 );
		// 1,130 ms in the air and landed, then a revert of 300 ms on its wheels back to where it started.
		expect( TRICKS[ 'pop-shove-it' ].duration ).toBe( 1430 );
		expect( TRICKS[ '360-flip' ].duration ).toBe( 1470 );
	} );

	it.each( NAMES )( '%s starts and ends at rest', ( name ) => {
		expect( at( name, 0 ) ).toEqual( {
			rise: 0,
			roll: 0,
			pitch: 0,
			yaw: 0,
		} );
		resting( posed( name, 0 ) );
		resting( posed( name, 1 ) );
		const end = at( name, 1 );
		expect( end.rise ).toBe( 0 );
		expect( end.pitch ).toBe( 0 );
		// Whole turns alone: the board lands as it took off.
		expect( Math.abs( end.roll / TURN ) % 1 ).toBe( 0 );
		expect( Math.abs( end.yaw / TURN ) % 1 ).toBe( 0 );
	} );

	it.each( NAMES )( '%s leaves the ground and comes back', ( name ) => {
		expect( at( name, 0.4 ).rise ).toBeGreaterThan( 100 );
		const high = posed( name, 0.4 );
		expect( high.position.z ).toBeGreaterThan( 50 );
	} );

	it( 'rise to about two-thirds of the teardown’s heights, so the board stays in the frame', () => {
		expect( peak( 'ollie' ).rise ).toBeCloseTo( 200, 0 );
		expect( peak( 'kickflip' ).rise ).toBeCloseTo( 240, 0 );
		expect( peak( 'heelflip' ).rise ).toBeCloseTo( 240, 0 );
		expect( peak( 'pop-shove-it' ).rise ).toBeCloseTo( 200, 0 );
		expect( peak( '360-flip' ).rise ).toBeCloseTo( 280, 0 );
	} );

	it( 'an ollie pops nose up 24°, levels, and lifts the nose 7° before it lands, and turns no other way', () => {
		let most = 0;
		for ( let ms = 0; ms <= 930; ms += 10 ) {
			const now = at( 'ollie', ms / 930 );
			most = Math.max( most, now.pitch );
			expect( now.roll ).toBe( 0 );
			expect( now.yaw ).toBe( 0 );
		}
		expect( most ).toBeCloseTo( ( 24 * Math.PI ) / 180, 9 );
		expect( at( 'ollie', 420 / 930 ).pitch ).toBeCloseTo( 0, 9 );
		expect( at( 'ollie', 720 / 930 ).pitch ).toBeCloseTo(
			( 7 * Math.PI ) / 180,
			9
		);
	} );

	it( 'a kickflip turns once about the long axis, and a heelflip once the other way', () => {
		expect( at( 'kickflip', 1 ).roll ).toBeCloseTo( TURN, 9 );
		expect( at( 'heelflip', 1 ).roll ).toBeCloseTo( -TURN, 9 );
		expect( at( 'kickflip', 1 ).yaw ).toBe( 0 );
		expect( at( 'heelflip', 1 ).yaw ).toBe( 0 );
		// Half over at the middle of its flip, the board upside down.
		const half = at( 'kickflip', 510 / 1200 );
		expect( half.roll ).toBeCloseTo( Math.PI, 9 );
		// The deck's own up, the matrix's third column, points down.
		expect(
			posed( 'kickflip', 510 / 1200 ).matrix.elements[ 10 ]
		).toBeCloseTo( -1, 9 );
	} );

	it( 'a pop shove-it turns half a turn about the vertical in the air, and the other half on its wheels', () => {
		const landed = at( 'pop-shove-it', 900 / 1430 );
		expect( landed.rise ).toBeCloseTo( 0, 9 );
		expect( at( 'pop-shove-it', 1130 / 1430 ).yaw ).toBeCloseTo(
			Math.PI,
			9
		);
		expect( at( 'pop-shove-it', 1 ).yaw ).toBeCloseTo( TURN, 9 );
		const airborne: number[] = [];
		for ( let ms = 0; ms <= 1430; ms += 10 ) {
			const now = at( 'pop-shove-it', ms / 1430 );
			expect( now.roll ).toBe( 0 );
			// Past half a turn only once it has landed.
			if ( now.yaw > Math.PI + 1e-9 && now.rise !== 0 ) {
				airborne.push( ms );
			}
		}
		expect( airborne ).toEqual( [] );
	} );

	it.each( NAMES )(
		'%s is a number at every moment, and never below the ground',
		( name ) => {
			const wrong: string[] = [];
			for ( let ms = 0; ms <= TRICKS[ name ].duration; ms += 5 ) {
				const now = at( name, ms / TRICKS[ name ].duration );
				for ( const [ key, value ] of Object.entries( now ) ) {
					if ( ! Number.isFinite( value ) ) {
						wrong.push( `${ key } at ${ ms } ms` );
					}
				}
				if ( now.rise < 0 ) {
					wrong.push( `below the ground at ${ ms } ms` );
				}
			}
			expect( wrong ).toEqual( [] );
		}
	);

	it( 'a heelflip is upside down the other way half over, and every flip pops nose up', () => {
		expect( at( 'heelflip', 510 / 1200 ).roll ).toBeCloseTo( -Math.PI, 9 );
		expect( at( 'kickflip', 200 / 1200 ).pitch ).toBeCloseTo( 20 * DEG, 9 );
		expect( at( 'heelflip', 200 / 1200 ).pitch ).toBeCloseTo( 20 * DEG, 9 );
		expect( at( '360-flip', 200 / 1470 ).pitch ).toBeCloseTo( 20 * DEG, 9 );
		expect( at( 'pop-shove-it', 180 / 1430 ).pitch ).toBeCloseTo(
			14 * DEG,
			9
		);
		// The 360 flip's spin and flip are each half done in the middle of their own keys.
		expect( at( '360-flip', 640 / 1470 ).yaw ).toBeCloseTo( Math.PI, 9 );
		expect( at( '360-flip', 635 / 1470 ).roll ).toBeCloseTo( Math.PI, 9 );
		expect( at( 'pop-shove-it', 470 / 1430 ).yaw ).toBeCloseTo(
			Math.PI / 2,
			9
		);
	} );

	it( 'a 360 flip turns a full turn about the vertical with one flip', () => {
		const end = at( '360-flip', 1 );
		expect( end.yaw ).toBeCloseTo( TURN, 9 );
		expect( end.roll ).toBeCloseTo( TURN, 9 );
	} );
} );

describe( 'place', () => {
	it( 'turns the board about its middle, so a flip spins it in place', () => {
		const board = new Group();
		place( board, { rise: 100, roll: Math.PI, pitch: 0, yaw: 0 } );
		board.updateMatrix();
		// The point PIVOT below the deck's top stays above the origin, at the rise.
		const middle = new Vector3( 0, 0, -PIVOT ).applyMatrix4( board.matrix );
		expect( middle.x ).toBeCloseTo( 0, 9 );
		expect( middle.y ).toBeCloseTo( 0, 9 );
		expect( middle.z ).toBeCloseTo( 100 - PIVOT, 9 );
		// Upside down, the deck's top now faces down.
		const top = new Vector3( 0, 0, 0 ).applyMatrix4( board.matrix );
		expect( top.z ).toBeCloseTo( 100 - 2 * PIVOT, 9 );
	} );

	it( 'lifts the nose for a positive pitch, and turns the nose to +y for a positive yaw', () => {
		const board = new Group();
		place( board, { rise: 0, roll: 0, pitch: Math.PI / 6, yaw: 0 } );
		board.updateMatrix();
		const nose = new Vector3( 400, 0, -PIVOT ).applyMatrix4( board.matrix );
		expect( nose.z ).toBeGreaterThan( -PIVOT );
		place( board, { rise: 0, roll: 0, pitch: 0, yaw: Math.PI / 2 } );
		board.updateMatrix();
		const turned = new Vector3( 400, 0, 0 ).applyMatrix4( board.matrix );
		expect( turned.x ).toBeCloseTo( 0, 9 );
		expect( turned.y ).toBeCloseTo( 400, 9 );
	} );

	it( 'rolls about the board’s own long axis after it has turned', () => {
		const board = new Group();
		place( board, {
			rise: 0,
			roll: Math.PI / 2,
			pitch: 0,
			yaw: Math.PI / 2,
		} );
		board.updateMatrix();
		// Turned a quarter, the nose points along +y; rolled about it, the nose stays there.
		const nose = new Vector3( 400, 0, -PIVOT ).applyMatrix4( board.matrix );
		expect( nose.x ).toBeCloseTo( 0, 9 );
		expect( nose.y ).toBeCloseTo( 400, 9 );
		expect( nose.z ).toBeCloseTo( -PIVOT, 9 );
	} );

	it( 'is the rest at no pose', () => {
		const board = new Group();
		place( board, { rise: 0, roll: 0, pitch: 0, yaw: 0 } );
		expect( board.position.toArray() ).toEqual( [ 0, 0, 0 ] );
		expect( board.rotation.toArray() ).toEqual( [ 0, 0, 0, 'ZYX' ] );
	} );
} );
