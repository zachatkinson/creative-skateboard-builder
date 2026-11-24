/**
 * The builder's store (D-148): private and locked; a step's form, a pick, a link and Back each ask the state route
 * and show its view, and each does what it does without JavaScript when the route fails.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setConfig, setElement, stores } from './support/interactivity';
import {
	INTENT,
	NAME,
	SETTLE,
	browser,
	reset,
} from '../../assets/src/builder/view';
import { HOLD, SLOW, WAIT } from '../../assets/src/builder/navigate';

interface Actions {
	submit: ( event: SubmitEvent ) => void;
	add: ( event: SubmitEvent ) => Promise< void >;
	copy: () => Promise< void >;
	turn: () => void;
	reveal: ( event: FocusEvent ) => void;
	pick: ( event: Event ) => void;
	search: ( event: Event ) => void;
	narrow: ( event: Event ) => void;
	fit: () => void;
	follow: ( event: MouseEvent ) => void;
	back: () => void;
	filters: () => void;
	point: ( event: PointerEvent ) => void;
	leave: () => void;
}

interface Defined {
	state: {
		view: Record< string, unknown >;
		pending: boolean;
		status: string;
		added: boolean;
		canCopy: boolean;
		canTurn: boolean;
		turning: boolean;
		folded: boolean;
		live: boolean;
		limited: string;
		drawing: boolean;
		missing: string;
		readonly dimmed: boolean;
	};
	actions: Actions;
}

const defined = stores.get( NAME )!;
const { state, actions } = defined.definition as Defined;
const ENDPOINT = window.location.origin + '/wp-json/cbuild/v1/kits/7/state';
const START = {
	url: window.location.origin + '/?page_id=25',
	step: { slot: 'deck' },
	title: 'Step 1 of 7: Deck',
	status: '',
	isChanges: false,
	errors: { shown: false },
};
const NEXT = {
	url: window.location.origin + '/?next',
	step: { slot: 'trucks' },
	title: 'Step 2 of 7: Trucks',
	status: 'A deck with trucks. Total: $97.00.',
	isChanges: false,
	errors: { shown: false },
};
const TITLE = { before: '', after: ' - Build a complete - Shop' };
const CART = window.location.origin + '/wp-json/wc/store/v1/cart';
const REVIEW = {
	...START,
	url: window.location.origin + '/?review',
	step: { slot: 'review' },
	review: {
		added: 'The build is in your cart.',
		copied: 'Link copied.',
		link: window.location.origin + '/?cbuild%5Bstep%5D=review',
	},
};

function answer( view: unknown, status = 200 ): Response {
	return new Response( JSON.stringify( { view } ), {
		status,
		headers: { 'Content-Type': 'application/json' },
	} );
}

function form(): {
	form: HTMLFormElement;
	next: HTMLButtonElement;
	radio: HTMLInputElement;
} {
	document.body.innerHTML = `
		<form method="get" action="/">
			<input type="hidden" name="page_id" value="25">
			<input type="hidden" name="cbuild[trucks]" value="30">
			<input type="radio" name="cbuild[deck]" value="11">
			<input type="radio" name="cbuild[deck]" value="12" checked>
			<input type="text" name="note" value="x">
			<input type="file" name="cbuild[photo]">
			<button type="submit" name="cbuild[step]" value="trucks">Next</button>
		</form>`;
	return {
		form: document.querySelector( 'form' )!,
		next: document.querySelector( 'button' )!,
		radio: document.querySelectorAll< HTMLInputElement >(
			'input[type=radio]'
		)[ 1 ]!,
	};
}

async function settle(): Promise< void > {
	for ( let i = 0; i < 6; i++ ) {
		await Promise.resolve();
	}
}

let fetched: URL[];
let reply: () => Response | Promise< Response >;

/**
 * Put what the test has made into a builder, as the page has it: the store's regions are pieces inside one (D-296).
 */
function wrapped(): HTMLElement {
	const root = document.createElement( 'div' );
	root.className = 'cbuild';
	root.append( ...document.body.childNodes );
	document.body.append( root );
	return root;
}

beforeEach( () => {
	vi.useFakeTimers();
	reset();
	setConfig( NAME, {
		cart: CART,
		endpoint: ENDPOINT,
		page: 25,
		locale: 'fr_CA',
		title: TITLE,
	} );
	state.view = START;
	state.pending = false;
	state.status = '';
	state.added = false;
	state.missing = '';
	document.title = 'Build a complete - Shop';
	fetched = [];
	reply = () => answer( NEXT );
	vi.stubGlobal( 'fetch', ( input: string ) => {
		fetched.push( new URL( input ) );
		return Promise.resolve().then( reply );
	} );
	window.history.replaceState( null, '', '/?page_id=25' );
} );

afterEach( () => {
	vi.useRealTimers();
	vi.unstubAllGlobals();
	vi.restoreAllMocks();
	setElement( null );
} );

