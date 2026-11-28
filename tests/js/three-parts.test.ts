/**
 * The 3D view's parts (M14, #139): each slot a group of its own from the scene's sizes, its texture or its colour,
 * and what each is drawn from, so a part change swaps one group.
 */
import { describe, expect, it, vi } from 'vitest';
import {
	Box3,
	BoxGeometry,
	CylinderGeometry,
	LatheGeometry,
	Mesh,
	MeshStandardMaterial,
	RepeatWrapping,
	SRGBColorSpace,
	Texture,
	Vector3,
	type Group,
} from 'three';
import {
	BOLT,
	COLOURS,
	TRUCK,
	bolts,
	deck,
	drawnFrom,
	geometry,
	part,
	rgb,
	risers,
	stripes,
	trucks,
	wheels,
} from '../../assets/src/builder/three/parts';
import {
	face,
	photoUv,
	spot,
	surface,
} from '../../assets/src/builder/three/shapes';
import { SLOTS } from '../../assets/src/builder/three/scene';
import { scene } from './support/scene';

const none = { load: () => null };

/**
 * A group's meshes.
 *
 * @param group The group.
 */
function meshes( group: Group | null ): Mesh[] {
	return ( group?.children ?? [] ).filter(
		( child ): child is Mesh => child instanceof Mesh
	);
}

/**
 * A mesh's material, as the parts make it.
 *
 * @param mesh The mesh.
 */
function look( mesh: Mesh | undefined ): MeshStandardMaterial {
	return mesh?.material as MeshStandardMaterial;
}

describe( 'rgb', () => {
	it( 'reads a colour’s channels', () => {
		expect( rgb( '#e7cf9f' ) ).toEqual( [ 0xe7, 0xcf, 0x9f ] );
		expect( rgb( '#010203' ) ).toEqual( [ 1, 2, 3 ] );
	} );
} );

describe( 'stripes', () => {
	it( 'has a band a ply, the two tones in turn', () => {
		const texture = stripes( 7 );
		expect( texture.image.width ).toBe( 1 );
		expect( texture.image.height ).toBe( 7 );
		const data = texture.image.data as Uint8Array;
		expect( [ ...data.slice( 0, 4 ) ] ).toEqual( [
			...rgb( COLOURS.ply[ 0 ] ),
			255,
		] );
		expect( [ ...data.slice( 4, 8 ) ] ).toEqual( [
			...rgb( COLOURS.ply[ 1 ] ),
			255,
		] );
		expect( [ ...data.slice( 24, 28 ) ] ).toEqual( [
			...rgb( COLOURS.ply[ 0 ] ),
			255,
		] );
		expect( texture.colorSpace ).toBe( SRGBColorSpace );
		expect( texture.wrapS ).toBe( RepeatWrapping );
		expect( texture.version ).toBeGreaterThan( 0 );
	} );

	it( 'has one band at least, and whole ones', () => {
		expect( stripes( 0 ).image.height ).toBe( 1 );
		expect( stripes( 6.6 ).image.height ).toBe( 7 );
	} );
} );

describe( 'geometry', () => {
	it( 'holds the face’s points, places and triangles, with normals', () => {
		const shape = face( scene().deck, 'top' );
		const made = geometry( shape );
		expect( made.getAttribute( 'position' ).count ).toBe(
			shape.points.length / 3
		);
		expect( made.getAttribute( 'uv' ).count ).toBe( shape.uvs.length / 2 );
		expect( made.getIndex()?.count ).toBe( shape.triangles.length );
		// The top's normal points up, out of the board.
		expect( made.getAttribute( 'normal' ).getZ( 8 ) ).toBeGreaterThan(
			0.9
		);
	} );
} );

