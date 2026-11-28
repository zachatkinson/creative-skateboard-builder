/**
 * The admin kit's reading of a form's description (docs/ADMIN-KIT.md). The server describes each field once
 * (src/Admin/Form): its id, the input name its value is posted under, its label, type, help, choices and limits. This
 * turns that description into what DataForm takes: its data, its fields, its form and the validity of the server's
 * refusals. Nothing here touches the page, so the whole of it is tested without one.
 *
 * The limits are hints. The server's check decides, and what it refused comes back as `errors`, each to its field
 * (M15's principles 1 to 3, D-553).
 */
import type { Field, Form, FormValidity } from '@wordpress/dataviews';

/** A value as DataForm's data holds it. */
export type Value = string | number | boolean;

/** A form's data: each field's value, by field id. */
export type Data = Record< string, Value >;

/** A field, as src/Admin/Form/Field.php describes it. */
export type FieldDescription = {
	id: string;
	name: string;
	label: string;
	type: string;
	value: string;
	description?: string;
	control?: string;
	elements?: Array< { value: string; label: string } >;
	rules?: {
		required?: boolean;
		min?: number;
		max?: number;
		maxLength?: number;
		pattern?: string;
	};
	suffix?: string;
	placeholder?: string;
	readOnly?: boolean;
	source?: string;
	visibleWhen?: Record< string, string[] >;
};

/** A card, as src/Admin/Form/Card.php describes it. */
export type CardDescription = {
	id: string;
	label: string;
	fields: string[];
	open: boolean;
	summary?: string;
};

/** A form, as src/Admin/Form/Form.php describes it. */
export type Description = {
	id: string;
	fields: FieldDescription[];
	cards: CardDescription[];
	errors: Record< string, string >;
	/** Whether the kit warns before the page is left with a change; false where the page warns itself. */
	leave?: boolean;
};

/** The field types whose min and max DataForm applies: a text field's are left to the server. */
const NUMERIC = new Set( [ 'integer', 'number' ] );

/**
 * A value as its field's type takes it: a boolean as checked, a number as a number. An integer or number that is no
 * number stays as it was typed, so the server sees what was typed and names it.
 *
 * @param field The field.
 */
function valueOf( field: FieldDescription ): Value {
	if ( 'boolean' === field.type ) {
		return '' !== field.value && '0' !== field.value;
	}
	if ( NUMERIC.has( field.type ) && '' !== field.value.trim() ) {
		const number = Number( field.value );
		return Number.isFinite( number ) ? number : field.value;
	}
	return field.value;
}

/**
 * The form's data.
 *
 * @param description The form's description.
 */
export function dataOf( description: Description ): Data {
	return Object.fromEntries(
		description.fields.map( ( field ) => [ field.id, valueOf( field ) ] )
	);
}

/**
 * The limits DataForm applies to a field of its type.
 *
 * @param field The field.
 */
function rulesOf( field: FieldDescription ): Field< Data >[ 'isValid' ] {
	const { min, max, ...rest } = field.rules ?? {};
	const rules: Record< string, unknown > = { ...rest };
	if ( NUMERIC.has( field.type ) ) {
		if ( undefined !== min ) {
			rules.min = min;
		}
		if ( undefined !== max ) {
			rules.max = max;
		}
	}
	return Object.keys( rules ).length > 0
		? ( rules as Field< Data >[ 'isValid' ] )
		: undefined;
}

/**
 * DataForm's fields.
 *
 * @param description The form's description.
 */
export function fieldsOf( description: Description ): Field< Data >[] {
	return description.fields.map( ( field ) => {
		const made: Record< string, unknown > = {
			id: field.id,
			label: field.label,
			type: field.type,
		};
		const help = field.readOnly ? field.source : field.description;
		if ( undefined !== help ) {
			made.description = help;
		}
		const control =
			field.control ??
			( 'boolean' === field.type ? 'checkbox' : undefined );
		if ( undefined !== control ) {
			made.Edit = control;
		}
		if ( undefined !== field.elements ) {
			made.elements = field.elements;
		}
		if ( undefined !== field.placeholder ) {
			made.placeholder = field.placeholder;
		}
		if ( field.readOnly ) {
			made.readOnly = true;
		}
		const rules = rulesOf( field );
		if ( undefined !== rules ) {
			made.isValid = rules;
		}
		const when = Object.entries( field.visibleWhen ?? {} )[ 0 ];
		if ( undefined !== when ) {
			const [ other, values ] = when;
			made.isVisible = ( item: Data ) =>
				values.includes( String( item[ other ] ) );
		}
		return made as Field< Data >;
	} );
}

/**
 * DataForm's form: a card per card, or the fields in order where there are none.
 *
 * @param description The form's description.
 */
export function formOf( description: Description ): Form {
	if ( 0 === description.cards.length ) {
		return {
			layout: { type: 'regular' },
			fields: description.fields.map( ( field ) => field.id ),
		};
	}
	return {
		layout: { type: 'regular' },
		fields: description.cards.map( ( card ) => ( {
			id: card.id,
			label: card.label,
			layout: {
				type: 'card' as const,
				isOpened: card.open,
				...( card.summary ? { summary: card.summary } : {} ),
			},
			children: card.fields,
		} ) ),
	};
}

/**
 * The validity of the server's refusals: each refused field's message, until the field is edited. A message kept past
 * an edit would hold a corrected field invalid, and the form could not be sent again. DataForm reads a field in a card
 * under the card, as `validity[card].children[field]`, and one outside any card by its own id.
 *
 * @param description The form's description, with the server's messages by field id.
 * @param edited      The fields edited since the page loaded.
 */
export function validityOf(
	description: Description,
	edited: ReadonlySet< string >
): FormValidity {
	const kept = Object.entries( description.errors ).filter(
		( [ id ] ) => ! edited.has( id )
	);
	if ( 0 === kept.length ) {
		return undefined;
	}
	const validity: Record< string, unknown > = {};
	for ( const [ id, message ] of kept ) {
		const refused = { custom: { type: 'invalid' as const, message } };
		const card = description.cards.find( ( one ) =>
			one.fields.includes( id )
		);
		if ( undefined === card ) {
			validity[ id ] = refused;
			continue;
		}
		const held = ( validity[ card.id ] ?? { children: {} } ) as {
			children: Record< string, unknown >;
		};
		held.children[ id ] = refused;
		validity[ card.id ] = held;
	}
	return validity as FormValidity;
}
