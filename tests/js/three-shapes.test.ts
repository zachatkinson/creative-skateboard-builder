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
	axleHeight,
	centres,
	edge,
	ends,
	face,
	halfWidth,
	mm,
	profile,
	rise,
	spot,
	stations,
	surface,
} from '../../assets/src/builder/three/shapes';
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
		// u across from the toe side's opposite edge, v from the tail to the nose.
		expect( top.uvs[ 0 ] ).toBeCloseTo( 0.5, 9 );
		expect( top.uvs[ 1 ] ).toBeCloseTo( 0, 9 );
		expect( top.uvs[ middle * 2 ] ).toBeCloseTo( 0, 6 );
		expect( top.uvs.at( -1 ) ).toBeCloseTo( 1, 9 );
		expect( top.uvs[ ( middle + GRID.across ) * 2 ] ).toBeCloseTo( 1, 6 );
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

describe( 'centres', () => {
	it( 'puts each truck at the middle of its own holes, the nose’s first', () => {
		const [ front, back ] = centres( scene().trucks! );
		expect( front![ 0 ] ).toBeCloseTo( ( 180.975 + 234.95 ) / 2, 9 );
		expect( front![ 1 ] ).toBeCloseTo( 0, 9 );
		expect( back![ 0 ] ).toBeCloseTo( -( 180.975 + 234.95 ) / 2, 9 );
		expect( back![ 1 ] ).toBeCloseTo( 0, 9 );
	} );

	it( 'leaves out a side with no holes', () => {
		const trucks = scene().trucks!;
		expect(
			centres( {
				...trucks,
				holes: trucks.holes.filter( ( point ) => point[ 0 ]! > 0 ),
			} )
		).toHaveLength( 1 );
		expect( centres( { ...trucks, holes: [ [ 0, 5000 ] ] } ) ).toEqual(
			[]
		);
	} );

	it( 'meets the deck’s own holes', () => {
		const { deck: board, trucks } = scene();
		expect( trucks!.holes ).toEqual( board.holes.points );
	} );
} );

describe( 'axleHeight', () => {
	it( 'is the deck, the riser and the truck’s height under the top', () => {
		const { deck: board, trucks } = scene();
		expect( axleHeight( board, trucks!, 0 ) ).toBeCloseTo( -63, 9 );
		expect( axleHeight( board, trucks!, 3175 ) ).toBeCloseTo( -66.175, 9 );
	} );

	it( 'is where the scene puts the wheels', () => {
		const { deck: board, trucks, wheels } = scene();
		expect( mm( wheels!.places[ 0 ]![ 2 ] ) ).toBeCloseTo(
			axleHeight( board, trucks!, 0 ),
			9
		);
	} );
} );

describe( 'spot', () => {
	it( 'turns a point into millimetres, a flat one on the top face', () => {
		expect( spot( [ 1000, -2000, 3000 ] ) ).toEqual( [ 1, -2, 3 ] );
		expect( spot( [ 1000, 2000 ] ) ).toEqual( [ 1, 2, 0 ] );
	} );
} );
