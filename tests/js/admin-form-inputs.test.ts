/**
 * The admin kit writes each value DataForm changes into the server form's own control, so the form posts what it
 * always did (M15's principle 1). The control fires a bubbling change, as WooCommerce's Settings (its Save button)
 * and its variations (the mark that saves one) wait for one (docs/research/admin-handbooks-2026.md).
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { takeOver, write } from '../../assets/src/admin/form/inputs';

let form: HTMLFormElement;
let changes: string[];

beforeEach( () => {
	document.body.innerHTML = `<form id="mainform">
		<div class="cbuild-form__server">
			<input type="text" name="cbuild[lead_days]" value="3">
			<input type="hidden" name="cbuild[assembly]" value="0"><input type="checkbox" name="cbuild[assembly]" value="1">
			<select name="map[0][unit]"><option value="mm">mm</option><option value="in">in</option></select>
			<input type="radio" name="kind" value="deck" checked><input type="radio" name="kind" value="wheel">
			<textarea name="note">Old</textarea>
			<input type="number" name="cbuild[weight]" value="">
		</div>
		<input type="text" name="cbuild[lead_days]" value="outside the kit's part of the form">
	</form>`;
	form = document.querySelector( 'form' )!;
	changes = [];
	form.addEventListener( 'change', ( event ) =>
		changes.push( ( event.target as HTMLInputElement ).name )
	);
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

const server = () =>
	document.querySelector< HTMLElement >( '.cbuild-form__server' )!;
const control = < T extends Element >( selector: string ) =>
	server().querySelector< T >( selector )!;

describe( 'writing a value into the server form', () => {
	it( "sets a text field's value and fires a bubbling change", () => {
		write( server(), 'cbuild[lead_days]', 5 );
		expect(
			control< HTMLInputElement >( '[name="cbuild[lead_days]"]' ).value
		).toBe( '5' );
		expect( changes ).toEqual( [ 'cbuild[lead_days]' ] );
		expect(
			document.querySelectorAll< HTMLInputElement >(
				'[name="cbuild[lead_days]"]'
			)[ 1 ]!.value,
			"only the kit's part of the form"
		).toBe( "outside the kit's part of the form" );
	} );

	it( 'ticks and clears a checkbox, leaving the hidden 0 beside it as the server wrote it', () => {
		write( server(), 'cbuild[assembly]', true );
		const box = control< HTMLInputElement >( '[type=checkbox]' );
		expect( box.checked ).toBe( true );
		write( server(), 'cbuild[assembly]', false );
		expect( box.checked ).toBe( false );
		expect( control< HTMLInputElement >( '[type=hidden]' ).value ).toBe(
			'0'
		);
		expect( changes ).toEqual( [ 'cbuild[assembly]', 'cbuild[assembly]' ] );
	} );

	it( 'chooses an option and a radio by value', () => {
		write( server(), 'map[0][unit]', 'in' );
		expect( control< HTMLSelectElement >( 'select' ).value ).toBe( 'in' );
		write( server(), 'kind', 'wheel' );
		expect( control< HTMLInputElement >( '[value=wheel]' ).checked ).toBe(
			true
		);
		expect( control< HTMLInputElement >( '[value=deck]' ).checked ).toBe(
			false
		);
	} );

	it( 'writes a textarea, and an empty number as empty', () => {
		write( server(), 'note', 'New' );
		expect( control< HTMLTextAreaElement >( 'textarea' ).value ).toBe(
			'New'
		);
		write( server(), 'cbuild[weight]', '' );
		expect( control< HTMLInputElement >( '[type=number]' ).value ).toBe(
			''
		);
	} );

	it( 'fires nothing when the value is what the control holds', () => {
		write( server(), 'cbuild[lead_days]', '3' );
		write( server(), 'kind', 'deck' );
		expect( changes ).toEqual( [] );
	} );

	it( 'refuses a name the server form has not, as the description and the template disagree', () => {
		expect( () => write( server(), 'cbuild[gone]', 'x' ) ).toThrow(
			'cbuild[gone]'
		);
	} );
} );

describe( 'taking over the form', () => {
	it( "turns off the browser's own checks, so a hint never blocks a save the server would take, and gives them back", () => {
		expect( form.noValidate ).toBe( false );
		const giveBack = takeOver( form );
		expect( form.noValidate ).toBe( true );
		giveBack();
		expect( form.noValidate ).toBe( false );
	} );

	it( 'leaves a form that had them off as it was', () => {
		form.noValidate = true;
		takeOver( form )();
		expect( form.noValidate ).toBe( true );
	} );
} );
