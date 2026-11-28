/**
 * The admin kit's list (docs/ADMIN-KIT.md): a field of rows, drawn in DataForm as a DataViews table, with an Add button,
 * a modal that holds DataForm for one row, and an Undo after a removal (M15's plan; docs/research/admin-ux-2026.md,
 * pattern 5). Every change goes to DataForm's `onChange` as the whole list, and the mount posts it (rows.ts); nothing is
 * saved until the page's own Save.
 *
 * - **The list is a group, not a fieldset:** in Chromium a fieldset around DataViews' wrapper, which is a size
 *   container, left the table unrendered after a removal (D-578).
 * - **The table** shows each column, with no search, filters or view settings, since a list is short and its order is
 *   the shop's. Each row's actions are in its menu: Edit, Move up and Move down where order matters, and Remove.
 * - **Each row keeps a key of its own** through moves and removals, so React moves a row's markup with it, and the
 *   focus on its menu goes where the row goes.
 * - **The modal** holds its own small form, outside the page's, so the browser checks a required column there, and
 *   Enter is Done.
 * - **A removal** speaks "Row removed." in a snackbar with Undo, which puts the row back where it was. It stays until it
 *   is closed or the list changes again, since a list waits for its Save: no timer takes the Undo away (WCAG 2.2.1).
 *   Focus goes to the Add button, which is always there.
 */
import { Button, Modal, Snackbar } from '@wordpress/components';
import { DataForm, DataViews, type View } from '@wordpress/dataviews';
import {
	createElement,
	useRef,
	useState,
	type FormEvent,
} from '@wordpress/element';
import {
	fieldsOf,
	type Data,
	type FieldDescription,
	type ListEdit,
	type Row,
} from './describe';
import { added, moved, removed, replaced, restored } from './rows';

/** A row as the table holds it: the row's values, and its key. */
type Item = Row & { [ KEY ]: string };

/** The key's name, which no column id can be: ids are slugs. */
const KEY = '#';

/** What DataForm gives a field's control. */
type EditProps = {
	data: Data;
	onChange: ( edits: Partial< Data > ) => void;
	validity?: { custom?: { message?: string } };
};

/**
 * A blank row: each column's empty value.
 *
 * @param columns The list's columns.
 */
function blank( columns: FieldDescription[] ): Row {
	return Object.fromEntries(
		columns.map( ( column ) => [
			column.id,
			'boolean' === column.type ? false : '',
		] )
	);
}

/**
 * Draws a list: the control its field is given in DataForm.
 *
 * @param list  The list.
 * @param words The kit's own words.
 */