describe( 'the store', () => {
	// Spec (configurator): Phone, "The filters sit behind a Filters button, so the cards come first."
	it( 'folds the filters behind the Filters button once the script runs, and the button opens and closes them', () => {
		state.folded = false;
		document.body.innerHTML = '<div class="cbuild"></div>';
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		expect( state.folded ).toBe( true );
		actions.filters();
		expect( state.folded ).toBe( false );
		actions.filters();
		expect( state.folded ).toBe( true );
	} );

	// Chips and sorts ask the route as they change, so their Apply buttons, there for the page without the script, hide.
	it( 'hides the Apply buttons once the script runs, and starts with no builder found without throwing', () => {
		state.live = false;
		document.body.innerHTML = '<div class="cbuild"></div>';
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		const init = (
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init;
		init();
		expect( state.live ).toBe( true );

		state.live = false;
		setElement( null );
		expect( () => init() ).not.toThrow();
		expect( state.live ).toBe( true );
	} );

	it( 'goes to an address as the browser would without JavaScript', () => {
		browser.assign( window.location.origin + '/?page_id=25#cbuild' );
		expect( window.location.hash ).toBe( '#cbuild' );
	} );

	it( 'is private and locked', () => {
		expect( defined.options ).toEqual( { lock: true } );
		expect( NAME ).toBe( 'creative-skateboard-builder' );
	} );

	it( "sends a step's form to the route, the button pressed among its fields, and shows its view", async () => {
		const { form: step, next } = form();
		setElement( step );
		const pushed = vi.spyOn( window.history, 'pushState' );
		const event = new SubmitEvent( 'submit', {
			submitter: next,
			cancelable: true,
		} );
		actions.submit( event );
		expect( event.defaultPrevented ).toBe( true );
		expect( state.pending, 'not before the shopper has waited' ).toBe(
			false
		);
		vi.advanceTimersByTime( SLOW );
		expect( state.pending ).toBe( true );
		expect( fetched ).toHaveLength( 1 );
		expect( fetched[ 0 ]!.origin + fetched[ 0 ]!.pathname ).toBe(
			ENDPOINT
		);
		expect( [ ...fetched[ 0 ]!.searchParams ] ).toEqual( [
			[ 'cbuild[trucks]', '30' ],
			[ 'cbuild[deck]', '12' ],
			[ 'cbuild[step]', 'trucks' ],
			[ 'page', '25' ],
			[ 'locale', 'fr_CA' ],
			[ '_fields', 'view' ],
		] );
		await settle();
		expect( state.view ).toEqual( NEXT );
		expect( state.pending ).toBe( false );
		expect( pushed ).toHaveBeenCalledWith(
			null,
			'',
			window.location.origin + '/?next'
		);
	} );

	// Found by the browser test of Clear search (spec review S-b): Enter sent the search at once, and the search still
	// waiting from the same keys went out after a button pressed within 150 ms, so Clear search was undone.
	it( 'drops a search still waiting when the form is sent, so a button pressed after it is the last word', async () => {
		document.body.innerHTML = `
			<form method="get" action="/">
				<input type="hidden" name="page_id" value="25">
				<input type="search" name="cbuild[_q]" value="Zebracorn">
				<button type="submit" name="cbuild[_q]" value="">Clear search</button>
			</form>`;
		const step = document.querySelector( 'form' )!;
		const box = document.querySelector( 'input[type=search]' )!;
		const clear = document.querySelector( 'button' )!;
		setElement( step );
		actions.search( { target: box } as unknown as Event );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: clear, cancelable: true } )
		);
		vi.advanceTimersByTime( 1000 );
		await settle();
		expect( fetched ).toHaveLength( 1 );
		expect(
			fetched[ 0 ]!.searchParams.getAll( 'cbuild[_q]' ).at( -1 )
		).toBe( '' );
	} );

	it( 'sends the form as it would be sent without JavaScript when the route fails, the button pressed in it', async () => {
		const { form: step, next } = form();
		setElement( step );
		reply = () => answer( null, 503 );
		const sent = vi
			.spyOn( browser, 'submit' )
			.mockImplementation( () => undefined );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		await settle();
		// The button travels as the browser sends it, so no field is made for it (audit, spec review S11).
		expect( sent ).toHaveBeenCalledWith( step, next );
		expect( step.querySelectorAll( 'input[type=hidden]' ) ).toHaveLength(
			2
		);
		expect( state.view ).toBe( START );
	} );

	it( 'sends a form through the browser with the button pressed, and its own handlers stand aside while it does', () => {
		const { form: step, next } = form();
		setElement( step );
		const seen: boolean[] = [];
		const request = vi
			.spyOn( HTMLFormElement.prototype, 'requestSubmit' )
			.mockImplementation( function ( this: HTMLFormElement ) {
				// What the browser does: the submit event, then the request unless a handler stops it.
				for ( const handler of [ actions.submit, actions.add ] ) {
					const event = new SubmitEvent( 'submit', {
						submitter: next,
						cancelable: true,
					} );
					void handler( event );
					seen.push( event.defaultPrevented );
				}
			} );
		browser.submit( step, next );
		expect( request ).toHaveBeenCalledOnce();
		expect( request.mock.contexts[ 0 ] ).toBe( step );
		expect( request.mock.calls[ 0 ] ).toEqual( [ next ] );
		expect( seen ).toEqual( [ false, false ] );
		expect( fetched ).toHaveLength( 0 );

		// Once sent, the handlers are the store's again.
		const after = new SubmitEvent( 'submit', {
			submitter: next,
			cancelable: true,
		} );
		actions.submit( after );
		expect( after.defaultPrevented ).toBe( true );
	} );

	it( 'sends a form with no button pressed, or one that is not its own, by the form alone', () => {
		const { form: step } = form();
		const request = vi
			.spyOn( HTMLFormElement.prototype, 'requestSubmit' )
			.mockImplementation( () => undefined );
		browser.submit( step, null );
		const stranger = document.createElement( 'button' );
		browser.submit( step, stranger );
		expect( request.mock.calls ).toEqual( [
			[ undefined ],
			[ undefined ],
		] );
	} );

	it( 'leaves a submit on anything but a form alone', () => {
		document.body.innerHTML = '<div></div>';
		setElement( document.querySelector( 'div' ) );
		const event = new SubmitEvent( 'submit', { cancelable: true } );
		actions.submit( event );
		expect( event.defaultPrevented ).toBe( false );
		expect( fetched ).toHaveLength( 0 );
	} );

	it( 'asks for the same step with a pick once the shopper stops picking, and replaces the history entry', async () => {
		const { radio } = form();
		const replaced = vi.spyOn( window.history, 'replaceState' );
		radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		actions.pick( { target: radio } as unknown as Event );
		expect( state.pending ).toBe( false );
		vi.advanceTimersByTime( WAIT );
		expect( fetched ).toHaveLength( 1 );
		expect( [ ...fetched[ 0 ]!.searchParams ] ).toEqual( [
			[ 'cbuild[trucks]', '30' ],
			[ 'cbuild[deck]', '12' ],
			[ 'cbuild[step]', 'deck' ],
			[ 'page', '25' ],
			[ 'locale', 'fr_CA' ],
			[ '_fields', 'view' ],
		] );
		await settle();
		expect( replaced ).toHaveBeenCalledWith(
			null,
			'',
			window.location.origin + '/?next'
		);
	} );

	// The slow deck swap (M11, D-308; NN/g's response limits, Baymard's loading feedback): the board's new image is
	// fetched while the pointer rests on the deck's card, a pick by a press asks at once, the view is shown once the
	// image is decoded, and the picture says it is being drawn once the shopper has waited SLOW for a pick.
	describe( 'the deck swap', () => {
		const PRELOAD = {
			shown: true,
			src: 'https://shop.example/12.png',
			avif: 'https://shop.example/12-216x813.avif 216w',
			webp: 'https://shop.example/12-216x813.webp 216w',
			sizes: '(min-height: 960px) 153px, 15.95svh',
		};
		let made: Array< {
			src: string;
			srcset: string;
			sizes: string;
			decoded: () => void;
		} >;

		/**
		 * The deck step's cards, the second with a board image, and the page's board drawing the first deck in a type.
		 *
		 * @param type The stage image's type, as the browser chose it.
		 */
		function deckCards( type: string ): HTMLElement {
			const { radio } = form();
			const card = document.createElement( 'li' );
			card.append( radio.cloneNode() );
			document.querySelector( 'form' )!.append( card );
			const stage = document.createElement( 'div' );
			stage.innerHTML = `<figure class="cbuild-picture"><div class="cbuild-board"><picture><img class="cbuild-board__image" src="https://shop.example/11.${ type }"></picture></div></figure>`;
			document.body.append( stage );
			wrapped();
			Object.defineProperty(
				document.querySelector( '.cbuild-board__image' )!,
				'currentSrc',
				{ value: `https://shop.example/11-216x813.${ type }` }
			);
			state.view = {
				...START,
				step: {
					slot: 'deck',
					options: [
						{
							id: 11,
							value: '11',
							preload: { ...PRELOAD, shown: false },
						},
						{ id: 12, value: '12', preload: PRELOAD },
					],
				},
				picture: {
					turnable: false,
					turned: false,
					board: {
						layers: [
							{
								key: 'deck-1',
								style: '',
								src: 'https://shop.example/11.png',
							},
						],
					},
				},
			};
			return document.querySelectorAll< HTMLElement >( 'li' )[ 0 ]!;
		}

		beforeEach( () => {
			made = [];
			vi.stubGlobal(
				'Image',
				class {
					src = '';
					srcset = '';
					sizes = '';
					decoding = '';
					constructor() {
						const self = this;
						made.push( {
							get src() {
								return self.src;
							},
							get srcset() {
								return self.srcset;
							},
							get sizes() {
								return self.sizes;
							},
							decoded: () => undefined,
						} );
					}
					decode(): Promise< void > {
						return new Promise( ( resolve ) => {
							made.at( -1 )!.decoded = resolve;
						} );
					}
				}
			);
		} );

		it( "fetches a deck's board image once the pointer rests on its card, in the type the stage draws, and once only", () => {
			setElement( deckCards( 'avif' ) );
			actions.point( { type: 'pointerenter' } as PointerEvent );
			vi.advanceTimersByTime( INTENT - 1 );
			expect( made, 'a pointer passing over fetches nothing' ).toEqual(
				[]
			);
			actions.leave();
			vi.advanceTimersByTime( INTENT );
			expect( made ).toEqual( [] );

			actions.point( { type: 'pointerenter' } as PointerEvent );
			vi.advanceTimersByTime( INTENT );
			expect(
				made.map( ( image ) => [
					image.srcset,
					image.sizes,
					image.src,
				] )
			).toEqual( [ [ PRELOAD.avif, PRELOAD.sizes, PRELOAD.src ] ] );
			actions.point( { type: 'pointerdown' } as PointerEvent );
			expect( made, 'fetched once' ).toHaveLength( 1 );
		} );

		it( 'fetches WebP where the stage draws WebP, and nothing for a deck with no board image', () => {
			setElement( deckCards( 'webp' ) );
			actions.point( { type: 'pointerdown' } as PointerEvent );
			expect(
				made.map( ( image ) => image.srcset ),
				'a press fetches at once'
			).toEqual( [ PRELOAD.webp ] );

			state.view = {
				...state.view,
				step: {
					slot: 'deck',
					options: [
						{
							id: 12,
							value: '12',
							preload: { ...PRELOAD, shown: false },
						},
					],
				},
			};
			reset();
			made = [];
			actions.point( { type: 'pointerdown' } as PointerEvent );
			expect( made ).toEqual( [] );
		} );

		it( 'asks at once for a pick made by a press, and waits for one made by the arrows', () => {
			const card = deckCards( 'webp' );
			setElement( card );
			const radio = card.querySelector( 'input' )!;
			actions.point( { type: 'pointerdown' } as PointerEvent );
			actions.pick( { target: radio } as unknown as Event );
			expect( fetched, 'no wait' ).toHaveLength( 1 );

			vi.advanceTimersByTime( 2000 );
			actions.pick( { target: radio } as unknown as Event );
			expect( fetched, 'the arrows still wait' ).toHaveLength( 1 );
			vi.advanceTimersByTime( WAIT );
			expect( fetched ).toHaveLength( 2 );
		} );

		it( "shows a new deck's view once its board image is decoded, and says the picture is being drawn meanwhile", async () => {
			const card = deckCards( 'webp' );
			setElement( card );
			const radio = card.querySelector( 'input' )!;
			reply = () =>
				answer( {
					...START,
					url: window.location.origin + '/?deck=12',
					picture: {
						turnable: false,
						turned: false,
						board: {
							layers: [
								{
									key: 'deck-1',
									style: '',
									src: PRELOAD.src,
									sizes: PRELOAD.sizes,
									sources: [
										{
											type: 'image/avif',
											srcset: PRELOAD.avif,
										},
										{
											type: 'image/webp',
											srcset: PRELOAD.webp,
										},
									],
								},
							],
						},
					},
				} );
			actions.point( { type: 'pointerdown' } as PointerEvent );
			actions.pick( { target: radio } as unknown as Event );
			await settle();
			expect( state.view.url, 'held for the image' ).toBe( START.url );
			vi.advanceTimersByTime( SLOW );
			expect( state.drawing ).toBe( true );
			expect( made, 'the image the press fetched' ).toHaveLength( 1 );
			made[ 0 ]!.decoded();
			await settle();
			expect( state.view.url ).toBe(
				window.location.origin + '/?deck=12'
			);
			expect( state.drawing ).toBe( false );
			expect( HOLD ).toBeGreaterThan( SLOW );
		} );

		it( 'never says the picture is drawn for a wait that is no pick', async () => {
			link( '/?page_id=25&cbuild%5Bstep%5D=trucks' );
			reply = () => new Promise< Response >( () => undefined );
			actions.follow(
				new MouseEvent( 'click', { cancelable: true, button: 0 } )
			);
			vi.advanceTimersByTime( SLOW );
			await settle();
			expect( state.pending ).toBe( true );
			expect( state.drawing ).toBe( false );
		} );
	} );

	// #47: the card binds its radio's checked to the store, which said unchecked until the answer, so a re-render of the
	// card while the route was held put a pick back to unchecked (CI run 43997746452, webkit-desktop).
	it( 'marks the pick checked in the store before it asks, and the other cards and No risers not', () => {
		const { radio } = form();
		state.view = {
			...START,
			step: {
				slot: 'deck',
				options: [
					{ value: '11', checked: true },
					{ value: '12', checked: false },
				],
				leave: { checked: true },
			},
		};
		actions.pick( { target: radio } as unknown as Event );
		const step = state.view.step as {
			options: { value: string; checked: boolean }[];
			leave: { checked: boolean };
		};
		expect( step.options.map( ( option ) => option.checked ) ).toEqual( [
			false,
			true,
		] );
		expect( step.leave.checked ).toBe( false );
		expect( fetched, 'before the route is asked' ).toHaveLength( 0 );

		radio.value = '0';
		actions.pick( { target: radio } as unknown as Event );
		expect( step.options.map( ( option ) => option.checked ) ).toEqual( [
			false,
			false,
		] );
		expect( step.leave.checked, 'No risers picked' ).toBe( true );
	} );

	// D-279: a size picked after the card is a pick of the step, so its card stays picked and the size is marked.
	it( 'marks a size picked after its card in the store, and keeps the card picked', () => {
		const { radio } = form();
		state.view = {
			...START,
			step: {
				slot: 'wheels',
				options: [
					{
						value: '302',
						checked: true,
						sizeGroup: {
							inputs: [
								{ value: '301', checked: false },
								{ value: '302', checked: true },
							],
						},
					},
					{ value: '303', checked: false, sizeGroup: { inputs: [] } },
				],
			},
		};
		radio.value = '301';
		actions.pick( { target: radio } as unknown as Event );
		const step = state.view.step as {
			options: {
				checked: boolean;
				sizeGroup: { inputs: { checked: boolean }[] };
			}[];
		};
		expect( step.options.map( ( option ) => option.checked ) ).toEqual( [
			true,
			false,
		] );
		expect(
			step.options[ 0 ]?.sizeGroup.inputs.map(
				( input ) => input.checked
			)
		).toEqual( [ true, false ] );
	} );

	it( 'marks a card unpicked when none of its sizes is the one picked', () => {
		const { radio } = form();
		state.view = {
			...START,
			step: {
				slot: 'wheels',
				options: [
					{
						value: '302',
						checked: true,
						sizeGroup: {
							inputs: [
								{ value: '302', checked: true },
								{ value: '304', checked: false },
							],
						},
					},
				],
			},
		};
		radio.value = '301';
		actions.pick( { target: radio } as unknown as Event );
		const card = (
			state.view.step as {
				options: {
					checked: boolean;
					sizeGroup: { inputs: { checked: boolean }[] };
				}[];
			}
		 ).options[ 0 ]!;
		expect( card.checked ).toBe( false );
		expect(
			card.sizeGroup.inputs.map( ( input ) => input.checked )
		).toEqual( [ false, false ] );
	} );

	it( 'leaves anything in the list that is not a card alone, and a step with no No risers', () => {
		const { radio } = form();
		const card = { value: '12', checked: false };
		state.view = {
			...START,
			step: { slot: 'deck', options: [ null, 3, card ], leave: null },
		};
		expect( () =>
			actions.pick( { target: radio } as unknown as Event )
		).not.toThrow();
		expect( card.checked ).toBe( true );
		expect( ( state.view.step as { leave: unknown } ).leave ).toBeNull();
	} );

	it( "keeps the pick when the route fails, for the form's Next to send", async () => {
		const { radio } = form();
		reply = () => answer( null, 429 );
		const sent = vi
			.spyOn( browser, 'submit' )
			.mockImplementation( () => undefined );
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( sent ).not.toHaveBeenCalled();
		expect( radio.checked ).toBe( true );
		expect( state.pending ).toBe( false );
	} );

	/**
	 * The deck step with nothing picked, as the server draws it: the footer's Next not ready, and what it would say.
	 */
	const EMPTY = {
		...START,
		step: {
			slot: 'deck',
			options: [ { value: '11', checked: false } ],
		},
		bar: {
			next: { ready: false, missing: 'Choose your deck first.' },
		},
	};

	/**
	 * The step's form with the footer's Next as the bar draws it, aria-disabled bound once the script runs.
	 *
	 * @param dimmed What the bar's Next says it is: dimmed or not.
	 */
	function dimmedForm( dimmed: boolean ): ReturnType< typeof form > {
		const made = form();
		made.next.setAttribute( 'aria-disabled', String( dimmed ) );
		made.radio.checked = false;
		setElement( made.form );
		return made;
	}

	// #100, F15: Next is dimmed until the step has a pick, and only once the script runs: without it the form is
	// sent and the server answers as before, so nothing is dimmed that would still work.
	it( "dims the footer's Next until the step has a pick, only once the script runs", () => {
		state.view = EMPTY;
		state.live = false;
		expect( state.dimmed, 'without the script' ).toBe( false );
		state.live = true;
		expect( state.dimmed ).toBe( true );
		state.view = {
			...EMPTY,
			bar: { next: { ready: true, missing: '' } },
		};
		expect( state.dimmed, 'the step has a pick' ).toBe( false );
		state.view = START;
		expect( state.dimmed, 'a view with no footer' ).toBe( false );
	} );

	it( 'says what the step needs beside a dimmed Next when it is pressed, and sends nothing', async () => {
		state.view = EMPTY;
		state.live = true;
		const { next } = dimmedForm( true );
		const sent = vi
			.spyOn( browser, 'submit' )
			.mockImplementation( () => undefined );
		const event = new SubmitEvent( 'submit', {
			submitter: next,
			cancelable: true,
		} );
		actions.submit( event );
		expect( event.defaultPrevented ).toBe( true );
		expect( state.missing ).toBe( 'Choose your deck first.' );
		// Said once, in the builder's one status region: the words beside Next are its description, not a second region.
		expect( state.status ).toBe( 'Choose your deck first.' );
		vi.advanceTimersByTime( SLOW );
		await settle();
		expect( fetched, 'no error summary asked for' ).toHaveLength( 0 );
		expect( sent ).not.toHaveBeenCalled();
		expect( state.view ).toBe( EMPTY );
	} );

	// A polite live region says nothing when its text is set to what it already holds: a second press on the dimmed
	// Next empties the region, and says the words again on the next frame (M11 audit, finding 6).
	it( 'says what the step needs again when the dimmed Next is pressed a second time', () => {
		state.view = EMPTY;
		state.live = true;
		const { next } = dimmedForm( true );
		const press = () =>
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: next,
					cancelable: true,
				} )
			);
		press();
		expect( state.status ).toBe( 'Choose your deck first.' );
		press();
		expect( state.status, 'emptied, so the region hears a change' ).toBe(
			''
		);
		vi.advanceTimersToNextFrame();
		expect( state.status ).toBe( 'Choose your deck first.' );
		expect( state.missing ).toBe( 'Choose your deck first.' );
	} );

	it( 'leaves the status alone when a view came before the frame after a second press', () => {
		state.view = EMPTY;
		state.live = true;
		const { next } = dimmedForm( true );
		const press = () =>
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: next,
					cancelable: true,
				} )
			);
		press();
		press();
		// Something else spoke first, such as a view's answer: the frame does not say the old words over it.
		state.status = 'A deck. Total: $70.00.';
		vi.advanceTimersToNextFrame();
		expect( state.status ).toBe( 'A deck. Total: $70.00.' );
	} );

	it( 'lights Next as soon as the shopper picks, before the route answers, and forgets what it said', async () => {
		state.view = structuredClone( EMPTY );
		state.live = true;
		const { next, radio } = dimmedForm( true );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		expect( state.missing ).not.toBe( '' );
		radio.checked = true;
		actions.pick( { target: radio } as unknown as Event );
		expect( state.missing ).toBe( '' );
		expect( state.dimmed, 'lit while the route is asked' ).toBe( false );
		// Pressed before the pick's answer, Next sends the pick.
		const event = new SubmitEvent( 'submit', {
			submitter: next,
			cancelable: true,
		} );
		actions.submit( event );
		expect( event.defaultPrevented ).toBe( true );
		expect( state.missing ).toBe( '' );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect(
			fetched.some(
				( url ) => url.searchParams.get( 'cbuild[step]' ) === 'trucks'
			)
		).toBe( true );
	} );

	it( 'forgets what it said beside Next once a view comes', async () => {
		state.view = EMPTY;
		state.live = true;
		const { next } = dimmedForm( true );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		expect( state.missing ).not.toBe( '' );
		actions.back();
		await settle();
		expect( state.view ).toEqual( NEXT );
		expect( state.missing ).toBe( '' );
	} );

	it( 'sends a Next the bar says is lit, and any other button of the form, as before', async () => {
		state.view = { ...EMPTY, bar: { next: { ready: true, missing: '' } } };
		state.live = true;
		const { next } = dimmedForm( false );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		expect( state.missing ).toBe( '' );
		vi.advanceTimersByTime( SLOW );
		expect( fetched ).toHaveLength( 1 );
	} );

	it( 'sends any other button of the form while Next is dimmed, as a width is pressed before a pick', () => {
		state.view = EMPTY;
		state.live = true;
		const { next } = dimmedForm( true );
		next.removeAttribute( 'aria-disabled' );
		next.value = 'deck';
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		expect( state.missing ).toBe( '' );
		vi.advanceTimersByTime( SLOW );
		expect( fetched ).toHaveLength( 1 );
	} );

	it.each( [
		[
			'a text field',
			() =>
				document.querySelector< HTMLInputElement >(
					'input[type=text]'
				),
		],
		[
			'a hidden field',
			() =>
				document.querySelector< HTMLInputElement >(
					'input[type=hidden]'
				),
		],
		[
			'a radio outside a form',
			() => {
				const loose = document.createElement( 'input' );
				loose.type = 'radio';
				document.body.appendChild( loose );
				return loose;
			},
		],
		[ 'no input at all', () => document.body ],
	] )( 'asks nothing for a change on %s', ( _name, target ) => {
		form();
		actions.pick( { target: target() } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		expect( fetched ).toHaveLength( 0 );
		expect( state.pending ).toBe( false );
	} );

	function link( href: string ): HTMLAnchorElement {
		document.body.innerHTML = `<a href="${ href }">Back</a>`;
		const a = document.querySelector( 'a' )!;
		setElement( a );
		return a;
	}

	it( 'follows a link on the builder through the route, as a new history entry', async () => {
		link( '/?page_id=25&cbuild%5Bdeck%5D=12&cbuild%5Bstep%5D=deck' );
		const pushed = vi.spyOn( window.history, 'pushState' );
		const event = new MouseEvent( 'click', {
			cancelable: true,
			button: 0,
		} );
		actions.follow( event );
		expect( event.defaultPrevented ).toBe( true );
		expect( [ ...fetched[ 0 ]!.searchParams ] ).toEqual( [
			[ 'cbuild[deck]', '12' ],
			[ 'cbuild[step]', 'deck' ],
			[ 'page', '25' ],
			[ 'locale', 'fr_CA' ],
			[ '_fields', 'view' ],
		] );
		await settle();
		expect( pushed ).toHaveBeenCalledOnce();
	} );

	it( "opens the link without JavaScript's help when the route fails", async () => {
		link( '/?cbuild%5Bstep%5D=deck' );
		reply = () => Promise.reject( new TypeError( 'Failed to fetch' ) );
		const went = vi
			.spyOn( browser, 'assign' )
			.mockImplementation( () => undefined );
		actions.follow(
			new MouseEvent( 'click', { cancelable: true, button: 0 } )
		);
		await settle();
		expect( went ).toHaveBeenCalledWith(
			window.location.origin + '/?cbuild%5Bstep%5D=deck'
		);
	} );

	it.each( [
		[ 'the middle button', { button: 1 } ],
		[ 'Command', { button: 0, metaKey: true } ],
		[ 'Control', { button: 0, ctrlKey: true } ],
		[ 'Shift', { button: 0, shiftKey: true } ],
		[ 'Option', { button: 0, altKey: true } ],
	] )( 'leaves a click with %s to the browser', ( _name, init ) => {
		link( '/?cbuild%5Bstep%5D=deck' );
		const event = new MouseEvent( 'click', { cancelable: true, ...init } );
		actions.follow( event );
		expect( event.defaultPrevented ).toBe( false );
		expect( fetched ).toHaveLength( 0 );
	} );

	it( 'leaves a link to another site, or a click on anything but a link, to the browser', () => {
		link( 'https://elsewhere.example/?cbuild%5Bstep%5D=deck' );
		const away = new MouseEvent( 'click', { cancelable: true, button: 0 } );
		actions.follow( away );
		expect( away.defaultPrevented ).toBe( false );

		document.body.innerHTML = '<span></span>';
		setElement( document.querySelector( 'span' ) );
		const span = new MouseEvent( 'click', { cancelable: true, button: 0 } );
		actions.follow( span );
		expect( span.defaultPrevented ).toBe( false );
		expect( fetched ).toHaveLength( 0 );
	} );

	it( 'shows the build the address holds when the shopper goes back, making no entry', async () => {
		window.history.replaceState(
			null,
			'',
			'/?page_id=25&cbuild%5Bdeck%5D=11'
		);
		const pushed = vi.spyOn( window.history, 'pushState' );
		const replaced = vi.spyOn( window.history, 'replaceState' );
		actions.back();
		expect( fetched[ 0 ]!.searchParams.get( 'cbuild[deck]' ) ).toBe( '11' );
		await settle();
		expect( state.view ).toEqual( NEXT );
		expect( pushed ).not.toHaveBeenCalled();
		expect( replaced ).not.toHaveBeenCalled();
	} );

	it( 'reloads the page it went back to when the route fails', async () => {
		reply = () => answer( null, 500 );
		const reloaded = vi
			.spyOn( browser, 'reload' )
			.mockImplementation( () => undefined );
		actions.back();
		await settle();
		expect( reloaded ).toHaveBeenCalledOnce();
	} );

	it( 'asks the route the page names, once, however many moves', async () => {
		link( '/?cbuild%5Bstep%5D=deck' );
		actions.follow(
			new MouseEvent( 'click', { cancelable: true, button: 0 } )
		);
		setConfig( NAME, {
			endpoint: 'https://other.example/x',
			page: 1,
			locale: 'en_US',
		} );
		actions.back();
		expect( fetched.map( ( url ) => url.origin + url.pathname ) ).toEqual( [
			ENDPOINT,
			ENDPOINT,
		] );
	} );

	it( 'falls back when the page names no route', async () => {
		setConfig( NAME, {} );
		const reloaded = vi
			.spyOn( browser, 'reload' )
			.mockImplementation( () => undefined );
		actions.back();
		await settle();
		expect( fetched ).toHaveLength( 0 );
		expect( reloaded ).toHaveBeenCalledOnce();
	} );

	/**
	 * The builder's markup, its init callback run on it.
	 */
	function builder(): HTMLElement {
		document.body.innerHTML = `
			<div class="cbuild">
				<div class="cbuild-errors" tabindex="-1"></div>
				<h2 class="cbuild__heading" tabindex="-1">Step</h2>
				<h3 class="cbuild-changes__heading" tabindex="-1">Changes</h3>
				<a href="/?cbuild%5Bstep%5D=trucks">Next</a>
			</div>`;
		const div = document.querySelector< HTMLElement >( '.cbuild' )!;
		setElement( div );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		setElement( document.querySelector( 'a' ) );
		return div;
	}

	async function move(): Promise< void > {
		actions.follow(
			new MouseEvent( 'click', { cancelable: true, button: 0 } )
		);
		await settle();
	}

	it( "puts focus on the new step's heading once the page shows it, and says the step in the title", async () => {
		const div = builder();
		await move();
		expect( document.title ).toBe(
			'Step 2 of 7: Trucks - Build a complete - Shop'
		);
		expect(
			state.status,
			'the heading is read; the region says nothing'
		).toBe( '' );
		expect( div.ownerDocument.activeElement ).not.toBe(
			div.querySelector( '.cbuild__heading' )
		);
		vi.advanceTimersToNextFrame();
		expect( div.ownerDocument.activeElement ).toBe(
			div.querySelector( '.cbuild__heading' )
		);
	} );

	it( 'puts focus on the error summary when one is shown', async () => {
		const div = builder();
		reply = () =>
			answer( {
				...NEXT,
				title: 'Error: Step 1 of 7: Deck',
				errors: { shown: true },
			} );
		await move();
		vi.advanceTimersToNextFrame();
		expect( div.ownerDocument.activeElement ).toBe(
			div.querySelector( '.cbuild-errors' )
		);
		expect( document.title ).toBe(
			'Error: Step 1 of 7: Deck - Build a complete - Shop'
		);
	} );

	it( "puts focus on the change panel's heading, and says how many parts changed", async () => {
		const div = builder();
		reply = () =>
			answer( {
				...NEXT,
				isChanges: true,
				status: '2 parts no longer fit.',
			} );
		await move();
		vi.advanceTimersToNextFrame();
		expect( div.ownerDocument.activeElement ).toBe(
			div.querySelector( '.cbuild-changes__heading' )
		);
		expect( state.status ).toBe( '2 parts no longer fit.' );
	} );

	it( "takes a pick that opens the change panel as a step: a new entry, the panel's heading focused, the title", async () => {
		const { radio } = form();
		document.body.insertAdjacentHTML(
			'afterbegin',
			'<h2 class="cbuild-changes__heading" tabindex="-1">Changes</h2>'
		);
		setElement( wrapped() );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const pushed = vi.spyOn( window.history, 'pushState' );
		const replaced = vi.spyOn( window.history, 'replaceState' );
		const panel = {
			...START,
			url: window.location.origin + '/?changes',
			title: 'Step 1 of 7: Deck',
			isChanges: true,
			status: '1 part no longer fits.',
		};
		reply = () => answer( panel );
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		vi.advanceTimersToNextFrame();
		expect( pushed ).toHaveBeenCalledWith( null, '', panel.url );
		expect( replaced ).not.toHaveBeenCalled();
		expect( radio.ownerDocument.activeElement ).toBe(
			document.querySelector( '.cbuild-changes__heading' )
		);
		expect( document.title ).toBe( 'Step 1 of 7: Deck' + TITLE.after );
		expect( state.status ).toBe( '1 part no longer fits.' );

		// A pick on a panel already shown, were there one, stays a pick.
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( replaced ).toHaveBeenCalledWith( null, '', panel.url );
	} );

	// "Never on load": a card settles at a pick alone, not when a page or a step is drawn with one picked (M11 audit,
	// finding 8). The stylesheet plays the settle only under this mark.
	it( 'marks the builder at a pick, so its card settles, until a new step is shown', async () => {
		const { radio } = form();
		const div = wrapped();
		setElement( div );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		expect( div.hasAttribute( 'data-picked' ), 'nothing picked yet' ).toBe(
			false
		);
		reply = () => answer( { ...START, status: 'A deck. Total: $70.00.' } );
		actions.pick( { target: radio } as unknown as Event );
		// A mark with no value, as `data-ready` is.
		expect( div.getAttribute( 'data-picked' ) ).toBe( '' );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( state.status ).toBe( 'A deck. Total: $70.00.' );
		expect(
			div.hasAttribute( 'data-picked' ),
			'the pick’s own answer keeps it'
		).toBe( true );
		reply = () => answer( NEXT );
		actions.back();
		await settle();
		expect( state.view ).toEqual( NEXT );
		expect( div.hasAttribute( 'data-picked' ), 'a new step' ).toBe( false );
	} );

	it( 'says the build and its total after a pick, and leaves focus and the title alone', async () => {
		const { radio } = form();
		document.body.insertAdjacentHTML(
			'afterbegin',
			'<h2 class="cbuild__heading" tabindex="-1">Step</h2>'
		);
		setElement( wrapped() );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		radio.focus();
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		vi.advanceTimersToNextFrame();
		expect( state.status ).toBe( 'A deck with trucks. Total: $97.00.' );
		expect( document.title ).toBe( 'Build a complete - Shop' );
		expect( radio.ownerDocument.activeElement ).toBe( radio );
	} );

	it( 'keeps focus on the control used and says the total after a move that stays on the step (assembly, a filter)', async () => {
		const div = builder();
		const used = div.querySelector( 'a' )!;
		used.focus();
		reply = () =>
			answer( {
				...START,
				url: window.location.origin + '/?assembly',
				status: 'A deck, assembled. Total: $85.00.',
			} );
		await move();
		vi.advanceTimersToNextFrame();
		expect(
			div.ownerDocument.activeElement,
			'the shopper keeps their place'
		).toBe( used );
		expect( state.status ).toBe( 'A deck, assembled. Total: $85.00.' );
	} );

	// The spec reviewer's M10 minor M1: once chosen, the width is the step's first line with Change. A width pressed
	// in the open fold folds it again, and focus goes to the line that now says it; with no width chosen yet the line
	// is hidden and the widths stay open.
	it( 'folds the widths behind their line after an answer, and gives that line the focus a width button had', async () => {
		const div = builder();
		div.insertAdjacentHTML(
			'beforeend',
			`<details class="cbuild-width__pick" open><summary class="cbuild-width__chosen">8.25″ Change</summary><button type="submit">8.5″</button></details>
			<details class="cbuild-width__pick" open><summary class="cbuild-width__chosen" hidden>Asking</summary></details>`
		);
		const [ chosen, asking ] =
			div.querySelectorAll< HTMLDetailsElement >( 'details' );
		const width = chosen!.querySelector( 'button' )!;
		width.focus();
		reply = () =>
			answer( {
				...START,
				url: window.location.origin + '/?width',
				status: 'Showing 8.5″ decks.',
			} );
		await move();
		vi.advanceTimersToNextFrame();
		expect( chosen!.open, 'the widths fold behind their line' ).toBe(
			false
		);
		expect( asking!.open, 'no width chosen: they stay open' ).toBe( true );
		expect(
			div.ownerDocument.activeElement,
			'the shopper keeps their place, on the line that says the width'
		).toBe( chosen!.querySelector( 'summary' ) );
	} );

	it( 'puts focus on the first new card after Show more, and says how many were added', async () => {
		const div = builder();
		div.insertAdjacentHTML(
			'beforeend',
			'<ul class="cbuild-cards"><li><input type="radio" name="cbuild[deck]" value="1"></li><li><input type="radio" name="cbuild[deck]" value="2"></li></ul>'
		);
		state.view = { ...START, step: { slot: 'deck', options: [ {}, {} ] } };
		reply = () =>
			answer( {
				...START,
				url: window.location.origin + '/?more',
				step: {
					slot: 'deck',
					options: [ {}, {}, {}, {} ],
					more: { added: '2 more shown, 4 of 4.' },
				},
				status: 'A deck. Total: $70.00.',
			} );
		await move();
		// The page draws the new cards before the next frame.
		div.querySelector( '.cbuild-cards' )!.insertAdjacentHTML(
			'beforeend',
			'<li><input type="radio" name="cbuild[deck]" value="3"></li><li><input type="radio" name="cbuild[deck]" value="4"></li>'
		);
		vi.advanceTimersToNextFrame();
		expect( div.ownerDocument.activeElement ).toBe(
			div.querySelector( 'input[value="3"]' )
		);
		// Spec (configurator): "Show 30 more" says how many were added. The card is read with it; the total has not
		// changed, so it is not said.
		expect( state.status ).toBe( '2 more shown, 4 of 4.' );
	} );

	/**
	 * The step's cards, as the page draws them.
	 *
	 * @param div   The builder.
	 * @param count How many.
	 */
	function cards( div: HTMLElement, count: number ): void {
		const items = Array.from(
			{ length: count },
			( _, at ) =>
				`<li><input type="radio" name="cbuild[deck]" value="${ at + 1 }"></li>`
		).join( '' );
		div.insertAdjacentHTML(
			'beforeend',
			`<ul class="cbuild-cards">${ items }</ul>`
		);
	}

	// A search, a width or a chip that lists other cards, or as many, is no Show more: focus stays on the control used,
	// and the region says the total (spec: Keyboard and screen readers).
	it.each( [
		[ 'lists cards where there were none', undefined, [ {}, {} ] ],
		[
			'lists the same cards',
			[ { id: 1 }, { id: 2 } ],
			[ { id: 1 }, { id: 2 } ],
		],
		[
			'lists more cards, but others',
			[ { id: 1 }, { id: 2 } ],
			[ { id: 1 }, { id: 3 }, { id: 4 } ],
		],
	] )(
		'keeps focus on the control used, and says the total, when a search %s',
		async ( _name, was, now ) => {
			const div = builder();
			cards( div, now.length );
			const used = div.querySelector( 'a' )!;
			used.focus();
			state.view = { ...START, step: { slot: 'deck', options: was } };
			reply = () =>
				answer( {
					...START,
					url: window.location.origin + '/?found',
					step: { slot: 'deck', options: now, more: { added: '' } },
					status: 'A deck. Total: $70.00.',
				} );
			await move();
			vi.advanceTimersToNextFrame();
			expect( div.ownerDocument.activeElement ).toBe( used );
			expect( state.status ).toBe( 'A deck. Total: $70.00.' );
		}
	);

	it( 'still puts focus on the first new card after Show more where the server gives no count, and says nothing', async () => {
		const div = builder();
		cards( div, 3 );
		state.view = { ...START, step: { slot: 'deck', options: [ {}, {} ] } };
		state.status = 'A deck. Total: $70.00.';
		reply = () =>
			answer( {
				...START,
				url: window.location.origin + '/?more',
				step: { slot: 'deck', options: [ {}, {}, {} ] },
				status: 'A deck. Total: $70.00.',
			} );
		await move();
		vi.advanceTimersToNextFrame();
		expect( div.ownerDocument.activeElement ).toBe(
			div.querySelector( 'input[value="3"]' )
		);
		expect( state.status ).toBe( '' );
	} );

	it( 'moves no focus, and throws nothing, after Show more where the new cards are not drawn or no builder was found', async () => {
		const more = () =>
			answer( {
				...START,
				url: window.location.origin + '/?more',
				step: {
					slot: 'deck',
					options: [ {}, {}, {}, {} ],
					more: { added: '2 more shown, 4 of 4.' },
				},
			} );
		builder();
		state.view = { ...START, step: { slot: 'deck', options: [ {}, {} ] } };
		reply = more;
		await move();
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		expect( document.body.ownerDocument.activeElement ).toBe(
			document.body
		);

		reset();
		document.body.innerHTML =
			'<a href="/?cbuild%5Bstep%5D=deck">Show more</a>';
		setElement( document.querySelector( 'a' ) );
		state.view = { ...START, step: { slot: 'deck', options: [ {}, {} ] } };
		await move();
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		expect( state.status ).toBe( '2 more shown, 4 of 4.' );
	} );

	// D-152: the change panel is a step of its own, but going back to it, as to any address, makes no entry.
	it( 'makes no history entry going back to an address that shows the change panel', async () => {
		const pushed = vi.spyOn( window.history, 'pushState' );
		reply = () =>
			answer( {
				...START,
				url: window.location.origin + '/?changes',
				isChanges: true,
				status: '1 part no longer fits.',
			} );
		actions.back();
		await settle();
		expect( state.view ).toMatchObject( { isChanges: true } );
		expect( pushed ).not.toHaveBeenCalled();
	} );

	// Spec (configurator): Search, past the rate limit the step keeps what it shows and says when to try again.
	it( "says the route's rate limit in its own words, and forgets it once a view comes", async () => {
		reply = () =>
			new Response(
				JSON.stringify( {
					message: 'Too many searches. Try again in a minute.',
				} ),
				{ status: 429, headers: { 'Content-Type': 'application/json' } }
			);
		actions.back();
		await settle();
		expect( state.limited ).toBe(
			'Too many searches. Try again in a minute.'
		);
		expect( state.status ).toBe(
			'Too many searches. Try again in a minute.'
		);
		expect( state.view ).toBe( START );

		reply = () => answer( NEXT );
		actions.back();
		await settle();
		expect( state.view ).toEqual( NEXT );
		expect( state.limited ).toBe( '' );
	} );

	// D-364: past the store's limit the request is given up, and the step says so in the page's words, as past the rate limit.
	it( "says the page's words when the route has not answered in time, and keeps the view", async () => {
		setConfig( NAME, {
			endpoint: ENDPOINT,
			late: 'The shop did not answer in time. Try again in a moment.',
		} );
		vi.stubGlobal(
			'fetch',
			( _input: string, init?: RequestInit ) =>
				new Promise< Response >( ( _done, fail ) =>
					init?.signal?.addEventListener( 'abort', () =>
						fail( new DOMException( 'Aborted', 'AbortError' ) )
					)
				)
		);
		actions.back();
		await vi.advanceTimersByTimeAsync( 10000 );
		await settle();
		expect( state.limited ).toBe(
			'The shop did not answer in time. Try again in a moment.'
		);
		expect( state.status ).toBe( state.limited );
		expect( state.pending ).toBe( false );
		expect( state.view ).toBe( START );

		// A page whose config has no words for it says nothing, rather than "undefined".
		reset();
		setConfig( NAME, { endpoint: ENDPOINT } );
		state.limited = '';
		actions.back();
		await vi.advanceTimersByTimeAsync( 10000 );
		await settle();
		expect( state.limited ).toBe( '' );
	} );

	it( 'says the step alone in the title when the page gave no title, and moves no focus with no builder found', async () => {
		setConfig( NAME, { endpoint: ENDPOINT } );
		document.body.innerHTML =
			'<a href="/?cbuild%5Bstep%5D=trucks">Next</a>';
		setElement( document.querySelector( 'a' ) );
		await move();
		vi.advanceTimersToNextFrame();
		expect( document.title ).toBe( 'Step 2 of 7: Trucks' );
		expect( document.body.ownerDocument.activeElement ).toBe(
			document.body
		);
	} );

	it( 'moves no focus, and throws nothing, when the builder has nothing to focus', async () => {
		document.body.innerHTML =
			'<div class="cbuild"><a href="/?cbuild%5Bstep%5D=trucks">Next</a></div>';
		setElement( document.querySelector< HTMLElement >( '.cbuild' ) );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		setElement( document.querySelector( 'a' ) );
		await move();
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		expect( document.body.ownerDocument.activeElement ).toBe(
			document.body
		);
	} );

	it( 'reads a config with nothing in it as no page and no language, and asks for no page', () => {
		setConfig( NAME, { endpoint: ENDPOINT } );
		actions.back();
		// A builder on an archive or a template has no post: the route takes a page of 1 or more, and would refuse 0.
		expect( fetched[ 0 ]!.searchParams.has( 'page' ) ).toBe( false );
		expect( fetched[ 0 ]!.searchParams.get( 'locale' ) ).toBe( '' );
	} );
} );

