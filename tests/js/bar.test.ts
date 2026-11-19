/**
 * The phone bar's place among a theme's layout (D-163): an ancestor's `overflow: hidden` becomes `clip`, so the bar
 * can stick, and a theme's own bar fixed to the foot of the screen is measured, so the builder's sits above it. The
 * browser test runs both on Storefront, which does both.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fit, size, stage, taken, unclip } from '../../assets/src/builder/bar';

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

// D-272: the bar is as tall as its lines make it: the total, the estimate, and Back and Next on a row of their own
// where they do not fit beside the total. The page's scroll padding reads its height, so focus stays clear of it.
describe( 'size', () => {
	const read = () =>
		document.documentElement.style.getPropertyValue( '--cbuild-bar-size' );

	it( "sets the bar's height on the page while it sticks over the page, and takes it away when it doesn't", () => {
		const { root } = page( '', '' );
		const bar = root.querySelector( '.cbuild-bar' ) as HTMLElement;
		const shown = vi
			.spyOn( bar, 'getClientRects' )
			.mockReturnValue( [ new DOMRect() ] as unknown as DOMRectList );
		box( bar, 600, 149.5 );
		bar.style.position = 'sticky';
		size( root );
		expect( read() ).toBe( '150px' );

		// Pinned under the panel on a desktop, it is in the page's flow and covers nothing.
		bar.style.position = 'static';
		size( root );
		expect( read() ).toBe( '' );

		bar.style.position = 'sticky';
		size( root );
		shown.mockReturnValue( [] as unknown as DOMRectList );
		size( root );
		expect( read(), 'no bar shown' ).toBe( '' );
	} );
} );

// The auditor's M10 review, 1 (WCAG 2.4.11): narrower than 64rem the stage sticks at the top of the screen, so the page
// keeps focus clear of it as it does of the bar. The scroll padding reads where the stage's foot is.
describe( 'stage', () => {
	const read = () =>
		document.documentElement.style.getPropertyValue(
			'--cbuild-stage-size'
		);

	it( "sets the stage's foot on the page while it sticks, and nothing to clear when it doesn't", () => {
		const { root } = page( '', '' );
		root.insertAdjacentHTML(
			'afterbegin',
			'<div class="cbuild__stage"></div>'
		);
		const shown = root.querySelector( '.cbuild__stage' ) as HTMLElement;
		vi.spyOn( shown, 'getClientRects' ).mockReturnValue( [
			new DOMRect(),
		] as unknown as DOMRectList );
		shown.style.position = 'sticky';
		shown.style.top = '40px';
		box( shown, 40, 293.4 );
		stage( root );
		expect(
			read(),
			"a theme's header of 40 px and the stage under it"
		).toBe( '334px' );

		// At the top of the page the stage has not stuck yet: it stands under the page's title, its foot at 696 px of
		// a 720 px screen. What it covers is where its foot will be once it sticks, or the page kept no room to scroll
		// a control into (verify, 19 November: "Start from Street" behind the footer at 1280 by 720).
		box( shown, 394, 302 );
		stage( root );
		expect( read(), 'not stuck yet' ).toBe( '342px' );

		// From 64rem the stage is beside the panel and covers nothing, nor below 320 px, where it scrolls away.
		shown.style.position = 'static';
		stage( root );
		expect( read() ).toBe( '0px' );
		root.querySelector( '.cbuild__stage' )?.remove();
		stage( root );
		expect( read(), 'no stage' ).toBe( '0px' );
	} );
} );
