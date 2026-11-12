/**
 * The phone bar's place among a theme's layout (D-163): an ancestor's `overflow: hidden` becomes `clip`, so the bar
 * can stick, and a theme's own bar fixed to the foot of the screen is measured, so the builder's sits above it. The
 * browser test runs both on Storefront, which does both.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fit, taken, unclip } from '../../assets/src/builder/bar';

/**
 * A page: the builder, with its bar, inside two wrappers.
 *
 * @param outer The outer wrapper's style.
 * @param inner The inner wrapper's style.
 */
function page( outer: string, inner: string ) {
	document.body.innerHTML = `<div id="outer" style="${ outer }"><div id="inner" style="${ inner }"><div class="cbuild"><div class="cbuild-bar"></div></div></div></div><nav id="theme-bar"></nav>`;
	return {
		root: document.querySelector( '.cbuild' ) as HTMLElement,
		outer: document.getElementById( 'outer' ) as HTMLElement,
		inner: document.getElementById( 'inner' ) as HTMLElement,
		theme: document.getElementById( 'theme-bar' ) as HTMLElement,
	};
}

/**
 * Give an element a box on the screen.
 *
 * @param node   The element.
 * @param top    Its top.
 * @param height Its height.
 */
function box( node: Element, top: number, height: number ) {
	vi.spyOn( node, 'getBoundingClientRect' ).mockReturnValue(
		DOMRect.fromRect( { x: 0, y: top, width: 390, height } )
	);
}

afterEach( () => {
	vi.restoreAllMocks();
	document.documentElement.removeAttribute( 'style' );
	document.body.removeAttribute( 'style' );
} );

describe( 'unclip', () => {
	it( "turns an ancestor's hidden overflow to clip, each axis as it was, and keeps a block's formatting context", () => {
		const { root, outer, inner } = page(
			'overflow-x: hidden',
			'display: flex; overflow: hidden'
		);
		unclip( root );
		expect( outer.style.overflowX ).toBe( 'clip' );
		expect( outer.style.overflowY ).toBe( '' );
		expect( outer.style.display ).toBe( 'flow-root' );
		expect( inner.style.overflowX ).toBe( 'clip' );
		expect( inner.style.overflowY ).toBe( 'clip' );
		expect( inner.style.display ).toBe( 'flex' );
	} );

	it( 'leaves an ancestor that clips nothing, or scrolls, as it is, and the page root too', () => {
		const { root, outer, inner } = page( '', 'overflow-y: auto' );
		document.documentElement.style.overflowX = 'hidden';
		unclip( root );
		expect( outer.getAttribute( 'style' ) ).toBe( '' );
		expect( inner.getAttribute( 'style' ) ).toBe( 'overflow-y: auto' );
		expect( document.documentElement.style.overflowX ).toBe( 'hidden' );
	} );
} );

describe( 'taken', () => {
	it( "measures the tallest fixed bar of the theme's at the foot of the screen, by its own box or an ancestor's", () => {
		const { root, theme } = page( '', '' );
		theme.style.position = 'fixed';
		theme.innerHTML = '<a id="link">Cart</a>';
		const link = document.getElementById( 'link' ) as HTMLElement;
		box( theme, window.innerHeight - 67.5, 67.5 );
		document.elementsFromPoint = vi.fn( () => [
			link,
			root,
			document.body,
		] );
		expect( taken( root ) ).toBe( 68 );
		expect( document.elementsFromPoint ).toHaveBeenCalledWith(
			window.innerWidth * 0.5,
			window.innerHeight - 1
		);
	} );

	it( "counts nothing of the builder's own, nothing that is not fixed, nothing above the foot and nothing half the screen tall", () => {
		const { root, theme, outer } = page( '', '' );
		const bar = root.querySelector( '.cbuild-bar' ) as HTMLElement;
		bar.style.position = 'fixed';
		box( bar, window.innerHeight - 60, 60 );
		box( outer, window.innerHeight - 60, 60 );
		theme.style.position = 'fixed';
		box( theme, 0, window.innerHeight );
		document.elementsFromPoint = vi.fn( () => [ bar, outer, theme ] );
		expect( taken( root ) ).toBe( 0 );

		box( theme, window.innerHeight - 100, 90 );
		expect( taken( root ) ).toBe( 0 );
	} );
} );

describe( 'fit', () => {
	it( "sets the theme's bar's height on the page while the builder's bar is shown, and takes it away after", () => {
		const { root, theme, outer } = page( 'overflow-x: hidden', '' );
		const bar = root.querySelector( '.cbuild-bar' ) as HTMLElement;
		const shown = vi
			.spyOn( bar, 'getClientRects' )
			.mockReturnValue( [ new DOMRect() ] as unknown as DOMRectList );
		theme.style.position = 'fixed';
		box( theme, window.innerHeight - 50, 50 );
		document.elementsFromPoint = vi.fn( () => [ theme ] );
		fit( root );
		expect(
			document.documentElement.style.getPropertyValue(
				'--cbuild-bar-offset'
			)
		).toBe( '50px' );
		expect( outer.style.overflowX ).toBe( 'clip' );

		document.elementsFromPoint = vi.fn( () => [] );
		fit( root );
		expect(
			document.documentElement.style.getPropertyValue(
				'--cbuild-bar-offset'
			)
		).toBe( '' );

		document.documentElement.style.setProperty(
			'--cbuild-bar-offset',
			'50px'
		);
		shown.mockReturnValue( [] as unknown as DOMRectList );
		fit( root );
		expect(
			document.documentElement.style.getPropertyValue(
				'--cbuild-bar-offset'
			)
		).toBe( '' );
	} );
} );
