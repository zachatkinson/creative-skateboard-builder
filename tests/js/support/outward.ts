/**
 * Which way a mesh's triangles look (M14, D-505). three.js draws a triangle's front only, the side its points run
 * counter-clockwise round, and lights it by the normal on that side; a triangle wound the other way is culled from
 * outside and lit from inside. These count the triangles that look in, two ways:
 *
 * - **against a direction:** a triangle looks out where its normal and the outward direction at its centre agree;
 * - **by a ray:** a triangle of a closed mesh looks out where a ray from just in front of it crosses the mesh an even
 *   number of times, which holds for any closed shape, a wheel's bore among them.
 */
import { BufferGeometry, Vector3 } from 'three';

/** A triangle of a geometry: its corners, its centre and its normal, not made unit. */
export interface Triangle {
	corners: [ Vector3, Vector3, Vector3 ];
	centre: Vector3;
	normal: Vector3;
}

/** A triangle with less area than this, in square millimetres, is a point or a line and looks nowhere. */
const FLAT = 1e-9;

/**
 * A geometry's triangles, with their area, and each one's corners in its own order.
 *
 * @param made The geometry, indexed or not.
 */
export function triangles( made: BufferGeometry ): Triangle[] {
	const position = made.getAttribute( 'position' );
	const index = made.getIndex();
	const count = index ? index.count : position.count;
	const corner = ( at: number ): Vector3 =>
		new Vector3().fromBufferAttribute(
			position,
			index ? index.getX( at ) : at
		);
	const out: Triangle[] = [];
	for ( let at = 0; at + 2 < count; at += 3 ) {
		const a = corner( at );
		const b = corner( at + 1 );
		const c = corner( at + 2 );
		const normal = new Vector3()
			.subVectors( b, a )
			.cross( new Vector3().subVectors( c, a ) );
		if ( normal.length() / 2 < FLAT ) {
			continue;
		}
		const centre = new Vector3()
			.add( a )
			.add( b )
			.add( c )
			.divideScalar( 3 );
		out.push( { corners: [ a, b, c ], centre, normal } );
	}
	return out;
}

/**
 * The triangles that look in, against the outward direction at each one's centre.
 *
 * @param made    The geometry.
 * @param outward The outward direction at a point.
 */
export function inwardOf(
	made: BufferGeometry,
	outward: ( point: Vector3 ) => Vector3
): Triangle[] {
	return triangles( made ).filter(
		( one ) => one.normal.dot( outward( one.centre ) ) <= 0
	);
}

/**
 * Whether a ray crosses a triangle, in front of its start (Möller and Trumbore's test).
 *
 * @param from  The ray's start.
 * @param along The ray's direction.
 * @param one   The triangle.
 */
function crosses( from: Vector3, along: Vector3, one: Triangle ): boolean {
	const [ a, b, c ] = one.corners;
	const ab = new Vector3().subVectors( b, a );
	const ac = new Vector3().subVectors( c, a );
	const p = new Vector3().crossVectors( along, ac );
	const det = ab.dot( p );
	if ( Math.abs( det ) < 1e-12 ) {
		return false;
	}
	const t0 = new Vector3().subVectors( from, a );
	const u = t0.dot( p ) / det;
	if ( u < 0 || u > 1 ) {
		return false;
	}
	const q = new Vector3().crossVectors( t0, ab );
	const v = along.dot( q ) / det;
	if ( v < 0 || u + v > 1 ) {
		return false;
	}
	return ac.dot( q ) / det > 0;
}

/**
 * The triangles of a closed geometry that look in: from just in front of each, a ray out along its normal, turned a
 * little off it so it misses the grid's lines, crosses the mesh an odd number of times.
 *
 * @param made The geometry, or the geometries that close it between them.
 */
export function inwardByRay( ...made: BufferGeometry[] ): Triangle[] {
	const all = made.flatMap( triangles );
	return all.filter( ( one ) => {
		const along = one.normal
			.clone()
			.normalize()
			.add( new Vector3( 0.0131, 0.0173, 0.0197 ) )
			.normalize();
		const from = one.centre.clone().addScaledVector( along, 1e-4 );
		const hits = all.filter(
			( other ) => other !== one && crosses( from, along, other )
		).length;
		return hits % 2 === 1;
	} );
}
