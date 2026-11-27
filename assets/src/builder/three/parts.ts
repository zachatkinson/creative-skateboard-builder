/**
 * Each part of the scene as a three.js group of its own (M14, #139), in the scene's axes and in millimetres, so a part
 * change swaps one group and nothing else. The geometry is generated from the sizes in shapes.ts; no model file is
 * loaded. A part with a texture draws it, one without draws its colour, and a texture that fails to load leaves the
 * colour showing.
 */
import {
	BoxGeometry,
	BufferGeometry,
	CylinderGeometry,
	DataTexture,
	Float32BufferAttribute,
	Group,
	LatheGeometry,
	Mesh,
	MeshStandardMaterial,
	RepeatWrapping,
	SRGBColorSpace,
	Vector2,
	type Texture,
} from 'three';
import {
	axleHeight,
	centres,
	edge,
	face,
	mm,
	profile,
	spot,
	surface,
	type Face,
} from './shapes';
import type { Scene, Slot } from './scene';

/**
 * What a part asks for its textures: the texture at an address, handed to `use` once its image has arrived, or null
 * where there is none to draw. Where the image fails, `use` is never called.
 */
export interface Textures {
	load: (
		url: string | null | undefined,
		use: ( texture: Texture ) => void
	) => Texture | null;
}

/** The colours of a part with no texture, and of the parts that never have one. */
export const COLOURS = {
	maple: '#dcbf8f',
	grip: '#26272b',
	truck: '#b8bcc2',
	wheel: '#f4f1ea',
	bolt: '#2f3034',
	riser: '#1f2023',
	ply: [ '#e7cf9f', '#c9a26a' ],
} as const;

/** A baseplate's thickness, and the hanger's and the axle's radii, in millimetres: shapes simplified. */
export const TRUCK = { plate: 6, hanger: 9, axle: 4 } as const;

/** A bolt's head and shank, in millimetres. */
export const BOLT = { head: 4.5, rise: 1.2, shank: 2.4 } as const;

/**
 * A face as a geometry.
 *
 * @param shape The face's points, texture places and triangles.
 */
export function geometry( shape: Face ): BufferGeometry {
	const made = new BufferGeometry();
	made.setAttribute(
		'position',
		new Float32BufferAttribute( shape.points, 3 )
	);
	made.setAttribute( 'uv', new Float32BufferAttribute( shape.uvs, 2 ) );
	made.setIndex( shape.triangles );
	made.computeVertexNormals();
	return made;
}

/**
 * The plies' stripes for the deck's edge: a band a ply, two maple tones in turn, one pixel wide and repeated along it.
 *
 * @param plies How many plies.
 */
export function stripes( plies: number ): DataTexture {
	const count = Math.max( 1, Math.round( plies ) );
	const data = new Uint8Array( count * 4 );
	for ( let at = 0; at < count; at++ ) {
		const tone = COLOURS.ply[ at % 2 ] ?? COLOURS.maple;
		data.set( [ ...rgb( tone ), 255 ], at * 4 );
	}
	const texture = new DataTexture( data, 1, count );
	texture.colorSpace = SRGBColorSpace;
	texture.wrapS = RepeatWrapping;
	texture.needsUpdate = true;
	return texture;
}

/**
 * A colour's red, green and blue, from `#rrggbb`.
 *
 * @param hex The colour.
 */
export function rgb( hex: string ): [ number, number, number ] {
	const channel = ( at: number ): number =>
		Number.parseInt( hex.slice( at, at + 2 ), 16 );
	return [ channel( 1 ), channel( 3 ), channel( 5 ) ];
}

/**
 * A material in a texture that is ready, or in the colour where there is none.
 *
 * @param texture   The part's texture, or null.
 * @param colour    The colour without one.
 * @param roughness How rough the surface is.
 * @param metalness How metal it is.
 */
function material(
	texture: Texture | null,
	colour: string,
	roughness: number,
	metalness = 0
): MeshStandardMaterial {
	return new MeshStandardMaterial( {
		color: null === texture ? colour : '#ffffff',
		map: texture,
		roughness,
		metalness,
	} );
}