describe( 'adding to the cart', () => {
	function review(): { root: HTMLElement; sheet: HTMLFormElement } {
		document.body.innerHTML = `
			<div class="cbuild">
				<form method="post" action="/?review">
					<input type="hidden" name="add-to-cart" value="7">
					<input type="hidden" name="cbuild[picks][deck]" value="101">
					<input type="hidden" name="cbuild[assembly]" value="1">
					<button type="submit">Add to cart</button>
				</form>
				<p class="cbuild-review__added" tabindex="-1"></p>
			</div>`;
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		setElement( root );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const sheet = document.querySelector( 'form' )!;
		setElement( sheet );
		state.view = REVIEW;
		return { root, sheet };
	}

	function cart( added: number ): string[] {
		const asked: string[] = [];
		vi.stubGlobal( 'fetch', ( input: string, init?: RequestInit ) => {
			asked.push( ( init?.method ?? 'GET' ) + ' ' + input );
			return Promise.resolve(
				input === CART
					? new Response( '{}', { headers: { Nonce: 'n' } } )
					: new Response( '{}', { status: added } )
			);
		} );
		return asked;
	}

	it( 'posts the build to the Store API, tells the carts, says so, and sends the builder its own event', async () => {
		const { root, sheet } = review();
		const asked = cart( 201 );
		const told: string[] = [];
		// As WooCommerce's own add sends it: bubbling, and cancelable.
		const carts = ( event: Event ) =>
			told.push(
				`carts:${ String( event.bubbles ) },${ String( event.cancelable ) }`
			);
		let detail: unknown;
		const builder = ( event: Event ) => {
			told.push(
				'builder:' + String( ( event.target as Element ).className )
			);
			detail = ( event as CustomEvent ).detail;
		};
		root.ownerDocument.addEventListener( 'wc-blocks_added_to_cart', carts );
		root.ownerDocument.addEventListener( 'cbuild:added', builder );
		const submitted = vi.spyOn( browser, 'submit' );
		const event = new SubmitEvent( 'submit', { cancelable: true } );
		await actions.add( event );
		vi.advanceTimersToNextFrame();
		root.ownerDocument.removeEventListener(
			'wc-blocks_added_to_cart',
			carts
		);
		root.ownerDocument.removeEventListener( 'cbuild:added', builder );

		expect( event.defaultPrevented ).toBe( true );
		expect( asked ).toEqual( [
			'GET ' + CART,
			'POST ' + CART + '/add-item',
		] );
		expect( told ).toEqual( [ 'carts:true,true', 'builder:cbuild' ] );
		expect( detail ).toEqual( {
			kit: 7,
			picks: { deck: 101 },
			assembly: true,
		} );
		expect( state.added ).toBe( true );
		// Said once: focus moves to the notice, which a screen reader reads, so the status says nothing more.
		expect( state.status ).toBe( '' );
		expect( state.pending ).toBe( false );
		expect( submitted ).not.toHaveBeenCalled();
		expect( root.ownerDocument.activeElement ).toBe(
			root.querySelector( '.cbuild-review__added' )
		);
		expect( sheet.isConnected ).toBe( true );
	} );

	it( 'leaves focus to a cart drawer that opened on the add, and says the add instead', async () => {
		// Cowork's review, minor 10: the Mini-Cart's drawer, set to open on an add, takes focus, and the builder's
		// move to its notice was lost, so a screen reader never heard it.
		const { root } = review();
		cart( 201 );
		const drawer = document.createElement( 'button' );
		document.body.append( drawer );
		const opens = () => drawer.focus();
		root.ownerDocument.addEventListener( 'wc-blocks_added_to_cart', opens );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		vi.advanceTimersToNextFrame();
		root.ownerDocument.removeEventListener(
			'wc-blocks_added_to_cart',
			opens
		);

		expect( root.ownerDocument.activeElement ).toBe( drawer );
		expect( state.added ).toBe( true );
		expect( state.status ).toBe( REVIEW.review.added );
		drawer.remove();
	} );

	it( 'says the add the same way when the drawer opens a beat after the notice took focus', async () => {
		// Claude in Cowork's recheck, finding 10: on the demo the drawer took focus 5 ms after the notice, and nothing
		// was said.
		const { root } = review();
		cart( 201 );
		const drawer = document.createElement( 'button' );
		document.body.append( drawer );
		const opens = () => window.setTimeout( () => drawer.focus(), 100 );
		root.ownerDocument.addEventListener( 'wc-blocks_added_to_cart', opens );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		vi.advanceTimersToNextFrame();
		root.ownerDocument.removeEventListener(
			'wc-blocks_added_to_cart',
			opens
		);
		expect( root.ownerDocument.activeElement ).toBe(
			root.querySelector( '.cbuild-review__added' )
		);
		expect( state.status ).toBe( '' );

		vi.advanceTimersByTime( 100 );
		expect( root.ownerDocument.activeElement ).toBe( drawer );
		expect( state.status ).toBe( REVIEW.review.added );
		drawer.remove();
	} );

	it( 'says nothing more once the add has settled, wherever the shopper moves focus after', async () => {
		const { root } = review();
		cart( 201 );
		const elsewhere = document.createElement( 'button' );
		document.body.append( elsewhere );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		vi.advanceTimersToNextFrame();
		vi.advanceTimersByTime( SETTLE );
		elsewhere.focus();
		expect( state.status ).toBe( '' );
		expect( root.contains( root.ownerDocument.activeElement ) ).toBe(
			false
		);
		elsewhere.remove();
	} );

	it( 'puts focus on the notice where the page around the builder took it, as a theme may on a click', async () => {
		// Storefront under WebKit focuses its content's wrapper, which holds the builder: no drawer opened.
		const { root } = review();
		cart( 201 );
		const wrapper = document.createElement( 'div' );
		wrapper.tabIndex = -1;
		root.before( wrapper );
		wrapper.append( root );
		const focuses = () => wrapper.focus();
		root.ownerDocument.addEventListener(
			'wc-blocks_added_to_cart',
			focuses
		);
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		vi.advanceTimersToNextFrame();
		root.ownerDocument.removeEventListener(
			'wc-blocks_added_to_cart',
			focuses
		);
		expect( root.ownerDocument.activeElement ).toBe(
			root.querySelector( '.cbuild-review__added' )
		);
		expect( state.status ).toBe( '' );
	} );

	it( 'adds the build once when Add to cart is pressed again while it adds', async () => {
		review();
		const asked = cart( 201 );
		const first = new SubmitEvent( 'submit', { cancelable: true } );
		const again = new SubmitEvent( 'submit', { cancelable: true } );
		const adding = actions.add( first );
		await actions.add( again );
		await adding;

		expect( again.defaultPrevented ).toBe( true );
		expect(
			asked.filter( ( line ) => line.endsWith( '/add-item' ) )
		).toHaveLength( 1 );
	} );

	it( "sends the form to WooCommerce's own handler when the add fails, and says nothing was added", async () => {
		const { root, sheet } = review();
		cart( 400 );
		const submitted = vi
			.spyOn( browser, 'submit' )
			.mockImplementation( () => undefined );
		const told: string[] = [];
		const builder = () => told.push( 'added' );
		root.ownerDocument.addEventListener( 'cbuild:added', builder );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		root.ownerDocument.removeEventListener( 'cbuild:added', builder );
		expect( submitted ).toHaveBeenCalledWith( sheet, null );
		expect( state.added ).toBe( false );
		expect( state.pending ).toBe( false );
		expect( told ).toEqual( [] );
	} );

	it( 'leaves a submit on anything but a form alone', async () => {
		const { root } = review();
		setElement( root.querySelector( 'p' ) );
		const event = new SubmitEvent( 'submit', { cancelable: true } );
		await actions.add( event );
		expect( event.defaultPrevented ).toBe( false );
	} );

	it( 'forgets an add once the view moves on', async () => {
		review();
		cart( 201 );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		expect( state.added ).toBe( true );
		vi.stubGlobal( 'fetch', () => Promise.resolve( answer( NEXT ) ) );
		const { radio } = form();
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( state.added ).toBe( false );
	} );

	it( 'shows the add on its way while it is', async () => {
		review();
		cart( 201 );
		const adding = actions.add(
			new SubmitEvent( 'submit', { cancelable: true } )
		);
		expect( state.pending ).toBe( true );
		await adding;
		expect( state.pending ).toBe( false );
	} );

	it( "sends the form to WooCommerce's own handler, asking nothing, where the page names no Store API cart", async () => {
		const { sheet } = review();
		setConfig( NAME, { endpoint: ENDPOINT } );
		const asked = cart( 201 );
		const submitted = vi
			.spyOn( browser, 'submit' )
			.mockImplementation( () => undefined );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		expect( asked ).toEqual( [] );
		expect( submitted ).toHaveBeenCalledWith( sheet, null );
	} );

	it( "sends the builder's event with no assembly where the form asks for none", async () => {
		const { root, sheet } = review();
		sheet.querySelector( '[name="cbuild[assembly]"]' )!.remove();
		cart( 201 );
		let detail: unknown;
		const builder = ( event: Event ) => {
			detail = ( event as CustomEvent ).detail;
		};
		root.addEventListener( 'cbuild:added', builder );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		root.removeEventListener( 'cbuild:added', builder );
		expect( detail ).toEqual( {
			kit: 7,
			picks: { deck: 101 },
			assembly: false,
		} );
	} );

	it( 'says the add once, though focus moves again after a drawer took it', async () => {
		const { root } = review();
		cart( 201 );
		const drawer = document.createElement( 'button' );
		const close = document.createElement( 'button' );
		document.body.append( drawer, close );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		vi.advanceTimersToNextFrame();
		drawer.focus();
		expect( state.status ).toBe( REVIEW.review.added );
		// Something said since, such as a link copied, is not said over by the add again.
		state.status = REVIEW.review.copied;
		close.focus();
		expect( state.status ).toBe( REVIEW.review.copied );
		expect( root.contains( root.ownerDocument.activeElement ) ).toBe(
			false
		);
	} );

	it( 'says nothing more while the shopper moves about the builder after an add, and the add once a drawer takes focus', async () => {
		const { root } = review();
		cart( 201 );
		const drawer = document.createElement( 'button' );
		document.body.append( drawer );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		vi.advanceTimersToNextFrame();
		root.querySelector( 'button' )!.focus();
		expect( state.status ).toBe( '' );
		drawer.focus();
		expect( state.status ).toBe( REVIEW.review.added );
	} );

	it( 'puts focus on the notice where the browser says no element has focus', async () => {
		const { root } = review();
		cart( 201 );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		Object.defineProperty( document, 'activeElement', {
			get: () => null,
			configurable: true,
		} );
		try {
			vi.advanceTimersToNextFrame();
		} finally {
			delete ( document as { activeElement?: unknown } ).activeElement;
		}
		expect( root.ownerDocument.activeElement ).toBe(
			root.querySelector( '.cbuild-review__added' )
		);
		expect( state.status ).toBe( '' );
	} );

	it( 'throws nothing where the review shows no notice to focus', async () => {
		const { root } = review();
		root.querySelector( '.cbuild-review__added' )!.remove();
		cart( 201 );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		expect( state.added ).toBe( true );
	} );

	it( "adds the build and tells the carts where the builder's element was never found, though a drawer takes focus", async () => {
		document.body.innerHTML = `
			<form method="post" action="/?review">
				<input type="hidden" name="add-to-cart" value="7">
				<input type="hidden" name="cbuild[picks][deck]" value="101">
				<button type="submit">Add to cart</button>
			</form>`;
		setElement( document.querySelector( 'form' ) );
		state.view = REVIEW;
		cart( 201 );
		const drawer = document.createElement( 'button' );
		document.body.append( drawer );
		const told: string[] = [];
		const opens = () => {
			told.push( 'carts' );
			drawer.focus();
		};
		document.addEventListener( 'wc-blocks_added_to_cart', opens );
		await actions.add( new SubmitEvent( 'submit', { cancelable: true } ) );
		document.removeEventListener( 'wc-blocks_added_to_cart', opens );
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		expect( told ).toEqual( [ 'carts' ] );
		expect( state.added ).toBe( true );
		expect( drawer.ownerDocument.activeElement ).toBe( drawer );
	} );

	it( 'sends the builder an event for each pick, with the kit, the slot and the part', () => {
		const { radio } = form();
		document.body.insertAdjacentHTML(
			'afterbegin',
			'<div class="cbuild"></div>'
		);
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		root.appendChild( radio.form! );
		setElement( root );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		state.view = { ...START, kit: 7 };
		let detail: unknown;
		const picked = ( event: Event ) => {
			detail = ( event as CustomEvent ).detail;
		};
		root.ownerDocument.addEventListener( 'cbuild:pick', picked );
		actions.pick( { target: radio } as unknown as Event );
		root.ownerDocument.removeEventListener( 'cbuild:pick', picked );
		expect( detail ).toEqual( { kit: 7, slot: 'deck', part: 12 } );
	} );
} );

