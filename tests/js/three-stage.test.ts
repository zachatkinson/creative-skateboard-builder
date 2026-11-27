/**
 * The 3D view's stage (M14, #139): frames only on demand, paused off the screen and in a hidden tab, the pixel ratio
 * capped, a part change swapping its group alone, the camera's places and keys, the tricks' seam, the fallbacks and
 * everything given back on close. The renderer is a double that counts its frames; three.js's scene, camera and
 * controls are the real ones.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	BoxGeometry,
	Group,
	Mesh,
	MeshStandardMaterial,
	Texture,
	Vector3,
	type Object3D,
	type Scene as World,
} from 'three';
import {
	FOV,
	KEYS,
	MOVE,
	SWIPE,
	RATIO,
	aim,
	distance,
	ease,
	ratio,
	release,
	stage,
	type Drawing,
	type Needs,
} from '../../assets/src/builder/three/stage';
import { scene } from './support/scene';

type Seen = ( entries: { isIntersecting: boolean }[] ) => void;

let seen: Seen | undefined;
let observed: Element[] = [];
let resized: ( () => void ) | undefined;
let disconnected = 0;

/**
 * A renderer that counts its frames, and remembers the scene and camera it was last given.
 */
function drawing(): Drawing & {
	last?: { world: Object3D; camera: { position: Vector3 } };
	ratios: number[];
	sizes: [ number, number, boolean | undefined ][];
	disposed: number;
} {
	const canvas = document.createElement( 'canvas' );
	document.body.append( canvas );
	const made = {
		domElement: canvas,
		info: {
			render: { frame: 0 },
			memory: { geometries: 0, textures: 0 },
		},
		ratios: [] as number[],
		sizes: [] as [ number, number, boolean | undefined ][],
		disposed: 0,
		last: undefined as
			{ world: Object3D; camera: { position: Vector3 } } | undefined,
		render( world: Object3D, camera: { position: Vector3 } ) {
			made.info.render.frame++;
			made.last = { world, camera };
		},
		setPixelRatio( value: number ) {
			made.ratios.push( value );
		},
		setSize( width: number, height: number, style?: boolean ) {
			made.sizes.push( [ width, height, style ] );
		},
		dispose() {
			made.disposed++;
		},
	};
	return made as unknown as ReturnType< typeof drawing >;
}

/**
 * A pointer event at a place on the page: jsdom's pointer events have no page place, which the controls read of a touch.
 *
 * @param type        The event's type.
 * @param pointerId   Which pointer.
 * @param x           Across the page.
 * @param y           Down the page.
 * @param pointerType A touch or a mouse.
 */
function pointer(
	type: string,
	pointerId: number,
	x: number,
	y: number,
	pointerType: string
): PointerEvent {
	const event = new PointerEvent( type, {
		pointerId,
		button: 0,
		clientX: x,
		clientY: y,
		pointerType,
		bubbles: true,
	} );
	Object.defineProperties( event, {
		pageX: { value: x },
		pageY: { value: y },
	} );
	return event;
}

/**
 * The stage on a counting renderer.
 *
 * @param more What the test changes.
 */
function open( more: Partial< Needs > = {} ) {
	const renderer = drawing();
	const failed = vi.fn();
	const texture = vi.fn( () => null as Texture | null );
	let reduced = false;
	const shown = stage( {
		renderer,
		texture,
		reduced: () => reduced,
		failed,
		...more,
	} );
	return {
		shown,
		renderer,
		failed,
		texture,
		reduce: ( on: boolean ) => {
			reduced = on;
		},
		frames: () => renderer.info.render.frame,
		camera: () => renderer.last!.camera.position.clone(),
		board: () => renderer.last!.world.getObjectByName( 'board' )!,
	};
}

/**
 * Let a frame be drawn.
 */
function frame(): void {
	vi.advanceTimersToNextFrame();
}

beforeEach( () => {
	vi.useFakeTimers( {
		toFake: [
			'requestAnimationFrame',
			'cancelAnimationFrame',
			'setTimeout',
			'clearTimeout',
			'performance',
		],
	} );
	seen = undefined;
	observed = [];
	resized = undefined;
	disconnected = 0;
	window.IntersectionObserver = class {
		constructor( callback: Seen ) {
			seen = callback;
		}
		observe( element: Element ) {
			observed.push( element );
		}
		disconnect() {
			disconnected++;
		}
	} as unknown as typeof IntersectionObserver;
	window.ResizeObserver = class {
		constructor( callback: () => void ) {
			resized = callback;
		}
		observe( element: Element ) {
			observed.push( element );
		}
		disconnect() {
			disconnected++;
		}
	} as unknown as typeof ResizeObserver;
} );

afterEach( () => {
	vi.useRealTimers();
	document.body.innerHTML = '';
	Object.defineProperty( document, 'hidden', {
		configurable: true,
		get: () => false,
	} );
	Reflect.deleteProperty( window, 'IntersectionObserver' );
	Reflect.deleteProperty( window, 'ResizeObserver' );
} );

describe( 'ratio', () => {
	it( 'is the screen’s, at most 2', () => {
		expect( ratio( 1, undefined ) ).toBe( 1 );
		expect( ratio( 3, undefined ) ).toBe( RATIO.most );
		expect( ratio( 3, 8 ) ).toBe( 2 );
	} );

	it( 'is at most 1.5 with 4 GB of memory or less', () => {
		expect( ratio( 3, 4 ) ).toBe( 1.5 );
		expect( ratio( 3, 2 ) ).toBe( 1.5 );
		expect( ratio( 3, 4.5 ) ).toBe( 2 );
		expect( ratio( 1, 2 ) ).toBe( 1 );
	} );

	it( 'is 1 where the screen says none', () => {
		expect( ratio( 0, undefined ) ).toBe( 1 );
		expect( ratio( -1, undefined ) ).toBe( 1 );
	} );
} );

describe( 'ease', () => {
	it( 'eases in and out', () => {
		expect( ease( 0 ) ).toBe( 0 );
		expect( ease( 0.25 ) ).toBe( 0.125 );
		expect( ease( 0.5 ) ).toBe( 0.5 );
		expect( ease( 0.75 ) ).toBe( 0.875 );
		expect( ease( 1 ) ).toBe( 1 );
	} );
} );

