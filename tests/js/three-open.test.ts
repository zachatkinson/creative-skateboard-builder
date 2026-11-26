/**
 * The 3D view's chunk opening on a renderer (1.4.0, #139), with three's WebGL renderer, lighting and loader as doubles,
 * since jsdom has no WebGL: the renderer on the server's canvas, the room's lighting through PMREM, the shop's own
 * textures alone, and the probe only where a browser test turns it on.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { NeutralToneMapping, SRGBColorSpace, Texture } from 'three';
import { scene } from './support/scene';

const made = vi.hoisted( () => ( {
	renderers: [] as Record< string, unknown >[],
	generators: [] as {
		fromScene: ReturnType< typeof vi.fn >;
		dispose: ReturnType< typeof vi.fn >;
	}[],
	loads: [] as [ string, () => void ][],
	textures: [] as Texture[],
	ratios: [] as number[],
	rooms: 0,
} ) );

vi.mock( 'three', async ( original ) => {
	const three = await original< typeof import( 'three' ) >();
	class WebGLRenderer {
		domElement: HTMLCanvasElement;
		outputColorSpace = '';
		toneMapping = 0;
		info = {
			render: { frame: 0 },
			memory: { geometries: 0, textures: 0 },
		};
		options: unknown;
		constructor( options: { canvas: HTMLCanvasElement } ) {
			this.domElement = options.canvas;
			this.options = options;
			made.renderers.push( this as unknown as Record< string, unknown > );
		}
		render() {
			this.info.render.frame++;
		}
		setPixelRatio( ratio: number ) {
			made.ratios.push( ratio );
		}
		setSize() {}
		dispose() {}
	}
	class PMREMGenerator {
		target = { texture: new three.Texture(), dispose: vi.fn() };
		fromScene = vi.fn( () => this.target );
		dispose = vi.fn();
		constructor() {
			made.generators.push( this );
		}
	}
	class TextureLoader {
		load( url: string, done: () => void ) {
			made.loads.push( [ url, done ] );
			const texture = new three.Texture();
			made.textures.push( texture as unknown as Texture );
			return texture;
		}
	}
	return { ...three, WebGLRenderer, PMREMGenerator, TextureLoader };
} );

vi.mock( 'three/addons/environments/RoomEnvironment.js', async () => {
	const three = await import( 'three' );
	return {
		RoomEnvironment: class extends three.Scene {
			constructor() {
				super();
				made.rooms++;
			}
			override dispose() {
				made.rooms--;
			}
		},
	};
} );

const { open } = await import( '../../assets/src/builder/three/index' );

beforeEach( () => {
	made.renderers.length = 0;
	made.generators.length = 0;
	made.loads.length = 0;
	made.textures.length = 0;
	made.ratios.length = 0;
	made.rooms = 0;
	vi.useFakeTimers( {
		toFake: [
			'requestAnimationFrame',
			'cancelAnimationFrame',
			'setTimeout',
			'clearTimeout',
			'performance',
		],
	} );
} );

afterEach( () => {
	vi.useRealTimers();
	document.documentElement.removeAttribute( 'data-cbuild-probe' );
	delete ( window as Window & { cbuildProbe?: unknown } ).cbuildProbe;
	Reflect.deleteProperty( window.navigator, 'deviceMemory' );
} );

/**
 * The view opened on a canvas of its own.
 */
function opened() {
	const canvas = document.createElement( 'canvas' );
	document.body.append( canvas );
	const failed = vi.fn();
	const view = open( { canvas, reduced: () => false, failed } );
	return { canvas, failed, view };
}

describe( 'open', () => {
	it( 'draws on the server’s canvas with a WebGL 2 renderer in sRGB', () => {
		const { canvas } = opened();
		const [ renderer ] = made.renderers;
		expect( renderer!.options ).toEqual( {
			canvas,
			antialias: true,
			powerPreference: 'low-power',
		} );
		expect( renderer!.outputColorSpace ).toBe( SRGBColorSpace );
		expect( renderer!.toneMapping ).toBe( NeutralToneMapping );
	} );

	it( 'lights the room through PMREM after the first frame, and gives it back on close', () => {
		const { view } = opened();
		view.show( scene(), false );
		vi.advanceTimersToNextFrame();
		expect( made.generators ).toHaveLength( 0 );
		vi.advanceTimersByTime( 1 );
		const [ generator ] = made.generators;
		expect( generator!.fromScene ).toHaveBeenCalledWith(
			expect.any( Object ),
			0.04
		);
		expect( made.rooms ).toBe( 0 );
		view.close();
		expect( generator!.dispose ).toHaveBeenCalled();
	} );

	it( 'loads the shop’s own textures in sRGB, and draws once each arrives', () => {
		const { view } = opened();
		const textured = scene();
		textured.deck.texture = {
			top: 'https://cdn.example/top.png',
			bottom: window.location.origin + '/graphic.png',
		};
		view.show( textured, false );
		expect( made.loads.map( ( [ url ] ) => url ) ).toEqual( [
			window.location.origin + '/graphic.png',
		] );
		vi.advanceTimersToNextFrame();
		const [ renderer ] = made.renderers as unknown as {
			info: { render: { frame: number } };
		}[];
		expect( renderer!.info.render.frame ).toBe( 1 );
		made.loads[ 0 ]![ 1 ]();
		vi.advanceTimersToNextFrame();
		expect( renderer!.info.render.frame ).toBe( 2 );
	} );

	it( 'gives a texture its colour space and a sharper angle', () => {
		const { view } = opened();
		const textured = scene();
		textured.deck.texture = { top: null, bottom: '/graphic.png' };
		view.show( textured, false );
		const [ texture ] = made.textures;
		expect( texture!.colorSpace ).toBe( SRGBColorSpace );
		expect( texture!.anisotropy ).toBe( 4 );
	} );

	it( 'caps the pixel ratio on a device with little memory', () => {
		Object.defineProperty( window, 'devicePixelRatio', {
			configurable: true,
			value: 3,
		} );
		opened();
		expect( made.ratios.at( -1 ) ).toBe( 2 );
		Object.defineProperty( window.navigator, 'deviceMemory', {
			configurable: true,
			value: 2,
		} );
		opened();
		expect( made.ratios.at( -1 ) ).toBe( 1.5 );
		Object.defineProperty( window, 'devicePixelRatio', {
			configurable: true,
			value: 1,
		} );
	} );

	it( 'sets the probe only where a browser test turns it on', () => {
		const { view } = opened();
		expect(
			( window as Window & { cbuildProbe?: unknown } ).cbuildProbe
		).toBeUndefined();
		document.documentElement.setAttribute( 'data-cbuild-probe', '' );
		const probed = opened();
		expect(
			( window as Window & { cbuildProbe?: unknown } ).cbuildProbe
		).toBe( probed.view );
		expect( probed.view ).not.toBe( view );
	} );
} );