describe( 'copying the build link', () => {
	function share( writeText?: ( text: string ) => Promise< void > ): {
		link: HTMLInputElement;
	} {
		Object.defineProperty( navigator, 'clipboard', {
			value: undefined === writeText ? undefined : { writeText },
			configurable: true,
		} );
		document.body.innerHTML = `
			<div class="cbuild">
				<input class="cbuild-share__link" readonly value="${ REVIEW.review.link }">
				<button type="button">Copy link</button>
			</div>`;
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		state.view = REVIEW;
		state.status = '';
		return { link: document.querySelector( 'input' )! };
	}

	afterEach( () => {
		Object.defineProperty( navigator, 'clipboard', {
			value: undefined,
			configurable: true,
		} );
	} );

	it( 'offers Copy link only where the browser has a clipboard to write to', () => {
		share();
		expect( state.canCopy ).toBe( false );
		share( () => Promise.resolve() );
		expect( state.canCopy ).toBe( true );
	} );

	it( "copies the build's shared link, not the page's own address, and says so", async () => {
		const copied: string[] = [];
		share( ( text ) => {
			copied.push( text );
			return Promise.resolve();
		} );
		await actions.copy();
		expect( copied ).toEqual( [ REVIEW.review.link ] );
		expect( state.status ).toBe( 'Link copied.' );
	} );

	it( 'selects the link for the shopper to copy when the browser refuses', async () => {
		const { link } = share( () => Promise.reject( new Error( 'denied' ) ) );
		await actions.copy();
		expect( state.status ).toBe( '' );
		expect( link.ownerDocument.activeElement ).toBe( link );
		expect( link.selectionStart ).toBe( 0 );
		expect( link.selectionEnd ).toBe( REVIEW.review.link.length );
	} );

	it( 'throws nothing when the browser refuses and there is no link to select, or no builder', async () => {
		const { link } = share( () => Promise.reject( new Error( 'denied' ) ) );
		link.remove();
		await expect( actions.copy() ).resolves.toBeUndefined();
		reset();
		await expect( actions.copy() ).resolves.toBeUndefined();
		expect( state.status ).toBe( '' );
	} );
} );