describe( 'deck', () => {
	it( 'is the deck’s width and length, the top at the origin', () => {
		const group = deck( scene(), none );
		expect( group.name ).toBe( 'deck' );
		expect( group.userData ).toEqual( { slot: 'deck', part: 101 } );
		const box = new Box3().setFromObject( group );
		const size = box.getSize( new Vector3() );
		// Within half a millimetre of its specs (the plan's accuracy limit).
		expect( Math.abs( size.x - 812.8 ) ).toBeLessThan( 0.5 );
		expect( Math.abs( size.y - 209.55 ) ).toBeLessThan( 0.5 );
		expect( box.min.z ).toBeCloseTo( -11, 3 );
	} );

	it( 'draws the maple, a graphic, and the plies on its edge', () => {
		const [ top, bottom, side ] = meshes( deck( scene(), none ) );
		expect( look( top ).map ).toBeNull();
		expect( look( top ).color.getHexString() ).toBe(
			COLOURS.maple.slice( 1 )
		);
		expect( look( bottom ).color.getHexString() ).toBe(
			COLOURS.maple.slice( 1 )
		);
		expect( ( look( side ).map?.image as { height: number } ).height ).toBe(
			7
		);
		expect( look( side ).color.getHexString() ).toBe( 'ffffff' );
	} );

	it( 'draws a grip without a texture in the grip’s colour', () => {
		const gripped = { ...scene(), grip: { part: 5, texture: null } };
		const [ top ] = meshes( deck( gripped, none ) );
		expect( look( top ).color.getHexString() ).toBe(
			COLOURS.grip.slice( 1 )
		);
	} );

	it( 'textures the top from the deck’s, or the grip’s, and the bottom from the graphic', () => {
		const arrive: ( ( texture: Texture ) => void )[] = [];
		const load = vi.fn(
			( url: string | null | undefined, use: ( t: Texture ) => void ) => {
				if ( ! url ) {
					return null;
				}
				arrive.push( use );
				return new Texture();
			}
		);
		const textured = scene();
		textured.deck.texture = { top: '/top.png', bottom: '/bottom.png' };
		const [ top, bottom ] = meshes( deck( textured, { load } ) );
		expect( load ).toHaveBeenCalledWith(
			'/top.png',
			expect.any( Function )
		);
		expect( load ).toHaveBeenCalledWith(
			'/bottom.png',
			expect.any( Function )
		);
		for ( const use of arrive ) {
			use( new Texture() );
		}
		expect( look( top ).map ).toBeInstanceOf( Texture );
		expect( look( top ).color.getHexString() ).toBe( 'ffffff' );
		expect( look( bottom ).map ).toBeInstanceOf( Texture );
		const gripped = {
			...scene(),
			grip: { part: 5, texture: '/grip.png' },
		};
		deck( gripped, { load } );
		expect( load ).toHaveBeenCalledWith(
			'/grip.png',
			expect.any( Function )
		);
	} );

	// F1: a texture's part is drawn in its colour until its image arrives, and stays in it where the image fails.
	it( 'draws a textured part in its colour until its image arrives, then in the image', () => {
		let arrive: ( ( texture: Texture ) => void ) | undefined;
		const pending = new Texture();
		const load = vi.fn(
			( url: string | null | undefined, use: ( t: Texture ) => void ) => {
				if ( ! url ) {
					return null;
				}
				arrive = use;
				return pending;
			}
		);
		const textured = scene();
		textured.deck.texture = { top: null, bottom: '/bottom.png' };
		const [ , bottom ] = meshes( deck( textured, { load } ) );
		expect( look( bottom ).map ).toBeNull();
		expect( look( bottom ).color.getHexString() ).toBe(
			COLOURS.maple.slice( 1 )
		);
		// Given back with the material even where its image never arrives.
		expect( look( bottom ).userData.texture ).toBe( pending );
		const version = look( bottom ).version;
		arrive!( pending );
		expect( look( bottom ).map ).toBe( pending );
		expect( look( bottom ).color.getHexString() ).toBe( 'ffffff' );
		expect( look( bottom ).version ).toBeGreaterThan( version );
	} );

	it( 'is rough on top and smoother underneath', () => {
		const [ top, bottom, side ] = meshes( deck( scene(), none ) );
		expect( look( top ).roughness ).toBe( 0.85 );
		expect( look( bottom ).roughness ).toBe( 0.6 );
		expect( look( side ).roughness ).toBe( 0.7 );
		expect( look( top ).metalness ).toBe( 0 );
	} );
} );

