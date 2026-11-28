/**
 * The 3D view's shapes from the scene's sizes (M14, #139): the deck's outline and faces, the kick and the concave, a
 * wheel's lathe profile and the trucks' places, in millimetres. The scene is the contract's fixture.
 */
import { describe, expect, it } from 'vitest';
import {
	AXLE_RADIUS,
	BEARING,
	GRID,
	KICK,
	edge,
	ends,
	face,
	halfWidth,
	halves,
	mm,
	photoUv,
	profile,
	rise,
	spot,
	stations,
	surface,
} from '../../assets/src/builder/three/shapes';
import { aim } from '../../assets/src/builder/three/stage';
import { Euler, PerspectiveCamera, Vector3 } from 'three';
import { scene } from './support/scene';

const deck = scene().deck;

describe( 'mm', () => {
	it( 'turns micrometres into millimetres', () => {
		expect( mm( 209550 ) ).toBe( 209.55 );
	} );

	it( 'reads a missing or broken size as nothing', () => {
		expect( mm( undefined ) ).toBe( 0 );
		expect( mm( Number.NaN ) ).toBe( 0 );
		expect( mm( Number.POSITIVE_INFINITY ) ).toBe( 0 );
	} );
} );

describe( 'ends', () => {
	it( 'puts the nose its length past the outer holes, and the tail the deck’s length behind it', () => {
		const { nose, tail } = ends( deck );
		expect( nose ).toBeCloseTo( 234.95 + 177.8, 6 );
		expect( nose - tail ).toBeCloseTo( 812.8, 6 );
		// The fixture's tail adds up to its own length too.
		expect( tail ).toBeCloseTo( -234.95 - 165.1, 6 );
	} );

	it( 'centres a deck with no holes', () => {
		const bare = { ...deck, holes: { ...deck.holes, points: [] } };
		const { nose, tail } = ends( bare );
		expect( nose ).toBeCloseTo( 406.4, 9 );
		expect( tail ).toBeCloseTo( -406.4, 9 );
	} );
} );

describe( 'halfWidth', () => {
	const { nose, tail } = ends( deck );

	it( 'is half the width between the ends', () => {
		expect( halfWidth( deck, 0 ) ).toBeCloseTo( 104.775, 6 );
		expect( halfWidth( deck, nose - 104.775 ) ).toBeCloseTo( 104.775, 6 );
		expect( halfWidth( deck, tail + 104.775 ) ).toBeCloseTo( 104.775, 6 );
	} );

	it( 'rounds to nothing at each tip', () => {
		expect( halfWidth( deck, nose ) ).toBe( 0 );
		expect( halfWidth( deck, tail ) ).toBe( 0 );
		expect( halfWidth( deck, nose + 1 ) ).toBe( 0 );
		expect( halfWidth( deck, tail - 1 ) ).toBe( 0 );
		expect( halfWidth( deck, nose - 1 ) ).toBeGreaterThan( 0 );
		expect( halfWidth( deck, tail + 1 ) ).toBeGreaterThan( 0 );
	} );

	it( 'is a quarter circle at a round nose', () => {
		// Half the radius back from the tip, the circle is at √(1 − ¼) of the half-width.
		expect( halfWidth( deck, nose - 104.775 / 2 ) ).toBeCloseTo(
			104.775 * Math.sqrt( 0.75 ),
			6
		);
		expect( halfWidth( deck, tail + 104.775 / 2 ) ).toBeCloseTo(
			104.775 * Math.sqrt( 0.75 ),
			6
		);
	} );

	it( 'keeps a deck shorter than it is wide round, not past its middle', () => {
		const stub = {
			...deck,
			length: 100000,
			nose: 0,
			holes: { ...deck.holes, points: [ [ 50000, 0 ] ] },
		};
		// The ends are 50 mm apart either way of the middle: the curve is no longer than that.
		expect( halfWidth( stub, 0 ) ).toBeCloseTo( 104.775, 6 );
		expect( halfWidth( stub, 25 ) ).toBeCloseTo(
			104.775 * Math.sqrt( 0.75 ),
			6
		);
	} );
} );

