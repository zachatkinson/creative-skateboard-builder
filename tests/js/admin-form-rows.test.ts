/**
 * The admin kit's lists (docs/ADMIN-KIT.md): a list's rows as DataForm's data holds them, the moves the table makes,
 * and what the form posts for them. The server's own controls for a list are its no-script form; while the kit is
 * mounted they are turned off and the kit posts the rows as they stand, each under the list's name, its place and the
 * column's name, so the server reads the same names either way.
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
	added,
	moved,
	post,
	release,
	removed,
	replaced,
	restored,
	rowsOf,
	type ListDescription,
} from '../../assets/src/admin/form/rows';

const list: ListDescription = {
	id: 'guide',
	name: 'cbuild[guide]',
	label: 'Width guide',
	type: 'rows',
	value: '',
	max: 3,
	ordered: true,
	columns: [
		{
			id: 'size',
			name: '[size]',
			label: 'Shoe size',
			type: 'text',
			value: '',
		},
		{
			id: 'from',
			name: '[widths][from]',
			label: 'From',
			type: 'number',
			value: '',
		},
		{
			id: 'kept',
			name: '[kept]',
			label: 'Kept',
			type: 'boolean',
			value: '',
		},
	],
	rows: [
		{ size: '8', from: '8.25', kept: '1' },
		{ size: '10', from: 'wide', kept: '' },
	],
	words: {
		add: 'Add a row',
		edit: 'Edit the row',
		empty: 'None yet.',
		full: 'Full.',
	},
};

const a = { size: 'a' };
const b = { size: 'b' };
const c = { size: 'c' };

describe( "a list's rows", () => {
	it( 'are typed by their columns, as single fields are', () => {
		expect( rowsOf( list ) ).toEqual( [
			{ size: '8', from: 8.25, kept: true },
			{ size: '10', from: 'wide', kept: false },
		] );
	} );

	it( 'move up and down, and stay where they are at either end', () => {
		expect( moved( [ a, b, c ], 1, -1 ) ).toEqual( [ b, a, c ] );
		expect( moved( [ a, b, c ], 1, 1 ) ).toEqual( [ a, c, b ] );
		expect( moved( [ a, b, c ], 0, -1 ) ).toEqual( [ a, b, c ] );
		expect( moved( [ a, b, c ], 2, 1 ) ).toEqual( [ a, b, c ] );
	} );

	it( 'are removed, and restored to the place they left', () => {
		const rows = [ a, b, c ];
		expect( removed( rows, 1 ) ).toEqual( [ a, c ] );
		expect( restored( removed( rows, 1 ), b, 1 ) ).toEqual( rows );
		expect( restored( [ a ], c, 5 ) ).toEqual( [ a, c ] );
		expect( rows ).toEqual( [ a, b, c ] );
	} );

	it( 'are added at the end up to the limit, and replaced in place', () => {
		expect( added( [ a ], b, 3 ) ).toEqual( [ a, b ] );
		expect( added( [ a, b, c ], { size: 'd' }, 3 ) ).toEqual( [ a, b, c ] );
		expect( replaced( [ a, b ], 1, c ) ).toEqual( [ a, c ] );
	} );
} );

describe( 'what a list posts', () => {
	let server: HTMLElement;
	const posted = () =>
		Array.from(
			new FormData( document.querySelector( 'form' )! ).entries()
		).filter( ( [ name ] ) => name.startsWith( 'cbuild[guide]' ) );

	beforeEach( () => {
		document.body.innerHTML = `<form><div class="cbuild-form__server"><input name="cbuild[guide][0][size]" value="8"><input name="cbuild[guide][0][widths][from]" value="8.25"><input name="cbuild[guide][1][size]" value=""><input name="cbuild[guidebook]" value="kept"></div></form>`;
		server = document.querySelector( '.cbuild-form__server' )!;
	} );
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( "turns the server's own controls off and posts each row as it stands, in order", () => {
		post( server, list, [
			{ size: '10', from: 9, kept: true },
			{ size: '8', from: '', kept: false },
		] );
		expect( posted() ).toEqual( [
			[ 'cbuild[guide][0][size]', '10' ],
			[ 'cbuild[guide][0][widths][from]', '9' ],
			[ 'cbuild[guide][0][kept]', '1' ],
			[ 'cbuild[guide][1][size]', '8' ],
			[ 'cbuild[guide][1][widths][from]', '' ],
		] );
		expect(
			server.querySelector< HTMLInputElement >(
				'[name="cbuild[guidebook]"]'
			)!.disabled
		).toBe( false );
	} );

	it( 'posts the rows again in place of the last, with a change WooCommerce hears', () => {
		let changes = 0;
		document.body.addEventListener( 'change', () => changes++ );
		post( server, list, [ { size: '10', from: 9, kept: true } ] );
		post( server, list, [] );
		expect( posted() ).toEqual( [] );
		expect( changes ).toBe( 2 );
	} );

	it( "gives the server's controls back as they were", () => {
		post( server, list, [ { size: '10', from: 9, kept: true } ] );
		release( server, list );
		expect( posted() ).toEqual( [
			[ 'cbuild[guide][0][size]', '8' ],
			[ 'cbuild[guide][0][widths][from]', '8.25' ],
			[ 'cbuild[guide][1][size]', '' ],
		] );
	} );
} );
