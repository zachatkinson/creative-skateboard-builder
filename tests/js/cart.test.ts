/**
 * The builder's add with JavaScript (spec: How a build travels, "Adding to the cart posts the build to the Store
 * API's cart/add-item route with the build as item data"; D-155). The nonce is asked for when the shopper adds, never
 * printed in a page a cache may keep, and anything but a line added is a failure the form answers.
 */
import { describe, expect, it } from 'vitest';
import { add, body } from '../../assets/src/builder/cart';

const CART = 'https://shop.example/wp-json/wc/store/v1/cart';

const FIELDS: Array< [ string, string ] > = [
	[ 'add-to-cart', '7' ],
	[ 'cbuild[picks][deck]', '101' ],
	[ 'cbuild[picks][trucks]', '139' ],
	[ 'cbuild[picks][risers]', '0' ],
	[ 'cbuild[assembly]', '1' ],
	[ 'unrelated', 'x' ],
];

interface Asked {
	url: string;
	init: RequestInit | undefined;
}

function fetcher( ...responses: Array< Response | Error > ) {
	const asked: Asked[] = [];
	const fetch = ( url: string, init?: RequestInit ): Promise< Response > => {
		asked.push( { url, init } );
		const next = responses.shift();
		if ( undefined === next ) {
			throw new Error( 'Nothing more was expected.' );
		}
		return next instanceof Error
			? Promise.reject( next )
			: Promise.resolve( next );
	};
	return { asked, fetch };
}

function nonced( nonce: string | null, status = 200 ): Response {
	return new Response( '{}', {
		status,
		headers: null === nonce ? {} : { Nonce: nonce },
	} );
}

describe( 'the build as the Store API takes it', () => {
	it( "is the kit's ID, quantity one, and the picks and assembly under cbuild", () => {
		expect( body( FIELDS ) ).toEqual( {
			id: 7,
			quantity: 1,
			cbuild: {
				picks: { deck: 101, trucks: 139, risers: 0 },
				assembly: true,
			},
		} );
		expect(
			body( [
				[ 'add-to-cart', '7' ],
				[ 'cbuild[assembly]', '0' ],
			] )
		).toEqual( {
			id: 7,
			quantity: 1,
			cbuild: { picks: {}, assembly: false },
		} );
	} );

	it( 'leaves assembly out where the form does not say, for the kit to decide', () => {
		expect( body( [ [ 'add-to-cart', '7' ] ] ) ).toEqual( {
			id: 7,
			quantity: 1,
			cbuild: { picks: {} },
		} );
	} );

	it( 'takes as a pick only a field named as one, whole', () => {
		expect(
			body( [
				[ 'add-to-cart', '7' ],
				[ 'old-cbuild[picks][deck]', '101' ],
				[ 'cbuild[picks][deck][]', '102' ],
			] )
		).toEqual( { id: 7, quantity: 1, cbuild: { picks: {} } } );
	} );

	it( 'carries the build it replaces, where Edit build sent one', () => {
		expect(
			body( [
				[ 'add-to-cart', '7' ],
				[ 'cbuild[replace]', 'abc-123' ],
			] )
		).toEqual( {
			id: 7,
			quantity: 1,
			cbuild: { picks: {}, replace: 'abc-123' },
		} );
	} );
} );

describe( 'adding', () => {
	it( 'asks the cart for a nonce, then posts the build with it', async () => {
		const { asked, fetch } = fetcher(
			nonced( 'n0nce' ),
			new Response( '{}', { status: 201 } )
		);
		expect( await add( { cart: CART, fetch }, FIELDS ) ).toBe( true );
		expect( asked ).toHaveLength( 2 );
		expect( asked[ 0 ]!.url ).toBe( CART );
		expect( asked[ 0 ]!.init ).toMatchObject( {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		} );
		expect( asked[ 1 ]!.url ).toBe( CART + '/add-item' );
		expect( asked[ 1 ]!.init ).toMatchObject( {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				Nonce: 'n0nce',
			},
		} );
		expect( JSON.parse( String( asked[ 1 ]!.init?.body ) ) ).toEqual(
			body( FIELDS )
		);
	} );

	it( 'keeps a cart endpoint that is itself a query, as plain permalinks have it', async () => {
		const plain = 'https://shop.example/?rest_route=/wc/store/v1/cart';
		const { asked, fetch } = fetcher(
			nonced( 'n' ),
			new Response( '{}', { status: 201 } )
		);
		await add( { cart: plain, fetch }, FIELDS );
		expect( asked[ 1 ]!.url ).toBe(
			'https://shop.example/?rest_route=%2Fwc%2Fstore%2Fv1%2Fcart%2Fadd-item'
		);
	} );

	it( 'fails, posting nothing, when the cart gives no nonce or cannot be read', async () => {
		for ( const first of [
			nonced( null ),
			nonced( 'n', 500 ),
			new Error( 'offline' ),
		] ) {
			const { asked, fetch } = fetcher( first );
			expect( await add( { cart: CART, fetch }, FIELDS ) ).toBe( false );
			expect( asked ).toHaveLength( 1 );
		}
	} );

	it( 'fails when the Store API refuses the build or cannot be reached', async () => {
		for ( const second of [
			new Response( '{"code":"cbuild_build_refused"}', { status: 400 } ),
			new Error( 'offline' ),
		] ) {
			const { fetch } = fetcher( nonced( 'n' ), second );
			expect( await add( { cart: CART, fetch }, FIELDS ) ).toBe( false );
		}
	} );

	it( 'fails, asking nothing, with no cart to ask', async () => {
		const { asked, fetch } = fetcher();
		expect( await add( { cart: '', fetch }, FIELDS ) ).toBe( false );
		expect( asked ).toHaveLength( 0 );
	} );
} );
