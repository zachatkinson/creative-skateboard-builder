/**
 * Every generated mesh's faces point outward (M14, D-505): three.js culls a triangle's back, so one wound inward is
 * invisible from outside, and lit from inside. The deck's edge was wound inward, and the deck looked see-through
 * between its faces from the side.
 */
import { describe, expect, it } from 'vitest';
import { Mesh, Vector3, type BufferGeometry, type Group } from 'three';
import {
	bolts,
	deck,
	geometry,
	risers,
	trucks,
	wheels,
} from '../../assets/src/builder/three/parts';
import {
	edge,
	ends,
	face,
	halfWidth,
} from '../../assets/src/builder/three/shapes';
import { inwardByRay, inwardOf, triangles } from './support/outward';
import { scene } from './support/scene';

const none = { load: () => null };

/**
 * The outward direction across the deck at a point: away from its centre line, (0, ±1, 0) along the sides and
 * toward the tip round the nose and the tail.
 *
 * @param point The point, in millimetres.
 */
function fromCentreLine( point: Vector3 ): Vector3 {
	const board = scene().deck;
	const { nose, tail } = ends( board );
	const round = halfWidth( board, 0 );
	const along = Math.min( Math.max( point.x, tail + round ), nose - round );
	return new Vector3( point.x - along, point.y, 0 );
}

/**
 * A group's meshes' geometries, each in its own place.
 *
 * @param group The group.
 */
function shapes( group: Group | null ): BufferGeometry[] {
	return ( group?.children ?? [] )
		.filter( ( child ): child is Mesh => child instanceof Mesh )
		.map( ( mesh ) => {
			mesh.updateMatrix();
			return mesh.geometry.clone().applyMatrix4( mesh.matrix );
		} );
}

describe( 'the deck’s faces', () => {
	const board = scene().deck;

	it( 'point outward round the edge, away from the centre line', () => {
		const band = geometry( edge( board ) );
		expect( triangles( band ).length ).toBeGreaterThan( 0 );
		expect( inwardOf( band, fromCentreLine ) ).toHaveLength( 0 );
	} );

	it( 'point up on the top and down on the bottom', () => {
		const up = new Vector3( 0, 0, 1 );
		const down = new Vector3( 0, 0, -1 );
		expect(
			inwardOf( geometry( face( board, 'top' ) ), () => up )
		).toHaveLength( 0 );
		expect(
			inwardOf( geometry( face( board, 'bottom' ) ), () => down )
		).toHaveLength( 0 );
	} );

	it( 'close the deck, every one looking out of it', () => {
		const made = shapes( deck( scene(), none ) );
		expect( made ).toHaveLength( 3 );
		expect( inwardByRay( ...made ) ).toHaveLength( 0 );
	} );
} );

describe( 'the other parts’ faces', () => {
	const built = scene();
	built.risers = { part: 404, thickness: 6000 };

	it.each( [
		[ 'trucks', trucks( built ) ],
		[ 'risers', risers( built ) ],
		[ 'wheels', wheels( built, none ) ],
		[ 'bolts', bolts( built ) ],
	] )( 'point outward on the %s', ( _slot, group ) => {
		const made = shapes( group );
		expect( made.length ).toBeGreaterThan( 0 );
		for ( const one of made ) {
			expect( inwardByRay( one ) ).toHaveLength( 0 );
		}
	} );
} );