describe( 'trucks', () => {
	it( 'is none without trucks', () => {
		expect( trucks( { ...scene(), trucks: null } ) ).toBeNull();
		expect( trucks( { deck: scene().deck } ) ).toBeNull();
	} );

	it( 'puts each on its holes, its axle the truck’s height under the deck', () => {
		// The scene's own centres (F9), between each truck's rows of holes.
		const group = trucks( scene() );
		expect( group?.userData ).toEqual( { slot: 'trucks', part: 202 } );
		const parts = meshes( group );
		expect( parts ).toHaveLength( 8 );
		const [ base, block, hanger, axle ] = parts;
		expect( base!.position.x ).toBeCloseTo( 207.963, 6 );
		expect( base!.position.y ).toBeCloseTo( 0, 6 );
		expect( base!.position.z ).toBeCloseTo( -11 - TRUCK.plate / 2, 6 );
		expect( parts[ 4 ]!.position.x ).toBeCloseTo( -207.963, 6 );
		const plate = ( base!.geometry as BoxGeometry ).parameters;
		expect( plate ).toMatchObject( { width: 70, height: 55, depth: 6 } );
		const drop = ( block!.geometry as BoxGeometry ).parameters;
		expect( drop.depth ).toBeCloseTo( 63 - 11 - TRUCK.plate, 6 );
		expect( block!.position.z ).toBeCloseTo(
			-11 - TRUCK.plate - drop.depth / 2,
			6
		);
		expect( drop.width ).toBeCloseTo( 70 * 0.45, 6 );
		expect( drop.height ).toBeCloseTo( 55 * 0.6, 6 );
		expect( hanger!.position.z ).toBeCloseTo( -63, 6 );
		expect(
			( hanger!.geometry as CylinderGeometry ).parameters
		).toMatchObject( { radiusTop: TRUCK.hanger, height: 140 } );
		expect( axle!.position.z ).toBeCloseTo( -63, 6 );
		expect(
			( axle!.geometry as CylinderGeometry ).parameters
		).toMatchObject( { radiusTop: TRUCK.axle, height: 209.55 } );
	} );

	// F9: the axle's height is the server's, sent as each truck's centre, as the wheels' places are; nothing here
	// measures it again, so a truck and its wheels move together.
	it( 'draws each truck at the scene’s axle centre, and the wheels at theirs alike', () => {
		const taller = scene();
		taller.trucks!.centres = [
			[ 207963, 0, -66000 ],
			[ -207963, 0, -66000 ],
		];
		taller.wheels!.places = taller.wheels!.places.map( ( [ x, y ] ) => [
			x ?? 0,
			y ?? 0,
			-66000,
		] );
		const [ base, block, hanger, axle ] = meshes( trucks( taller ) );
		expect( hanger!.position.z ).toBeCloseTo( -66, 6 );
		expect( axle!.position.z ).toBeCloseTo( -66, 6 );
		expect( base!.position.z ).toBeCloseTo( -11 - TRUCK.plate / 2, 6 );
		expect(
			( block!.geometry as BoxGeometry ).parameters.depth
		).toBeCloseTo( 66 - 11 - TRUCK.plate, 6 );
		expect( meshes( wheels( taller, none ) )[ 0 ]!.position.z ).toBeCloseTo(
			-66,
			6
		);
		// The truck's height alone, with the centres the same, moves nothing: it is not measured again.
		const same = scene();
		same.trucks!.height += 5000;
		expect( meshes( trucks( same ) )[ 2 ]!.position.z ).toBeCloseTo(
			-63,
			6
		);
		// A truck on a centre off the line, as another plugin's answer may put it, is drawn there.
		const moved = scene();
		moved.trucks!.centres = [ [ 100000, 5000, -63000 ] ];
		const one = meshes( trucks( moved ) );
		expect( one ).toHaveLength( 4 );
		expect( one[ 0 ]!.position.x ).toBeCloseTo( 100, 6 );
		expect( one[ 0 ]!.position.y ).toBeCloseTo( 5, 6 );
	} );

	it( 'is the truck’s colour, or the plain metal’s, and metal', () => {
		const [ base ] = meshes( trucks( scene() ) );
		expect( look( base ).color.getHexString() ).toBe( 'b8bcc2' );
		expect( look( base ).metalness ).toBe( 0.8 );
		expect( look( base ).roughness ).toBe( 0.35 );
		const plain = scene();
		plain.trucks!.color = null;
		expect(
			look( meshes( trucks( plain ) )[ 0 ] ).color.getHexString()
		).toBe( COLOURS.truck.slice( 1 ) );
		plain.trucks!.color = '#112233';
		expect(
			look( meshes( trucks( plain ) )[ 0 ] ).color.getHexString()
		).toBe( '112233' );
	} );

	it( 'stands under a riser', () => {
		const raised = { ...scene(), risers: { part: 9, thickness: 3175 } };
		// The server's centres, under the riser too.
		raised.trucks!.centres = [
			[ 207963, 0, -66175 ],
			[ -207963, 0, -66175 ],
		];
		const [ base, , hanger ] = meshes( trucks( raised ) );
		expect( base!.position.z ).toBeCloseTo( -11 - 3.175 - 3, 6 );
		expect( hanger!.position.z ).toBeCloseTo( -66.175, 6 );
	} );

	it( 'has no block where the truck is shorter than its baseplate', () => {
		const low = scene();
		low.trucks!.height = 1000;
		low.trucks!.centres = [
			[ 207963, 0, -12000 ],
			[ -207963, 0, -12000 ],
		];
		const block = meshes( trucks( low ) )[ 1 ]!;
		expect( ( block.geometry as BoxGeometry ).parameters.depth ).toBe( 0 );
	} );
} );