describe( 'keeping focus clear of the phone bar', () => {
	function page( barTop: number ): {
		below: HTMLButtonElement;
		scrolled: string[];
	} {
		document.body.innerHTML = `
			<div class="cbuild">
				<button class="below">Next</button>
				<div class="cbuild-bar"><button class="in-bar">Next</button></div>
			</div>`;
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const scrolled: string[] = [];
		const box = ( top: number, bottom: number ) => () =>
			( { top, bottom } ) as DOMRect;
		const bar = document.querySelector< HTMLElement >( '.cbuild-bar' )!;
		bar.getBoundingClientRect = box( barTop, barTop + 68 );
		bar.getClientRects = () =>
			( barTop < 0 ? [] : [ {} ] ) as unknown as DOMRectList;
		const below = document.querySelector< HTMLButtonElement >( '.below' )!;
		below.getBoundingClientRect = box( 600, 644 );
		below.scrollIntoView = ( options ) =>
			scrolled.push( JSON.stringify( options ) );
		return { below, scrolled };
	}

	it( 'brings a control the bar covers into view above it', () => {
		const { below, scrolled } = page( 632 );
		actions.reveal( { target: below } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [ '{"block":"nearest"}' ] );
	} );

	it( 'leaves a control the bar does not cover, the bar itself, and a page with no bar alone', () => {
		let { below, scrolled } = page( 650 );
		actions.reveal( { target: below } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [] );
		( { below, scrolled } = page( -1 ) );
		actions.reveal( { target: below } as unknown as FocusEvent );
		// The bar is not shown at this width.
		expect( scrolled ).toEqual( [] );
		const inBar = document.querySelector( '.in-bar' )!;
		actions.reveal( { target: inBar } as unknown as FocusEvent );
		actions.reveal( { target: document } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [] );
	} );

	// D-296: the builder's pieces are regions of their own, so init runs in one of them: the root around it takes the
	// focusin, and a focus anywhere in the builder is brought clear of the bar.
	it( 'brings a control the bar covers into view when it takes focus, from a region inside the builder', () => {
		const { below, scrolled } = page( 632 );
		const region = document.createElement( 'p' );
		document.querySelector( '.cbuild' )!.append( region );
		setElement( region );
		// jsdom has no layout: init fits the bar, which asks what is under its foot.
		Object.defineProperty( document, 'elementsFromPoint', {
			value: () => [],
			configurable: true,
		} );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		// An earlier test's listener on the document would hear the focus after the builder: stopped short of it.
		const stop = ( event: Event ) => event.stopPropagation();
		document
			.querySelector( '.cbuild' )!
			.addEventListener( 'focusin', stop );
		below.dispatchEvent( new FocusEvent( 'focusin', { bubbles: true } ) );
		expect( scrolled.length ).toBeGreaterThan( 0 );
		expect(
			scrolled.every( ( said ) => '{"block":"nearest"}' === said )
		).toBe( true );
		// A focus outside the builder is no concern of its own.
		const outside = document.createElement( 'button' );
		const main = document.createElement( 'main' );
		main.addEventListener( 'focusin', stop );
		main.append( outside );
		document.body.append( main );
		outside.scrollIntoView = () => scrolled.push( 'outside' );
		outside.getBoundingClientRect = () =>
			( { top: 600, bottom: 644 } ) as DOMRect;
		outside.dispatchEvent( new FocusEvent( 'focusin', { bubbles: true } ) );
		expect( scrolled ).not.toContain( 'outside' );
	} );

	it( 'says on the builder whether an answer is awaited, from a region inside it', () => {
		page( 650 );
		const region = document.createElement( 'p' );
		document.querySelector( '.cbuild' )!.append( region );
		setElement( region );
		const busy = (
			defined.definition as { callbacks: { busy: () => void } }
		 ).callbacks.busy;
		state.pending = true;
		busy();
		expect(
			document.querySelector( '.cbuild' )!.getAttribute( 'aria-busy' )
		).toBe( 'true' );
		state.pending = false;
		busy();
		expect(
			document.querySelector( '.cbuild' )!.getAttribute( 'aria-busy' )
		).toBe( 'false' );
		// Outside a builder there is nothing to say it on, and nothing throws.
		const loose = document.createElement( 'p' );
		document.body.append( loose );
		setElement( loose );
		expect( busy ).not.toThrow();
		setElement( null );
		expect( busy ).not.toThrow();
	} );

	it( 'marks the builder ready from its last region, and nothing outside one', () => {
		page( 650 );
		const bar = document.querySelector< HTMLElement >( '.cbuild-bar' )!;
		setElement( bar );
		const ready = (
			defined.definition as { callbacks: { ready: () => void } }
		 ).callbacks.ready;
		ready();
		// A bare mark, as the server writes a boolean attribute.
		expect(
			document.querySelector( '.cbuild' )!.getAttribute( 'data-ready' )
		).toBe( '' );
		const loose = document.createElement( 'p' );
		document.body.append( loose );
		setElement( loose );
		expect( ready ).not.toThrow();
		expect( loose.hasAttribute( 'data-ready' ) ).toBe( false );
		setElement( null );
		expect( ready ).not.toThrow();
	} );

	it( "leaves a control whose foot meets the bar's top alone", () => {
		const { below, scrolled } = page( 644 );
		actions.reveal( { target: below } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [] );
	} );

	it( 'scrolls nothing, and throws nothing, where no builder was found', () => {
		const { below, scrolled } = page( 632 );
		reset();
		expect( () =>
			actions.reveal( { target: below } as unknown as FocusEvent )
		).not.toThrow();
		expect( scrolled ).toEqual( [] );
	} );
} );