export const listEdit: ListEdit = ( list, words ) => {
	const columns = fieldsOf( {
		id: list.id,
		fields: list.columns,
		cards: [],
		errors: {},
	} ).map( ( field ) => ( {
		...field,
		enableSorting: false,
		enableHiding: false,
		filterBy: false as const,
	} ) );
	const ids = list.columns.map( ( column ) => column.id );
	const form = { layout: { type: 'regular' as const }, fields: ids };
	let made = 0;
	const key = () => `${ list.id }-${ made++ }`;

	return function ListControl( { data, onChange, validity }: EditProps ) {
		const rows = ( data[ list.id ] ?? [] ) as Row[];
		const [ keys, setKeys ] = useState< string[] >( () => rows.map( key ) );
		const [ view, setView ] = useState< View >( {
			type: 'table',
			fields: ids,
			page: 1,
			perPage: list.max,
			layout: {},
		} );
		const [ editing, setEditing ] = useState< {
			at: number | null;
			row: Row;
		} | null >( null );
		const [ undo, setUndo ] = useState< {
			row: Row;
			key: string;
			at: number;
		} | null >( null );
		const add = useRef< HTMLButtonElement >( null );
		// Two frames on, once the row's menu has closed and handed focus back to its button, which a removal takes away.
		const toAdd = () =>
			requestAnimationFrame( () =>
				requestAnimationFrame( () => add.current?.focus() )
			);

		const change = ( next: Row[], nextKeys: string[] ) => {
			setKeys( nextKeys );
			setUndo( null );
			onChange( { [ list.id ]: next } );
		};
		const at = ( item: Item ) => keys.indexOf( item[ KEY ] );
		const items: Item[] = rows.map( ( row, place ) => ( {
			...row,
			[ KEY ]: keys[ place ] ?? `${ list.id }-row-${ place }`,
		} ) );

		const actions = [
			{
				id: 'edit',
				label: words.edit,
				callback: ( [ item ]: Item[] ) => {
					const place = at( item as Item );
					setEditing( { at: place, row: rows[ place ] as Row } );
				},
			},
			...( list.ordered
				? [
						{
							id: 'move-up',
							label: words.moveUp,
							isEligible: ( item: Item ) => at( item ) > 0,
							callback: ( [ item ]: Item[] ) => {
								const place = at( item as Item );
								change(
									moved( rows, place, -1 ),
									moved( keys, place, -1 )
								);
							},
						},
						{
							id: 'move-down',
							label: words.moveDown,
							isEligible: ( item: Item ) =>
								at( item ) < rows.length - 1,
							callback: ( [ item ]: Item[] ) => {
								const place = at( item as Item );
								change(
									moved( rows, place, 1 ),
									moved( keys, place, 1 )
								);
							},
						},
					]
				: [] ),
			{
				id: 'remove',
				label: words.remove,
				callback: ( [ item ]: Item[] ) => {
					const place = at( item as Item );
					change( removed( rows, place ), removed( keys, place ) );
					setUndo( {
						row: rows[ place ] as Row,
						key: keys[ place ] as string,
						at: place,
					} );
					toAdd();
				},
			},
		];

		const done = ( event: FormEvent< HTMLFormElement > ) => {
			event.preventDefault();
			if ( null === editing ) {
				return;
			}
			if ( null === editing.at ) {
				change(
					added( rows, editing.row, list.max ),
					added( keys, key(), list.max )
				);
			} else {
				change( replaced( rows, editing.at, editing.row ), keys );
			}
			setEditing( null );
		};
		const full = rows.length >= list.max;
		const message = validity?.custom?.message;

		return createElement(
			'div',
			{
				className: 'cbuild-form__list',
				role: 'group',
				'aria-labelledby': `${ list.id }-label`,
				'aria-describedby':
					undefined !== message ? `${ list.id }-error` : undefined,
				// The summary's link moves focus here (summary.ts).
				tabIndex: -1,
			},
			createElement(
				'div',
				{
					className: 'cbuild-form__list-label',
					id: `${ list.id }-label`,
				},
				list.label
			),
			undefined !== list.description &&
				createElement(
					'p',
					{ className: 'cbuild-form__list-help' },
					list.description
				),
			createElement(
				DataViews< Item >,
				{
					view,
					onChangeView: setView,
					fields: columns as never,
					data: items,
					getItemId: ( item: Item ) => item[ KEY ],
					actions: actions as never,
					search: false,
					paginationInfo: {
						totalItems: rows.length,
						totalPages: 1,
					},
					defaultLayouts: { table: {} },
					empty: createElement( 'p', null, list.words.empty ),
				},
				createElement( DataViews.Layout )
			),
			undefined !== message &&
				createElement(
					'p',
					{
						className: 'cbuild-form__list-error',
						id: `${ list.id }-error`,
					},
					message
				),
			createElement(
				'div',
				{ className: 'cbuild-form__list-add' },
				createElement(
					Button,
					{
						ref: add,
						variant: 'secondary',
						disabled: full,
						accessibleWhenDisabled: true,
						'aria-describedby': full
							? `${ list.id }-full`
							: undefined,
						onClick: () =>
							setEditing( {
								at: null,
								row: blank( list.columns ),
							} ),
					},
					list.words.add
				),
				full &&
					createElement(
						'p',
						{ id: `${ list.id }-full` },
						list.words.full
					)
			),
			null !== editing &&
				createElement(
					Modal,
					{
						title:
							null === editing.at
								? list.words.add
								: list.words.edit,
						onRequestClose: () => setEditing( null ),
					},
					createElement(
						'form',
						{
							className: 'cbuild-form__list-modal',
							onSubmit: done,
						},
						createElement( DataForm< Row >, {
							data: editing.row,
							fields: columns as never,
							form,
							onChange: ( edits: Partial< Row > ) =>
								setEditing( {
									...editing,
									row: { ...editing.row, ...edits } as Row,
								} ),
						} ),
						createElement(
							'div',
							{ className: 'cbuild-form__list-buttons' },
							createElement(
								Button,
								{
									variant: 'tertiary',
									onClick: () => setEditing( null ),
								},
								words.cancel
							),
							createElement(
								Button,
								{ variant: 'primary', type: 'submit' },
								words.done
							)
						)
					)
				),
			null !== undo &&
				createElement(
					'div',
					{ className: 'cbuild-form__snackbar' },
					createElement(
						Snackbar,
						{
							explicitDismiss: true,
							onRemove: () => setUndo( null ),
							actions: [
								{
									label: words.undo,
									onClick: () => {
										change(
											restored( rows, undo.row, undo.at ),
											restored( keys, undo.key, undo.at )
										);
										toAdd();
									},
								},
							],
						},
						words.removed
					)
				)
		);
	};
};