describe( 'rise', () => {
	it( 'is nothing before the kick', () => {
		expect( rise( 0, 1 ) ).toBe( 0 );
		expect( rise( -5, 1 ) ).toBe( 0 );
	} );

	it( 'curves over its bend, then runs straight at its slope', () => {
		expect( rise( KICK.bend / 2, 1 ) ).toBeCloseTo( KICK.bend / 8, 9 );
		// A slope of other than 1, so the curve is seen to scale by it, not divide (#176).
		expect( rise( KICK.bend / 2, 0.5 ) ).toBeCloseTo( KICK.bend / 16, 9 );
		expect( rise( KICK.bend, 1 ) ).toBeCloseTo( KICK.bend / 2, 9 );
		expect( rise( KICK.bend + 10, 0.5 ) ).toBeCloseTo(
			( KICK.bend / 2 + 10 ) * 0.5,
			9
		);
	} );
} );

describe( 'surface', () => {
	it( 'is flat on the centre line between the trucks', () => {
		expect( surface( deck, 0, 0 ) ).toBe( 0 );
		expect( surface( deck, 234.95 + KICK.start, 0 ) ).toBe( 0 );
		expect( surface( deck, -234.95 - KICK.start, 0 ) ).toBe( 0 );
	} );

	it( 'is the concave’s depth at the edge, and a quarter of it half way out', () => {
		expect( surface( deck, 0, 104.775 ) ).toBeCloseTo( 9.5, 9 );
		expect( surface( deck, 0, -104.775 ) ).toBeCloseTo( 9.5, 9 );
		expect( surface( deck, 0, 104.775 / 2 ) ).toBeCloseTo( 9.5 / 4, 9 );
		expect( surface( deck, 0, 200 ) ).toBeCloseTo( 9.5, 9 );
	} );

	it( 'rises at the kick’s angle past each truck’s outer holes', () => {
		const slope = Math.tan( ( 18 * Math.PI ) / 180 );
		const past = KICK.bend + 20;
		const expected = ( KICK.bend / 2 + 20 ) * slope;
		expect( surface( deck, 234.95 + KICK.start + past, 0 ) ).toBeCloseTo(
			expected,
			9
		);
		expect( surface( deck, -234.95 - KICK.start - past, 0 ) ).toBeCloseTo(
			expected,
			9
		);
	} );

	it( 'has no concave without a width, and no kick without holes', () => {
		expect( surface( { ...deck, width: 0 }, 0, 50 ) ).toBe( 0 );
		const bare = { ...deck, holes: { ...deck.holes, points: [] } };
		expect( surface( bare, 400, 0 ) ).toBe( 0 );
		expect( surface( bare, -400, 0 ) ).toBe( 0 );
		expect( surface( bare, 0, 104.775 ) ).toBeCloseTo( 9.5, 9 );
	} );
} );

describe( 'stations', () => {
	it( 'runs from the tail to the nose, closer together at the tips', () => {
		const along = stations( deck );
		const { nose, tail } = ends( deck );
		expect( along ).toHaveLength( GRID.along + 1 );
		expect( along[ 0 ] ).toBeCloseTo( tail, 9 );
		expect( along.at( -1 ) ).toBeCloseTo( nose, 9 );
		const first = along[ 1 ]! - along[ 0 ]!;
		const middle = along[ GRID.along / 2 + 1 ]! - along[ GRID.along / 2 ]!;
		expect( first ).toBeLessThan( middle / 10 );
	} );
} );

