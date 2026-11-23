/**
 * The builder's navigation (spec: How it fits, "debounced"; Resilience, "falls back to the form when the route
 * fails"; Quality bar, "a pick's own selected state painted within 100 ms"). Milestone 6's order of work, step 6: a
 * pick painted at once, the debounced request, stale answers, the pending state, the route failing, and history.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	Navigator,
	SLOW,
	WAIT,
	type Deps,
	type Move,
	type View,
} from '../../assets/src/builder/navigate';

const ENDPOINT = 'https://shop.example/wp-json/cbuild/v1/kits/7/state';

function view( url: string ): View {
	return { url, step: 'deck' };
}

function answer( body: unknown, status = 200 ): Response {
	return new Response( JSON.stringify( body ), {
		status,
		headers: { 'Content-Type': 'application/json' },
	} );
}

interface Harness {
	deps: Deps;
	shown: View[];
	pending: boolean[];
	fallbacks: string[];
	limited: string[];
	pushed: string[];
	replaced: string[];
	asked: URL[];
	inits: Array< RequestInit | undefined >;
	reply: ( response: Response | Error ) => void;
	/** How the store treats history for a view, where it says otherwise than the move. */
	turn?: ( view: View ) => Move[ 'history' ];
}

/**
 * A navigator whose route answers when the test says so, in the order asked.
 * @param endpoint
 */
function harness( endpoint = ENDPOINT ): Harness {
	const waiting: Array< {
		resolve: ( r: Response ) => void;
		reject: ( e: Error ) => void;
		signal: AbortSignal | undefined;
	} > = [];
	const h: Harness = {
		shown: [],
		pending: [],
		fallbacks: [],
		limited: [],
		pushed: [],
		replaced: [],
		asked: [],
		inits: [],
		reply: ( response ) => {
			const next = waiting.shift();
			if ( ! next ) {
				throw new Error( 'Nothing was asked.' );
			}
			if ( response instanceof Error ) {
				next.reject( response );
			} else {
				next.resolve( response );
			}
		},
		deps: {
			endpoint,
			page: 25,
			locale: 'fr_CA',
			fetch: ( input, init ) => {
				h.asked.push( new URL( String( input ) ) );
				h.inits.push( init );
				return new Promise( ( resolve, reject ) => {
					const signal = init?.signal ?? undefined;
					waiting.push( { resolve, reject, signal } );
					signal?.addEventListener( 'abort', () =>
						reject( new DOMException( 'Aborted', 'AbortError' ) )
					);
				} );
			},
			show: ( v, history ) => {
				h.shown.push( v );
				return h.turn ? h.turn( v ) : history;
			},
			pending: ( on ) => h.pending.push( on ),
			limited: ( message ) => h.limited.push( message ),
			push: ( url ) => h.pushed.push( url ),
			replace: ( url ) => h.replaced.push( url ),
		},
	};
	return h;
}

const STEP: Array< [ string, string ] > = [
	[ 'page_id', '25' ],
	[ 'cbuilder', 'not a build' ],
	[ 'cbuild[deck]', '12' ],
	[ 'cbuild[step]', 'trucks' ],
];

async function settle(): Promise< void > {
	for ( let i = 0; i < 5; i++ ) {
		await Promise.resolve();
	}
}

