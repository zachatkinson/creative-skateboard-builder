/**
 * The Build tab on a kit's product screen (spec: Admin, The kit's Build tab).
 *
 * - A slot's row moves with its Move up and Move down buttons, as WCAG 2.2 asks beside dragging (2.5.7). Focus stays
 *   on the button pressed, or goes to the row's other button once the row is at an end, and a screen reader hears
 *   where the row now is. The rows are sent in the order they stand in, which is the order the kit keeps.
 * - The rows also drag by their handle, with jQuery UI's sortable, which WooCommerce's product screen loads.
 * - A notice's link names a field: `#cbuild-kit-slots-2-units`. WooCommerce's product screen opens no tab from the
 *   address, so this opens the Build tab, opens the group the field is in, and moves to the field. An address that
 *   names something inside a field, one ID in a list of them, leads to the field.
 *
 * No data is read and no markup is built: every word comes from the page.
 */
import { settle } from './settle';

/**
 * As much of jQuery as this script uses: the rows made to drag, and a callback for when the page is ready.
 */
export interface Query {
	( list: Element ): { sortable?: ( options: object ) => unknown };
	( ready: () => void ): unknown;
}

/**
 * Says a sentence to a screen reader, as wp.a11y.speak does.
 */
export type Speak = ( sentence: string ) => unknown;

const PANEL = 'cbuild_build';
const IDS = 'cbuild-kit';
const LIST = '.cbuild-kit-rows';
const ROW = '.cbuild-kit-row';
const CONTROLS =
	'input:not([type="hidden"]), select, textarea, button:not(:disabled)';

/**
 * The ID an address names, where it is one of the tab's, or nothing.
 *
 * @param hash The address's fragment, with its #.
 */
export function field( hash: string ): string | null {
	return hash.match( /^#(cbuild-kit(?:-[a-z0-9_]+)+)$/ )?.[ 1 ] ?? null;
}

/**
 * The element an ID leads to: its own, or the nearest it is inside of by name. `cbuild-kit-slots-2-sources-brands-0`
 * leads to the brands field, and a field that is not on the page to its row.
 *
 * @param doc The product screen.
 * @param id  The ID.
 */
export function find( doc: Document, id: string ): HTMLElement | null {
	for ( let name = id; name.length > IDS.length; ) {
		const found = doc.getElementById( name );
		if ( found ) {
			return found;
		}
		name = name.slice( 0, name.lastIndexOf( '-' ) );
	}
	return null;
}

/**
 * Opens the tab and moves to what the address names.
 *
 * @param doc  The product screen.
 * @param hash The address's fragment, with its #.
 */
export function open( doc: Document, hash: string ): void {
	const found = find( doc, field( hash ) ?? '' );
	if ( ! found ) {
		return;
	}
	doc.querySelector< HTMLElement >(
		`ul.wc-tabs a[href="#${ PANEL }"]`
	)?.click();
	// Every group the field is folded into, the innermost first.
	for (
		let group = found.closest( 'details' );
		group;
		group = group.parentElement?.closest( 'details' ) ?? null
	) {
		group.setAttribute( 'open', '' );
	}
	// A row or a section takes no focus itself, so its first control does.
	const control = found.matches( CONTROLS )
		? found
		: ( found.querySelector< HTMLElement >( CONTROLS ) ?? found );
	control.scrollIntoView( { block: 'center' } );
	control.focus( { preventScroll: true } );
	settle( doc.defaultView, control );
}

/**
 * The rows of a list, in the order they stand in.
 *
 * @param list The list.
 */
function rowsOf( list: Element ): Element[] {
	return Array.from( list.children ).filter( ( row ) => row.matches( ROW ) );
}

/**
 * A row's button.
 *
 * @param row       The row.
 * @param direction Which way it moves the row.
 */
function button( row: Element, direction: string ): HTMLButtonElement | null {
	return row.querySelector< HTMLButtonElement >(
		`button[data-cbuild-move="${ direction }"]`
	);
}

/**
 * Switches off the buttons that have nowhere to go: Move up on the first row and Move down on the last.
 *
 * @param list The list.
 */
export function edges( list: Element ): void {
	const all = rowsOf( list );
	all.forEach( ( row, at ) => {
		const up = button( row, 'up' );
		const down = button( row, 'down' );
		if ( up ) {
			up.disabled = at === 0;
		}
		if ( down ) {
			down.disabled = at === all.length - 1;
		}
	} );
}

/**
 * Moves a row one place, keeps focus in it, and says where it now is.
 *
 * @param pressed The button pressed.
 * @param speak   Says a sentence to a screen reader.
 */
export function move( pressed: HTMLButtonElement, speak?: Speak ): void {
	const row = pressed.closest( ROW );
	const list = row?.parentElement;
	if ( ! row || ! list ) {
		return;
	}
	const all = rowsOf( list );
	const at = all.indexOf( row );
	const up = pressed.dataset.cbuildMove === 'up';
	const other = all[ up ? at - 1 : at + 1 ];
	if ( ! other ) {
		return;
	}
	list.insertBefore( up ? row : other, up ? other : row );
	edges( list );
	// A button that is switched off cannot hold focus, so the row's other one takes it.
	( pressed.disabled ? button( row, up ? 'down' : 'up' ) : pressed )?.focus();

	const said = list instanceof HTMLElement ? list.dataset.cbuildMoved : '';
	if ( said && speak ) {
		const label =
			row.querySelector< HTMLInputElement >( 'input[id$="-label"]' )
				?.value ||
			row.querySelector( 'legend' )?.textContent ||
			'';
		speak(
			said
				.replace( '%1$s', label.trim() )
				.replace( '%2$d', String( rowsOf( list ).indexOf( row ) + 1 ) )
				.replace( '%3$d', String( all.length ) )
		);
	}
}

/**
 * Makes each list's rows move: by their buttons, and by dragging where jQuery UI is loaded.
 *
 * @param doc   The product screen.
 * @param speak Says a sentence to a screen reader.
 * @param $     jQuery, where the page has it.
 */
export function rows( doc: Document, speak?: Speak, $?: Query ): void {
	doc.querySelectorAll( LIST ).forEach( ( list ) => {
		edges( list );
		$?.( list ).sortable?.( {
			items: `> ${ ROW }`,
			handle: '.cbuild-kit-handle',
			axis: 'y',
			update: () => edges( list ),
		} );
		list.addEventListener( 'click', ( event ) => {
			const pressed =
				event.target instanceof Element
					? event.target.closest< HTMLButtonElement >(
							'button[data-cbuild-move]'
						)
					: null;
			if ( pressed ) {
				move( pressed, speak );
			}
		} );
	} );
}

/**
 * Makes the rows move, then opens what the address names once the page is ready, and again each time the address
 * changes, as it does when a notice's link is followed.
 *
 * @param win The window.
 */
export function start(
	win: Window & {
		jQuery?: Query;
		wp?: { a11y?: { speak?: Speak } };
	}
): void {
	const doc = win.document;
	const now = () => open( doc, win.location.hash );
	const ready = () => {
		rows( doc, win.wp?.a11y?.speak, win.jQuery );
		now();
	};
	if ( win.jQuery ) {
		// After WooCommerce's own ready callbacks, which show the first tab.
		win.jQuery( ready );
	} else if ( doc.readyState === 'loading' ) {
		doc.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
	win.addEventListener( 'hashchange', now );
}

start( window );