describe( 'face', () => {
	it( 'draws the top over the outline, its texture nose up', () => {
		const top = face( deck, 'top' );
		const row = GRID.across + 1;
		expect( top.points ).toHaveLength( ( GRID.along + 1 ) * row * 3 );
		expect( top.uvs ).toHaveLength( ( GRID.along + 1 ) * row * 2 );
		expect( top.triangles ).toHaveLength( GRID.along * GRID.across * 6 );
		// The middle row's first point: the width's edge, the concave's height.
		const middle = ( GRID.along / 2 ) * row;
		const x = top.points[ middle * 3 ]!;
		expect( top.points[ middle * 3 + 1 ] ).toBeCloseTo(
			-halfWidth( deck, x ),
			9
		);
		expect( top.points[ middle * 3 + 2 ] ).toBeCloseTo(
			surface( deck, x, -halfWidth( deck, x ) ),
			9
		);
		// u across, from 1 at the -y edge to 0 at the +y edge (which way it reads is the test below's), v from the tail
		// to the nose.
		expect( top.uvs[ 0 ] ).toBeCloseTo( 0.5, 9 );
		expect( top.uvs[ 1 ] ).toBeCloseTo( 0, 9 );
		expect( top.uvs[ middle * 2 ] ).toBeCloseTo( 1, 6 );
		expect( top.uvs.at( -1 ) ).toBeCloseTo( 1, 9 );
		expect( top.uvs[ ( middle + GRID.across ) * 2 ] ).toBeCloseTo( 0, 6 );
	} );

	it( 'draws the bottom the thickness under the top, its texture mirrored and wound the other way', () => {
		const top = face( deck, 'top' );
		const bottom = face( deck, 'bottom' );
		const row = GRID.across + 1;
		const middle = ( GRID.along / 2 ) * row + 3;
		expect( bottom.points[ middle * 3 + 2 ] ).toBeCloseTo(
			top.points[ middle * 3 + 2 ]! - 11,
			9
		);
		expect( bottom.uvs[ middle * 2 ] ).toBeCloseTo(
			1 - top.uvs[ middle * 2 ]!,
			9
		);
		expect( top.triangles.slice( 0, 6 ) ).toEqual( [
			0,
			row,
			1,
			1,
			row,
			row + 1,
		] );
		expect( bottom.triangles.slice( 0, 6 ) ).toEqual( [
			0,
			1,
			row,
			1,
			row + 1,
			row,
		] );
	} );

	/**
	 * Which way round a face's texture reads from a place: the turn from the image's u direction to its v direction
	 * on the screen, positive where it is the image's own (u to the right, v up, as it is drawn), negative where it
	 * is mirrored. The face's points are put in three's axes as the stage puts the board (z up turned to y up), and
	 * seen through a camera where the stage puts it.
	 *
	 * @param side  The face.
	 * @param place The place it is seen from.
	 */
	function reads(
		side: 'top' | 'bottom',
		place: Parameters< typeof aim >[ 0 ]
	): number {
		const shape = face( deck, side );
		const row = GRID.across + 1;
		const a = ( GRID.along / 2 ) * row + GRID.across / 2;
		const turn = new Euler( -Math.PI / 2, 0, 0 );
		const camera = new PerspectiveCamera( 40, 1, 10, 20000 );
		const { from } = aim( place, 'top' === side );
		camera.position
			.set( ...from )
			.normalize()
			.multiplyScalar( 2000 );
		camera.lookAt( 0, 0, 0 );
		camera.updateMatrixWorld();
		const seen = ( at: number ): Vector3 =>
			new Vector3(
				shape.points[ at * 3 ],
				shape.points[ at * 3 + 1 ],
				shape.points[ at * 3 + 2 ]
			)
				.applyEuler( turn )
				.project( camera );
		const along = ( at: number, axis: 0 | 1 ): [ number, number ] => {
			const step = Math.sign(
				shape.uvs[ at * 2 + axis ]! - shape.uvs[ a * 2 + axis ]!
			);
			const [ p, q ] = [ seen( a ), seen( at ) ];
			return [ ( q.x - p.x ) * step, ( q.y - p.y ) * step ];
		};
		const [ ux, uy ] = along( a + 1, 0 );
		const [ vx, vy ] = along( a + row, 1 );
		return ux * vy - uy * vx;
	}

	// Cowork's 1.4.0 review, Zach on the demo, 27 November: a deck's graphic read backwards from under, its words
	// mirrored, since the bottom's u was turned for a view the axes don't make.
	it( "reads the bottom's graphic the right way round from under, and the top's grip from above", () => {
		expect( reads( 'bottom', 'under' ), 'the underside' ).toBeGreaterThan(
			0
		);
		expect(
			reads( 'bottom', 'graphic' ),
			'the graphic close-up'
		).toBeGreaterThan( 0 );
		expect(
			reads( 'bottom', 'reset' ),
			'the reset, on a step showing the underside'
		).toBeGreaterThan( 0 );
		expect( reads( 'top', 'top' ), 'the top' ).toBeGreaterThan( 0 );
		expect(
			reads( 'top', 'reset' ),
			'the reset, on a step showing the top'
		).toBeGreaterThan( 0 );
	} );

	it( 'centres a texture on a deck with no width', () => {
		const flat = face( { ...deck, width: 0 }, 'top' );
		expect( flat.uvs[ 0 ] ).toBe( 0.5 );
		expect( face( { ...deck, width: 0 }, 'bottom' ).uvs[ 0 ] ).toBe( 0.5 );
	} );
} );

