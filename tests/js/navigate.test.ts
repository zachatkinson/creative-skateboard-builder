/**
 * The builder's navigation (spec: How it fits, "debounced"; Resilience, "falls back to the form when the route
 * fails"; Quality bar, "a pick's own selected state painted within 100 ms"). Milestone 6's order of work, step 6: a
 * pick painted at once, the debounced request, stale answers, the pending state, the route failing, and history.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	Navigator,
	FRESH,
	HOLD,
	LIMIT,
	SLOW,
	WAIT,
	type Deps,
	type Move,
	type View,
} from '../../assets/src/builder/navigate';

const ENDPOINT = 'https://shop.example/wp-json/cbuild/v1/kits/7/state';

const LATE_WORDS = 'The shop did not answer in time. Try again in a moment.';

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
			late: LATE_WORDS,
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

	// The slow deck swap (M11, D-308): a pick made with a press, a tap or a click, asks at once; arrowing through the
	// radios still waits, so a run of arrows asks once.
	it( 'asks at once for a pick made by a press, and still waits for one made by the arrows', () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		nav.pick(
			{ fields: STEP, history: 'replace', fallback: () => undefined },
			true
		);
		expect( h.asked, 'no wait' ).toHaveLength( 1 );

		const arrows = harness();
		new Navigator( arrows.deps ).pick( {
			fields: STEP,
			history: 'replace',
			fallback: () => undefined,
		} );
		expect( arrows.asked ).toHaveLength( 0 );
		vi.advanceTimersByTime( WAIT );
		expect( arrows.asked ).toHaveLength( 1 );
	} );

	// Swap in once painted (D-308): the board's new image is decoded before the view is shown, so the old board stays
	// whole until the new one can take its place, never half drawn; the wait says it is pending, and is capped.
	it( 'shows an answer once the store has its pictures ready, waiting meanwhile, and at most HOLD ms', async () => {
		expect( HOLD ).toBe( 1000 );
		const h = harness();
		let ready: () => void = () => undefined;
		const settling: View[] = [];
		h.deps.settle = ( v ) => {
			settling.push( v );
			return new Promise< void >( ( resolve ) => {
				ready = resolve;
			} );
		};
		new Navigator( h.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => undefined,
		} );
		h.reply( answer( { view: view( 'https://shop.example/?deck' ) } ) );
		await settle();
		expect( settling.map( ( v ) => v.url ) ).toEqual( [
			'https://shop.example/?deck',
		] );
		expect( h.shown, 'not yet' ).toEqual( [] );
		vi.advanceTimersByTime( SLOW );
		expect( h.pending, 'the wait for the picture is a wait' ).toEqual( [
			true,
		] );
		ready();
		await settle();
		expect( h.shown.map( ( v ) => v.url ) ).toEqual( [
			'https://shop.example/?deck',
		] );
		expect( h.pending ).toEqual( [ true, false ] );

		const stuck = harness();
		stuck.deps.settle = () => new Promise< void >( () => undefined );
		new Navigator( stuck.deps ).go( {
			fields: STEP,
			history: 'push',
			fallback: () => undefined,
		} );
		stuck.reply(
			answer( { view: view( 'https://shop.example/?stuck' ) } )
		);
		await settle();
		vi.advanceTimersByTime( HOLD - 1 );
		await settle();
		expect( stuck.shown ).toEqual( [] );
		vi.advanceTimersByTime( 1 );
		await settle();
		expect(
			stuck.shown.map( ( v ) => v.url ),
			'never held longer'
		).toEqual( [ 'https://shop.example/?stuck' ] );
	} );

	it( 'drops an answer overtaken while its pictures were readied', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		const waits: Array< () => void > = [];
		h.deps.settle = () =>
			new Promise< void >( ( resolve ) => {
				waits.push( resolve );
			} );
		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		h.reply( answer( { view: view( 'https://shop.example/?first' ) } ) );
		await settle();
		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		h.reply( answer( { view: view( 'https://shop.example/?second' ) } ) );
		await settle();
		waits[ 0 ]?.();
		waits[ 1 ]?.();
		await settle();
		expect( h.shown.map( ( v ) => v.url ) ).toEqual( [
			'https://shop.example/?second',
		] );
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
	it( 'stops waiting once the route has not answered in LIMIT ms: keeps the view and says so, as past the rate limit (D-364)', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		nav.go( {
			fields: STEP,
			history: 'push',
			fallback: () => h.fallbacks.push( 'form' ),
		} );
		await vi.advanceTimersByTimeAsync( LIMIT - 1 );
		expect( h.inits[ 0 ]?.signal?.aborted ).toBe( false );
		expect( h.limited ).toEqual( [] );
		await vi.advanceTimersByTimeAsync( 1 );
		await settle();
		expect( h.inits[ 0 ]?.signal?.aborted, 'the request is given up' ).toBe(
			true
		);
		expect( h.limited ).toEqual( [ LATE_WORDS ] );
		expect( h.pending ).toEqual( [ true, false ] );
		expect( h.shown, 'no view, so no moment' ).toEqual( [] );
		expect( h.fallbacks ).toEqual( [] );
		expect( h.pushed ).toEqual( [] );
	} );

	// The auditor's second pass on M12, minor 8: a page cached before 1.2.0 has no words for the limit in its config,
	// so past it the form is sent, as past a 429 with nothing to say.
	it( 'sends the form past LIMIT where the page has no words for it', async () => {
		const h = harness();
		const nav = new Navigator( { ...h.deps, late: '' } );
		nav.go( {
			fields: STEP,
			history: 'push',
			fallback: () => h.fallbacks.push( 'form' ),
		} );
		await vi.advanceTimersByTimeAsync( LIMIT );
		await settle();
		expect( h.limited, 'nothing said' ).toEqual( [] );
		expect( h.fallbacks ).toEqual( [ 'form' ] );
		expect( h.pending ).toEqual( [ true, false ] );
	} );

	it( 'gives up nothing once the answer is in, nor a request a later one overtook', async () => {
		const h = harness();
		const nav = new Navigator( h.deps );
		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		await vi.advanceTimersByTimeAsync( LIMIT - 1 );
		h.reply( answer( { view: view( 'https://shop.example/build/?a' ) } ) );
		await settle();
		await vi.advanceTimersByTimeAsync( LIMIT );
		expect( h.inits[ 0 ]?.signal?.aborted ).toBe( false );
		expect( h.limited ).toEqual( [] );

		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		await vi.advanceTimersByTimeAsync( LIMIT - 1 );
		nav.go( { fields: STEP, history: 'push', fallback: () => undefined } );
		await vi.advanceTimersByTimeAsync( LIMIT - 1 );
		expect(
			h.inits[ 2 ]?.signal?.aborted,
			'the later request has its own time'
		).toBe( false );
		expect( h.limited ).toEqual( [] );
		await vi.advanceTimersByTimeAsync( 1 );
		await settle();
		expect( h.limited ).toEqual( [ LATE_WORDS ] );
	} );

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

	// Cowork's review of 1.1.0, F20 (#118; D-355): the store asks ahead for the step's lit Next, so the next step shows at
	// the press, not after a route that answers past CLS's 500 ms.
	describe( 'asked ahead', () => {
		const NEXT_STEP: Array< [ string, string ] > = [
			[ 'cbuild[deck]', '12' ],
			[ 'cbuild[step]', 'trucks' ],
		];
		const move = ( h: Harness, fields = NEXT_STEP ): Move => ( {
			fields,
			history: 'push',
			fallback: () => h.fallbacks.push( 'form' ),
		} );

		it( 'asks once, says and shows nothing, and the move made with the same fields takes its answer at once', async () => {
			expect( FRESH ).toBe( 60_000 );
			const h = harness();
			const navigator = new Navigator( h.deps );
			navigator.prefetch( NEXT_STEP );
			expect( h.asked ).toHaveLength( 1 );
			expect( h.asked[ 0 ]!.searchParams.get( 'cbuild[step]' ) ).toBe(
				'trucks'
			);
			h.reply(
				answer( { view: view( 'https://shop.example/?trucks' ) } )
			);
			await settle();
			vi.advanceTimersByTime( SLOW );
			expect( h.shown ).toEqual( [] );
			expect( h.pending ).toEqual( [] );
			expect( h.pushed ).toEqual( [] );

			navigator.go( move( h ) );
			await settle();
			expect( h.asked, 'nothing asked again' ).toHaveLength( 1 );
			expect( h.shown ).toEqual( [
				view( 'https://shop.example/?trucks' ),
			] );
			expect( h.pushed ).toEqual( [ 'https://shop.example/?trucks' ] );
			expect( h.pending ).toEqual( [ false ] );
		} );

		it( 'waits for an answer asked ahead and still on its way, rather than asking again', async () => {
			const h = harness();
			const navigator = new Navigator( h.deps );
			navigator.prefetch( NEXT_STEP );
			navigator.go( move( h ) );
			expect( h.asked ).toHaveLength( 1 );
			vi.advanceTimersByTime( SLOW );
			expect( h.pending, 'the press waits as any does' ).toEqual( [
				true,
			] );
			h.reply(
				answer( { view: view( 'https://shop.example/?trucks' ) } )
			);
			await settle();
			expect( h.shown ).toHaveLength( 1 );
			expect( h.pending ).toEqual( [ true, false ] );
		} );

		it( 'asks ahead once for the same fields while fresh, and again once past FRESH', () => {
			const h = harness();
			const navigator = new Navigator( h.deps );
			navigator.prefetch( NEXT_STEP );
			vi.advanceTimersByTime( FRESH - 1 );
			navigator.prefetch( NEXT_STEP );
			expect( h.asked ).toHaveLength( 1 );
			vi.advanceTimersByTime( 1 );
			navigator.prefetch( NEXT_STEP );
			expect( h.asked ).toHaveLength( 2 );
			navigator.prefetch( [ ...NEXT_STEP, [ 'cbuild[trucks]', '30' ] ] );
			expect( h.asked, 'other fields asked for at once' ).toHaveLength(
				3
			);
		} );

		it( 'asks the route again for a move past FRESH, or with other fields, and forgets what it asked ahead', async () => {
			const stale = harness();
			const late = new Navigator( stale.deps );
			late.prefetch( NEXT_STEP );
			stale.reply(
				answer( { view: view( 'https://shop.example/?stale' ) } )
			);
			await settle();
			vi.advanceTimersByTime( FRESH );
			late.go( move( stale ) );
			expect( stale.asked ).toHaveLength( 2 );

			const h = harness();
			const navigator = new Navigator( h.deps );
			navigator.prefetch( NEXT_STEP );
			h.reply(
				answer( { view: view( 'https://shop.example/?trucks' ) } )
			);
			await settle();
			navigator.go( move( h, [ [ 'cbuild[deck]', '11' ] ] ) );
			expect( h.asked ).toHaveLength( 2 );
			h.reply(
				answer( { view: view( 'https://shop.example/?other' ) } )
			);
			await settle();
			navigator.go( move( h ) );
			expect( h.asked, 'what was asked ahead is forgotten' ).toHaveLength(
				3
			);
		} );

		it( 'asks the route again where what it asked ahead was no view, or the rate limit', async () => {
			for ( const failed of [
				answer( null, 500 ),
				answer( { message: 'Try again in a minute.' }, 429 ),
			] ) {
				const h = harness();
				const navigator = new Navigator( h.deps );
				navigator.prefetch( NEXT_STEP );
				h.reply( failed );
				await settle();
				expect( h.limited, 'nothing said for it' ).toEqual( [] );
				navigator.go( move( h ) );
				await settle();
				expect( h.asked ).toHaveLength( 2 );
				h.reply(
					answer( { view: view( 'https://shop.example/?trucks' ) } )
				);
				await settle();
				expect( h.shown ).toEqual( [
					view( 'https://shop.example/?trucks' ),
				] );
				expect( h.fallbacks ).toEqual( [] );
			}
		} );

		it( 'lets no later move abort what it asked ahead', () => {
			const h = harness();
			const navigator = new Navigator( h.deps );
			navigator.prefetch( NEXT_STEP );
			navigator.go( move( h, [ [ 'cbuild[deck]', '11' ] ] ) );
			expect( h.inits[ 0 ]?.signal?.aborted ).toBe( false );
			navigator.go( move( h, [ [ 'cbuild[deck]', '13' ] ] ) );
			expect( h.inits[ 0 ]?.signal?.aborted ).toBe( false );
			expect( h.inits[ 1 ]?.signal?.aborted ).toBe( true );
		} );

		it( 'gives up what it asked ahead past LIMIT, so a move waiting for it stops waiting and says so (D-364)', async () => {
			const h = harness();
			const navigator = new Navigator( h.deps );
			navigator.prefetch( NEXT_STEP );
			navigator.go( move( h ) );
			expect(
				h.asked,
				'the move waits for what was asked ahead'
			).toHaveLength( 1 );
			await vi.advanceTimersByTimeAsync( LIMIT - 1 );
			expect( h.inits[ 0 ]?.signal?.aborted ).toBe( false );
			expect( h.limited ).toEqual( [] );
			await vi.advanceTimersByTimeAsync( 1 );
			await settle();
			expect(
				h.inits[ 0 ]?.signal?.aborted,
				'what was asked ahead is given up'
			).toBe( true );
			expect( h.limited ).toEqual( [ LATE_WORDS ] );
			expect( h.pending ).toEqual( [ true, false ] );
			expect( h.shown ).toEqual( [] );
			expect( h.fallbacks ).toEqual( [] );
		} );
	} );
} );