/**
 * A material in a part's colour, which takes the part's texture once its image arrives (F1): until then, and where it
 * fails, the colour shows, where a map with no image would draw black. The texture is kept on the material, so it is
 * given back with it whether or not its image came.
 *
 * @param textures  Where the texture comes from.
 * @param url       The texture's address, or none.
 * @param colour    The part's colour.
 * @param roughness How rough the surface is.
 */
function textured(
	textures: Textures,
	url: string | null | undefined,
	colour: string,
	roughness: number
): MeshStandardMaterial {
	const made = material( null, colour, roughness );
	made.userData.texture = textures.load( url, ( arrived ) => {
		made.map = arrived;
		made.color.set( '#ffffff' );
		made.needsUpdate = true;
	} );
	return made;
}

/**
 * A group named for its slot and its part, so a test can tell which was swapped.
 *
 * @param slot The slot.
 * @param id   The part's ID.
 */
function group( slot: Slot, id: number ): Group {
	const made = new Group();
	made.name = slot;
	made.userData = { slot, part: id };
	return made;
}

/**
 * The deck: its top in the grip's texture or the maple's, its bottom in its graphic, its edge in its plies.
 *
 * @param scene    The scene.
 * @param textures Where its textures come from.
 */
export function deck( scene: Scene, textures: Textures ): Group {
	const board = scene.deck;
	const made = group( 'deck', board.part );
	const gripped = null !== ( scene.grip ?? null );
	made.add(
		new Mesh(
			geometry( face( board, 'top' ) ),
			textured(
				textures,
				board.texture.top ?? scene.grip?.texture,
				gripped ? COLOURS.grip : COLOURS.maple,
				0.85
			)
		),
		new Mesh(
			geometry( face( board, 'bottom' ) ),
			textured( textures, board.texture.bottom, COLOURS.maple, 0.6 )
		),
		new Mesh(
			geometry( edge( board ) ),
			material( stripes( board.plies ), COLOURS.maple, 0.7 )
		)
	);
	return made;
}

/**
 * The trucks, each on its own holes: a baseplate under the deck and any riser, a block to the hanger, the hanger
 * across, and the axle out to its own width. The casting is simplified; the axle's width and the height are the
 * truck's.
 *
 * @param scene The scene.
 */
export function trucks( scene: Scene ): Group | null {
	const truck = scene.trucks;
	if ( ! truck ) {
		return null;
	}
	const made = group( 'trucks', truck.part );
	const metal = material( null, truck.color ?? COLOURS.truck, 0.35, 0.8 );
	const under = -(
		mm( scene.deck.thickness ) + mm( scene.risers?.thickness )
	);
	const axle = axleHeight( scene.deck, truck, scene.risers?.thickness ?? 0 );
	const plate = {
		length: mm( truck.baseplate.length ),
		width: mm( truck.baseplate.width ),
	};
	for ( const [ x, y ] of centres( truck ) ) {
		const base = new Mesh(
			new BoxGeometry( plate.length, plate.width, TRUCK.plate ),
			metal
		);
		base.position.set( x, y, under - TRUCK.plate / 2 );
		// The kingpin's block, from the baseplate down to the hanger.
		const drop = Math.max( under - TRUCK.plate - axle, 0 );
		const block = new Mesh(
			new BoxGeometry( plate.length * 0.45, plate.width * 0.6, drop ),
			metal
		);
		block.position.set( x, y, under - TRUCK.plate - drop / 2 );
		// A cylinder's axis is its y, as the axle's is the scene's.
		const hanger = new Mesh(
			new CylinderGeometry(
				TRUCK.hanger,
				TRUCK.hanger,
				mm( truck.hanger ),
				24
			),
			metal
		);
		hanger.position.set( x, y, axle );
		const rod = new Mesh(
			new CylinderGeometry(
				TRUCK.axle,
				TRUCK.axle,
				mm( truck.axle ),
				12
			),
			metal
		);
		rod.position.set( x, y, axle );
		made.add( base, block, hanger, rod );
	}
	return made;
}

/**
 * The wheels at their places: a lathe of the wheel's own profile, turned about the axle.
 *
 * @param scene    The scene.
 * @param textures Where its texture comes from.
 */