describe( 'edge', () => {
	it( 'is a closed band round the outline, a thickness deep', () => {
		const band = edge( deck );
		const rim = 2 * ( GRID.along + 1 ) - 2;
		expect( band.points ).toHaveLength( rim * 6 );
		expect( band.uvs ).toHaveLength( rim * 4 );
		expect( band.triangles ).toHaveLength( rim * 6 );
		for ( let at = 0; at < rim; at++ ) {
			expect(
				band.points[ at * 6 + 2 ]! - band.points[ at * 6 + 5 ]!
			).toBeCloseTo( 11, 9 );
			// The texture's u runs once round the band, top and bottom alike, v across it (#176).
			expect( band.uvs.slice( at * 4, at * 4 + 4 ) ).toEqual( [
				at / rim,
				1,
				at / rim,
				0,
			] );
		}
		// The last quad closes the band on its first points, wound to look out (D-505).
		expect( band.triangles.slice( -6 ) ).toEqual( [
			2 * ( rim - 1 ),
			0,
			2 * ( rim - 1 ) + 1,
			0,
			1,
			2 * ( rim - 1 ) + 1,
		] );
		// Out along the toe side, back along the heel side.
		expect( band.points[ 6 * ( GRID.along / 2 ) + 1 ] ).toBeCloseTo(
			104.775,
			6
		);
		expect(
			band.points[ 6 * ( GRID.along + GRID.along / 2 ) + 1 ]
		).toBeCloseTo( -104.775, 6 );
		expect( band.uvs.slice( 0, 4 ) ).toEqual( [ 0, 1, 0, 0 ] );
		expect( band.uvs[ 4 ] ).toBeCloseTo( 1 / rim, 9 );
	} );
} );

describe( 'profile', () => {
	const wheels = scene().wheels!;

	it( 'is the wheel’s own diameter, width and contact band', () => {
		const points = profile( wheels );
		const radii = points.map( ( [ r ] ) => r );
		const widths = points.map( ( [ , y ] ) => y );
		expect( Math.max( ...radii ) ).toBeCloseTo( 27, 9 );
		expect( Math.min( ...widths ) ).toBeCloseTo( -16, 9 );
		expect( Math.max( ...widths ) ).toBeCloseTo( 16, 9 );
		const band = points.filter( ( [ r ] ) => Math.abs( r - 27 ) < 1e-9 );
		expect(
			band.map( ( [ , y ] ) => y ).sort( ( a, b ) => a - b )
		).toEqual( [ -9, 9 ] );
	} );

	it( 'holds a 608 bearing’s seat and the axle’s hole, and closes', () => {
		const points = profile( wheels );
		expect( points[ 0 ] ).toEqual( [ AXLE_RADIUS, -16 + BEARING.depth ] );
		expect( points[ 1 ] ).toEqual( [
			BEARING.radius,
			-16 + BEARING.depth,
		] );
		expect( points[ 2 ] ).toEqual( [ BEARING.radius, -16 ] );
		expect( points.at( -1 ) ).toEqual( points[ 0 ] );
		expect( points.at( -2 ) ).toEqual( [
			AXLE_RADIUS,
			16 - BEARING.depth,
		] );
		// The face runs from the seat out to its lip, about half way to the tread.
		expect( points[ 3 ] ).toEqual( [ 11 + ( 27 - 11 ) * 0.55, -16 ] );
	} );

	it( 'takes half the width as the band where the scene has none, and keeps the band inside the width', () => {
		const none = profile( { ...wheels, contact: 0 } );
		expect(
			none
				.filter( ( [ r ] ) => Math.abs( r - 27 ) < 1e-9 )
				.map( ( [ , y ] ) => y )
		).toEqual( [ -8, 8 ] );
		const wide = profile( { ...wheels, contact: 50000 } );
		expect(
			wide
				.filter( ( [ r ] ) => Math.abs( r - 27 ) < 1e-9 )
				.map( ( [ , y ] ) => y )
		).toEqual( [ -16, 16 ] );
	} );

	it( 'fits the seat into a small wheel', () => {
		const small = profile( { ...wheels, diameter: 20000, width: 10000 } );
		expect( small[ 1 ] ).toEqual( [ 6, -5 + 4 ] );
		expect( small[ 0 ]![ 0 ] ).toBe( 3 );
	} );
} );

