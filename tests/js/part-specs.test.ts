import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
	enable,
	field,
	groups,
	named,
	open,
	settle,
	start,
} from '../../assets/src/admin/part-specs';
import type { Query } from '../../assets/src/admin/part-specs';

/**
 * The product screen's script (D-083, D-086): the Catalogue check's links open the tab or the variation they name.
 * jQuery here is a double with the two things the script asks of it; the browser tests run the real one
 * (tests/e2e/check.spec.ts, tests/e2e/part-specs.spec.ts).
 */

interface Heard {
	element: Element;
	event: string;
	run: () => void;
}

interface Promised {
	element: Element;
	finish: () => void;
}

function jQuery() {
	const heard: Heard[] = [];
	const promised: Promised[] = [];
	const ready: Array< () => void > = [];
	const $ = ( ( target: Element | ( () => void ) ) => {
		if ( typeof target === 'function' ) {
			ready.push( target );
			return undefined;
		}
		return {
			one: ( event: string, run: () => void ) =>
				heard.push( { element: target, event, run } ),
			promise: () => ( {
				done: ( finish: () => void ) =>
					promised.push( { element: target, finish } ),
			} ),
		};
	} ) as Query;
	return { $, heard, promised, ready };
}

// A variation's row as WooCommerce prints it, with the plugin's fields inside unless left out.
function variation(
	id: number,
	closed: boolean,
	fields = true,
	content = true
) {
	const inner = `${
		fields
			? `<div class="cbuild-part-specs"><input type="hidden" value="x"><details><summary id="first-${ id }">Deck</summary><input id="field-${ id }"></details></div>`
			: ''
	}<input type="hidden" class="variable_post_id" value="${ id }">`;
	return `<div class="woocommerce_variation wc-metabox${
		closed ? ' closed' : ''
	}" id="row-${ id }">
		<h3>#${ id }</h3>
		${ content ? `<div class="wc-metabox-content">${ inner }</div>` : inner }
	</div>`;
}

function screen( rows = '', box = true ) {
	document.body.innerHTML = `
		<ul class="wc-tabs">
			<li><a href="#general_product_data">General</a></li>
			<li><a href="#cbuild_part_specs">Part specs</a></li>
			<li><a href="#variable_product_options">Variations</a></li>
		</ul>
		<div ${ box ? 'id="woocommerce-product-data"' : '' }>
			<div id="cbuild_part_specs"><input type="hidden" value="x"><input id="panel-first"><select id="panel-second"></select><details id="group"><summary>Deck</summary><input id="cbuild-spec-deck-width"></details><select id="cbuild-spec-chart"></select></div>
			<div id="variable_product_options">${ rows }</div>
		</div>`;
}

// Every click on the page, by what was clicked.
function clicks(): string[] {
	const seen: string[] = [];
	document.body.addEventListener( 'click', ( event ) => {
		const target = event.target as HTMLElement;
		seen.push( target.getAttribute( 'href' ) ?? target.tagName );
	} );
	return seen;
}

// Where focus is, asked of the page the fixture drew.
function focus(): Element | null {
	return document.body.ownerDocument.activeElement;
}

const scrolled: Array< [ Element, unknown ] > = [];

beforeEach( () => {
	scrolled.length = 0;
	Element.prototype.scrollIntoView = function ( how?: unknown ) {
		scrolled.push( [ this, how ] );
	};
} );

describe( 'what an address names', () => {
	it( 'is the panel, a variation by its ID, or nothing of ours', () => {
		expect( named( '#cbuild_part_specs' ) ).toBe( 'panel' );
		expect( named( '#cbuild_variation_412' ) ).toBe( '412' );
		expect( named( '#cbuild_variation_0' ) ).toBe( '0' );
		for ( const hash of [
			'',
			'#',
			'#general_product_data',
			'cbuild_part_specs',
			'#cbuild_part_specs_more',
			'#cbuild_variation_',
			'#cbuild_variation_12x',
			'#cbuild_variation_x12',
			'x#cbuild_variation_12',
			'#cbuild_variation_12 ',
		] ) {
			expect( [ hash, named( hash ) ] ).toEqual( [ hash, null ] );
		}
	} );
} );