export function wheels( scene: Scene, textures: Textures ): Group | null {
	const wheel = scene.wheels;
	if ( ! wheel ) {
		return null;
	}
	const made = group( 'wheels', wheel.part );
	// A lathe turns about its y, as the axle runs along the scene's.
	const shape = new LatheGeometry(
		profile( wheel ).map( ( [ r, y ] ) => new Vector2( r, y ) ),
		48
	);
	const urethane = textured(
		textures,
		wheel.texture,
		wheel.color ?? COLOURS.wheel,
		0.55
	);
	for ( const place of wheel.places ) {
		const one = new Mesh( shape, urethane );
		one.position.set( ...spot( place ) );
		made.add( one );
	}
	return made;
}

/**
 * The risers, each the truck's baseplate's size and the riser's thickness, between the deck and the truck.
 *
 * @param scene The scene.
 */
export function risers( scene: Scene ): Group | null {
	const riser = scene.risers;
	const truck = scene.trucks;
	if ( ! riser || ! truck || riser.thickness <= 0 ) {
		return null;
	}
	const made = group( 'risers', riser.part );
	const pad = material( null, COLOURS.riser, 0.8 );
	const thick = mm( riser.thickness );
	for ( const [ x, y ] of centres( truck ) ) {
		const one = new Mesh(
			new BoxGeometry(
				mm( truck.baseplate.length ),
				mm( truck.baseplate.width ),
				thick
			),
			pad
		);
		one.position.set( x, y, -mm( scene.deck.thickness ) - thick / 2 );
		made.add( one );
	}
	return made;
}

/**
 * The bolts, a head on the deck's top at each hole and a shank its length down through it.
 *
 * @param scene The scene.
 */
export function bolts( scene: Scene ): Group | null {
	const bolt = scene.bolts;
	if ( ! bolt ) {
		return null;
	}
	const made = group( 'bolts', bolt.part );
	const steel = material( null, COLOURS.bolt, 0.4, 0.7 );
	const length = mm( bolt.length );
	const head = new CylinderGeometry( BOLT.head, BOLT.head, BOLT.rise, 16 );
	const shank = new CylinderGeometry( BOLT.shank, BOLT.shank, length, 8 );
	for ( const point of bolt.points ) {
		const [ x, y ] = spot( point );
		const z = surface( scene.deck, x, y );
		// A cylinder stands on its y; the bolt stands on the scene's z.
		const top = new Mesh( head, steel );
		top.rotation.x = Math.PI / 2;
		top.position.set( x, y, z + BOLT.rise / 2 );
		const down = new Mesh( shank, steel );
		down.rotation.x = Math.PI / 2;
		down.position.set( x, y, z - length / 2 );
		made.add( top, down );
	}
	return made;
}

/**
 * A slot's group from the scene, or null where the slot is empty.
 *
 * @param slot     The slot.
 * @param scene    The scene.
 * @param textures Where textures come from.
 */
export function part(
	slot: Slot,
	scene: Scene,
	textures: Textures
): Group | null {
	switch ( slot ) {
		case 'deck':
			return deck( scene, textures );
		case 'trucks':
			return trucks( scene );
		case 'wheels':
			return wheels( scene, textures );
		case 'risers':
			return risers( scene );
		case 'bolts':
			return bolts( scene );
	}
}

/**
 * What a slot's group is drawn from, so a view whose answer leaves a part as it was keeps its group. Each part reads
 * its own sizes, and those of the parts it stands on.
 *
 * @param slot  The slot.
 * @param scene The scene.
 */
export function drawnFrom( slot: Slot, scene: Scene ): string {
	const { deck: board } = scene;
	const of: Record< Slot, unknown[] > = {
		deck: [ board, scene.grip ?? null ],
		trucks: [ scene.trucks ?? null, board.thickness, scene.risers ?? null ],
		wheels: [ scene.wheels ?? null ],
		risers: [ scene.risers ?? null, board.thickness, scene.trucks ?? null ],
		// The deck's sizes alone: its textures change with a grip or a graphic, and the bolts stand on neither (F13).
		bolts: [ scene.bolts ?? null, { ...board, texture: null } ],
	};
	return JSON.stringify( of[ slot ] );
}