describe( 'the scene’s trucks', () => {
	it( 'meet the deck’s own holes', () => {
		const { deck: board, trucks } = scene();
		expect( trucks!.holes ).toEqual( board.holes.points );
	} );

	// F9: the server sends each truck's axle centre, on its wheels' axle, so the view measures neither.
	it( 'are centred on their wheels’ axles, the nose’s first', () => {
		const { trucks, wheels } = scene();
		const [ front, back ] = trucks!.centres;
		expect( front ).toEqual( [ 207963, 0, -63000 ] );
		expect( back![ 0 ] ).toBe( -front![ 0 ]! );
		expect( wheels!.places[ 0 ]![ 0 ] ).toBe( front![ 0 ] );
		expect( wheels!.places[ 0 ]![ 2 ] ).toBe( front![ 2 ] );
	} );
} );

describe( 'spot', () => {
	it( 'turns a point into millimetres, a flat one on the top face', () => {
		expect( spot( [ 1000, -2000, 3000 ] ) ).toEqual( [ 1, -2, 3 ] );
		expect( spot( [ 1000, 2000 ] ) ).toEqual( [ 1, 2, 0 ] );
	} );
} );

describe( 'photoUv', () => {
	// The face's box: from 625 to 9375 ten-thousandths of the photo across and down.
	const box = { x: 625, y: 625, size: 8750 };

	it( 'puts the axle at the box’s centre and the tread’s edge at its sides', () => {
		const [ u, v ] = photoUv( 0, 0, 27, box );
		expect( u ).toBeCloseTo( 0.5, 9 );
		expect( v ).toBeCloseTo( 0.5, 9 );
		expect( photoUv( 27, 0, 27, box )[ 0 ] ).toBeCloseTo( 0.9375, 9 );
		expect( photoUv( -27, 0, 27, box )[ 0 ] ).toBeCloseTo( 0.0625, 9 );
	} );

	it( 'reads the photo’s top as up: a texture’s v runs from its foot', () => {
		expect( photoUv( 0, 27, 27, box )[ 1 ] ).toBeCloseTo( 1 - 0.0625, 9 );
		expect( photoUv( 0, -27, 27, box )[ 1 ] ).toBeCloseTo( 0.0625, 9 );
	} );

	it( 'follows a box off the photo’s centre', () => {
		const [ u, v ] = photoUv( 0, 0, 27, { x: 1000, y: 2000, size: 6000 } );
		expect( u ).toBeCloseTo( 0.4, 9 );
		expect( v ).toBeCloseTo( 1 - 0.5, 9 );
	} );
} );

describe( 'halves', () => {
	const points = profile( scene().wheels! );

	it( 'splits the profile at its outer half: the face and its shoulder, and the rest from where that ends', () => {
		const { outer, rest } = halves( points );
		expect( outer.every( ( [ , y ] ) => y < 0 ) ).toBe( true );
		expect( outer[ 0 ] ).toEqual( points[ 0 ] );
		expect( rest[ 0 ] ).toEqual( outer.at( -1 ) );
		expect( outer.length + rest.length - 1 ).toBe( points.length );
		expect( rest.at( -1 ) ).toEqual( points.at( -1 ) );
	} );
} );