describe( 'narrowing the step', () => {
	function chips(): {
		chip: HTMLInputElement;
		sort: HTMLSelectElement;
		loose: HTMLInputElement;
	} {
		document.body.innerHTML = `
			<form method="get" action="/">
				<input type="hidden" name="page_id" value="25">
				<input type="checkbox" name="cbuild[brand][]" value="acme" checked>
				<select name="cbuild[sort]"><option value="price-asc" selected>Price</option></select>
			</form>
			<input type="checkbox" class="loose" name="loose" value="1" checked>`;
		return {
			chip: document.querySelector< HTMLInputElement >(
				'form input[type=checkbox]'
			)!,
			sort: document.querySelector( 'select' )!,
			loose: document.querySelector< HTMLInputElement >( '.loose' )!,
		};
	}

	it( 'asks for the same step with a chip or a sort, at once, and replaces the history entry', async () => {
		const replaced = vi.spyOn( window.history, 'replaceState' );
		for ( const target of [ chips().chip, chips().sort ] ) {
			fetched = [];
			state.view = START;
			actions.narrow( { target } as unknown as Event );
			await settle();
			expect( fetched ).toHaveLength( 1 );
			expect( [ ...fetched[ 0 ]!.searchParams ] ).toEqual( [
				[ 'cbuild[brand][]', 'acme' ],
				[ 'cbuild[sort]', 'price-asc' ],
				[ 'cbuild[step]', 'deck' ],
				[ 'page', '25' ],
				[ 'locale', 'fr_CA' ],
				[ '_fields', 'view' ],
			] );
		}
		expect( replaced ).toHaveBeenCalledWith(
			null,
			'',
			window.location.origin + '/?next'
		);
	} );

	it( 'leaves a change outside a form, or on anything but a chip or a sort, alone', async () => {
		const { loose } = chips();
		actions.narrow( { target: loose } as unknown as Event );
		actions.narrow( {
			target: document.querySelector( 'form' ),
		} as unknown as Event );
		await settle();
		expect( fetched ).toEqual( [] );
	} );

	it( 'sends the form as it would be sent without JavaScript when the route fails on a chip', async () => {
		const { chip } = chips();
		reply = () => answer( null, 503 );
		const sent = vi
			.spyOn( browser, 'submit' )
			.mockImplementation( () => undefined );
		actions.narrow( { target: chip } as unknown as Event );
		await settle();
		expect( sent ).toHaveBeenCalledWith( chip.form, null );
	} );

	function box(): HTMLInputElement {
		document.body.innerHTML = `
			<form method="get" action="/">
				<input type="hidden" name="page_id" value="25">
				<input type="search" name="cbuild[_q]" value="Zebra">
			</form>`;
		return document.querySelector< HTMLInputElement >(
			'input[type=search]'
		)!;
	}

	// SPEC-CONFIGURATOR, Search: the step is asked again 150 ms after the last key.
	it( 'asks for the step with the search 150 ms after the last key, and replaces the history entry', async () => {
		const search = box();
		const replaced = vi.spyOn( window.history, 'replaceState' );
		actions.search( { target: search } as unknown as Event );
		vi.advanceTimersByTime( 100 );
		search.value = 'Zebracorn';
		actions.search( { target: search } as unknown as Event );
		vi.advanceTimersByTime( 149 );
		expect( fetched, 'not while the shopper types' ).toHaveLength( 0 );
		vi.advanceTimersByTime( 1 );
		expect( fetched ).toHaveLength( 1 );
		expect( [ ...fetched[ 0 ]!.searchParams ] ).toEqual( [
			[ 'cbuild[_q]', 'Zebracorn' ],
			[ 'cbuild[step]', 'deck' ],
			[ 'page', '25' ],
			[ 'locale', 'fr_CA' ],
			[ '_fields', 'view' ],
		] );
		await settle();
		expect( state.view ).toEqual( NEXT );
		expect( replaced ).toHaveBeenCalledWith(
			null,
			'',
			window.location.origin + '/?next'
		);
	} );

	// The auditor's M10 review, 19: an answer to the search as it was would put the box back to what it was asked with.
	it( 'drops the answer to a search the shopper has gone on typing over', async () => {
		const search = box();
		let release: ( response: Response ) => void = () => undefined;
		reply = () =>
			new Promise< Response >( ( done ) => {
				release = done;
			} );
		actions.search( { target: search } as unknown as Event );
		vi.advanceTimersByTime( 150 );
		await settle();
		expect( fetched ).toHaveLength( 1 );
		actions.search( { target: search } as unknown as Event );
		release( answer( NEXT ) );
		await settle();
		expect( state.view ).toBe( START );
	} );

	it( 'asks nothing for a key in a box outside a form, or on anything but a box', async () => {
		const loose = document.createElement( 'input' );
		document.body.innerHTML = '';
		document.body.append( loose );
		for ( const target of [ loose, document.body ] ) {
			actions.search( { target } as unknown as Event );
		}
		expect( () => vi.advanceTimersByTime( 1000 ) ).not.toThrow();
		await settle();
		expect( fetched ).toEqual( [] );
	} );
} );

