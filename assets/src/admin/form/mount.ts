/**
 * The admin kit's mount (docs/ADMIN-KIT.md). It finds a form the server described (src/Admin/Form/Mount.php), draws
 * DataForm in a box of its own before the server's controls and hides them, and writes each edit back into them, so
 * the form posts what it always did (M15's principle 1). A refused field keeps the server's message until it is
 * edited (principle 3).
 *
 * The drawing itself is given (`Draw`): draw.ts draws the real DataForm, and the tests a stand-in, so what the mount
 * decides is tested without React.
 */
import type { Field, Form, FormValidity } from '@wordpress/dataviews';
import {
	dataOf,
	fieldsOf,
	formOf,
	validityOf,
	type Data,
	type Description,
} from './describe';
import { takeOver, write } from './inputs';
import { guard } from './leaving';

/** What DataForm is drawn with. */
export type Drawn = {
	data: Data;
	fields: Field< Data >[];
	form: Form;
	validity: FormValidity;
	onChange: ( edits: Partial< Data > ) => void;
};

/** A drawing: drawn again with new props, or taken away. */
export type Drawing = {
	update: ( props: Drawn ) => void;
	unmount: () => void;
};

/** What draws DataForm into a box. */
export type Draw = ( where: HTMLElement, props: Drawn ) => Drawing;

/**
 * The form's description, from the JSON the server printed beside it.
 *
 * @param  kit The mount.
 * @throws {Error} A description that can't be read: the server form is left as it is.
 */
function describe( kit: HTMLElement ): Description {
	const json = kit.querySelector( ':scope > .cbuild-form__description' );
	try {
		return JSON.parse( json?.textContent ?? '' ) as Description;
	} catch {
		throw new Error(
			`The form ${ kit.dataset.cbuildForm ?? '' } has no description the script can read.`
		);
	}
}

/**
 * Mounts DataForm over a described form.
 *
 * @param kit  The mount: `.cbuild-form`.
 * @param draw What draws DataForm.
 * @return What gives the page back as it was.
 */
export function mount( kit: HTMLElement, draw: Draw ): () => void {
	const description = describe( kit );
	const server = kit.querySelector< HTMLElement >(
		':scope > .cbuild-form__server'
	);
	if ( null === server ) {
		throw new Error(
			`The form ${ description.id } has no server controls to write to.`
		);
	}
	const names = new Map(
		description.fields.map( ( field ) => [ field.id, field.name ] )
	);
	const fields = fieldsOf( description );
	const form = formOf( description );
	let data = dataOf( description );
	const loaded = JSON.stringify( data );
	const edited = new Set< string >();

	const host = kit.closest( 'form' );
	const giveBack = host ? takeOver( host ) : () => {};
	const unguard =
		false === description.leave
			? () => {}
			: guard( host, () => JSON.stringify( data ) !== loaded );
	const app = document.createElement( 'div' );
	app.className = 'cbuild-form__app';
	kit.insertBefore( app, server );
	server.hidden = true;

	const props = (): Drawn => ( {
		data,
		fields,
		form,
		validity: validityOf( description, edited ),
		onChange,
	} );
	function onChange( edits: Partial< Data > ): void {
		for ( const [ id, value ] of Object.entries( edits ) ) {
			const name = names.get( id );
			if ( undefined === name || undefined === value ) {
				continue;
			}
			write( server as HTMLElement, name, value );
			edited.add( id );
		}
		data = { ...data, ...edits } as Data;
		drawing.update( props() );
	}
	const drawing = draw( app, props() );

	return () => {
		drawing.unmount();
		app.remove();
		server.hidden = false;
		giveBack();
		unguard();
	};
}