describe( 'wheels', () => {
	it( 'is none without wheels', () => {
		expect( wheels( { ...scene(), wheels: null }, none ) ).toBeNull();
	} );

	it( 'puts a lathe of the wheel’s profile at each place, sharing one shape', () => {
		const group = wheels( scene(), none );
		expect( group?.userData ).toEqual( { slot: 'wheels', part: 303 } );
		const four = meshes( group );
		expect( four ).toHaveLength( 4 );
		expect( four[ 0 ]!.geometry ).toBeInstanceOf( LatheGeometry );
		expect( four[ 1 ]!.geometry ).toBe( four[ 0 ]!.geometry );
		expect(
			( four[ 0 ]!.geometry as LatheGeometry ).parameters.segments
		).toBe( 48 );
		expect( four[ 0 ]!.position.toArray() ).toEqual( [
			207.963, 88.775, -63,
		] );
		const box = new Box3().setFromObject( four[ 0 ]! );
		const size = box.getSize( new Vector3() );
		expect( size.y ).toBeCloseTo( 32, 3 );
		expect( size.x ).toBeCloseTo( 54, 0 );
		expect( look( four[ 0 ] ).color.getHexString() ).toBe( 'f4f1ea' );
		expect( look( four[ 0 ] ).roughness ).toBe( 0.55 );
	} );

	it( 'is the plain urethane’s colour without one, and its texture with one', () => {
		const plain = scene();
		plain.wheels!.color = null;
		expect(
			look( meshes( wheels( plain, none ) )[ 0 ] ).color.getHexString()
		).toBe( COLOURS.wheel.slice( 1 ) );
		plain.wheels!.texture = '/wheel.png';
		const load = vi.fn(
			(
				_url: string | null | undefined,
				use: ( t: Texture ) => void
			) => {
				use( new Texture() );
				return null;
			}
		);
		const [ one ] = meshes( wheels( plain, { load } ) );
		expect( load ).toHaveBeenCalledWith(
			'/wheel.png',
			expect.any( Function )
		);
		expect( look( one ).map ).toBeInstanceOf( Texture );
	} );
} );

