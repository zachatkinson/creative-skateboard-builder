/**
 * The admin kit's mount (docs/ADMIN-KIT.md): it finds each form the server described, hides the server's controls
 * behind DataForm, writes each edit back into them, and holds the server's refusal of a field until that field is
 * edited. DataForm itself is drawn by a stand-in here; the browser tests draw the real one.
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
	mount,
	type Draw,
	type Drawn,
} from '../../assets/src/admin/form/mount';
// Imported with the file, not inside a test: it brings DataForm, whose first transform on a cold CI runner took longer
// than a test's 5 seconds (D-574).
import { refusals } from '../../assets/src/admin/form/draw';

const description = {
	id: 'cbuild-settings',
	fields: [
		{
			id: 'lead_days',
			name: 'cbuild[lead_days]',
			label: 'Assembly time',
			type: 'integer',
			value: '3',
		},
		{
			id: 'assembly',
			name: 'cbuild[assembly]',
			label: 'Assembly',
			type: 'boolean',
			value: '',
		},
	],
	cards: [],
	errors: { lead_days: 'Use a whole number.' },
};

let drawn: Drawn[];
let unmounted: number;
const draw: Draw = ( where, props ) => {
	drawn.push( props );
	where.dataset.drawn = 'yes';
	return {
		update: ( next ) => drawn.push( next ),
		unmount: () => unmounted++,
	};
};

beforeEach( () => {
	drawn = [];
	unmounted = 0;
	document.body.innerHTML = `<form id="post"><div class="cbuild-form" data-cbuild-form="cbuild-settings"><script type="application/json" class="cbuild-form__description">${ JSON.stringify(
		description
	) }</script><div class="cbuild-form__server"><input type="text" name="cbuild[lead_days]" value="3"><input type="checkbox" name="cbuild[assembly]" value="1"></div></div></form>`;
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

const last = (): Drawn => drawn[ drawn.length - 1 ]!;
const kit = () => document.querySelector< HTMLElement >( '.cbuild-form' )!;
const server = () =>
	document.querySelector< HTMLElement >( '.cbuild-form__server' )!;

describe( 'mounting a described form', () => {
	it( "draws DataForm in its own box before the server's controls, and hides them, which still post", () => {
		mount( kit(), draw );
		const app = kit().querySelector< HTMLElement >( '.cbuild-form__app' )!;
		expect( app.dataset.drawn ).toBe( 'yes' );
		expect( app.nextElementSibling ).toBe( server() );
		expect( server().hidden ).toBe( true );
		expect(
			document.querySelector< HTMLFormElement >( 'form' )!.noValidate
		).toBe( true );
	} );

	it( "gives DataForm the data, the fields, the form and the server's refusals", () => {
		mount( kit(), draw );
		expect( last().data ).toEqual( { lead_days: 3, assembly: false } );
		expect( last().fields.map( ( field ) => field.id ) ).toEqual( [
			'lead_days',
			'assembly',
		] );
		expect( last().form ).toEqual( {
			layout: { type: 'regular' },
			fields: [ 'lead_days', 'assembly' ],
		} );
		expect( last().validity ).toEqual( {
			lead_days: {
				custom: { type: 'invalid', message: 'Use a whole number.' },
			},
		} );
	} );

	it( "writes an edit into the server's control, and drops the edited field's refusal", () => {
		mount( kit(), draw );
		last().onChange( { lead_days: 5 } );
		expect(
			server().querySelector< HTMLInputElement >( '[type=text]' )!.value
		).toBe( '5' );
		expect( last().data ).toEqual( { lead_days: 5, assembly: false } );
		expect( last().validity ).toBeUndefined();
		last().onChange( { assembly: true } );
		expect(
			server().querySelector< HTMLInputElement >( '[type=checkbox]' )!
				.checked
		).toBe( true );
		expect( last().data ).toEqual( { lead_days: 5, assembly: true } );
	} );

	it( 'gives the page back as it was when unmounted', () => {
		const unmount = mount( kit(), draw );
		unmount();
		expect( unmounted ).toBe( 1 );
		expect( kit().querySelector( '.cbuild-form__app' ) ).toBeNull();
		expect( server().hidden ).toBe( false );
		expect(
			document.querySelector< HTMLFormElement >( 'form' )!.noValidate
		).toBe( false );
	} );

	it( 'leaves the server form alone where the description cannot be read', () => {
		kit().querySelector( 'script' )!.textContent = '{not json';
		expect( () => mount( kit(), draw ) ).toThrow( 'cbuild-settings' );
		expect( server().hidden ).toBe( false );
		expect( drawn ).toEqual( [] );
	} );

	it( 'mounts a form outside any form element too, as a screen of its own may', () => {
		const lone = kit();
		document.body.replaceChildren( lone );
		mount( lone, draw );
		expect( server().hidden ).toBe( true );
	} );
} );

describe( 'counting the refusals to show', () => {
	it( 'counts the fields in cards and outside them', () => {
		const refused = { custom: { type: 'invalid', message: 'No.' } };
		expect( refusals( undefined ) ).toBe( 0 );
		expect(
			refusals( {
				main: { children: { a: refused, b: refused } },
				c: refused,
			} )
		).toBe( 3 );
	} );
} );