describe( 'a field of the panel', () => {
	// Issue #19: a value that was not saved is named in a notice, and the notice is a link to its field.
	it( "is named by its ID, and a variation's field or anything else is not", () => {
		expect( field( '#cbuild-spec-deck-width' ) ).toBe(
			'cbuild-spec-deck-width'
		);
		expect( field( '#cbuild-spec-chart' ) ).toBe( 'cbuild-spec-chart' );
		for ( const hash of [
			'',
			'#cbuild-spec-',
			'#cbuild-spec-3-deck-width',
			'#cbuild-spec-deck_width',
			'#cbuild-spec-deck-',
			'#cbuild-spec--deck',
			'cbuild-spec-chart',
			'#cbuild-spec-chart ',
			'x#cbuild-spec-chart',
			'#cbuild_part_specs',
		] ) {
			expect( [ hash, field( hash ) ] ).toEqual( [ hash, null ] );
		}
	} );

	it( 'has its tab and its group opened, and is scrolled and moved to', () => {
		screen();
		const seen = clicks();
		const { $, heard } = jQuery();
		const own = document.getElementById(
			'cbuild-spec-deck-width'
		) as HTMLElement;
		const focused: unknown[] = [];
		const native = own.focus.bind( own );
		own.focus = ( how?: FocusOptions ) => {
			focused.push( how );
			native( how );
		};
		open( document, '#cbuild-spec-deck-width', $ );
		expect( seen ).toEqual( [ '#cbuild_part_specs' ] );
		expect( document.getElementById( 'group' ) ).toHaveProperty(
			'open',
			true
		);
		expect( scrolled ).toEqual( [ [ own, { block: 'center' } ] ] );
		expect( focus() ).toBe( own );
		expect( focused ).toEqual( [ { preventScroll: true } ] );
		expect( heard ).toEqual( [] );
	} );

	it( 'in no group is moved to all the same', () => {
		screen();
		const { $ } = jQuery();
		open( document, '#cbuild-spec-chart', $ );
		expect( focus()?.id ).toBe( 'cbuild-spec-chart' );
		expect( document.getElementById( 'group' ) ).toHaveProperty(
			'open',
			false
		);
	} );

	it( 'that the screen does not have opens nothing', () => {
		screen();
		const seen = clicks();
		const { $ } = jQuery();
		open( document, '#cbuild-spec-wheelbase', $ );
		expect( seen ).toEqual( [] );
		expect( scrolled ).toEqual( [] );
	} );
} );

describe( 'the Part specs tab', () => {
	it( 'is opened and scrolled to when the address names it', () => {
		screen();
		const seen = clicks();
		const { $, heard } = jQuery();
		open( document, '#cbuild_part_specs', $ );
		expect( seen ).toEqual( [ '#cbuild_part_specs' ] );
		expect( scrolled ).toEqual( [
			[
				document.getElementById( 'cbuild_part_specs' ),
				{ block: 'start' },
			],
		] );
		expect( heard ).toEqual( [] );
	} );

	it( 'is left alone by an address that names nothing of ours', () => {
		screen( variation( 7, true ) );
		const seen = clicks();
		const { $, heard } = jQuery();
		open( document, '#general_product_data', $ );
		open( document, '', $ );
		expect( seen ).toEqual( [] );
		expect( scrolled ).toEqual( [] );
		expect( heard ).toEqual( [] );
	} );

	it( 'is not an error on a screen without the tab', () => {
		document.body.innerHTML = '';
		const { $ } = jQuery();
		expect( () => open( document, '#cbuild_part_specs', $ ) ).not.toThrow();
		expect( scrolled ).toEqual( [] );
	} );
} );