describe( 'Navigator', () => {
	beforeEach( () => {
		vi.useFakeTimers();
	} );
	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'asks the route for the build link alone, with the page and the language', () => {
		const h = harness();
		new Navigator( h.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => h.fallbacks.push( 'form' ),
		} );
		expect( h.asked ).toHaveLength( 1 );
		const asked = h.asked[ 0 ]!;
		expect( asked.origin + asked.pathname ).toBe( ENDPOINT );
		expect( [ ...asked.searchParams ] ).toEqual( [
			[ 'cbuild[deck]', '12' ],
			[ 'cbuild[step]', 'trucks' ],
			[ 'page', '25' ],
			[ 'locale', 'fr_CA' ],
			// The view alone: the store draws nothing else, and the raw shelf and options doubled the answer (#89).
			[ '_fields', 'view' ],
		] );
		expect( h.inits[ 0 ] ).toMatchObject( {
			headers: { Accept: 'application/json' },
			credentials: 'same-origin',
		} );
		expect( h.inits[ 0 ]?.signal ).toBeInstanceOf( AbortSignal );
	} );

	it( 'keeps an endpoint that is itself a query, as plain permalinks have it', () => {
		const h = harness(
			'https://shop.example/?rest_route=/cbuild/v1/kits/7/state'
		);
		new Navigator( h.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => undefined,
		} );
		const asked = h.asked[ 0 ]!;
		expect( asked.searchParams.get( 'rest_route' ) ).toBe(
			'/cbuild/v1/kits/7/state'
		);
		expect( asked.searchParams.get( 'cbuild[deck]' ) ).toBe( '12' );
	} );

	it( 'says it is waiting only once an answer has kept the shopper 300 ms, and never for a quick one (spec: Resilience)', async () => {
		expect( SLOW ).toBe( 300 );
		const slow = harness();
		new Navigator( slow.deps ).pick( {
			fields: STEP,
			history: 'replace',
			fallback: () => undefined,
		} );
		vi.advanceTimersByTime( SLOW - 1 );
		expect( slow.pending, 'counted from the pick' ).toEqual( [] );
		vi.advanceTimersByTime( 1 );
		expect( slow.pending ).toEqual( [ true ] );
		slow.reply( answer( { view: view( 'https://shop.example/?slow' ) } ) );
		await settle();
		expect( slow.pending ).toEqual( [ true, false ] );

		const quick = harness();
		new Navigator( quick.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => undefined,
		} );
		quick.reply(
			answer( { view: view( 'https://shop.example/?quick' ) } )
		);
		await settle();
		vi.advanceTimersByTime( SLOW );
		expect( quick.pending, 'no flicker' ).not.toContain( true );
	} );

	it( 'shows the answer and makes the step a history entry at the address the form would have made', async () => {
		const h = harness();
		new Navigator( h.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => h.fallbacks.push( 'form' ),
		} );
		expect(
			h.pending,
			'nothing said before the shopper has waited'
		).toEqual( [] );
		const next = view(
			'https://shop.example/?page_id=25&cbuild%5Bdeck%5D=12&cbuild%5Bstep%5D=trucks'
		);
		h.reply( answer( { step: 'trucks', view: next } ) );
		await settle();
		expect( h.shown ).toEqual( [ next ] );
		expect( h.pushed ).toEqual( [ next.url ] );
		expect( h.replaced ).toEqual( [] );
		expect( h.pending ).toEqual( [ false ] );
		expect( h.fallbacks ).toEqual( [] );
	} );

	it( 'makes a new entry for a view the store says is a step of its own, though a pick brought it', async () => {
		const h = harness();
		h.turn = ( v ) => ( v.url.includes( 'changes' ) ? 'push' : 'replace' );
		const navigator = new Navigator( h.deps );
		navigator.pick( {
			fields: STEP,
			history: 'replace',
			fallback: () => undefined,
		} );
		vi.advanceTimersByTime( WAIT );
		h.reply( answer( { view: view( 'https://shop.example/?changes' ) } ) );
		await settle();
		expect( h.pushed ).toEqual( [ 'https://shop.example/?changes' ] );
		expect( h.replaced ).toEqual( [] );

		navigator.pick( {
			fields: STEP,
			history: 'replace',
			fallback: () => undefined,
		} );
		vi.advanceTimersByTime( WAIT );
		h.reply( answer( { view: view( 'https://shop.example/?deck' ) } ) );
		await settle();
		expect( h.replaced ).toEqual( [ 'https://shop.example/?deck' ] );
	} );

	it( 'takes the address from the answer, which may have landed on another step than the one asked', async () => {
		const h = harness();
		new Navigator( h.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => undefined,
		} );
		h.reply(
			answer( {
				view: view( 'https://shop.example/?cbuild%5Bstep%5D=risers' ),
			} )
		);
		await settle();
		expect( h.pushed ).toEqual( [
			'https://shop.example/?cbuild%5Bstep%5D=risers',
		] );
	} );

	it( 'asks once for picks made in quick succession, 150 ms after the last, and replaces the history entry', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		const pick = ( id: string ) =>
			nav.pick( {
				fields: [ [ 'cbuild[deck]', id ] ],
				history: 'replace',
				fallback: () => undefined,
			} );
		pick( '10' );
		vi.advanceTimersByTime( 100 );
		pick( '11' );
		vi.advanceTimersByTime( 149 );
		expect( h.asked ).toHaveLength( 0 );
		expect( h.pending, 'one wait, from the first pick' ).toEqual( [] );
		vi.advanceTimersByTime( 51 );
		expect( h.pending ).toEqual( [ true ] );
		vi.advanceTimersByTime( 1 );
		expect( h.asked ).toHaveLength( 1 );
		expect( h.asked[ 0 ]!.searchParams.get( 'cbuild[deck]' ) ).toBe( '11' );
		h.reply(
			answer( {
				view: view( 'https://shop.example/?cbuild%5Bdeck%5D=11' ),
			} )
		);
		await settle();
		expect( h.replaced ).toEqual( [
			'https://shop.example/?cbuild%5Bdeck%5D=11',
		] );
		expect( h.pushed ).toEqual( [] );
	} );

	it( 'drops an answer a later request has overtaken, and aborts it', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		let aborted = false;
		const first = h.deps.fetch;
		h.deps.fetch = ( input, init ) => {
			init?.signal?.addEventListener( 'abort', () => {
				aborted = true;
			} );
			h.deps.fetch = first;
			return first( input, init );
		};
		nav.go( {
			fields: STEP,
			history: 'push',
			fallback: () => h.fallbacks.push( 'one' ),
		} );
		nav.go( {
			fields: STEP,
			history: 'push',
			fallback: () => h.fallbacks.push( 'two' ),
		} );
		expect( aborted ).toBe( true );
		h.reply( answer( { view: view( 'https://shop.example/?old' ) } ) );
		h.reply( answer( { view: view( 'https://shop.example/?new' ) } ) );
		await settle();
		expect( h.shown.map( ( v ) => v.url ) ).toEqual( [
			'https://shop.example/?new',
		] );
		expect( h.fallbacks ).toEqual( [] );
		expect( h.pending.at( -1 ) ).toBe( false );
	} );

	it( 'drops an answer that arrives after a later request though nothing aborted it', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		h.deps.fetch = ( ( input: RequestInfo | URL, init?: RequestInit ) => {
			h.asked.push( new URL( String( input ) ) );
			void init;
			return Promise.resolve(
				answer( {
					view: view( 'https://shop.example/?' + h.asked.length ),
				} )
			);
		} ) as Deps[ 'fetch' ];
		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		await settle();
		expect( h.shown.map( ( v ) => v.url ) ).toEqual( [
			'https://shop.example/?2',
		] );
	} );

	// Spec (configurator): Search, "After a 429 the step keeps its last results and says when to try again."
	it( 'keeps the view and says when to try again when the route says 429', async () => {
		const h = harness();
		new Navigator( h.deps ).go( {
			fields: STEP,
			history: 'replace',
			fallback: () => h.fallbacks.push( 'form' ),
		} );
		h.reply(
			answer(
				{
					code: 'cbuild_rate_limited',
					message: 'Too many requests. Try again in 20 seconds.',
				},
				429
			)
		);
		await settle();
		expect( h.limited ).toEqual( [
			'Too many requests. Try again in 20 seconds.',
		] );
		expect( h.fallbacks ).toEqual( [] );
		expect( h.shown ).toEqual( [] );
		expect( h.replaced ).toEqual( [] );
		expect( h.pending ).toEqual( [ false ] );
	} );

	// The auditor's M10 review, 19: a key typed in the search drops the answer already asked for, which would put the
	// search box back to the letters it was asked with.
	it( 'drops the answer it waits for once the shopper types on, and says it waits no more', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		nav.go( {
			fields: STEP,
			history: 'replace',
			fallback: () => h.fallbacks.push( 'sent' ),
		} );
		nav.drop();
		h.reply( answer( { view: view( 'https://shop.example/?b' ) } ) );
		await settle();
		expect( h.shown ).toEqual( [] );
		expect( h.fallbacks ).toEqual( [] );
		expect( h.pending.at( -1 ) ).toBe( false );
	} );

	it( 'asks nothing for a waiting pick once the shopper types on, and never says it waits', () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		nav.pick( {
			fields: [ [ 'cbuild[deck]', '10' ] ],
			history: 'replace',
			fallback: () => undefined,
		} );
		nav.drop();
		vi.advanceTimersByTime( SLOW + WAIT );
		expect( h.asked ).toEqual( [] );
		expect( h.pending ).toEqual( [ false ] );
	} );

	it( 'never says it waits for a pick the route answers quickly', async () => {
		const h = harness();
		new Navigator( h.deps ).pick( {
			fields: [ [ 'cbuild[deck]', '10' ] ],
			history: 'replace',
			fallback: () => undefined,
		} );
		vi.advanceTimersByTime( WAIT );
		h.reply( answer( { view: view( 'https://shop.example/?quick' ) } ) );
		await settle();
		vi.advanceTimersByTime( SLOW );
		expect( h.pending ).toEqual( [ false ] );
	} );

	it( 'cancels a waiting pick when the shopper moves on', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		nav.pick( {
			fields: [ [ 'cbuild[deck]', '10' ] ],
			history: 'replace',
			fallback: () => undefined,
		} );
		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		vi.advanceTimersByTime( 500 );
		expect( h.asked ).toHaveLength( 1 );
		expect( h.asked[ 0 ]!.searchParams.get( 'cbuild[step]' ) ).toBe(
			'trucks'
		);
	} );

	it.each( [
		[ 'the network fails', () => new TypeError( 'Failed to fetch' ) ],
		[
			'the route says 429 with nothing to say',
			() => answer( { code: 'cbuild_rate_limited' }, 429 ),
		],
		[
			'the route says 429 with an empty message',
			() => answer( { code: 'cbuild_rate_limited', message: '' }, 429 ),
		],
		[
			'the route says 429 with a message that is no text',
			() => answer( { code: 'cbuild_rate_limited', message: 20 }, 429 ),
		],
		[
			'the route says 429 with null',
			() => new Response( 'null', { status: 429 } ),
		],
		[
			'the route says 429 with text',
			() => answer( 'Too many requests.', 429 ),
		],
		[
			'the route says 500',
			() => answer( { code: 'cbuild_no_price' }, 500 ),
		],
		[ 'the answer has no view', () => answer( { step: 'deck' } ) ],
		[
			'the answer is no JSON',
			() => new Response( '<html>', { status: 200 } ),
		],
		[ 'the answer is null', () => new Response( 'null', { status: 200 } ) ],
		[
			'the route refuses with a view in its body',
			() => answer( { view: view( 'https://shop.example/?x' ) }, 500 ),
		],
		[ 'the view is null', () => answer( { view: null } ) ],
		[
			'the view is text',
			() => answer( { view: 'https://shop.example/' } ),
		],
		[
			'the view has no address',
			() => answer( { view: { step: 'deck' } } ),
		],
		[
			"the view's address is no text",
			() => answer( { view: { url: 5 } } ),
		],
	] )( 'falls back to the form when %s', async ( _name, response ) => {
		const h = harness();
		new Navigator( h.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => h.fallbacks.push( 'form' ),
		} );
		h.reply( response() );
		await settle();
		expect( h.fallbacks ).toEqual( [ 'form' ] );
		expect( h.shown ).toEqual( [] );
		expect( h.pushed ).toEqual( [] );
		expect( h.pending ).toEqual( [ false ] );
	} );

	it( 'shows the build the address holds when the shopper goes back, making no new entry', async () => {
		const h = harness();
		new Navigator( h.deps ).go( {
			fields: [
				...new URLSearchParams(
					'page_id=25&cbuild%5Bdeck%5D=12&cbuild%5Bstep%5D=trucks'
				),
			],
			fallback: () => h.fallbacks.push( 'reload' ),
		} );
		h.reply( answer( { view: view( 'https://shop.example/?x' ) } ) );
		await settle();
		expect( h.shown ).toHaveLength( 1 );
		expect( h.pushed ).toEqual( [] );
		expect( h.replaced ).toEqual( [] );
	} );

	it( 'asks for the clean page when the address holds no build', () => {
		const h = harness();
		new Navigator( h.deps ).go( {
			fields: [ [ 'page_id', '25' ] ],
			fallback: () => undefined,
		} );
		expect( h.asked[ 0 ]!.searchParams.get( 'cbuild' ) ).toBe( '' );
	} );
} );