describe( 'wheels with a face', () => {
	const faced = () => {
		const made = scene();
		made.wheels!.color = '#c81e28';
		made.wheels!.texture = '/wheel-layer.png';
		made.wheels!.face = {
			url: '/wheel-768x768.jpg',
			x: 625,
			y: 625,
			size: 8750,
		};
		return made;
	};
	const loaded = () =>
		vi.fn(
			(
				_url: string | null | undefined,
				use: ( t: Texture ) => void
			) => {
				use( new Texture() );
				return null;
			}
		);

	it( 'maps each wheel’s outer half from the photo and draws the rest in the tread’s colour', () => {
		const load = loaded();
		const group = wheels( faced(), { load } );
		const four = ( group?.children ?? [] ) as Group[];
		expect( four ).toHaveLength( 4 );
		const [ outer, rest ] = meshes( four[ 0 ]! );
		expect( outer!.geometry ).toBeInstanceOf( LatheGeometry );
		expect( look( outer ).map ).toBeInstanceOf( Texture );
		expect( look( rest ).map ).toBeNull();
		expect( look( rest ).color.getHexString() ).toBe( 'c81e28' );
		expect( load ).toHaveBeenCalledWith(
			'/wheel-768x768.jpg',
			expect.any( Function )
		);
		expect( load ).not.toHaveBeenCalledWith(
			'/wheel-layer.png',
			expect.any( Function )
		);
		// One shape for the four, each half's.
		expect( meshes( four[ 3 ]! )[ 0 ]!.geometry ).toBe( outer!.geometry );
	} );

	it( 'projects the photo along the axle: the axle at the box’s centre, the edge at its sides', () => {
		const [ outer ] = meshes(
			( wheels( faced(), { load: loaded() } )?.children ??
				[] )[ 0 ] as Group
		);
		const position = outer!.geometry.getAttribute( 'position' );
		const uv = outer!.geometry.getAttribute( 'uv' );
		for ( let at = 0; at < position.count; at++ ) {
			const [ u, v ] = photoUv(
				position.getX( at ),
				position.getZ( at ),
				27,
				{ x: 625, y: 625, size: 8750 }
			);
			expect( uv.getX( at ) ).toBeCloseTo( u, 6 );
			expect( uv.getY( at ) ).toBeCloseTo( v, 6 );
		}
		const us = Array.from( { length: uv.count }, ( _, at ) =>
			uv.getX( at )
		);
		expect( Math.max( ...us ) ).toBeCloseTo( 0.9375, 3 );
		expect( Math.min( ...us ) ).toBeCloseTo( 0.0625, 3 );
	} );

	it( 'turns the wheels on the far side half round, so each outer half faces out and reads the right way round', () => {
		const made = faced();
		const four = ( wheels( made, { load: loaded() } )?.children ??
			[] ) as Group[];
		made.wheels!.places.forEach( ( place, at ) => {
			const one = four[ at ]!;
			expect( one.rotation.z ).toBeCloseTo(
				( place[ 1 ] ?? 0 ) > 0 ? Math.PI : 0,
				9
			);
			expect( one.position.toArray() ).toEqual( spot( place ) );
		} );
		const box = new Box3().setFromObject( four[ 0 ]! );
		expect( box.getSize( new Vector3() ).y ).toBeCloseTo( 32, 3 );
	} );
} );

describe( 'risers', () => {
	it( 'is none without a riser, a truck, or any thickness', () => {
		expect( risers( scene() ) ).toBeNull();
		expect(
			risers( {
				...scene(),
				risers: { part: 9, thickness: 3175 },
				trucks: null,
			} )
		).toBeNull();
		expect(
			risers( { ...scene(), risers: { part: 9, thickness: 0 } } )
		).toBeNull();
	} );

	it( 'puts a pad the baseplate’s size under the deck at each truck', () => {
		const group = risers( {
			...scene(),
			risers: { part: 9, thickness: 3175 },
		} );
		expect( group?.userData ).toEqual( { slot: 'risers', part: 9 } );
		const two = meshes( group );
		expect( two ).toHaveLength( 2 );
		expect(
			( two[ 0 ]!.geometry as BoxGeometry ).parameters
		).toMatchObject( { width: 70, height: 55 } );
		expect(
			( two[ 0 ]!.geometry as BoxGeometry ).parameters.depth
		).toBeCloseTo( 3.175, 6 );
		expect( two[ 0 ]!.position.z ).toBeCloseTo( -11 - 3.175 / 2, 6 );
		expect( two[ 1 ]!.position.x ).toBeCloseTo( -207.963, 6 );
		expect( look( two[ 0 ] ).color.getHexString() ).toBe(
			COLOURS.riser.slice( 1 )
		);
	} );
} );

