/**
 * The 3D view's chunk (1.4.0, #139): it loads only the shop's own textures, and a browser without WebGL 2 throws from
 * it, so the store goes back to the board.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { open, own } from '../../assets/src/builder/three/index';

afterEach( () => {
	vi.restoreAllMocks();
	document.documentElement.removeAttribute( 'data-cbuild-probe' );
} );

describe( 'own', () => {
	const page = 'https://shop.example/build/?cbuild%5Bdeck%5D=11';

	it( 'takes an address on the page’s own origin', () => {
		expect( own( 'https://shop.example/wp-content/a.png', page ) ).toBe(
			true
		);
		expect( own( '/wp-content/a.png', page ) ).toBe( true );
	} );

	it( 'refuses another origin, no address and a broken one', () => {
		expect( own( 'https://cdn.example/a.png', page ) ).toBe( false );
		expect( own( 'http://shop.example/a.png', page ) ).toBe( false );
		expect( own( 'https://shop.example:8443/a.png', page ) ).toBe( false );
		expect( own( null, page ) ).toBe( false );
		expect( own( undefined, page ) ).toBe( false );
		expect( own( '', page ) ).toBe( false );
		expect( own( 'https://[broken', page ) ).toBe( false );
		expect( own( '/a.png', 'not a page' ) ).toBe( false );
	} );
} );

describe( 'open', () => {
	it( 'throws where the browser has no WebGL 2', () => {
		document.documentElement.setAttribute( 'data-cbuild-probe', '' );
		const canvas = document.createElement( 'canvas' );
		vi.spyOn( canvas, 'getContext' ).mockReturnValue( null );
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		expect( () =>
			open( { canvas, reduced: () => false, failed: () => undefined } )
		).toThrow();
		expect(
			( window as Window & { cbuildProbe?: unknown } ).cbuildProbe
		).toBeUndefined();
	} );
} );