describe( 'a variation', () => {
	it( 'has the Variations tab opened, and waits for WooCommerce to load its rows', () => {
		screen( variation( 7, true ) );
		const seen = clicks();
		const { $, heard } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		expect( seen ).toEqual( [ '#variable_product_options' ] );
		expect( heard ).toHaveLength( 1 );
		expect( heard[ 0 ]?.element ).toBe(
			document.getElementById( 'woocommerce-product-data' )
		);
		expect( heard[ 0 ]?.event ).toBe( 'woocommerce_variations_loaded' );
		// Nothing is opened or scrolled to until the rows are there.
		expect( scrolled ).toEqual( [] );
	} );

	it( 'is opened when closed, and scrolled to once its row has finished opening', () => {
		screen(
			variation( 6, true ) + variation( 7, true ) + variation( 77, true )
		);
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		const seen = clicks();
		heard[ 0 ]?.run();
		expect( seen ).toEqual( [ 'H3' ] );
		const row = document.getElementById( 'row-7' ) as HTMLElement;
		expect( promised ).toHaveLength( 1 );
		expect( promised[ 0 ]?.element ).toBe(
			row.querySelector( '.wc-metabox-content' )
		);
		expect( scrolled ).toEqual( [] );
		promised[ 0 ]?.finish();
		expect( scrolled ).toEqual( [
			[ row.querySelector( '.cbuild-part-specs' ), { block: 'start' } ],
		] );
	} );

	it( 'with no heading to click is still scrolled to', () => {
		screen( variation( 7, true ) );
		document.querySelector( '#row-7 h3' )?.remove();
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		expect( () => heard[ 0 ]?.run() ).not.toThrow();
		promised[ 0 ]?.finish();
		expect( scrolled ).toHaveLength( 1 );
	} );

	it( 'is not clicked shut when it is already open', () => {
		screen( variation( 7, false ) );
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		const seen = clicks();
		heard[ 0 ]?.run();
		expect( seen ).toEqual( [] );
		promised[ 0 ]?.finish();
		expect( scrolled ).toHaveLength( 1 );
	} );

	it( "is scrolled to by its row when it has none of the plugin's fields", () => {
		screen( variation( 7, true, false ) );
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		heard[ 0 ]?.run();
		promised[ 0 ]?.finish();
		expect( scrolled ).toEqual( [
			[ document.getElementById( 'row-7' ), { block: 'start' } ],
		] );
	} );

	it( 'is scrolled to at once when its row has nothing that opens', () => {
		screen( variation( 7, false, true, false ) );
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		heard[ 0 ]?.run();
		expect( promised ).toEqual( [] );
		expect( scrolled ).toEqual( [
			[
				document.querySelector( '#row-7 .cbuild-part-specs' ),
				{ block: 'start' },
			],
		] );
	} );

	it( 'on a later page is left to WooCommerce: nothing is opened or scrolled to', () => {
		screen( variation( 6, true ) + variation( 77, true ) );
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		const seen = clicks();
		heard[ 0 ]?.run();
		expect( seen ).toEqual( [] );
		expect( promised ).toEqual( [] );
		expect( scrolled ).toEqual( [] );
	} );

	it( 'is nothing to do on a screen without the product data box', () => {
		screen( variation( 7, true ), false );
		const seen = clicks();
		const { $, heard } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		expect( seen ).toEqual( [] );
		expect( heard ).toEqual( [] );
	} );
} );

describe( 'focus', () => {
	// Claude in Cowork's review of milestone 3, minor 8: the links scrolled, and left focus at the top of the page.
	it( "moves to the panel's first field, past a hidden one", () => {
		screen();
		const { $ } = jQuery();
		open( document, '#cbuild_part_specs', $ );
		expect( focus()?.id ).toBe( 'panel-first' );
	} );

	it( "moves to a variation's first group once its row has opened, not before", () => {
		screen( variation( 6, true ) + variation( 7, true ) );
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		heard[ 0 ]?.run();
		expect( focus() ).toBe( document.body );
		promised[ 0 ]?.finish();
		expect( focus()?.id ).toBe( 'first-7' );
	} );

	it( 'stays where it is when what is shown has no control', () => {
		screen( variation( 7, true, false ) );
		const { $, heard, promised } = jQuery();
		open( document, '#cbuild_variation_7', $ );
		heard[ 0 ]?.run();
		expect( () => promised[ 0 ]?.finish() ).not.toThrow();
		expect( focus() ).toBe( document.body );
	} );

	it( 'does not scroll a second time', () => {
		screen();
		const focused: unknown[] = [];
		const panel = document.getElementById( 'panel-first' ) as HTMLElement;
		panel.focus = ( how?: unknown ) => {
			focused.push( how );
		};
		const { $ } = jQuery();
		open( document, '#cbuild_part_specs', $ );
		expect( focused ).toEqual( [ { preventScroll: true } ] );
	} );
} );

