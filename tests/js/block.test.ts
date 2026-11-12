/**
 * The Builder block in the editor (D-144): a kit chosen in the sidebar from the shop's products, searched as the owner
 * types, and the server's own render as the preview. The block is dynamic, so it saves nothing.
 */
import { beforeEach, describe, expect, it } from 'vitest';
import { mount, registered, type Element } from './support/editor';
import { answers, asked } from './support/api-fetch';
import { plain } from '../../assets/src/builder/index';

const NAME = 'creative-skateboard-builder/builder';

interface Props {
	attributes: { kit: number };
	setAttributes: ( next: { kit: number } ) => void;
}

const settings = registered.get( NAME ) as {
	edit: ( props: Props ) => unknown;
	save: () => null;
};

/**
 * Every element in a tree of the given type.
 *
 * @param tree The tree.
 * @param type The element's type.
 */
function find( tree: unknown, type: string ): Element[] {
	if ( typeof tree !== 'object' || tree === null || ! ( 'type' in tree ) ) {
		return [];
	}
	const node = tree as Element;
	return [
		...( node.type === type ? [ node ] : [] ),
		...node.children.flatMap( ( child ) => find( child, type ) ),
	];
}

async function settle(): Promise< void > {
	for ( let i = 0; i < 5; i++ ) {
		await Promise.resolve();
	}
}

let set: Array< { kit: number } >;

function edit( kit: number ) {
	set = [];
	return mount( settings.edit, {
		attributes: { kit },
		setAttributes: ( next ) => set.push( next ),
	} );
}

beforeEach( () => {
	asked.length = 0;
	answers.next = () => Promise.resolve( [] );
} );

describe( 'the Builder block', () => {
	it( 'is registered as a dynamic block that saves nothing', () => {
		expect( settings ).toBeDefined();
		expect( settings.save() ).toBeNull();
	} );

	it( 'asks for a kit with a placeholder and the same chooser in the sidebar until one is chosen', () => {
		const tree = edit( 0 ).render();
		expect( tree.type ).toBe( 'Fragment' );
		const [ panel ] = find( tree, 'PanelBody' );
		expect( panel?.props ).toEqual( { title: 'Kit' } );
		const choosers = find( tree, 'ComboboxControl' );
		expect( choosers ).toHaveLength( 2 );
		expect( choosers[ 0 ] ).toBe( choosers[ 1 ] );
		expect( choosers[ 0 ]?.props ).toMatchObject( {
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
			label: 'Kit',
			help: 'The builder kit this block sells. Type to search the shop.',
			value: null,
			options: [],
		} );
		const [ placeholder ] = find( tree, 'Placeholder' );
		expect( placeholder?.props ).toEqual( {
			label: 'Skateboard builder',
			instructions: 'Choose the kit this block sells.',
		} );
		expect( find( tree, 'ServerSideRender' ) ).toHaveLength( 0 );
		const wrapper = tree.children[ 1 ] as Element;
		expect( wrapper.type ).toBe( 'div' );
		expect( wrapper.props ).toEqual( {
			className: 'wp-block-creative-skateboard-builder-builder',
		} );
	} );

	it( "shows the server's render of the chosen kit", () => {
		const tree = edit( 7 ).render();
		const [ preview ] = find( tree, 'ServerSideRender' );
		expect( preview?.props ).toEqual( {
			block: NAME,
			attributes: { kit: 7 },
		} );
		expect( find( tree, 'Placeholder' ) ).toHaveLength( 0 );
		expect( find( tree, 'ComboboxControl' )[ 0 ]?.props.value ).toBe( '7' );
	} );

	it( "searches the shop's published products as the owner types, and lists each by its title as text", async () => {
		answers.next = () =>
			Promise.resolve( [
				{ id: 7, title: { rendered: 'Complete &amp; co' } },
				{ id: 9, title: { rendered: 'Cruiser' } },
			] );
		const block = edit( 0 );
		let tree = block.render();
		expect( asked ).toEqual( [
			{
				path: '/wp/v2/product?search=&per_page=20&_fields=id%2Ctitle&status=publish',
			},
		] );
		await settle();
		tree = block.render();
		expect( find( tree, 'ComboboxControl' )[ 0 ]?.props.options ).toEqual( [
			{ value: '7', label: 'Complete & co' },
			{ value: '9', label: 'Cruiser' },
		] );

		const typed = find( tree, 'ComboboxControl' )[ 0 ]?.props
			.onFilterValueChange as ( value: string ) => void;
		answers.next = () =>
			Promise.resolve( [ { id: 9, title: { rendered: 'Cruiser' } } ] );
		typed( 'cru' );
		tree = block.render();
		expect( asked.at( -1 ) ).toEqual( {
			path: '/wp/v2/product?search=cru&per_page=20&_fields=id%2Ctitle&status=publish',
		} );
		block.render();
		expect( asked ).toHaveLength( 2 );
	} );

	it( 'lists nothing when the search fails', async () => {
		answers.next = () =>
			Promise.resolve( [ { id: 7, title: { rendered: 'Complete' } } ] );
		const block = edit( 0 );
		block.render();
		await settle();
		const typed = find( block.render(), 'ComboboxControl' )[ 0 ]?.props
			.onFilterValueChange as ( value: string ) => void;
		answers.next = () => Promise.reject( new Error( 'offline' ) );
		typed( 'x' );
		block.render();
		await settle();
		expect(
			find( block.render(), 'ComboboxControl' )[ 0 ]?.props.options
		).toEqual( [] );
	} );

	it( 'keeps an answer that arrives after the owner typed again, or left, out of the list', async () => {
		let release: ( products: unknown ) => void = () => undefined;
		answers.next = () =>
			new Promise( ( done ) => {
				release = done;
			} );
		const block = edit( 0 );
		block.render();
		const late = release;
		const typed = find( block.render(), 'ComboboxControl' )[ 0 ]?.props
			.onFilterValueChange as ( value: string ) => void;
		answers.next = () =>
			Promise.resolve( [ { id: 9, title: { rendered: 'Cruiser' } } ] );
		typed( 'cru' );
		block.render();
		late( [ { id: 7, title: { rendered: 'Complete' } } ] );
		await settle();
		expect(
			find( block.render(), 'ComboboxControl' )[ 0 ]?.props.options
		).toEqual( [ { value: '9', label: 'Cruiser' } ] );

		let failed: ( error: Error ) => void = () => undefined;
		answers.next = () =>
			new Promise( ( _done, fail ) => {
				failed = fail;
			} );
		typed( 'gone' );
		block.render();
		block.unmount();
		failed( new Error( 'offline' ) );
		await settle();
		expect(
			find( block.render(), 'ComboboxControl' )[ 0 ]?.props.options,
			'nothing set after the block left'
		).toEqual( [ { value: '9', label: 'Cruiser' } ] );
	} );

	it( 'sets the kit the owner chooses, and none when the choice is cleared', () => {
		const chooser = find( edit( 0 ).render(), 'ComboboxControl' )[ 0 ];
		const change = chooser?.props.onChange as (
			value: string | null
		) => void;
		change( '7' );
		change( null );
		change( '' );
		expect( set ).toEqual( [ { kit: 7 }, { kit: 0 }, { kit: 0 } ] );
	} );
} );

describe( "a product's title", () => {
	it( 'is read as text, its entities written back', () => {
		expect( plain( 'Complete &amp; co &lt;b&gt;' ) ).toBe(
			'Complete & co <b>'
		);
		expect( plain( '' ) ).toBe( '' );
	} );
} );