describe( 'aim', () => {
	it( 'looks down on the top, up at the underside, and close at the graphic', () => {
		expect( aim( 'top', false ) ).toEqual( {
			from: [ 0, 1, 0.0001 ],
			at: 0,
			near: 1,
		} );
		expect( aim( 'under', true ) ).toEqual( {
			from: [ 0, -1, 0.0001 ],
			at: 0,
			near: 1,
		} );
		expect( aim( 'graphic', true ) ).toEqual( {
			from: [ 0.25, -1, 0.35 ],
			at: 0.45,
			near: 0.5,
		} );
	} );

	it( 'resets to three-quarters above for a top’s step, below for the rest', () => {
		expect( aim( 'reset', true ).from ).toEqual( [ 0.55, 0.6, 0.75 ] );
		expect( aim( 'reset', false ).from ).toEqual( [ 0.55, -0.6, 0.75 ] );
		expect( aim( 'reset', false ).at ).toBe( 0 );
		expect( aim( 'reset', false ).near ).toBe( 1 );
	} );
} );

describe( 'distance', () => {
	it( 'fits the deck in the narrower of the lens’s two angles, with a margin', () => {
		const half = Math.tan( ( FOV * Math.PI ) / 360 );
		expect( distance( 800, 2 ) ).toBeCloseTo( ( 400 * 1.2 ) / half, 6 );
		const across = Math.atan( half * 0.5 );
		expect( distance( 800, 0.5 ) ).toBeCloseTo(
			( 400 * 1.2 ) / Math.tan( across ),
			6
		);
		expect( distance( 800, 0.5 ) ).toBeGreaterThan( distance( 800, 1 ) );
	} );
} );

describe( 'release', () => {
	it( 'gives back each geometry, material and texture once, however many share it', () => {
		const shape = new BoxGeometry();
		const map = new Texture();
		const paint = new MeshStandardMaterial( { map } );
		const plain = new MeshStandardMaterial();
		// A texture whose image has not arrived is kept on its material, and given back too.
		const pending = new Texture();
		plain.userData.texture = pending;
		const group = new Group();
		group.add(
			new Mesh( shape, paint ),
			new Mesh( shape, [ paint, plain ] ),
			new Group()
		);
		const given = [
			vi.spyOn( shape, 'dispose' ),
			vi.spyOn( map, 'dispose' ),
			vi.spyOn( paint, 'dispose' ),
			vi.spyOn( plain, 'dispose' ),
			vi.spyOn( pending, 'dispose' ),
		];
		release( group );
		for ( const spy of given ) {
			expect( spy ).toHaveBeenCalledTimes( 1 );
		}
	} );
} );