describe( 'the script', () => {
	it( 'waits for the page, then opens what the address names', () => {
		screen();
		const seen = clicks();
		const { $, ready } = jQuery();
		const spy = vi.fn( $ ) as unknown as Query;
		start( {
			jQuery: spy,
			document,
			location: { hash: '#cbuild_part_specs' },
			addEventListener: () => undefined,
		} as unknown as Window & { jQuery?: Query } );
		expect( ready ).toHaveLength( 1 );
		expect( seen ).toEqual( [] );
		ready[ 0 ]?.();
		expect( seen ).toEqual( [ '#cbuild_part_specs' ] );
	} );

	it( 'opens what the address names each time it changes', () => {
		screen();
		const seen = clicks();
		const { $, ready } = jQuery();
		const location = { hash: '' };
		const heard: Record< string, () => void > = {};
		start( {
			jQuery: $,
			document,
			location,
			addEventListener: ( event: string, run: () => void ) => {
				heard[ event ] = run;
			},
		} as unknown as Window & { jQuery?: Query } );
		ready[ 0 ]?.();
		expect( seen ).toEqual( [] );
		location.hash = '#cbuild-spec-deck-width';
		heard.hashchange?.();
		expect( seen ).toEqual( [ '#cbuild_part_specs' ] );
		expect( focus()?.id ).toBe( 'cbuild-spec-deck-width' );
		expect( Object.keys( heard ) ).toEqual( [ 'hashchange' ] );
	} );

	it( 'does nothing where jQuery is not loaded', () => {
		screen();
		const seen = clicks();
		expect( () =>
			start( {
				document,
				location: { hash: '#cbuild_part_specs' },
			} as unknown as Window )
		).not.toThrow();
		expect( seen ).toEqual( [] );
	} );

	it( 'hands jQuery itself on, so a variation is waited for', () => {
		screen( variation( 7, true ) );
		const { $, heard, ready } = jQuery();
		start( {
			jQuery: $,
			document,
			location: { hash: '#cbuild_variation_7' },
			addEventListener: () => undefined,
		} as unknown as Window & { jQuery?: Query } );
		ready[ 0 ]?.();
		expect( heard ).toHaveLength( 1 );
	} );
} );

// Issue #23 beside #19: a notice's link names a field in a group that is closed, whose fields are disabled.
describe( 'a field a notice names, in a closed group', () => {
	it( 'has its group opened and enabled before focus moves to it', () => {
		screen();
		const target = document.getElementById(
			'cbuild-spec-deck-width'
		) as HTMLInputElement;
		target.disabled = true;
		const { $ } = jQuery();
		open( document, '#cbuild-spec-deck-width', $ );
		expect( target.disabled ).toBe( false );
		expect( document.getElementById( 'group' ) ).toHaveProperty(
			'open',
			true
		);
		expect( focus() ).toBe( target );
	} );
} );

// Issue #23: a closed group's fields are drawn disabled, so a part with no specs adds nothing to the product's form.
describe( 'a closed group', () => {
	const drawn = () => {
		document.body.innerHTML = `
			<div class="cbuild-part-specs">
				<details id="deck"><summary>Deck</summary><input id="width" disabled><select id="pattern" disabled></select></details>
				<details id="trucks"><summary>Trucks</summary><input id="axle" disabled></details>
			</div>
			<details id="other"><summary>Another plugin's</summary><input id="theirs" disabled></details>`;
	};
	const disabled = ( id: string ) =>
		( document.getElementById( id ) as HTMLInputElement ).disabled;
	const toggle = ( id: string, isOpen: boolean ) => {
		const group = document.getElementById( id ) as HTMLDetailsElement;
		group.open = isOpen;
		// The browser's own event does not bubble; the script hears it on its way down.
		group.dispatchEvent( new Event( 'toggle' ) );
	};

	it( 'has its fields enabled when it opens, and no other group has', () => {
		drawn();
		groups( document );
		toggle( 'deck', true );
		expect( disabled( 'width' ) ).toBe( false );
		expect( disabled( 'pattern' ) ).toBe( false );
		expect( disabled( 'axle' ) ).toBe( true );
	} );

	it( 'keeps them enabled when it closes again, so what was typed is still sent', () => {
		drawn();
		groups( document );
		toggle( 'deck', true );
		toggle( 'deck', false );
		expect( disabled( 'width' ) ).toBe( false );
	} );

	it( 'is not touched while it stays closed, and nothing outside the panel is', () => {
		drawn();
		groups( document );
		toggle( 'trucks', false );
		toggle( 'other', true );
		expect( disabled( 'axle' ) ).toBe( true );
		expect( disabled( 'theirs' ) ).toBe( true );
	} );

	it( 'is heard in a variation WooCommerce loads later', () => {
		document.body.innerHTML = '';
		groups( document );
		drawn();
		toggle( 'trucks', true );
		expect( disabled( 'axle' ) ).toBe( false );
	} );

	it( 'can be enabled directly', () => {
		drawn();
		enable( document.getElementById( 'trucks' ) as Element );
		expect( disabled( 'axle' ) ).toBe( false );
		expect( disabled( 'width' ) ).toBe( true );
	} );

	it( 'is listened for when the script starts, with or without jQuery', () => {
		drawn();
		start( {
			document,
			location: { hash: '' },
		} as unknown as Window );
		toggle( 'deck', true );
		expect( disabled( 'width' ) ).toBe( false );
	} );

	// A page no other test has started the script on, so the listener is this start's own.
	it( 'is listened for by each start, on the page it is given', () => {
		const page = document.implementation.createHTMLDocument( '' );
		page.body.innerHTML =
			'<div class="cbuild-part-specs"><details id="deck"><input id="width" disabled></details></div>';
		const group = page.getElementById( 'deck' ) as HTMLDetailsElement;
		const width = page.getElementById( 'width' ) as HTMLInputElement;
		group.open = true;
		group.dispatchEvent( new Event( 'toggle' ) );
		expect( width.disabled ).toBe( true );
		start( {
			document: page,
			location: { hash: '' },
		} as unknown as Window );
		group.dispatchEvent( new Event( 'toggle' ) );
		expect( width.disabled ).toBe( false );
	} );

	it( 'is listened for from the moment the page loads the script', async () => {
		const heard = vi.spyOn( document, 'addEventListener' );
		vi.resetModules();
		await import( '../../assets/src/admin/part-specs' );
		expect( heard ).toHaveBeenCalledWith(
			'toggle',
			expect.any( Function ),
			true
		);
		heard.mockRestore();
	} );
} );