describe( 'keeping focus clear of the stage that sticks on top', () => {
	function page( position: string ): {
		under: HTMLButtonElement;
		inside: HTMLButtonElement;
		scrolled: string[];
	} {
		document.body.innerHTML = `
			<div class="cbuild">
				<div class="cbuild__stage"><button class="inside">Show the top</button></div>
				<button class="under">Deck 8</button>
			</div>`;
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const scrolled: string[] = [];
		const stage =
			document.querySelector< HTMLElement >( '.cbuild__stage' )!;
		stage.getBoundingClientRect = () =>
			( { top: 0, bottom: 300 } ) as DOMRect;
		const real = window.getComputedStyle.bind( window );
		vi.spyOn( window, 'getComputedStyle' ).mockImplementation(
			( element ) =>
				element === stage
					? ( { position } as CSSStyleDeclaration )
					: real( element )
		);
		const under = document.querySelector< HTMLButtonElement >( '.under' )!;
		under.getBoundingClientRect = () =>
			( { top: 250, bottom: 294 } ) as DOMRect;
		const inside =
			document.querySelector< HTMLButtonElement >( '.inside' )!;
		inside.getBoundingClientRect = () =>
			( { top: 10, bottom: 54 } ) as DOMRect;
		for ( const button of [ under, inside ] ) {
			button.scrollIntoView = ( options ) =>
				scrolled.push( JSON.stringify( options ) );
		}
		return { under, inside, scrolled };
	}

	it( 'brings a control the stuck stage covers into view below it', () => {
		const { under, scrolled } = page( 'sticky' );
		actions.reveal( { target: under } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [ '{"block":"nearest"}' ] );
	} );

	it( 'leaves a control in the stage, and one under a stage that scrolls with the page, alone', () => {
		let { under, inside, scrolled } = page( 'sticky' );
		actions.reveal( { target: inside } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [] );
		( { under, inside, scrolled } = page( 'static' ) );
		actions.reveal( { target: under } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [] );
	} );

	it( 'leaves a control that starts where the stuck stage ends alone', () => {
		const { under, scrolled } = page( 'sticky' );
		under.getBoundingClientRect = () =>
			( { top: 300, bottom: 344 } ) as DOMRect;
		actions.reveal( { target: under } as unknown as FocusEvent );
		expect( scrolled ).toEqual( [] );
	} );
} );

