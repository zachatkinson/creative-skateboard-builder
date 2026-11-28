/**
 * The admin kit's hold on the server's form (docs/ADMIN-KIT.md). The server renders the form as it always has; the
 * script draws DataForm beside it and writes each changed value into the server's own control, so the form posts the
 * same names, with the same nonce, to the same checks (M15's principle 1).
 *
 * Each write fires a bubbling `change` on the control. WooCommerce's Settings turn their Save button on at one, and a
 * variation is saved only once a `change` inside it has marked it (docs/research/admin-handbooks-2026.md).
 */
import type { Value } from './describe';

/**
 * The server form's controls of a name, inside the kit's part of the page.
 *
 * @param root The kit's part of the server form.
 * @param name The input name.
 */
function controls( root: HTMLElement, name: string ): HTMLElement[] {
	return Array.from(
		root.querySelectorAll< HTMLElement >(
			`[name="${ CSS.escape( name ) }"]`
		)
	);
}

/**
 * Sets one control to a value, and says whether that changed it.
 *
 * @param control The control.
 * @param value   The value.
 */
function set( control: HTMLElement, value: Value ): boolean {
	if ( control instanceof HTMLInputElement ) {
		if ( 'checkbox' === control.type ) {
			const checked = true === value;
			const changed = control.checked !== checked;
			control.checked = checked;
			return changed;
		}
		if ( 'radio' === control.type ) {
			const checked = control.value === String( value );
			const changed = checked && ! control.checked;
			if ( checked ) {
				control.checked = true;
			}
			return changed;
		}
		if ( 'hidden' === control.type ) {
			// A checkbox's hidden 0 beside it, as the server wrote it: the box decides.
			return false;
		}
	}
	if (
		control instanceof HTMLInputElement ||
		control instanceof HTMLSelectElement ||
		control instanceof HTMLTextAreaElement
	) {
		const text = String( value );
		const changed = control.value !== text;
		control.value = text;
		return changed;
	}
	return false;
}

/**
 * Writes a value into the server form's control of a name, firing a bubbling change on each control it changed.
 *
 * @param  root  The kit's part of the server form.
 * @param  name  The input name, as the field's description gives it.
 * @param  value The value.
 * @throws {Error} A name the server form has not: the description and the template disagree.
 */
export function write( root: HTMLElement, name: string, value: Value ): void {
	const found = controls( root, name );
	if ( 0 === found.length ) {
		throw new Error( `The form has no control named ${ name }.` );
	}
	for ( const control of found ) {
		if ( set( control, value ) ) {
			control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}
}

/**
 * Takes the form over while the kit is mounted: the browser's own checks are turned off, so a hint never blocks a save
 * the server would take, and a field in a closed card can't stop the form with no word said. The server decides
 * (docs/research/admin-handbooks-2026.md).
 *
 * @param form The server's form.
 * @return What gives the form back as it was.
 */
export function takeOver( form: HTMLFormElement ): () => void {
	const was = form.noValidate;
	form.noValidate = true;
	return () => {
		form.noValidate = was;
	};
}
