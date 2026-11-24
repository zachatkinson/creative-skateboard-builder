/**
 * #94: the panel's own scroll while the builder's regions hydrate. Hydrating a region takes its items out and puts them
 * back, and WebKit cuts the panel's scroll back to the top in between; the store puts it back until the builder is
 * ready, and from then on lets the panel scroll as the store moves it.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setElement, stores } from './support/interactivity';
import { NAME } from '../../assets/src/builder/view';

const { callbacks } = stores.get( NAME )!.definition as {
	callbacks: { ready: () => void };
};

/**
 * A panel whose scroll can be set and read, as jsdom lays nothing out.
 *
 * @param panel The panel.
 */
function scrollable( panel: HTMLElement ): void {
	let top = 0;
	Object.defineProperty( panel, 'scrollTop', {
		configurable: true,
		get: () => top,
		set: ( value: number ) => {
			top = value;
		},
	} );
}

/**
 * The shopper scrolls a panel to a place.
 *
 * @param panel The panel.
 * @param top   Where to.
 */
function scroll( panel: HTMLElement, top: number ): void {
	panel.scrollTop = top;
	panel.dispatchEvent( new Event( 'scroll' ) );
}

/**
 * Hydration takes an item out and puts it back, and the browser cuts the scroll to the top in between.
 *
 * @param panel The panel.
 */
async function hydrate( panel: HTMLElement ): Promise< void > {
	const item = panel.querySelector( 'li' )!;
	const list = item.parentElement!;
	item.remove();
	panel.scrollTop = 0;
	list.append( item );
	// The store's watch runs once the change's task is done.
	await Promise.resolve();
}

/**
 * Runs the ready callback, as the bar's region does once hydrated, on a builder.
 *
 * @param builder The builder.
 */
function ready( builder: HTMLElement ): void {
	setElement( builder.querySelector< HTMLElement >( '.cbuild-bar' ) );
	callbacks.ready();
}

describe( "the panel's own scroll while the builder hydrates", () => {
	let builders: [ HTMLElement, HTMLElement ];
	let panels: [ HTMLElement, HTMLElement, HTMLElement ];

	beforeEach( () => {
		const one =
			'<div class="cbuild"><div class="cbuild__body"><ul><li>8.25″</li></ul></div>' +
			'<div class="cbuild-bar"></div></div>';
		document.body.innerHTML =
			one +
			one +
			'<div class="cbuild__body"><ul><li>Elsewhere</li></ul></div>';
		const found = ( selector: string ): HTMLElement[] => [
			...document.querySelectorAll< HTMLElement >( selector ),
		];
		builders = found( '.cbuild' ) as typeof builders;
		panels = found( '.cbuild__body' ) as typeof panels;
		panels.forEach( scrollable );
	} );

	afterEach( () => {
		builders.forEach( ready );
		setElement( null );
		document.body.innerHTML = '';
	} );

	it( 'puts a panel the shopper scrolled back where they left it', async () => {
		scroll( panels[ 0 ], 447 );
		await hydrate( panels[ 0 ] );
		expect( panels[ 0 ].scrollTop ).toBe( 447 );
	} );

	it( 'keeps the last place the shopper left it', async () => {
		scroll( panels[ 0 ], 447 );
		scroll( panels[ 0 ], 120 );
		await hydrate( panels[ 0 ] );
		expect( panels[ 0 ].scrollTop ).toBe( 120 );
	} );

	it( 'leaves a panel alone once its builder is ready', async () => {
		scroll( panels[ 0 ], 447 );
		ready( builders[ 0 ] );
		expect( builders[ 0 ].hasAttribute( 'data-ready' ) ).toBe( true );
		await hydrate( panels[ 0 ] );
		expect( panels[ 0 ].scrollTop ).toBe( 0 );
	} );

	it( 'notes no scroll of a ready builder', async () => {
		ready( builders[ 0 ] );
		scroll( panels[ 0 ], 447 );
		await hydrate( panels[ 0 ] );
		expect( panels[ 0 ].scrollTop ).toBe( 0 );
	} );

	it( 'keeps another builder’s panel until that one is ready', async () => {
		scroll( panels[ 0 ], 447 );
		scroll( panels[ 1 ], 300 );
		ready( builders[ 0 ] );
		await hydrate( panels[ 0 ] );
		await hydrate( panels[ 1 ] );
		expect( panels[ 0 ].scrollTop ).toBe( 0 );
		expect( panels[ 1 ].scrollTop ).toBe( 300 );
	} );

	it( 'watches a panel again once scrolled after every builder was ready', async () => {
		ready( builders[ 0 ] );
		ready( builders[ 1 ] );
		builders[ 1 ].removeAttribute( 'data-ready' );
		scroll( panels[ 1 ], 300 );
		await hydrate( panels[ 1 ] );
		expect( panels[ 1 ].scrollTop ).toBe( 300 );
	} );

	it( 'watches a panel once, however often it is scrolled', () => {
		const observe = vi.spyOn( MutationObserver.prototype, 'observe' );
		scroll( panels[ 0 ], 447 );
		scroll( panels[ 0 ], 120 );
		expect( observe ).toHaveBeenCalledTimes( 1 );
		observe.mockRestore();
	} );

	it( 'stops watching once every scrolled builder is ready', () => {
		const disconnect = vi.spyOn( MutationObserver.prototype, 'disconnect' );
		scroll( panels[ 0 ], 447 );
		scroll( panels[ 1 ], 300 );
		ready( builders[ 0 ] );
		expect( disconnect ).not.toHaveBeenCalled();
		ready( builders[ 1 ] );
		expect( disconnect ).toHaveBeenCalledTimes( 1 );
		disconnect.mockRestore();
	} );

	it( 'leaves a scroll outside a builder alone', async () => {
		scroll( panels[ 2 ], 447 );
		await hydrate( panels[ 2 ] );
		expect( panels[ 2 ].scrollTop ).toBe( 0 );
	} );

	it( 'leaves a scroll of the page alone', async () => {
		document.dispatchEvent( new Event( 'scroll' ) );
		scroll( panels[ 0 ], 0 );
		await hydrate( panels[ 0 ] );
		expect( panels[ 0 ].scrollTop ).toBe( 0 );
	} );

	it( 'marks nothing ready outside a builder', () => {
		setElement( null );
		expect( () => callbacks.ready() ).not.toThrow();
	} );
} );
