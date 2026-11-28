/**
 * The admin kit's lists (docs/ADMIN-KIT.md, src/Admin/Form/Field.php's `rows()`): rows of columns, which the table
 * adds to, edits, removes from and reorders. Here are the rows as DataForm's data holds them, each move as a new list,
 * and what the form posts for them. Nothing here draws.
 *
 * The server's own controls for a list are its form with no script. While the kit is mounted they are turned off,
 * since a list's rows can't be written into a fixed set of controls once one is added or removed, and the kit posts
 * hidden inputs for the rows as they stand: the list's name, the row's place, the column's name, as
 * `cbuild[guide][0][size]`. The server reads the same names either way.
 */
import type { ListDescription, Row } from './describe';

export {
	isList,
	rowsOf,
	type KitWords,
	type ListDescription,
	type Row,
} from './describe';

/** What marks the inputs the kit posts, and the server's controls it turned off. */
const POSTED = 'cbuildPosted';
const OFF = 'cbuildOff';

/**
 * The rows with one moved a place up (-1) or down (1); as they were where it is already at that end.
 *
 * @param rows The rows.
 * @param at   The row's place.
 * @param by   -1 or 1.
 */
export function moved< T >( rows: readonly T[], at: number, by: -1 | 1 ): T[] {
	const to = at + by;
	if ( to < 0 || to >= rows.length ) {
		return [ ...rows ];
	}
	const next = [ ...rows ];
	[ next[ at ], next[ to ] ] = [ next[ to ] as T, next[ at ] as T ];
	return next;
}

/**
 * The rows without one.
 *
 * @param rows The rows.
 * @param at   The row's place.
 */
export function removed< T >( rows: readonly T[], at: number ): T[] {
	return rows.filter( ( _, place ) => place !== at );
}

/**
 * The rows with a removed one back in the place it left, or at the end where the list is shorter now.
 *
 * @param rows The rows.
 * @param row  The row.
 * @param at   The place it left.
 */
export function restored< T >( rows: readonly T[], row: T, at: number ): T[] {
	const next = [ ...rows ];
	next.splice( Math.min( at, next.length ), 0, row );
	return next;
}

/**
 * The rows with one more at the end, unless the list holds its most.
 *
 * @param rows The rows.
 * @param row  The row.
 * @param max  The most the list holds.
 */
export function added< T >( rows: readonly T[], row: T, max: number ): T[] {
	return rows.length >= max ? [ ...rows ] : [ ...rows, row ];
}

/**
 * The rows with one edited.
 *
 * @param rows The rows.
 * @param at   The row's place.
 * @param row  The row as edited.
 */
export function replaced< T >( rows: readonly T[], at: number, row: T ): T[] {
	return rows.map( ( one, place ) => ( place === at ? row : one ) );
}

/**
 * The server's own controls for a list, and the inputs the kit posted for it.
 *
 * @param server The server form's part inside the kit.
 * @param list   The list.
 */
function controlsOf(
	server: HTMLElement,
	list: ListDescription
): HTMLInputElement[] {
	const prefix = `${ list.name }[`;
	return Array.from(
		server.querySelectorAll< HTMLInputElement >( 'input, select, textarea' )
	).filter( ( control ) => control.name.startsWith( prefix ) );
}

/**
 * Posts a list's rows as they stand, in place of the server's own controls and of the rows last posted, and fires a
 * bubbling `change`, which WooCommerce's Settings and variations listen for (inputs.ts). A boolean column posts 1 when
 * on and nothing when off, as an unticked box does.
 *
 * @param server The server form's part inside the kit.
 * @param list   The list.
 * @param rows   The rows.
 */
export function post(
	server: HTMLElement,
	list: ListDescription,
	rows: readonly Row[]
): void {
	for ( const control of controlsOf( server, list ) ) {
		if ( undefined !== control.dataset[ POSTED ] ) {
			control.remove();
		} else if ( ! control.disabled ) {
			control.disabled = true;
			control.dataset[ OFF ] = '';
		}
	}
	const inputs = rows.flatMap( ( row, at ) =>
		list.columns.flatMap( ( column ) => {
			const value = row[ column.id ];
			if ( 'boolean' === column.type && true !== value ) {
				return [];
			}
			const input = server.ownerDocument.createElement( 'input' );
			input.type = 'hidden';
			input.name = `${ list.name }[${ at }]${ column.name }`;
			input.value = true === value ? '1' : String( value ?? '' );
			input.dataset[ POSTED ] = '';
			return [ input ];
		} )
	);
	server.append( ...inputs );
	server.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

/**
 * Gives a list's server controls back as they were, and takes the posted rows away.
 *
 * @param server The server form's part inside the kit.
 * @param list   The list.
 */
export function release( server: HTMLElement, list: ListDescription ): void {
	for ( const control of controlsOf( server, list ) ) {
		if ( undefined !== control.dataset[ POSTED ] ) {
			control.remove();
		} else if ( undefined !== control.dataset[ OFF ] ) {
			control.disabled = false;
			delete control.dataset[ OFF ];
		}
	}
}
