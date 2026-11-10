import { beforeEach, describe, expect, it, vi } from 'vitest';
import { named, open, start } from '../../assets/src/admin/part-specs';
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
		fields ? '<div class="cbuild-part-specs"></div>' : ''
	}<input class="variable_post_id" value="${ id }">`;
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
			<div id="cbuild_part_specs"></div>
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
		} as unknown as Window & { jQuery?: Query } );
		expect( ready ).toHaveLength( 1 );
		expect( seen ).toEqual( [] );
		ready[ 0 ]?.();
		expect( seen ).toEqual( [ '#cbuild_part_specs' ] );
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
		} as unknown as Window & { jQuery?: Query } );
		ready[ 0 ]?.();
		expect( heard ).toHaveLength( 1 );
	} );
} );
