/**
 * The admin kit's reading of a form's description (src/Admin/Form): the server describes each field once, and the
 * script turns it into DataForm's fields, its form and its validity (M15's principles 1 to 3, D-553).
 */
import { describe, expect, it } from 'vitest';
import {
	dataOf,
	fieldsOf,
	formOf,
	validityOf,
	type Description,
	type FieldDescription,
} from '../../assets/src/admin/form/describe';

const description: Description = {
	id: 'cbuild-settings',
	fields: [
		{
			id: 'lead_days',
			name: 'cbuild[lead_days]',
			label: 'Assembly time',
			type: 'integer',
			value: '3',
			description: 'Business days.',
			suffix: 'days',
			rules: { required: true, min: 0, max: 30 },
		},
		{
			id: 'unit',
			name: 'map[unit]',
			label: 'Unit',
			type: 'text',
			value: 'mm',
			control: 'select',
			elements: [
				{ value: 'mm', label: 'Millimetres' },
				{ value: 'in', label: 'Inches' },
			],
		},
		{
			id: 'assembly',
			name: 'cbuild[assembly]',
			label: 'Assembly',
			type: 'boolean',
			value: '1',
		},
		{
			id: 'note',
			name: 'cbuild[note]',
			label: 'Note',
			type: 'text',
			value: '',
			visibleWhen: { unit: [ 'in' ] },
		},
		{
			id: 'width',
			name: 'specs[width]',
			label: 'Width',
			type: 'text',
			value: '8.25',
			readOnly: true,
			source: 'From the attribute Width',
			rules: { pattern: '^[0-9.]+$' },
		},
		{
			id: 'state',
			name: 'state',
			label: 'State',
			type: 'text',
			value: 'Off',
			readOnly: true,
			source: 'Set below',
		},
	],
	cards: [
		{
			id: 'main',
			label: 'Builds',
			fields: [ 'lead_days', 'unit', 'assembly', 'note' ],
			open: true,
		},
		{
			id: 'more',
			label: 'Advanced',
			fields: [ 'width' ],
			open: false,
			summary: 'state',
		},
	],
	errors: { lead_days: 'Use a whole number from 0 to 30.' },
};

/**
 * One of the description's fields, by its place.
 *
 * @param index Its place.
 */
function at( index: number ): FieldDescription {
	const field = description.fields[ index ];
	if ( undefined === field ) {
		throw new Error( `No field at ${ index }` );
	}
	return field;
}

describe( 'the data', () => {
	it( 'reads each value as its type takes it, a boolean as checked and an integer as a number', () => {
		expect( dataOf( description ) ).toEqual( {
			lead_days: 3,
			unit: 'mm',
			assembly: true,
			note: '',
			width: '8.25',
			state: 'Off',
		} );
	} );

	it( 'keeps an integer that is no number as it was typed, so the server can name it', () => {
		const typed: Description = {
			...description,
			fields: [ { ...at( 0 ), value: 'three' } ],
			cards: [],
			errors: {},
		};
		expect( dataOf( typed ).lead_days ).toBe( 'three' );
		const blank: Description = {
			...typed,
			fields: [ { ...at( 0 ), value: '' } ],
		};
		expect( dataOf( blank ).lead_days ).toBe( '' );
	} );
} );

describe( 'the fields', () => {
	const fields = fieldsOf( description );
	const field = ( id: string ) => {
		const found = fields.find( ( made ) => made.id === id );
		if ( undefined === found ) {
			throw new Error( `No field ${ id }` );
		}
		return found;
	};

	it( "gives DataForm each field's label, type, help, control and choices", () => {
		expect( field( 'lead_days' ) ).toMatchObject( {
			id: 'lead_days',
			label: 'Assembly time',
			type: 'integer',
			description: 'Business days.',
		} );
		expect( field( 'unit' ) ).toMatchObject( {
			Edit: 'select',
			elements: [
				{ value: 'mm', label: 'Millimetres' },
				{ value: 'in', label: 'Inches' },
			],
		} );
		expect( field( 'assembly' ) ).toMatchObject( {
			type: 'boolean',
			Edit: 'checkbox',
		} );
	} );

	it( "keeps min and max for numbers, and puts a text field's limits in rules DataForm applies to text", () => {
		expect( field( 'lead_days' ).isValid ).toEqual( {
			required: true,
			min: 0,
			max: 30,
		} );
		expect( field( 'width' ).isValid ).toEqual( { pattern: '^[0-9.]+$' } );
		const [ text ] = fieldsOf( {
			...description,
			fields: [ { ...at( 3 ), rules: { min: 1, max: 5, maxLength: 9 } } ],
			cards: [],
			errors: {},
		} );
		expect(
			text?.isValid,
			'min and max mean nothing to a text field in DataForm'
		).toEqual( { maxLength: 9 } );
	} );

	it( 'shows a field only while the field it depends on holds one of its values', () => {
		expect( field( 'note' ).isVisible?.( { unit: 'in' } ) ).toBe( true );
		expect( field( 'note' ).isVisible?.( { unit: 'mm' } ) ).toBe( false );
		expect( field( 'unit' ).isVisible ).toBeUndefined();
	} );

	it( 'shows a read-only field as text that cannot be edited, with where it comes from', () => {
		expect( field( 'width' ).readOnly ).toBe( true );
		expect( field( 'width' ).description ).toBe(
			'From the attribute Width'
		);
	} );
} );

describe( 'the form', () => {
	it( 'is a card per card, open or closed, a closed one summed up by its field', () => {
		expect( formOf( description ) ).toEqual( {
			layout: { type: 'regular' },
			fields: [
				{
					id: 'main',
					label: 'Builds',
					layout: { type: 'card', isOpened: true },
					children: [ 'lead_days', 'unit', 'assembly', 'note' ],
				},
				{
					id: 'more',
					label: 'Advanced',
					layout: { type: 'card', isOpened: false, summary: 'state' },
					children: [ 'width' ],
				},
			],
		} );
	} );

	it( 'is the fields in order where there are no cards', () => {
		expect( formOf( { ...description, cards: [] } ) ).toEqual( {
			layout: { type: 'regular' },
			fields: [
				'lead_days',
				'unit',
				'assembly',
				'note',
				'width',
				'state',
			],
		} );
	} );
} );

describe( 'the validity', () => {
	const refused = {
		custom: {
			type: 'invalid',
			message: 'Use a whole number from 0 to 30.',
		},
	};

	it( "holds the server's message for each field it refused, under the card that holds it, as DataForm reads it", () => {
		expect( validityOf( description, new Set() ) ).toEqual( {
			main: { children: { lead_days: refused } },
		} );
	} );

	it( "holds it by the field's own id where the form has no cards", () => {
		expect(
			validityOf( { ...description, cards: [] }, new Set() )
		).toEqual( { lead_days: refused } );
	} );

	it( "drops a field's message once the field is edited, so a corrected field can be sent again", () => {
		expect(
			validityOf( description, new Set( [ 'lead_days' ] ) )
		).toBeUndefined();
	} );
} );
