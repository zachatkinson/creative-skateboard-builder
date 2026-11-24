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
import { layers, type Media } from './media';

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
const SECTION = '.options_group';
const ADDS = '.cbuild-kit-adds';
const ADD = 'button[data-cbuild-add]';
const REMOVE = 'button[data-cbuild-remove]';
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
	const id = field( hash );
	const found = id ? find( doc, id ) : null;
	if ( ! found ) {
		return;
	}
	doc.querySelector< HTMLElement >(
		`ul.wc-tabs a[href="#${ PANEL }"]`
	)?.click();
	// Every group the field is folded into, and no other.
	doc.querySelectorAll( 'details' ).forEach( ( group ) => {
		if ( group.contains( found ) ) {
			group.open = true;
		}
	} );
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

	const said = list.dataset.cbuildMoved;
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
 * The rows of a section that adds and removes them (1.2.0, #114), all of them, shown or waiting.
 *
 * @param section The section.
 */
function addsOf( section: Element ): HTMLElement[] {
	return Array.from( section.querySelectorAll< HTMLElement >( ADDS ) );
}

/**
 * The text fields of a row that adds and removes.
 *
 * @param row The row.
 */
function inputsOf( row: Element ): HTMLInputElement[] {
	return Array.from(
		row.querySelectorAll< HTMLInputElement >( 'input[type="text"]' )
	);
}

/**
 * Switches Add a row off once every row the list takes is shown.
 *
 * @param section The section.
 */
function full( section: Element ): void {
	const adding = section.querySelector< HTMLButtonElement >( ADD );
	if ( adding ) {
		adding.disabled = addsOf( section ).every( ( row ) => ! row.hidden );
	}
}

/**
 * The shop is typing rows of its own, so the builder's rows are no longer ticked.
 *
 * @param section The section.
 */
function own( section: Element ): void {
	const plugins = section.querySelector< HTMLInputElement >(
		'input[type="checkbox"][id$="-plugins"]'
	);
	if ( plugins ) {
		plugins.checked = false;
	}
}

/**
 * Shows the next row that waits, and moves focus to its first field.
 *
 * @param section The section.
 */
export function add( section: Element ): void {
	const next = addsOf( section ).find( ( row ) => row.hidden );
	if ( ! next ) {
		return;
	}
	next.hidden = false;
	own( section );
	full( section );
	inputsOf( next )[ 0 ]?.focus();
}

/**
 * Removes a row: the rows after it move up a place, so the numbers stay in order, and the last one shown is emptied
 * and waits again. Focus stays in the place of the row removed, or goes to the row before it, or to Add a row; and a
 * screen reader hears what happened.
 *
 * @param pressed The Remove button pressed.
 * @param speak   Says a sentence to a screen reader.
 */
export function remove( pressed: HTMLButtonElement, speak?: Speak ): void {
	const row = pressed.closest< HTMLElement >( ADDS );
	const section = row?.closest( SECTION );
	if ( ! row || ! section ) {
		return;
	}
	const shown = addsOf( section ).filter( ( each ) => ! each.hidden );
	const at = shown.indexOf( row );
	const legend = row.querySelector( 'legend' )?.textContent?.trim() ?? '';
	shown.slice( at ).forEach( ( each, from ) => {
		const after = shown[ at + from + 1 ];
		inputsOf( each ).forEach( ( input, place ) => {
			input.value = after
				? ( inputsOf( after )[ place ]?.value ?? '' )
				: '';
		} );
	} );
	const last = shown[ shown.length - 1 ];
	if ( last ) {
		last.hidden = true;
	}
	own( section );
	full( section );
	const stays = shown[ at + 1 ] ? row : shown[ at - 1 ];
	( stays
		? inputsOf( stays )[ 0 ]
		: section.querySelector< HTMLButtonElement >( ADD )
	)?.focus();
	const said =
		section.querySelector< HTMLElement >( ADD )?.dataset.cbuildRemoved;
	if ( said && speak && legend ) {
		speak( said.replace( '%s', legend ) );
	}
}

/**
 * Makes each section that adds and removes rows work: its buttons, which wait hidden for this script, are shown.
 *
 * @param doc   The product screen.
 * @param speak Says a sentence to a screen reader.
 */
export function adds( doc: Document, speak?: Speak ): void {
	doc.querySelectorAll< HTMLButtonElement >( ADD ).forEach( ( pressed ) => {
		const section = pressed.closest( SECTION );
		if ( ! section ) {
			return;
		}
		section
			.querySelectorAll< HTMLButtonElement >( `${ ADD }, ${ REMOVE }` )
			.forEach( ( each ) => {
				each.hidden = false;
			} );
		full( section );
		section.addEventListener( 'click', ( event ) => {
			const target =
				event.target instanceof Element ? event.target : null;
			const removing = target?.closest< HTMLButtonElement >( REMOVE );
			if ( removing ) {
				remove( removing, speak );
			} else if ( target?.closest( ADD ) ) {
				add( section );
			}
		} );
		section.addEventListener( 'input', ( event ) => {
			if (
				event.target instanceof Element &&
				event.target.closest( ADDS )
			) {
				own( section );
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
		wp?: { a11y?: { speak?: Speak }; media?: Media };
	}
): void {
	const doc = win.document;
	// A preset's Photo: the media library's modal (D-252).
	layers( doc, win.wp?.media );
	const now = () => open( doc, win.location.hash );
	const ready = () => {
		rows( doc, win.wp?.a11y?.speak, win.jQuery );
		adds( doc, win.wp?.a11y?.speak );
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