describe( 'measuring the bar and the stage', () => {
	function builder(): HTMLElement {
		document.body.innerHTML = `
			<div class="cbuild">
				<div class="cbuild__stage"></div>
				<div class="cbuild-bar"></div>
			</div>`;
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		setElement( root );
		return root;
	}

	it( 'measures the bar and the stage again whenever either changes size', () => {
		const root = builder();
		const watched: Array< { element: Element; again: () => void } > = [];
		vi.stubGlobal(
			'ResizeObserver',
			class {
				constructor( private again: () => void ) {}
				observe( element: Element ): void {
					watched.push( { element, again: this.again } );
				}
			}
		);
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		expect( watched.map( ( one ) => one.element.className ) ).toEqual( [
			'cbuild-bar',
			'cbuild__stage',
		] );
		const page = document.documentElement.style;
		const bar = root.querySelector< HTMLElement >( '.cbuild-bar' )!;
		const stage = root.querySelector< HTMLElement >( '.cbuild__stage' )!;
		for ( const element of [ bar, stage ] ) {
			element.getClientRects = () => [ {} ] as unknown as DOMRectList;
			element.getBoundingClientRect = () =>
				( { height: 120.2 } ) as DOMRect;
		}
		vi.spyOn( window, 'getComputedStyle' ).mockImplementation(
			() => ( { position: 'sticky', top: '8px' } ) as CSSStyleDeclaration
		);
		watched[ 0 ]!.again();
		expect( page.getPropertyValue( '--cbuild-bar-size' ) ).toBe( '121px' );
		watched[ 1 ]!.again();
		expect( page.getPropertyValue( '--cbuild-stage-size' ) ).toBe(
			'129px'
		);
		page.removeProperty( '--cbuild-bar-size' );
		page.removeProperty( '--cbuild-stage-size' );
	} );

	it( 'measures the bar again when what the dimmed Next says over it changes size', () => {
		const root = builder();
		root.querySelector( '.cbuild-bar' )!.innerHTML =
			'<div class="cbuild-bar__actions"><p class="cbuild-bar__missing"></p></div>';
		const watched: string[] = [];
		vi.stubGlobal(
			'ResizeObserver',
			class {
				observe( element: Element ): void {
					watched.push( element.className );
				}
			}
		);
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		expect( watched ).toEqual( [
			'cbuild-bar',
			'cbuild-bar__missing',
			'cbuild__stage',
		] );
		document.documentElement.style.removeProperty( '--cbuild-stage-size' );
	} );

	it( 'measures once, and watches nothing, where the browser has no resize observer', () => {
		builder();
		vi.stubGlobal( 'ResizeObserver', undefined );
		expect( () =>
			(
				defined.definition as { callbacks: { init: () => void } }
			 ).callbacks.init()
		).not.toThrow();
		expect(
			document.documentElement.style.getPropertyValue(
				'--cbuild-stage-size'
			)
		).toBe( '0px' );
		document.documentElement.style.removeProperty( '--cbuild-stage-size' );
	} );

	it( "measures the bar's height once the script runs", () => {
		const root = builder();
		vi.stubGlobal( 'ResizeObserver', undefined );
		const bar = root.querySelector< HTMLElement >( '.cbuild-bar' )!;
		bar.getClientRects = () => [ {} ] as unknown as DOMRectList;
		bar.getBoundingClientRect = () => ( { height: 70 } ) as DOMRect;
		Object.defineProperty( document, 'elementsFromPoint', {
			value: () => [],
			configurable: true,
		} );
		vi.spyOn( window, 'getComputedStyle' ).mockImplementation(
			() => ( { position: 'sticky', top: '0px' } ) as CSSStyleDeclaration
		);
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const page = document.documentElement.style;
		expect( page.getPropertyValue( '--cbuild-bar-size' ) ).toBe( '70px' );
		page.removeProperty( '--cbuild-bar-size' );
		page.removeProperty( '--cbuild-stage-size' );
	} );

	it( 'watches only the bar or the stage the page has', () => {
		const watched: string[] = [];
		vi.stubGlobal(
			'ResizeObserver',
			class {
				// As a browser's: anything but an element is refused.
				observe( element: unknown ): void {
					if ( ! ( element instanceof Element ) ) {
						throw new TypeError( 'Not an element.' );
					}
					watched.push( element.className );
				}
			}
		);
		const init = (
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init;
		for ( const only of [ '.cbuild-bar', '.cbuild__stage' ] ) {
			builder().querySelector( only )!.remove();
			expect( () => init() ).not.toThrow();
		}
		expect( watched ).toEqual( [ 'cbuild__stage', 'cbuild-bar' ] );
		document.documentElement.style.removeProperty( '--cbuild-stage-size' );
	} );

	// D-163: a theme's `overflow: hidden` around the builder would keep the bar from sticking.
	it( 'fits the bar into the theme once the script runs, and again when the screen changes', () => {
		document.body.innerHTML = `
			<div class="site" style="overflow-x: hidden">
				<div class="cbuild"><div class="cbuild-bar"></div></div>
			</div>`;
		const site = document.querySelector< HTMLElement >( '.site' )!;
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		const bar = root.querySelector< HTMLElement >( '.cbuild-bar' )!;
		bar.getClientRects = () => [ {} ] as unknown as DOMRectList;
		Object.defineProperty( document, 'elementsFromPoint', {
			value: () => [],
			configurable: true,
		} );
		setElement( root );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		expect( site.style.overflowX ).toBe( 'clip' );

		site.style.overflowX = 'hidden';
		actions.fit();
		expect( site.style.overflowX ).toBe( 'clip' );
		document.documentElement.style.removeProperty( '--cbuild-stage-size' );
	} );

	it( 'fits nothing, and throws nothing, when the screen changes where no builder was found', () => {
		expect( () => actions.fit() ).not.toThrow();
	} );

	it( 'fits and measures the bar again when the screen changes', () => {
		const root = builder();
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const bar = root.querySelector< HTMLElement >( '.cbuild-bar' )!;
		bar.getClientRects = () => [ {} ] as unknown as DOMRectList;
		bar.getBoundingClientRect = () => ( { height: 70 } ) as DOMRect;
		Object.defineProperty( document, 'elementsFromPoint', {
			value: () => [],
			configurable: true,
		} );
		vi.spyOn( window, 'getComputedStyle' ).mockImplementation(
			() => ( { position: 'sticky', top: '0px' } ) as CSSStyleDeclaration
		);
		actions.fit();
		expect(
			document.documentElement.style.getPropertyValue(
				'--cbuild-bar-size'
			)
		).toBe( '70px' );
		document.documentElement.style.removeProperty( '--cbuild-bar-size' );
	} );
} );

describe( 'motion', () => {
	function motion( reduce: boolean, api: boolean ): Array< () => void > {
		const transitions: Array< () => void > = [];
		vi.stubGlobal( 'matchMedia', ( query: string ) => ( {
			matches: reduce && query === '(prefers-reduced-motion: reduce)',
		} ) );
		Object.defineProperty( document, 'startViewTransition', {
			value: api
				? ( update: () => void ) => {
						transitions.push( update );
						update();
						return { finished: Promise.resolve() };
					}
				: undefined,
			configurable: true,
		} );
		return transitions;
	}

	afterEach( () => {
		Object.defineProperty( document, 'startViewTransition', {
			value: undefined,
			configurable: true,
		} );
	} );

	async function step(): Promise< void > {
		const { form: sheet, next } = form();
		setElement( sheet );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		await settle();
	}

	it( 'crosses over to the next view with a view transition where the browser has one', async () => {
		const transitions = motion( false, true );
		await step();
		expect( transitions ).toHaveLength( 1 );
		expect( state.view ).toEqual( NEXT );
	} );

	// A transition takes the screen's taps while it runs, so one for a pick that stays on its step lost a Next
	// tapped straight after (CI run 43461092467, webkit-phone). The change panel is a step of its own (D-152).
	it( 'shows a view on the same step at once, and crosses over to the change panel', async () => {
		const transitions = motion( false, true );
		const { radio } = form();
		reply = () => answer( { ...START, status: 'A deck. Total: $90.00.' } );
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( state.view ).toMatchObject( {
			status: 'A deck. Total: $90.00.',
		} );
		expect( transitions ).toHaveLength( 0 );

		reply = () => answer( { ...START, isChanges: true } );
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( state.view ).toMatchObject( { isChanges: true } );
		expect( transitions ).toHaveLength( 1 );
	} );

	// Milestone 11 (D-256): reduced motion crosses a step over with a fade of 150 ms, not at once; the class on the
	// page's root gives the stylesheet the fade, and the panel does not slide.
	it( 'fades the next view in under reduced motion', async () => {
		const transitions = motion( true, true );
		await step();
		expect( transitions ).toHaveLength( 1 );
		expect(
			document.documentElement.classList.contains( 'cbuild-calm' )
		).toBe( true );
		expect( state.view ).toEqual( NEXT );
	} );

	it( 'crosses a step over going on, and back to an earlier step the other way', async () => {
		motion( false, true );
		state.view = {
			...START,
			progress: [ { current: true }, { current: false } ],
		};
		const { form: sheet, next } = form();
		setElement( sheet );
		reply = () =>
			answer( {
				...NEXT,
				progress: [ { current: false }, { current: true } ],
			} );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		await settle();
		const page = document.documentElement;
		expect( page.classList.contains( 'cbuild-back' ) ).toBe( false );
		expect( page.classList.contains( 'cbuild-calm' ) ).toBe( false );
		setElement( sheet );
		reply = () =>
			answer( {
				...START,
				progress: [ { current: true }, { current: false } ],
			} );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		await settle();
		expect( page.classList.contains( 'cbuild-back' ) ).toBe( true );
	} );

	it( 'shows the next view at once where the browser has no view transitions', async () => {
		motion( false, false );
		await step();
		expect( state.view ).toEqual( NEXT );
	} );

	it( 'crosses over where the browser has view transitions but no media queries to ask about motion', async () => {
		const transitions = motion( false, true );
		vi.stubGlobal( 'matchMedia', undefined );
		await step();
		expect( transitions ).toHaveLength( 1 );
		expect( state.view ).toEqual( NEXT );
	} );

	it( 'takes the new picture once the page has rendered the view, a task after the change', async () => {
		motion( false, false );
		let rendered: Promise< void > | undefined;
		Object.defineProperty( document, 'startViewTransition', {
			value: ( update: () => Promise< void > ) => {
				rendered = update();
				return { finished: rendered };
			},
			configurable: true,
		} );
		await step();
		expect( state.view ).toEqual( NEXT );
		let taken = false;
		void rendered!.then( () => {
			taken = true;
		} );
		await settle();
		expect( taken, 'not before the render has run' ).toBe( false );
		vi.advanceTimersByTime( 0 );
		await settle();
		expect( taken ).toBe( true );
	} );
} );

// CI run 43520699158: the first deck's answer moved Next by 166 pixels, and WebKit dropped a press it came during
// (D-185).
describe( 'keeping the control in use where it was', () => {
	function builder(): {
		radio: HTMLInputElement;
		filter: HTMLButtonElement;
	} {
		document.body.innerHTML = `
			<div class="cbuild">
				<button type="button">8.0</button>
				<form class="cbuild-step" method="get" action="/">
					<input type="hidden" name="page_id" value="25">
					<input type="radio" name="cbuild[deck]" value="11">
					<input type="radio" name="cbuild[deck]" value="12" checked>
				</form>
			</div>`;
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		setElement( root );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		return {
			radio: root.querySelectorAll< HTMLInputElement >(
				'input[type=radio]'
			)[ 1 ]!,
			filter: root.querySelector( 'button' )!,
		};
	}

	function at( element: Element, tops: number[] ): void {
		let asked = 0;
		element.getBoundingClientRect = () =>
			( {
				top: tops[ Math.min( asked++, tops.length - 1 ) ],
			} ) as DOMRect;
	}

	async function pick( radio: HTMLInputElement ): Promise< void > {
		reply = () => answer( { ...START, status: 'A deck.' } );
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
	}

	let scrolled: ReturnType< typeof vi.fn >;
	beforeEach( () => {
		scrolled = vi.fn();
		vi.stubGlobal( 'scrollBy', scrolled );
	} );

	it( 'scrolls the page by what the view moved the part picked, once it is drawn and before it is painted', async () => {
		const { radio } = builder();
		// Before the view, after it, and back where it was once scrolled.
		at( radio, [ 300, 134, 300 ] );
		await pick( radio );
		expect( state.view ).toMatchObject( { status: 'A deck.' } );
		expect( scrolled ).not.toHaveBeenCalled();
		vi.advanceTimersToNextFrame();
		expect( scrolled ).toHaveBeenCalledExactlyOnceWith( 0, -166 );
	} );

	it( 'keeps the control with focus in the builder where it was, ahead of the part picked', async () => {
		const { radio, filter } = builder();
		filter.focus();
		at( filter, [ 100, 160, 100 ] );
		at( radio, [ 300, 0 ] );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( scrolled ).toHaveBeenCalledExactlyOnceWith( 0, 60 );
	} );

	// WebKit focuses no button a pointer presses: the control under the press is kept in place, ahead of what has focus,
	// and once the pointer comes up, or the browser takes the press over, focus decides again.
	it( 'keeps the control under a press where it was, ahead of the one with focus, until the press ends', async () => {
		for ( const end of [ 'pointerup', 'pointercancel' ] ) {
			const { radio, filter } = builder();
			radio.focus();
			at( radio, [ 300, 300 ] );
			at( filter, [ 610, 584, 610 ] );
			filter.dispatchEvent(
				new Event( 'pointerdown', { bubbles: true } )
			);
			await pick( radio );
			vi.advanceTimersToNextFrame();
			expect( scrolled ).toHaveBeenCalledExactlyOnceWith( 0, -26 );

			scrolled.mockClear();
			document.dispatchEvent( new Event( end ) );
			at( filter, [ 610, 584 ] );
			at( radio, [ 300, 300 ] );
			await pick( radio );
			vi.advanceTimersToNextFrame();
			expect( scrolled ).not.toHaveBeenCalled();
			scrolled.mockClear();
		}
	} );

	it( 'keeps no press on what is not a control, or once the builder is gone', async () => {
		const { radio } = builder();
		const step = document.querySelector( 'form' )!;
		at( radio, [ 300, 300 ] );
		at( step, [ 10, 50 ] );
		step.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( scrolled ).not.toHaveBeenCalled();

		// A press held when the builder was drawn again is on a control no longer in it.
		const { filter } = builder();
		filter.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
		const { radio: again } = builder();
		at( filter, [ 610, 584 ] );
		at( again, [ 300, 300 ] );
		await pick( again );
		vi.advanceTimersToNextFrame();
		expect( scrolled ).not.toHaveBeenCalled();
	} );

	it( 'keeps a press on a button in an element the page can focus from focusing that element, and no other', () => {
		document.body.innerHTML = `
			<main tabindex="-1">
				<div class="cbuild">
					<figure id="cbuild-picture" tabindex="-1"><button type="button"><span>Show the top</span></button></figure>
					<button type="button" class="next">Next</button>
					<button type="submit" class="default" tabindex="-1"></button>
					<p tabindex="-1"><a href="#x">A link</a></p>
				</div>
			</main>`;
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		setElement( root );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		// Two earlier tests drew the builder on the body itself, and their listener stays there: the press stops at the
		// page's main, past this builder's own listener, as the page's other listeners are none of this test's.
		document
			.querySelector( 'main' )!
			.addEventListener( 'mousedown', ( event ) =>
				event.stopPropagation()
			);
		const prevented = ( element: Element ): boolean =>
			! element.dispatchEvent(
				new Event( 'mousedown', { bubbles: true, cancelable: true } )
			);
		expect(
			prevented( root.querySelector( 'figure span' )! ),
			'the turn'
		).toBe( true );
		expect(
			prevented( root.querySelector( 'figure' )! ),
			'the figure'
		).toBe( false );
		expect(
			prevented( root.querySelector( '.next' )! ),
			'a button whose focusable ancestor is outside the builder'
		).toBe( false );
		expect(
			prevented( root.querySelector( '.default' )! ),
			'a button the page can focus itself'
		).toBe( false );
		expect( prevented( root.querySelector( 'a' )! ), 'a link' ).toBe(
			false
		);
	} );

	it( 'scrolls by a pixel, and by nothing for less', async () => {
		const { radio } = builder();
		at( radio, [ 300, 299, 300 ] );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( scrolled ).toHaveBeenCalledExactlyOnceWith( 0, -1 );

		scrolled.mockClear();
		at( radio, [ 300, 300.5 ] );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( scrolled ).not.toHaveBeenCalled();
	} );

	it( 'scrolls again by what is left where the first scroll moved the control too, as the phone bar does (D-267)', async () => {
		const { radio, filter } = builder();
		filter.focus();
		// Next in the bar: stuck at the screen's foot after the view, then at the builder's end once scrolled.
		at( filter, [ 614, 785, 653, 614 ] );
		at( radio, [ 300, 0 ] );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( scrolled.mock.calls ).toEqual( [
			[ 0, 171 ],
			[ 0, 39 ],
		] );
	} );

	it( 'scrolls three times at most for a control that never comes back', async () => {
		const { radio } = builder();
		at( radio, [ 300, 400 ] );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( scrolled.mock.calls ).toEqual( [
			[ 0, 100 ],
			[ 0, 100 ],
			[ 0, 100 ],
		] );
	} );

	it( 'scrolls nothing for a control the view took off the page, or with nothing picked or focused', async () => {
		const { radio } = builder();
		at( radio, [ 300, 0 ] );
		await pick( radio );
		radio.remove();
		vi.advanceTimersToNextFrame();
		expect( scrolled ).not.toHaveBeenCalled();

		const again = builder().radio;
		again.checked = false;
		await pick( again );
		vi.advanceTimersToNextFrame();
		expect( scrolled ).not.toHaveBeenCalled();
	} );

	it( 'keeps the part picked where it was when the builder itself has focus', async () => {
		const { radio } = builder();
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		root.tabIndex = -1;
		root.focus();
		at( radio, [ 300, 134, 300 ] );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( scrolled ).toHaveBeenCalledExactlyOnceWith( 0, -166 );
	} );

	it( 'leaves focus where it was when the part picked, not a control with focus, sits in a folded width', async () => {
		document.body.innerHTML = `
			<div class="cbuild">
				<form class="cbuild-step" method="get" action="/">
					<details class="cbuild-width__pick">
						<summary class="cbuild-width__chosen">8.25″ Change</summary>
						<input type="radio" name="cbuild[deck]" value="12" checked>
					</details>
				</form>
			</div>`;
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const radio =
			document.querySelector< HTMLInputElement >( 'input[type=radio]' )!;
		at( radio, [ 300, 300 ] );
		await pick( radio );
		vi.advanceTimersToNextFrame();
		expect( document.body.ownerDocument.activeElement ).toBe(
			document.body
		);
	} );

	it( 'leaves a width fold with no line open after an answer', async () => {
		const { radio } = builder();
		document
			.querySelector( '.cbuild' )!
			.insertAdjacentHTML(
				'beforeend',
				'<details class="cbuild-width__pick" open><button type="submit">8.5″</button></details>'
			);
		await pick( radio );
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		expect(
			document.querySelector< HTMLDetailsElement >( 'details' )!.open
		).toBe( true );
	} );

	it( 'leaves a move to another step to its own focus and motion', async () => {
		const { radio } = builder();
		at( radio, [ 300, 0 ] );
		const sheet = document.querySelector( 'form' )!;
		setElement( sheet );
		reply = () => answer( NEXT );
		actions.submit( new SubmitEvent( 'submit', { cancelable: true } ) );
		await settle();
		vi.advanceTimersToNextFrame();
		expect( state.view ).toEqual( NEXT );
		expect( scrolled ).not.toHaveBeenCalled();
	} );
} );

// Issue #46, settled on 17 November 2026: "The flip is a button by the board ... with aria-pressed"; "The grip step
// turns the board to the top, and back after it, unless the shopper chose a side"; "The phone bar's board shows the
// same side". The server says which side a step shows (`picture.turned`); the store keeps the shopper's own choice.
describe( 'turning the board over', () => {
	interface Picture {
		turnable: boolean;
		turned: boolean;
	}

	function view(
		slot: string,
		picture: Picture | undefined
	): Record< string, unknown > {
		return {
			...START,
			url: window.location.origin + '/?' + slot,
			step: { slot },
			...( picture ? { picture } : {} ),
		};
	}

	function shown(): Picture {
		return ( state.view as { picture: Picture } ).picture;
	}

	async function go( next: Record< string, unknown > ): Promise< void > {
		const { form: sheet, next: button } = form();
		setElement( sheet );
		reply = () => answer( next );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		await settle();
	}

	beforeEach( () => {
		state.turning = false;
	} );

	it( 'offers the button once the script runs, and not before', () => {
		document.body.innerHTML = '<div class="cbuild"></div>';
		state.canTurn = false;
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		expect( state.canTurn ).toBe( true );
	} );

	it( 'shows the top on the grip step and the underside after it, as the server says, while the shopper has not chosen', async () => {
		state.view = view( 'hardware', { turnable: true, turned: false } );
		await go( view( 'grip', { turnable: true, turned: true } ) );
		expect( shown().turned ).toBe( true );
		expect( state.turning, 'the turn is seen' ).toBe( true );
		await go( view( 'review', { turnable: true, turned: false } ) );
		expect( shown().turned ).toBe( false );
	} );

	it( "turns the board with the button, and keeps the shopper's side through the grip step and after it", async () => {
		state.view = view( 'trucks', { turnable: true, turned: false } );
		actions.turn();
		expect( shown().turned, 'pressed: the top' ).toBe( true );
		expect( state.turning ).toBe( true );
		await go( view( 'grip', { turnable: true, turned: true } ) );
		expect( shown().turned ).toBe( true );
		await go( view( 'review', { turnable: true, turned: false } ) );
		expect( shown().turned, 'the top stays: the shopper chose it' ).toBe(
			true
		);

		actions.turn();
		expect( shown().turned, 'pressed again: the underside' ).toBe( false );
		await go( view( 'grip', { turnable: true, turned: true } ) );
		expect(
			shown().turned,
			'the grip step keeps the underside the shopper chose'
		).toBe( false );
	} );

	it( 'shows the underside where a chosen top cannot be drawn, and the top again once it can', async () => {
		state.view = view( 'deck', { turnable: true, turned: false } );
		actions.turn();
		await go( view( 'trucks', { turnable: false, turned: false } ) );
		expect( shown().turned, 'a deck with no layer image has no top' ).toBe(
			false
		);
		await go( view( 'wheels', { turnable: true, turned: false } ) );
		expect( shown().turned ).toBe( true );
	} );

	it( 'does nothing where there is no top, and leaves a view with no picture as it came', async () => {
		state.view = view( 'deck', { turnable: false, turned: false } );
		actions.turn();
		expect( shown().turned ).toBe( false );
		expect( state.turning ).toBe( false );

		state.view = view( 'deck', undefined );
		actions.turn();
		const plain = view( 'trucks', undefined );
		await go( plain );
		expect( state.view ).toEqual( plain );
	} );

	it( 'turns nothing as a board comes into the picture or leaves it', async () => {
		state.view = view( 'deck', undefined );
		const drawn = view( 'trucks', { turnable: true, turned: true } );
		await go( drawn );
		expect( state.view ).toEqual( drawn );
		expect( state.turning ).toBe( false );

		const plain = view( 'wheels', undefined );
		await go( plain );
		expect( state.view ).toEqual( plain );
		expect( state.turning ).toBe( false );
	} );

	it( 'moves nothing when a new view shows the side already shown, so a board shown again later is not turned', async () => {
		state.view = view( 'deck', { turnable: true, turned: false } );
		actions.turn();
		expect( state.turning ).toBe( true );
		await go( view( 'trucks', { turnable: true, turned: false } ) );
		expect( shown().turned ).toBe( true );
		expect( state.turning, 'the turn is over' ).toBe( false );
	} );
} );