describe( 'stage', () => {
	it( 'paints no ground of its own: the frame’s shows through', () => {
		const { shown, renderer } = open();
		shown.show( scene(), false );
		frame();
		expect( ( renderer.last!.world as World ).background ).toBeNull();
	} );

	it( 'draws one frame for a scene, and none at rest', () => {
		const { shown, frames } = open();
		expect( frames() ).toBe( 0 );
		shown.show( scene(), false );
		shown.show( scene(), false );
		frame();
		expect( frames() ).toBe( 1 );
		vi.advanceTimersByTime( 5000 );
		for ( let at = 0; at < 300; at++ ) {
			frame();
		}
		expect( frames() ).toBe( 1 );
	} );

	it( 'draws nothing for a view whose scene is the same, or none', () => {
		const { shown, frames } = open();
		shown.show( scene(), false );
		frame();
		shown.show( scene(), false );
		shown.show( null, false );
		shown.show( undefined, true );
		frame();
		expect( frames() ).toBe( 1 );
	} );

	it( 'draws each part as a group of its own, and leaves an empty slot out', () => {
		const { shown, board } = open();
		shown.show( scene(), false );
		frame();
		expect( board().children.map( ( child ) => child.name ) ).toEqual( [
			'deck',
			'trucks',
			'wheels',
			'bolts',
		] );
		expect( Object.keys( shown.probe().groups ) ).toEqual( [
			'deck',
			'trucks',
			'wheels',
			'bolts',
		] );
	} );

	it( 'swaps a changed part’s group alone', () => {
		const { shown, frames, board } = open();
		shown.show( scene(), false );
		frame();
		const before = shown.probe().groups;
		const old = board().getObjectByName( 'wheels' ) as Group;
		const given = vi.spyOn(
			( old.children[ 0 ] as Mesh ).geometry,
			'dispose'
		);
		const after = scene();
		after.wheels!.part = 304;
		after.wheels!.diameter = 56000;
		shown.show( after, false );
		frame();
		const now = shown.probe().groups;
		expect( now.wheels ).not.toBe( before.wheels );
		expect( now.deck ).toBe( before.deck );
		expect( now.trucks ).toBe( before.trucks );
		expect( now.bolts ).toBe( before.bolts );
		expect( given ).toHaveBeenCalled();
		expect( frames() ).toBe( 2 );
		expect( board().children ).toHaveLength( 4 );
	} );

	// F13: the bolts stand on the deck's sizes alone, so a grip or a graphic changed redraws the deck and not them.
	it( 'keeps the bolts’ group when the grip or the deck’s textures change', () => {
		const { shown } = open();
		shown.show( scene(), false );
		frame();
		const before = shown.probe().groups;
		const gripped = scene();
		gripped.grip = { part: 9001, texture: '/grip.png' };
		gripped.deck.texture = { top: '/grip.png', bottom: '/graphic.png' };
		shown.show( gripped, false );
		frame();
		const now = shown.probe().groups;
		expect( now.deck ).not.toBe( before.deck );
		expect( now.bolts ).toBe( before.bolts );
		// The deck's own sizes still move them.
		const thicker = scene();
		thicker.deck.concave += 1000;
		shown.show( thicker, false );
		frame();
		expect( shown.probe().groups.bolts ).not.toBe( now.bolts );
	} );

	it( 'takes a part out, and keeps an empty slot empty without drawing', () => {
		const { shown, frames } = open();
		shown.show( scene(), false );
		frame();
		shown.show( { ...scene(), wheels: null }, false );
		frame();
		expect( shown.probe().groups.wheels ).toBeUndefined();
		expect( frames() ).toBe( 2 );
		shown.show( { ...scene(), wheels: null }, false );
		frame();
		expect( frames() ).toBe( 2 );
	} );

	it( 'waits while the tab is hidden, and draws once it shows', () => {
		const { shown, frames } = open();
		Object.defineProperty( document, 'hidden', {
			configurable: true,
			get: () => true,
		} );
		shown.show( scene(), false );
		frame();
		expect( frames() ).toBe( 0 );
		document.dispatchEvent( new Event( 'visibilitychange' ) );
		frame();
		expect( frames() ).toBe( 0 );
		Object.defineProperty( document, 'hidden', {
			configurable: true,
			get: () => false,
		} );
		document.dispatchEvent( new Event( 'visibilitychange' ) );
		frame();
		expect( frames() ).toBe( 1 );
		document.dispatchEvent( new Event( 'visibilitychange' ) );
		frame();
		expect( frames() ).toBe( 1 );
	} );

	it( 'waits while the canvas is off the screen, and draws once it is on', () => {
		const { shown, frames, renderer } = open();
		expect( observed ).toContain( renderer.domElement );
		seen!( [ { isIntersecting: false } ] );
		shown.show( scene(), false );
		frame();
		expect( frames() ).toBe( 0 );
		seen!( [] );
		frame();
		expect( frames() ).toBe( 0 );
		seen!( [ { isIntersecting: true } ] );
		frame();
		expect( frames() ).toBe( 1 );
	} );

	it( 'stops a move that is under way while off the screen, and goes on once back', () => {
		const { shown, frames } = open();
		shown.show( scene(), false );
		frame();
		shown.look( 'top' );
		frame();
		const moving = frames();
		seen!( [ { isIntersecting: false } ] );
		vi.advanceTimersByTime( 100 );
		frame();
		frame();
		expect( frames() ).toBe( moving + 1 );
		seen!( [ { isIntersecting: true } ] );
		frame();
		expect( frames() ).toBe( moving + 2 );
	} );

	it( 'sizes the drawing to the canvas, at the capped ratio', () => {
		const { renderer } = open( { memory: 2 } );
		expect( renderer.sizes.at( -1 ) ).toEqual( [ 1, 1, false ] );
		Object.defineProperty( renderer.domElement, 'clientWidth', {
			value: 400,
		} );
		Object.defineProperty( renderer.domElement, 'clientHeight', {
			value: 300,
		} );
		Object.defineProperty( window, 'devicePixelRatio', {
			configurable: true,
			value: 3,
		} );
		resized!();
		expect( renderer.sizes.at( -1 ) ).toEqual( [ 400, 300, false ] );
		expect( renderer.ratios.at( -1 ) ).toBe( 1.5 );
		Object.defineProperty( window, 'devicePixelRatio', {
			configurable: true,
			value: 1,
		} );
	} );

	it( 'sizes nothing again for a size that has not changed', () => {
		const { renderer, shown, frames } = open();
		shown.show( scene(), false );
		frame();
		const sized = renderer.sizes.length;
		resized!();
		resized!();
		frame();
		expect( renderer.sizes ).toHaveLength( sized );
		expect( frames() ).toBe( 1 );
	} );

	it( 'fits the camera to a new shape, unless the shopper has moved it', () => {
		const { renderer, shown, camera } = open();
		shown.show( scene(), false );
		frame();
		const square = camera().length();
		Object.defineProperty( renderer.domElement, 'clientWidth', {
			configurable: true,
			value: 300,
		} );
		Object.defineProperty( renderer.domElement, 'clientHeight', {
			configurable: true,
			value: 600,
		} );
		resized!();
		frame();
		// A tall canvas is narrow: the camera stands back to fit the deck across it.
		const tall = camera().length();
		expect( tall ).toBeGreaterThan( square * 1.5 );
		renderer.domElement.dispatchEvent(
			new KeyboardEvent( 'keydown', { key: '+' } )
		);
		frame();
		const zoomed = camera();
		Object.defineProperty( renderer.domElement, 'clientWidth', {
			configurable: true,
			value: 600,
		} );
		resized!();
		frame();
		expect( camera().distanceTo( zoomed ) ).toBeLessThan( 1e-6 );
		shown.look( 'top' );
		vi.advanceTimersByTime( MOVE * 2 );
		frame();
		frame();
		const top = camera();
		Object.defineProperty( renderer.domElement, 'clientWidth', {
			configurable: true,
			value: 300,
		} );
		resized!();
		frame();
		expect( camera().y ).toBeGreaterThan( 0 );
		expect( camera().length() ).toBeGreaterThan( top.length() * 1.5 );
	} );

	it( 'lets a vertical swipe scroll the page, and pans nothing', () => {
		const { renderer } = open();
		expect( renderer.domElement.style.touchAction ).toBe( 'pan-y' );
	} );

	it( 'opens on the step’s side at once, and turns to a new side over its move', () => {
		const { shown, frames, camera } = open();
		shown.show( scene(), false );
		frame();
		expect( camera().y ).toBeLessThan( 0 );
		expect( camera().x ).toBeGreaterThan( 0 );
		shown.show( scene(), true );
		frame();
		const first = camera();
		vi.advanceTimersByTime( MOVE / 2 );
		frame();
		const half = camera();
		vi.advanceTimersByTime( MOVE );
		frame();
		expect( camera().y ).toBeGreaterThan( 0 );
		expect( half.y ).toBeGreaterThan( first.y );
		expect( half.y ).toBeLessThan( camera().y );
		const done = frames();
		frame();
		expect( frames() ).toBe( done );
	} );

	it( 'turns at once under reduced motion', () => {
		const { shown, frames, camera, reduce } = open();
		shown.show( scene(), false );
		frame();
		reduce( true );
		shown.show( scene(), true );
		frame();
		expect( camera().y ).toBeGreaterThan( 0 );
		expect( frames() ).toBe( 2 );
		frame();
		expect( frames() ).toBe( 2 );
	} );

	it( 'sends the camera to the top, the underside, the graphic and back', () => {
		const { shown, camera, reduce } = open();
		reduce( true );
		shown.show( scene(), false );
		frame();
		const reset = camera();
		shown.look( 'top' );
		frame();
		expect( camera().y ).toBeGreaterThan( 1000 );
		expect( Math.abs( camera().x ) ).toBeLessThan( 1 );
		shown.look( 'under' );
		frame();
		expect( camera().y ).toBeLessThan( -1000 );
		shown.look( 'graphic' );
		frame();
		expect( camera().length() ).toBeLessThan( reset.length() );
		expect( camera().y ).toBeLessThan( 0 );
		shown.look( 'reset' );
		frame();
		expect( camera().distanceTo( reset ) ).toBeLessThan( 1e-6 );
	} );

	it( 'ends the camera’s move once the shopper takes it', () => {
		const { shown, camera, renderer } = open();
		shown.show( scene(), false );
		frame();
		shown.look( 'top' );
		frame();
		renderer.domElement.dispatchEvent(
			new KeyboardEvent( 'keydown', { key: 'ArrowLeft' } )
		);
		frame();
		const held = camera();
		vi.advanceTimersByTime( MOVE );
		frame();
		expect( camera().distanceTo( held ) ).toBeLessThan( 1e-6 );
	} );

	it( 'turns with the arrow keys, and zooms with + and −', () => {
		const { shown, camera, renderer, frames } = open();
		shown.show( scene(), false );
		frame();
		const keys = ( key: string, more: KeyboardEventInit = {} ) => {
			const event = new KeyboardEvent( 'keydown', {
				key,
				cancelable: true,
				...more,
			} );
			renderer.domElement.dispatchEvent( event );
			frame();
			return event.defaultPrevented;
		};
		const centre = new Vector3( 0, -20, 0 );
		const away = () => camera().distanceTo( centre );
		const start = camera();
		const far = away();
		expect( keys( 'ArrowLeft' ) ).toBe( true );
		const left = camera();
		expect( left.distanceTo( start ) ).toBeGreaterThan( 1 );
		expect( left.distanceTo( centre ) ).toBeCloseTo( far, 3 );
		expect( keys( 'ArrowRight' ) ).toBe( true );
		expect( camera().distanceTo( start ) ).toBeLessThan( 1e-3 );
		const before = camera().y;
		expect( keys( 'ArrowUp' ) ).toBe( true );
		expect( camera().y ).not.toBeCloseTo( before, 3 );
		expect( keys( 'ArrowDown' ) ).toBe( true );
		expect( camera().y ).toBeCloseTo( before, 3 );
		expect( keys( '+' ) ).toBe( true );
		expect( away() ).toBeCloseTo( far / KEYS.zoom, 3 );
		expect( keys( '-' ) ).toBe( true );
		expect( away() ).toBeCloseTo( far, 3 );
		expect( keys( '=' ) ).toBe( true );
		expect( away() ).toBeCloseTo( far / KEYS.zoom, 3 );
		expect( keys( '−' ) ).toBe( true );
		expect( away() ).toBeCloseTo( far, 3 );
		const drawn = frames();
		expect( keys( 'a' ) ).toBe( false );
		expect( keys( 'ArrowLeft', { ctrlKey: true } ) ).toBe( false );
		expect( keys( 'ArrowLeft', { metaKey: true } ) ).toBe( false );
		expect( keys( 'ArrowLeft', { altKey: true } ) ).toBe( false );
		expect( frames() ).toBe( drawn );
	} );

	it( 'turns by the keys’ angles', () => {
		const { shown, camera, renderer, reduce } = open();
		reduce( true );
		shown.show( scene(), false );
		shown.look( 'top' );
		frame();
		shown.look( 'reset' );
		frame();
		const start = camera();
		renderer.domElement.dispatchEvent(
			new KeyboardEvent( 'keydown', { key: 'ArrowLeft' } )
		);
		frame();
		const flat = ( point: Vector3 ) => Math.atan2( point.x, point.z );
		expect( flat( camera() ) - flat( start ) ).toBeCloseTo( -KEYS.turn, 3 );
	} );

	it( 'lights the room after the first frame, and gives the light back on close', () => {
		const lit = new Texture();
		const given = vi.fn();
		const environment = vi.fn( () => ( { texture: lit, dispose: given } ) );
		const { shown, frames, renderer } = open( { environment } );
		shown.show( scene(), false );
		expect( environment ).not.toHaveBeenCalled();
		frame();
		expect( environment ).not.toHaveBeenCalled();
		vi.advanceTimersByTime( 1 );
		expect( environment ).toHaveBeenCalledWith( renderer );
		frame();
		expect( frames() ).toBe( 2 );
		expect(
			( renderer.last!.world as unknown as { environment: Texture } )
				.environment
		).toBe( lit );
		shown.look( 'top' );
		frame();
		vi.advanceTimersByTime( 1 );
		expect( environment ).toHaveBeenCalledTimes( 1 );
		const texture = vi.spyOn( lit, 'dispose' );
		shown.close();
		expect( given ).toHaveBeenCalled();
		expect( texture ).toHaveBeenCalled();
	} );

	it( 'lights nothing once closed, and takes no lighting', () => {
		const environment = vi.fn( () => null );
		const { shown } = open( { environment } );
		shown.show( scene(), false );
		frame();
		shown.close();
		vi.advanceTimersByTime( 1 );
		expect( environment ).not.toHaveBeenCalled();
		const unlit = open( { environment } );
		unlit.shown.show( scene(), false );
		frame();
		vi.advanceTimersByTime( 1 );
		expect( environment ).toHaveBeenCalledTimes( 1 );
		expect( unlit.failed ).not.toHaveBeenCalled();
	} );

	it( 'fails where the lighting throws', () => {
		const { shown, failed, renderer } = open( {
			environment: () => {
				throw new Error( 'shader' );
			},
		} );
		shown.show( scene(), false );
		frame();
		vi.advanceTimersByTime( 1 );
		expect( failed ).toHaveBeenCalledWith( new Error( 'shader' ) );
		expect( renderer.disposed ).toBe( 1 );
	} );

	it( 'fails once and closes where the context is lost', () => {
		const { shown, failed, renderer, frames } = open();
		shown.show( scene(), false );
		frame();
		const event = new Event( 'webglcontextlost', { cancelable: true } );
		renderer.domElement.dispatchEvent( event );
		expect( event.defaultPrevented ).toBe( true );
		expect( failed ).toHaveBeenCalledTimes( 1 );
		expect( shown.probe().closed ).toBe( true );
		expect( renderer.disposed ).toBe( 1 );
		renderer.domElement.dispatchEvent( new Event( 'webglcontextlost' ) );
		expect( failed ).toHaveBeenCalledTimes( 1 );
		shown.show( scene(), true );
		frame();
		expect( frames() ).toBe( 1 );
	} );

	it( 'fails where drawing throws', () => {
		const { shown, failed, renderer } = open();
		renderer.render = () => {
			throw new Error( 'draw' );
		};
		shown.show( scene(), false );
		frame();
		expect( failed ).toHaveBeenCalledWith( new Error( 'draw' ) );
		expect( shown.probe().closed ).toBe( true );
	} );

	it( 'fails where a scene cannot be drawn', () => {
		const { shown, failed } = open();
		const broken = scene();
		( broken.deck as { holes: unknown } ).holes = null;
		shown.show( broken, false );
		expect( failed ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'loads a part’s texture, and draws once it arrives', () => {
		let arrived: ( () => void ) | undefined;
		const pending = new Texture();
		const texture = vi.fn( ( _url: string, done: () => void ) => {
			arrived = done;
			return pending;
		} );
		const { shown, frames, board } = open( { texture } );
		const textured = scene();
		textured.deck.texture = { top: null, bottom: '/graphic.png' };
		shown.show( textured, false );
		frame();
		expect( texture ).toHaveBeenCalledTimes( 1 );
		expect( texture.mock.calls[ 0 ]![ 0 ] ).toBe( '/graphic.png' );
		// F1: until the image arrives, the deck's bottom is its colour, with no map to draw black.
		const bottom = ( board().getObjectByName( 'deck' ) as Group )
			.children[ 1 ] as Mesh;
		const paint = bottom.material as MeshStandardMaterial;
		expect( paint.map ).toBeNull();
		expect( paint.color.getHexString() ).toBe( 'dcbf8f' );
		arrived!();
		frame();
		expect( frames() ).toBe( 2 );
		expect( paint.map ).toBe( pending );
		expect( paint.color.getHexString() ).toBe( 'ffffff' );
	} );

	it( 'keeps a part’s colour where its image never arrives, and gives the texture back', () => {
		const pending = new Texture();
		const given = vi.spyOn( pending, 'dispose' );
		const { shown, board } = open( { texture: () => pending } );
		const textured = scene();
		textured.deck.texture = { top: null, bottom: '/missing.png' };
		shown.show( textured, false );
		frame();
		const bottom = ( board().getObjectByName( 'deck' ) as Group )
			.children[ 1 ] as Mesh;
		const paint = bottom.material as MeshStandardMaterial;
		expect( paint.map ).toBeNull();
		expect( paint.color.getHexString() ).toBe( 'dcbf8f' );
		shown.close();
		expect( given ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'puts no texture on a part once the view has closed', () => {
		let arrived: ( () => void ) | undefined;
		const pending = new Texture();
		const { shown, board, frames } = open( {
			texture: ( _url: string, done: () => void ) => {
				arrived = done;
				return pending;
			},
		} );
		const textured = scene();
		textured.deck.texture = { top: null, bottom: '/late.png' };
		shown.show( textured, false );
		frame();
		const bottom = ( board().getObjectByName( 'deck' ) as Group )
			.children[ 1 ] as Mesh;
		shown.close();
		arrived!();
		frame();
		expect( ( bottom.material as MeshStandardMaterial ).map ).toBeNull();
		expect( frames() ).toBe( 1 );
	} );

	it( 'takes every listener it put on the canvas off it on close, the fingers’ among them', () => {
		const { shown, renderer } = open();
		shown.show( scene(), false );
		frame();
		const canvas = renderer.domElement;
		const added = vi.spyOn( canvas, 'addEventListener' );
		const taken = vi.spyOn( canvas, 'removeEventListener' );
		const window = canvas.ownerDocument.defaultView!;
		const unheld = vi.spyOn( window, 'removeEventListener' );
		shown.close();
		// The finger's drag held back on the window, ahead of the controls, is let go too.
		expect( unheld ).toHaveBeenCalledWith(
			'pointermove',
			expect.any( Function ),
			{ capture: true }
		);
		expect( added ).not.toHaveBeenCalled();
		const off = taken.mock.calls.map( ( [ type ] ) => type );
		for ( const type of [
			'keydown',
			'webglcontextlost',
			'pointerdown',
			'pointerup',
			'pointercancel',
		] ) {
			expect( off ).toContain( type );
		}
		// Each with the stage's own listener, which a finger's press no longer reaches.
		const fingers = taken.mock.calls.filter( ( [ type ] ) =>
			[ 'pointerup', 'pointercancel' ].includes( type )
		);
		expect( fingers[ 0 ]![ 1 ] ).toBe( fingers[ 1 ]![ 1 ] );
		// The press's: the stage's own, and the controls' as they are disposed.
		expect( off.filter( ( type ) => 'pointerdown' === type ) ).toHaveLength(
			2
		);
	} );

	it( 'gives everything back on close, and draws nothing after', () => {
		const { shown, renderer, frames, board } = open();
		shown.show( scene(), false );
		frame();
		const deck = board().getObjectByName( 'deck' ) as Group;
		const given = vi.spyOn(
			( deck.children[ 0 ] as Mesh ).geometry,
			'dispose'
		);
		shown.look( 'top' );
		shown.close();
		shown.close();
		expect( renderer.disposed ).toBe( 1 );
		expect( given ).toHaveBeenCalled();
		expect( disconnected ).toBe( 2 );
		expect( renderer.domElement.style.touchAction ).toBe( '' );
		expect( shown.probe() ).toMatchObject( { closed: true, groups: {} } );
		frame();
		shown.show( scene(), true );
		shown.look( 'under' );
		renderer.domElement.dispatchEvent(
			new KeyboardEvent( 'keydown', { key: 'ArrowLeft' } )
		);
		frame();
		expect( frames() ).toBe( 1 );
	} );

	it( 'reads the probe’s frames and memory from the renderer', () => {
		const { shown, renderer } = open();
		renderer.info.memory.geometries = 7;
		renderer.info.memory.textures = 3;
		expect( shown.probe() ).toEqual( {
			frames: 0,
			geometries: 7,
			textures: 3,
			groups: {},
			closed: false,
			playing: false,
			board: [ 0, 0, 0, 0, 0, 0 ],
			camera: [ 0, 0, 0, 0, 0, 0 ],
		} );
	} );

	it( 'reads the camera’s place and the point it turns about', () => {
		const { shown, camera, reduce } = open();
		reduce( true );
		shown.show( scene(), false );
		frame();
		shown.look( 'top' );
		frame();
		const [ x, y, z, ...target ] = shown.probe().camera;
		expect( [ x, y, z ] ).toEqual( camera().toArray() );
		expect( y ).toBeGreaterThan( 1000 );
		expect( target ).toEqual( [ 0, -20, 0 ] );
	} );

	it( 'reads the board’s pose and whether a trick plays', async () => {
		const { shown } = open();
		shown.show( scene(), false );
		frame();
		const played = shown.play( {
			duration: 100,
			pose: ( board: Object3D, progress: number ) => {
				board.position.set( 1, 2, 3 * progress );
				board.rotation.set( 0.5, 0.25, progress );
			},
		} );
		await Promise.resolve();
		await Promise.resolve();
		expect( shown.probe().playing ).toBe( true );
		vi.advanceTimersByTime( 50 );
		frame();
		const [ x, y, z, roll, pitch, yaw ] = shown.probe().board;
		expect( [ x, y, roll, pitch ] ).toEqual( [ 1, 2, 0.5, 0.25 ] );
		expect( z ).toBeGreaterThan( 0 );
		expect( yaw ).toBeGreaterThan( 0 );
		vi.advanceTimersByTime( 100 );
		frame();
		await played;
		expect( shown.probe().playing ).toBe( false );
		expect( shown.probe().board ).toEqual( [ 0, 0, 0, 0, 0, 0 ] );
	} );

	it( 'works without the observers', () => {
		Reflect.deleteProperty( window, 'IntersectionObserver' );
		Reflect.deleteProperty( window, 'ResizeObserver' );
		const { shown, frames } = open();
		shown.show( scene(), false );
		frame();
		expect( frames() ).toBe( 1 );
		shown.close();
	} );
} );

describe( 'play', () => {
	const trick = ( duration = 900 ) => {
		const poses: number[] = [];
		return {
			poses,
			trick: {
				duration,
				pose: ( board: Object3D, progress: number ) => {
					poses.push( progress );
					board.position.set( 0, 0, 100 * progress );
					board.rotation.set( progress, 0, 0 );
				},
			},
		};
	};

	it( 'poses the board through the trick, then puts it back at rest', async () => {
		const { shown, board } = open();
		shown.show( scene(), false );
		frame();
		const { trick: ollie, poses } = trick();
		const played = shown.play( ollie );
		await Promise.resolve();
		await Promise.resolve();
		frame();
		vi.advanceTimersByTime( 450 );
		frame();
		expect( board().position.z ).toBeGreaterThan( 0 );
		vi.advanceTimersByTime( 900 );
		frame();
		await expect( played ).resolves.toBe( true );
		expect( poses[ 0 ] ).toBeGreaterThanOrEqual( 0 );
		expect( poses.at( -1 ) ).toBe( 1 );
		expect( poses.some( ( at ) => at > 0.4 && at < 0.6 ) ).toBe( true );
		expect( board().position.toArray() ).toEqual( [ 0, 0, 0 ] );
		expect( board().rotation.x ).toBe( 0 );
	} );

	it( 'waits for the trick before it', async () => {
		const { shown } = open();
		shown.show( scene(), false );
		frame();
		const log: string[] = [];
		const logged = ( name: string ) => ( {
			duration: 100,
			pose: ( _board: Object3D, progress: number ) =>
				log.push( `${ name } ${ progress }` ),
		} );
		const one = shown.play( logged( 'one' ) );
		const two = shown.play( logged( 'two' ) );
		for ( let at = 0; at < 10; at++ ) {
			await Promise.resolve();
			vi.advanceTimersByTime( 30 );
			frame();
		}
		await expect( one ).resolves.toBe( true );
		await expect( two ).resolves.toBe( true );
		const ended = log.indexOf( 'one 1' );
		expect( ended ).toBeGreaterThan( 0 );
		expect( log.findIndex( ( line ) => line.startsWith( 'two' ) ) ).toBe(
			ended + 1
		);
		expect( log.at( -1 ) ).toBe( 'two 1' );
	} );

	it( 'plays nothing under reduced motion, or once closed', async () => {
		const { shown, frames, reduce } = open();
		shown.show( scene(), false );
		frame();
		reduce( true );
		const { trick: ollie, poses } = trick();
		await expect( shown.play( ollie ) ).resolves.toBe( false );
		frame();
		expect( frames() ).toBe( 1 );
		expect( poses ).toEqual( [] );
		reduce( false );
		shown.close();
		await expect( shown.play( ollie ) ).resolves.toBe( false );
	} );

	it( 'ends a trick that is playing, or waiting, when the stage closes', async () => {
		const { shown, reduce } = open();
		shown.show( scene(), false );
		frame();
		const one = shown.play( trick().trick );
		const two = shown.play( trick().trick );
		await Promise.resolve();
		await Promise.resolve();
		frame();
		shown.close();
		await expect( one ).resolves.toBe( false );
		await expect( two ).resolves.toBe( false );
		const fresh = open();
		fresh.shown.show( scene(), false );
		const first = fresh.shown.play( trick( 10 ).trick );
		const later = fresh.shown.play( trick( 10 ).trick );
		await Promise.resolve();
		frame();
		vi.advanceTimersByTime( 20 );
		frame();
		reduce( true );
		fresh.reduce( true );
		await first;
		await expect( later ).resolves.toBe( false );
	} );

	/**
	 * Start a trick and let its first frame draw.
	 *
	 * @param shown    The stage.
	 * @param duration How long it lasts.
	 */
	const started = async (
		shown: ReturnType< typeof open >[ 'shown' ],
		duration = 900
	) => {
		const played = shown.play( trick( duration ).trick );
		await Promise.resolve();
		await Promise.resolve();
		frame();
		// Wrapped, or the async function would wait for the trick itself.
		return { played };
	};

	it( 'holds a camera button pressed during a trick until the board lands, then goes', async () => {
		const { shown, camera, reduce } = open();
		shown.show( scene(), false );
		frame();
		const before = camera();
		const { played } = await started( shown );
		reduce( true );
		shown.look( 'top' );
		frame();
		expect( camera().distanceTo( before ) ).toBeLessThan( 1e-6 );
		reduce( false );
		vi.advanceTimersByTime( 900 );
		frame();
		await expect( played ).resolves.toBe( true );
		vi.advanceTimersByTime( MOVE );
		frame();
		frame();
		expect( camera().y ).toBeGreaterThan( 1000 );
	} );

	it( 'goes to the last camera button pressed during a trick', async () => {
		const { shown, camera, reduce } = open();
		reduce( false );
		shown.show( scene(), false );
		frame();
		const { played } = await started( shown, 100 );
		shown.look( 'top' );
		shown.look( 'under' );
		reduce( true );
		vi.advanceTimersByTime( 100 );
		frame();
		await played;
		frame();
		expect( camera().y ).toBeLessThan( -1000 );
	} );

	it( 'turns nothing by drag or key while a trick plays, and lets the camera’s move finish', async () => {
		const { shown, camera, renderer } = open();
		shown.show( scene(), false );
		frame();
		shown.look( 'top' );
		frame();
		const { played } = await started( shown, 2 * MOVE );
		const canvas = renderer.domElement as HTMLCanvasElement & {
			setPointerCapture: () => void;
			releasePointerCapture: () => void;
		};
		canvas.setPointerCapture = () => undefined;
		canvas.releasePointerCapture = () => undefined;
		canvas.dispatchEvent(
			new PointerEvent( 'pointerdown', {
				pointerId: 1,
				button: 0,
				clientX: 10,
				clientY: 10,
				pointerType: 'mouse',
			} )
		);
		const key = new KeyboardEvent( 'keydown', {
			key: 'ArrowLeft',
			cancelable: true,
		} );
		canvas.dispatchEvent( key );
		// The key is the canvas's still, so the page does not scroll under it.
		expect( key.defaultPrevented ).toBe( true );
		vi.advanceTimersByTime( MOVE );
		frame();
		frame();
		// The camera's move went on to the top: neither the drag nor the key took it.
		expect( camera().y ).toBeGreaterThan( 1000 );
		expect( Math.abs( camera().x ) ).toBeLessThan( 1 );
		vi.advanceTimersByTime( 2 * MOVE );
		frame();
		await expect( played ).resolves.toBe( true );
		// Landed, the keys turn the camera again.
		const landed = camera();
		canvas.dispatchEvent(
			new KeyboardEvent( 'keydown', { key: 'ArrowDown' } )
		);
		frame();
		expect( camera().distanceTo( landed ) ).toBeGreaterThan( 1 );
	} );

	// F14: Chrome hands the controls a swipe's first moves before it takes the page's pan.
	it( 'turns the camera about its upright alone under one finger, and freely under two or a mouse', () => {
		const { shown, camera, renderer } = open();
		shown.show( scene(), false );
		frame();
		const canvas = renderer.domElement as HTMLCanvasElement & {
			setPointerCapture: () => void;
			releasePointerCapture: () => void;
		};
		canvas.setPointerCapture = () => undefined;
		canvas.releasePointerCapture = () => undefined;
		// The controls turn by a drag's share of the canvas's height.
		Object.defineProperty( canvas, 'clientHeight', { value: 600 } );
		const send = (
			type: string,
			pointerId: number,
			x: number,
			y: number,
			pointerType = 'touch'
		) =>
			canvas.dispatchEvent(
				pointer( type, pointerId, x, y, pointerType )
			);
		const height = (): number => camera().y;
		// One finger, down with a little drift sideways, as Chrome's swipe gives: the page's. The camera holds, its
		// turn as well as its pitch, from the first move under `SWIPE` pixels to the last.
		const before = height();
		const still = camera();
		send( 'pointerdown', 1, 100, 100 );
		send( 'pointermove', 1, 101, 100 + SWIPE - 1 );
		frame();
		expect( camera().distanceTo( still ) ).toBeLessThan( 1e-9 );
		send( 'pointermove', 1, 103, 160 );
		send( 'pointermove', 1, 160, 160 );
		frame();
		expect( camera().distanceTo( still ) ).toBeLessThan( 1e-9 );
		send( 'pointerup', 1, 160, 160 );
		// A finger that goes sideways first turns the board about its upright, at the same height, and up or down
		// after that still tilts nothing.
		send( 'pointerdown', 2, 100, 100 );
		send( 'pointermove', 2, 100 + SWIPE, 100 );
		send( 'pointermove', 2, 160, 130 );
		frame();
		expect( camera().distanceTo( still ) ).toBeGreaterThan( 1 );
		expect( height() ).toBeCloseTo( before, 6 );
		const turned = camera();
		send( 'pointermove', 2, 160, 190 );
		frame();
		expect( camera().distanceTo( turned ) ).toBeLessThan( 1e-6 );
		send( 'pointerup', 2, 160, 190 );
		// Under `SWIPE` pixels a drag is still no one's, whichever way it began, and a drag as far one way as the other
		// is the page's.
		send( 'pointerdown', 6, 100, 100 );
		send( 'pointermove', 6, 100, 102 );
		send( 'pointermove', 6, 130, 102 );
		frame();
		expect( camera().distanceTo( turned ) ).toBeGreaterThan( 1 );
		send( 'pointerup', 6, 130, 102 );
		const diagonal = camera();
		send( 'pointerdown', 7, 100, 100 );
		send( 'pointermove', 7, 100 + SWIPE, 100 + SWIPE );
		send( 'pointermove', 7, 160, 110 );
		frame();
		expect( camera().distanceTo( diagonal ) ).toBeLessThan( 1e-9 );
		send( 'pointerup', 7, 160, 110 );
		send( 'pointerdown', 1, 160, 160 );
		// The browser took the pan: the finger is gone, and a mouse's drag tilts again.
		send( 'pointercancel', 1, 160, 160 );
		send( 'pointerdown', 3, 100, 100, 'mouse' );
		send( 'pointermove', 3, 100, 160, 'mouse' );
		send( 'pointerup', 3, 100, 160, 'mouse' );
		frame();
		expect( Math.abs( height() - before ) ).toBeGreaterThan( 1 );
		// A finger lifted lets the pitch go too.
		const tilted = height();
		send( 'pointerdown', 4, 100, 100 );
		send( 'pointerup', 4, 100, 100 );
		send( 'pointerdown', 5, 100, 100, 'mouse' );
		send( 'pointermove', 5, 100, 40, 'mouse' );
		send( 'pointerup', 5, 100, 40, 'mouse' );
		frame();
		expect( Math.abs( height() - tilted ) ).toBeGreaterThan( 1 );
	} );

	it( 'lets two fingers pinch the camera in, as before', () => {
		const { shown, camera, renderer } = open();
		shown.show( scene(), false );
		frame();
		const canvas = renderer.domElement as HTMLCanvasElement & {
			setPointerCapture: () => void;
			releasePointerCapture: () => void;
		};
		canvas.setPointerCapture = () => undefined;
		canvas.releasePointerCapture = () => undefined;
		// The controls turn by a drag's share of the canvas's height.
		Object.defineProperty( canvas, 'clientHeight', { value: 600 } );
		const send = (
			type: string,
			pointerId: number,
			x: number,
			y: number
		) => canvas.dispatchEvent( pointer( type, pointerId, x, y, 'touch' ) );
		// Spread up and down, which one finger's drag would leave to the page.
		const before = camera().length();
		send( 'pointerdown', 1, 100, 150 );
		send( 'pointerdown', 2, 100, 250 );
		send( 'pointermove', 1, 100, 100 );
		send( 'pointermove', 2, 100, 300 );
		frame();
		expect( camera().length() ).toBeLessThan( before - 1 );
	} );

	it( 'lets a drag turn the camera again once the board has landed', async () => {
		const { shown, camera, renderer } = open();
		shown.show( scene(), false );
		frame();
		const { played } = await started( shown, 100 );
		vi.advanceTimersByTime( 100 );
		frame();
		await played;
		shown.look( 'top' );
		frame();
		const canvas = renderer.domElement as HTMLCanvasElement & {
			setPointerCapture: () => void;
		};
		canvas.setPointerCapture = () => undefined;
		canvas.dispatchEvent(
			new PointerEvent( 'pointerdown', {
				pointerId: 1,
				button: 0,
				clientX: 10,
				clientY: 10,
				pointerType: 'mouse',
			} )
		);
		// The drag took the camera, so its move to the top stopped where it was.
		const held = camera();
		vi.advanceTimersByTime( MOVE );
		frame();
		expect( camera().distanceTo( held ) ).toBeLessThan( 1e-6 );
		expect( camera().y ).toBeLessThan( 1000 );
	} );

	it( 'lands a trick at rest once the canvas leaves the screen, and draws no more', async () => {
		const { shown, frames, board } = open();
		shown.show( scene(), false );
		frame();
		const { played } = await started( shown );
		vi.advanceTimersByTime( 300 );
		frame();
		expect( board().position.z ).toBeGreaterThan( 0 );
		seen!( [ { isIntersecting: false } ] );
		await expect( played ).resolves.toBe( true );
		expect( board().position.toArray() ).toEqual( [ 0, 0, 0 ] );
		const off = frames();
		vi.advanceTimersByTime( 1000 );
		frame();
		frame();
		expect( frames() ).toBeLessThanOrEqual( off + 1 );
		const last = frames();
		frame();
		expect( frames() ).toBe( last );
		seen!( [ { isIntersecting: true } ] );
		frame();
		expect( frames() ).toBe( last + 1 );
	} );

	it( 'draws nothing again for leaving the screen and coming back with nothing changed', () => {
		const { shown, frames } = open();
		shown.show( scene(), false );
		frame();
		const drawn = frames();
		seen!( [ { isIntersecting: false } ] );
		seen!( [ { isIntersecting: true } ] );
		frame();
		expect( frames() ).toBe( drawn );
	} );

	it( 'sends the camera nowhere once closed', () => {
		const { shown, camera, reduce } = open();
		shown.show( scene(), false );
		frame();
		const was = camera();
		shown.close();
		// Reduced, a camera that moved would be there at once.
		reduce( true );
		shown.look( 'top' );
		expect( camera().distanceTo( was ) ).toBeLessThan( 1e-6 );
	} );

	it( 'lands a trick at rest once the tab hides', async () => {
		const { shown, board } = open();
		shown.show( scene(), false );
		frame();
		const { played } = await started( shown );
		vi.advanceTimersByTime( 300 );
		frame();
		Object.defineProperty( document, 'hidden', {
			configurable: true,
			get: () => true,
		} );
		document.dispatchEvent( new Event( 'visibilitychange' ) );
		await expect( played ).resolves.toBe( true );
		expect( board().position.toArray() ).toEqual( [ 0, 0, 0 ] );
		expect( board().rotation.x ).toBe( 0 );
	} );

	it( 'lands a trick at rest where a frame finds the canvas gone', async () => {
		const { shown, board } = open();
		shown.show( scene(), false );
		frame();
		const { played } = await started( shown );
		vi.advanceTimersByTime( 300 );
		// Seen already, but no observer has said so before the frame asks for the next.
		Object.defineProperty( document, 'hidden', {
			configurable: true,
			get: () => true,
		} );
		frame();
		await expect( played ).resolves.toBe( true );
		expect( board().position.toArray() ).toEqual( [ 0, 0, 0 ] );
	} );

	it( 'holds a trick’s pose for a test’s picture, and puts the board back', async () => {
		const { shown, board, frames } = open();
		shown.show( scene(), false );
		frame();
		const { trick: ollie, poses } = trick();
		shown.hold( ollie, 0.5 );
		frame();
		expect( poses ).toEqual( [ 0.5 ] );
		expect( board().position.z ).toBe( 50 );
		expect( frames() ).toBe( 2 );
		shown.hold( null, 0 );
		frame();
		expect( board().position.toArray() ).toEqual( [ 0, 0, 0 ] );
		expect( frames() ).toBe( 3 );
		// While a trick plays, or once closed, nothing is held.
		const { played } = await started( shown, 100 );
		shown.hold( ollie, 0.25 );
		expect( poses ).toEqual( [ 0.5 ] );
		vi.advanceTimersByTime( 100 );
		frame();
		await played;
		shown.close();
		shown.hold( ollie, 0.25 );
		expect( poses ).toEqual( [ 0.5 ] );
	} );
} );