// Issue #23, seen once: the page moved after the jump, and the focused summary ended up above the viewport.
describe( 'what was scrolled to', () => {
	const view = ( height: number ) => {
		const frames: Array< () => void > = [];
		return {
			frames,
			innerHeight: height,
			requestAnimationFrame: ( run: () => void ) => frames.push( run ),
		};
	};
	const at = ( top: number, bottom: number ) => {
		const target = document.createElement( 'summary' );
		target.getBoundingClientRect = () => ( { top, bottom } ) as DOMRect;
		return target;
	};

	it( 'is checked a frame later, and brought back when it is above the viewport', () => {
		const win = view( 800 );
		const target = at( -200, -170 );
		settle( win, target );
		expect( scrolled ).toEqual( [] );
		win.frames[ 0 ]?.();
		expect( scrolled ).toEqual( [ [ target, { block: 'center' } ] ] );
	} );

	it( 'is brought back when it is below the viewport', () => {
		const win = view( 800 );
		const target = at( 790, 820 );
		settle( win, target );
		win.frames[ 0 ]?.();
		expect( scrolled ).toHaveLength( 1 );
	} );

	it( 'is left alone when it is in view, edge to edge', () => {
		const win = view( 800 );
		settle( win, at( 0, 800 ) );
		win.frames[ 0 ]?.();
		expect( scrolled ).toEqual( [] );
	} );

	it( 'is nothing to do without a window, or with nothing focused', () => {
		expect( () => settle( null, at( -5, 0 ) ) ).not.toThrow();
		const win = view( 800 );
		expect( () => settle( win, null ) ).not.toThrow();
		expect( win.frames ).toEqual( [] );
	} );

	it( 'is the field, when the address names one', () => {
		screen();
		const frames: Array< () => void > = [];
		const own = document.getElementById(
			'cbuild-spec-deck-width'
		) as HTMLElement;
		own.getBoundingClientRect = () =>
			( { top: -300, bottom: -270 } ) as DOMRect;
		const win = document.defaultView as Window;
		const real = win.requestAnimationFrame;
		win.requestAnimationFrame = ( run: FrameRequestCallback ) =>
			frames.push( () => run( 0 ) );
		const { $ } = jQuery();
		open( document, '#cbuild-spec-deck-width', $ );
		win.requestAnimationFrame = real;
		scrolled.length = 0;
		frames[ 0 ]?.();
		expect( scrolled ).toEqual( [ [ own, { block: 'center' } ] ] );
	} );

	it( 'is left as it is where the window has no frames to wait for', () => {
		expect( () =>
			settle( { innerHeight: 800 }, at( -200, -170 ) )
		).not.toThrow();
		expect( scrolled ).toEqual( [] );
	} );

	it( 'is the control focus moved to, when the address names the panel', () => {
		screen();
		const frames: Array< () => void > = [];
		const first = document.getElementById( 'panel-first' ) as HTMLElement;
		first.getBoundingClientRect = () =>
			( { top: -300, bottom: -270 } ) as DOMRect;
		const win = document.defaultView as Window;
		const real = win.requestAnimationFrame;
		win.requestAnimationFrame = ( run: FrameRequestCallback ) =>
			frames.push( () => run( 0 ) );
		const { $ } = jQuery();
		open( document, '#cbuild_part_specs', $ );
		scrolled.length = 0;
		frames[ 0 ]?.();
		win.requestAnimationFrame = real;
		expect( scrolled ).toEqual( [ [ first, { block: 'center' } ] ] );
	} );
} );