describe( 'bolts', () => {
	it( 'is none without bolts', () => {
		expect( bolts( { ...scene(), bolts: null } ) ).toBeNull();
	} );

	it( 'puts a head on the deck’s top and a shank down through it at each hole', () => {
		const board = scene();
		const group = bolts( board );
		expect( group?.userData ).toEqual( { slot: 'bolts', part: 404 } );
		const parts = meshes( group );
		expect( parts ).toHaveLength( 16 );
		const [ head, shank ] = parts;
		const top = surface( board.deck, 180.975, 20.638 );
		expect( head!.position.toArray() ).toEqual( [
			180.975,
			20.638,
			top + BOLT.rise / 2,
		] );
		expect( shank!.position.z ).toBeCloseTo( top - 25.4 / 2, 6 );
		expect( head!.rotation.x ).toBeCloseTo( Math.PI / 2, 9 );
		expect( shank!.rotation.x ).toBeCloseTo( Math.PI / 2, 9 );
		expect(
			( shank!.geometry as CylinderGeometry ).parameters
		).toMatchObject( { radiusTop: BOLT.shank, radialSegments: 8 } );
		expect(
			( shank!.geometry as CylinderGeometry ).parameters.height
		).toBeCloseTo( 25.4, 9 );
		expect(
			( head!.geometry as CylinderGeometry ).parameters
		).toMatchObject( { radiusTop: BOLT.head, height: BOLT.rise } );
		expect( look( head ).color.getHexString() ).toBe(
			COLOURS.bolt.slice( 1 )
		);
		expect( look( head ).metalness ).toBe( 0.7 );
	} );
} );

describe( 'part', () => {
	it( 'makes each slot’s group', () => {
		const full = { ...scene(), risers: { part: 9, thickness: 3175 } };
		for ( const slot of SLOTS ) {
			expect( part( slot, full, none )?.name ).toBe( slot );
		}
	} );
} );

describe( 'drawnFrom', () => {
	it( 'changes for a part’s own change, and for those it stands on, and no other', () => {
		const before = scene();
		const after = scene();
		after.wheels!.color = '#000000';
		const changed = SLOTS.filter(
			( slot ) => drawnFrom( slot, before ) !== drawnFrom( slot, after )
		);
		expect( changed ).toEqual( [ 'wheels' ] );
		const thicker = scene();
		thicker.deck.thickness = 12000;
		expect(
			SLOTS.filter(
				( slot ) =>
					drawnFrom( slot, before ) !== drawnFrom( slot, thicker )
			)
		).toEqual( [ 'deck', 'trucks', 'risers', 'bolts' ] );
		const gripped = { ...scene(), grip: { part: 5, texture: null } };
		expect(
			SLOTS.filter(
				( slot ) =>
					drawnFrom( slot, before ) !== drawnFrom( slot, gripped )
			)
		).toEqual( [ 'deck' ] );
		const raised = { ...scene(), risers: { part: 9, thickness: 3175 } };
		expect(
			SLOTS.filter(
				( slot ) =>
					drawnFrom( slot, before ) !== drawnFrom( slot, raised )
			)
		).toEqual( [ 'trucks', 'risers' ] );
		const other = scene();
		other.trucks!.axle = 215000;
		expect(
			SLOTS.filter(
				( slot ) =>
					drawnFrom( slot, before ) !== drawnFrom( slot, other )
			)
		).toEqual( [ 'trucks', 'risers' ] );
		const longer = scene();
		longer.bolts!.length = 30000;
		expect(
			SLOTS.filter(
				( slot ) =>
					drawnFrom( slot, before ) !== drawnFrom( slot, longer )
			)
		).toEqual( [ 'bolts' ] );
	} );

	it( 'reads a missing slot as empty', () => {
		const bare = { deck: scene().deck };
		const empty = {
			deck: scene().deck,
			trucks: null,
			wheels: null,
			risers: null,
			bolts: null,
			grip: null,
		};
		for ( const slot of SLOTS ) {
			expect( drawnFrom( slot, bare ) ).toBe( drawnFrom( slot, empty ) );
		}
	} );
} );
