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

const live: ( () => void )[] = [];
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
	// Each mount listens on the window, so each is given back before the next test.
	live.splice( 0 ).forEach( ( unmount ) => unmount() );
	document.body.innerHTML = '';
} );

/**
 * Mounts as the kit does, kept to be given back after the test.
 *
 * @param where The mount.
 */
function mounted( where: HTMLElement ): () => void {
	const unmount = mount( where, draw );
	live.push( unmount );
	return unmount;
}

const last = (): Drawn => drawn[ drawn.length - 1 ]!;
const kit = () => document.querySelector< HTMLElement >( '.cbuild-form' )!;
const server = () =>
	document.querySelector< HTMLElement >( '.cbuild-form__server' )!;

describe( 'mounting a described form', () => {
	it( "draws DataForm in its own box before the server's controls, and hides them, which still post", () => {
		mounted( kit() );
		const app = kit().querySelector< HTMLElement >( '.cbuild-form__app' )!;
		expect( app.dataset.drawn ).toBe( 'yes' );
		expect( app.nextElementSibling ).toBe( server() );
		expect( server().hidden ).toBe( true );
		expect(
			document.querySelector< HTMLFormElement >( 'form' )!.noValidate
		).toBe( true );
	} );

	it( "gives DataForm the data, the fields, the form and the server's refusals", () => {
		mounted( kit() );
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
		mounted( kit() );
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
		const unmount = mounted( kit() );
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
		expect( () => mounted( kit() ) ).toThrow( 'cbuild-settings' );
		expect( server().hidden ).toBe( false );
		expect( drawn ).toEqual( [] );
	} );

	it( 'mounts a form outside any form element too, as a screen of its own may', () => {
		const lone = kit();
		document.body.replaceChildren( lone );
		mounted( lone );
		expect( server().hidden ).toBe( true );
	} );
} );

/**
 * Whether leaving the page now would be warned of: a `beforeunload` the kit cancels.
 */
function warned(): boolean {
	const leaving = new Event( 'beforeunload', { cancelable: true } );
	window.dispatchEvent( leaving );
	return leaving.defaultPrevented;
}

describe( 'the leaving warning', () => {
	it( 'warns only once a value differs from what the page loaded with', () => {
		const unmount = mounted( kit() );
		expect( warned() ).toBe( false );
		last().onChange( { lead_days: 5 } );
		expect( warned() ).toBe( true );
		last().onChange( { lead_days: 3 } );
		expect( warned() ).toBe( false );
		unmount();
	} );

	it( 'lets the Save go without a warning', () => {
		const unmount = mounted( kit() );
		last().onChange( { assembly: true } );
		document
			.querySelector( 'form' )!
			.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		expect( warned() ).toBe( false );
		unmount();
	} );

	it( 'warns again where a Save was stopped before it left the page', () => {
		const unmount = mounted( kit() );
		last().onChange( { assembly: true } );
		const form = document.querySelector( 'form' )!;
		form.addEventListener( 'submit', ( event ) => event.preventDefault() );
		form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		expect( warned() ).toBe( true );
		unmount();
	} );

	it( "leaves it to a page that warns itself, as WooCommerce's Settings do", () => {
		kit().querySelector( 'script' )!.textContent = JSON.stringify( {
			...description,
			leave: false,
		} );
		const unmount = mounted( kit() );
		last().onChange( { lead_days: 5 } );
		expect( warned() ).toBe( false );
		unmount();
	} );

	it( 'warns no more once the kit gives the page back', () => {
		const unmount = mounted( kit() );
		last().onChange( { lead_days: 5 } );
		unmount();
		expect( warned() ).toBe( false );
	} );
} );

describe( 'the summary of the refusals', () => {
	const summed = `<div class="notice notice-error inline cbuild-form__summary" tabindex="-1"><p>1 field needs a change before the form can be saved.</p><ul><li><a href="#cbuild-form-cbuild-settings-lead_days" data-cbuild-field="lead_days">Assembly time: Use a whole number.</a></li><li><a href="#cbuild-form-cbuild-settings-gone" data-cbuild-field="gone">Gone: No.</a></li></ul></div>`;
	// A stand-in that draws each field's control with a label, as DataForm does, its id its own.
	const labelled: Draw = ( where, props ) => {
		where.innerHTML = props.fields
			.map(
				( field, at ) =>
					// DataForm adds "(Required)", translated, to a required field's label.
					`<label for="control-${ at }">${ String( field.label ) }${
						0 === at ? ' (Required)' : ''
					}</label><input id="control-${ at }">`
			)
			.join( '' );
		return { update: () => {}, unmount: () => {} };
	};
	const withSummary = () => {
		kit().insertAdjacentHTML( 'afterbegin', summed );
		const unmount = mount( kit(), labelled );
		live.push( unmount );
	};
	const link = ( field: string ) =>
		document.querySelector< HTMLAnchorElement >(
			`[data-cbuild-field="${ field }"]`
		)!;

	it( 'takes focus as the page loads, so a keyboard starts from it', () => {
		withSummary();
		expect( kit().ownerDocument.activeElement ).toBe(
			document.querySelector( '.cbuild-form__summary' )
		);
	} );

	it( "moves focus from a link to the field's own control in DataForm", () => {
		withSummary();
		const click = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		link( 'lead_days' ).dispatchEvent( click );
		expect( kit().ownerDocument.activeElement?.id ).toBe( 'control-0' );
		expect( click.defaultPrevented ).toBe( true );
	} );

	it( 'leaves a link to a field DataForm is not showing to the page', () => {
		withSummary();
		const click = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		link( 'gone' ).dispatchEvent( click );
		expect( click.defaultPrevented ).toBe( false );
	} );

	it( 'leaves focus alone where nothing was refused', () => {
		const before = kit().ownerDocument.activeElement;
		mounted( kit() );
		expect( kit().ownerDocument.activeElement ).toBe( before );
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
