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
	under: () => void;
	over: () => void;
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
			decoding: string;
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
							get decoding() {
								return self.decoding;
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

		// Cowork's review of 1.1.0, F23 (#121; D-357): the pressed deck's board is drawn at the press, once decoded, and
		// the answer confirms it or puts it back.
		describe( 'drawn at the press', () => {
			const init = (): void =>
				(
					defined.definition as { callbacks: { init: () => void } }
				 ).callbacks.init();

			/**
			 * The deck step drawing deck 11 with its files by type, the builder started, and deck 12's card to press.
			 */
			function started(): {
				radio: HTMLInputElement;
				deck: {
					src: string;
					sizes: string;
					sources: { type: string; srcset: string; sizes: string }[];
				};
			} {
				const card = deckCards( 'webp' );
				setElement(
					document.querySelector< HTMLElement >( '.cbuild' )!
				);
				init();
				setElement( card );
				const deck = {
					key: 'deck-1',
					style: '',
					src: 'https://shop.example/11.png',
					sizes: '153px',
					sources: [
						{
							type: 'image/avif',
							srcset: 'https://shop.example/11.avif 216w',
							sizes: '153px',
						},
						{
							type: 'image/webp',
							srcset: 'https://shop.example/11.webp 216w',
							sizes: '153px',
						},
					],
				};
				(
					state.view as {
						picture: { board: { layers: unknown[] } };
					}
				 ).picture.board.layers = [
					deck,
					{ key: 'trucks-1', style: '' },
				];
				return { radio: card.querySelector( 'input' )!, deck };
			}

			it( "draws the pressed deck's board once its image is decoded, before the answer, and the answer confirms it", async () => {
				const { radio, deck } = started();
				const animated: string[] = [];
				Object.assign(
					document.querySelector( '.cbuild-board > picture' )!,
					{
						animate: () => {
							const animation = {
								id: '',
								finished: Promise.resolve(),
							};
							queueMicrotask( () =>
								animated.push( animation.id )
							);
							return animation;
						},
					}
				);
				reply = () => new Promise< Response >( () => undefined );
				actions.point( { type: 'pointerdown' } as PointerEvent );
				actions.pick( { target: radio } as unknown as Event );
				expect( deck.src, 'not before it is decoded' ).toBe(
					'https://shop.example/11.png'
				);
				made[ 0 ]!.decoded();
				await settle();
				expect( deck.src ).toBe( PRELOAD.src );
				expect( deck.sizes ).toBe( PRELOAD.sizes );
				expect( deck.sources ).toEqual( [
					{
						type: 'image/avif',
						srcset: PRELOAD.avif,
						sizes: PRELOAD.sizes,
					},
					{
						type: 'image/webp',
						srcset: PRELOAD.webp,
						sizes: PRELOAD.sizes,
					},
				] );
				const root = document.querySelector( '.cbuild' )!;
				expect( root.getAttribute( 'data-swapped' ) ).toBe( '' );
				expect( animated, 'on the next frame' ).toEqual( [] );
				vi.advanceTimersToNextFrame();
				await settle();
				// The deck settles, and its area is outlined for the eye (#138, D-413).
				expect( animated ).toEqual( [
					'cbuild-swapped',
					'cbuild-outline',
				] );
				expect( state.view.url, 'the answer still awaited' ).toBe(
					START.url
				);
			} );

			it( 'gives way to the answer: no mark once it is in, and no image decoded after it drawn over it', async () => {
				const { radio, deck } = started();
				reply = () =>
					answer( { ...START, url: START.url + '&answered' } );
				actions.pick( { target: radio } as unknown as Event );
				vi.advanceTimersByTime( WAIT );
				await settle();
				expect( state.view.url ).toBe( START.url + '&answered' );
				made[ 0 ]!.decoded();
				await settle();
				expect( deck.src, 'the answer drew the board' ).toBe(
					'https://shop.example/11.png'
				);

				// Drawn, then answered: the mark goes with the answer.
				const again = started();
				let answered: ( response: Response ) => void = () => undefined;
				reply = () =>
					new Promise< Response >( ( done ) => ( answered = done ) );
				actions.pick( { target: again.radio } as unknown as Event );
				vi.advanceTimersByTime( WAIT );
				made.at( -1 )!.decoded();
				await settle();
				const root = document.querySelector( '.cbuild' )!;
				expect( again.deck.src ).toBe( PRELOAD.src );
				vi.advanceTimersByTime( SLOW );
				expect( state.pending ).toBe( true );
				expect(
					root.hasAttribute( 'data-swapped' ),
					'kept while the answer is awaited'
				).toBe( true );
				answered( answer( { ...START, url: START.url + '&again' } ) );
				await settle();
				expect( state.view.url ).toBe( START.url + '&again' );
				expect( root.hasAttribute( 'data-swapped' ) ).toBe( false );
			} );

			it( 'draws nothing for a deck with no board image, one already drawn, a board turned over, no deck on the board, or an image never decoded', async () => {
				reply = () => new Promise< Response >( () => undefined );
				const cases: Array< ( deck: { src: string } ) => void > = [
					() => {
						(
							state.view.step as {
								options: { preload: { shown: boolean } }[];
							}
						 ).options[ 1 ]!.preload = { ...PRELOAD, shown: false };
					},
					( deck ) => {
						deck.src = PRELOAD.src;
					},
					() => {
						( state.view.picture as { turned: boolean } ).turned =
							true;
					},
					() => {
						(
							state.view as {
								picture: { board: { layers: unknown[] } };
							}
						 ).picture.board.layers = [
							{ key: 'trucks-1', style: '' },
						];
					},
					() => {
						( state.view as { picture?: unknown } ).picture =
							undefined;
					},
					() => {
						(
							state.view as { picture: { board?: unknown } }
						 ).picture.board = undefined;
					},
					() => {
						(
							state.view.step as {
								options: { preload: { src: string } }[];
							}
						 ).options[ 1 ]!.preload = { ...PRELOAD, src: '' };
					},
				];
				for ( const make of cases ) {
					reset();
					made = [];
					const { radio, deck } = started();
					make( deck );
					const was = JSON.stringify( state.view.picture );
					actions.pick( { target: radio } as unknown as Event );
					made.forEach( ( image ) => image.decoded() );
					await settle();
					expect( JSON.stringify( state.view.picture ) ).toBe( was );
					expect(
						document
							.querySelector( '.cbuild' )!
							.hasAttribute( 'data-swapped' )
					).toBe( false );
				}

				reset();
				made = [];
				const { radio, deck } = started();
				vi.stubGlobal(
					'Image',
					class {
						decode(): Promise< void > {
							return Promise.reject( new Error( 'Not decoded' ) );
						}
					}
				);
				actions.pick( { target: radio } as unknown as Event );
				await settle();
				expect( deck.src ).toBe( 'https://shop.example/11.png' );
			} );

			it( 'draws the board for a builder the script never started', async () => {
				const card = deckCards( 'webp' );
				setElement( card );
				const deck = {
					key: 'deck-1',
					style: '',
					src: 'https://shop.example/11.png',
				};
				(
					state.view as { picture: { board: { layers: unknown[] } } }
				 ).picture.board.layers = [ deck ];
				reply = () => new Promise< Response >( () => undefined );
				actions.pick( {
					target: card.querySelector( 'input' )!,
				} as unknown as Event );
				made[ 0 ]!.decoded();
				await settle();
				expect( deck.src ).toBe( PRELOAD.src );
				expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
			} );
		} );

		it( 'fetches WebP where the stage draws nothing yet, and lets the browser decode it off the page', () => {
			deckCards( 'avif' );
			document.querySelector( '.cbuild-picture' )!.remove();
			setElement(
				document.querySelectorAll< HTMLElement >( 'li' )[ 0 ]!
			);
			actions.point( { type: 'pointerdown' } as PointerEvent );
			expect(
				made.map( ( image ) => [ image.srcset, image.decoding ] )
			).toEqual( [ [ PRELOAD.webp, 'async' ] ] );
		} );

		it( 'fetches each deck with no files by type its own image, by its address alone', () => {
			const card = deckCards( 'webp' );
			const bare = { ...PRELOAD, avif: '', webp: '' };
			state.view = {
				...state.view,
				step: {
					slot: 'deck',
					options: [
						{ id: 12, value: '12', preload: bare },
						{
							id: 13,
							value: '13',
							preload: {
								...bare,
								src: 'https://shop.example/13.png',
							},
						},
					],
				},
			};
			setElement( card );
			actions.point( { type: 'pointerdown' } as PointerEvent );
			card.querySelector( 'input' )!.value = '13';
			actions.point( { type: 'pointerdown' } as PointerEvent );
			expect(
				made.map( ( image ) => [
					image.src,
					image.srcset,
					image.sizes,
				] )
			).toEqual( [
				[ PRELOAD.src, '', '' ],
				[ 'https://shop.example/13.png', '', '' ],
			] );
		} );

		it( 'fetches only the card the pointer rests on last, once it moves on before INTENT', () => {
			const card = deckCards( 'webp' );
			setElement( card );
			const radio = card.querySelector( 'input' )!;
			actions.point( { type: 'pointerenter' } as PointerEvent );
			vi.advanceTimersByTime( INTENT / 2 );
			radio.value = '11';
			actions.point( { type: 'pointerenter' } as PointerEvent );
			vi.advanceTimersByTime( INTENT );
			expect( made, 'deck 11 has no board image' ).toEqual( [] );
		} );

		it( 'fetches nothing, and throws nothing, for a pointer on no card, a card with no radio, or one the step does not list', () => {
			const card = deckCards( 'webp' );
			const radio = card.querySelector( 'input' )!;
			radio.value = '99';
			setElement( card );
			actions.point( { type: 'pointerdown' } as PointerEvent );
			radio.remove();
			actions.point( { type: 'pointerdown' } as PointerEvent );
			setElement( null );
			actions.point( { type: 'pointerdown' } as PointerEvent );
			actions.point( { type: 'pointerenter' } as PointerEvent );
			vi.advanceTimersByTime( INTENT );
			expect( made ).toEqual( [] );
		} );

		it( 'forgets a pointer resting on a card when the page is reset', () => {
			setElement( deckCards( 'webp' ) );
			actions.point( { type: 'pointerenter' } as PointerEvent );
			reset();
			vi.advanceTimersByTime( INTENT );
			expect( made ).toEqual( [] );
		} );

		it( 'waits for a pick made a second after the press as for the arrows', () => {
			const card = deckCards( 'webp' );
			setElement( card );
			actions.point( { type: 'pointerdown' } as PointerEvent );
			vi.advanceTimersByTime( 1000 );
			actions.pick( {
				target: card.querySelector( 'input' )!,
			} as unknown as Event );
			expect( fetched ).toHaveLength( 0 );
			vi.advanceTimersByTime( WAIT );
			expect( fetched ).toHaveLength( 1 );
		} );

		it( 'never says the picture is drawn for a wait after a pick has been answered', async () => {
			const card = deckCards( 'webp' );
			setElement( card );
			reply = () => answer( { ...START, url: START.url + '&picked' } );
			actions.pick( {
				target: card.querySelector( 'input' )!,
			} as unknown as Event );
			vi.advanceTimersByTime( WAIT );
			await settle();
			expect( state.view.url ).toBe( START.url + '&picked' );
			reply = () => new Promise< Response >( () => undefined );
			actions.back();
			vi.advanceTimersByTime( SLOW );
			expect( state.pending ).toBe( true );
			expect( state.drawing ).toBe( false );
		} );

		describe( 'an answer that draws another deck', () => {
			type Deck = {
				key: string;
				style: string;
				src: string;
				sizes?: string;
				sources?: { type: string; srcset: string }[];
			};
			const boarded = ( layers: Deck[] ): Record< string, unknown > => ( {
				...START,
				url: window.location.origin + '/?answered',
				picture: { turnable: false, turned: false, board: { layers } },
			} );
			const DECK = {
				key: 'deck-1',
				style: '',
				src: PRELOAD.src,
				sizes: PRELOAD.sizes,
				sources: [
					{ type: 'image/avif', srcset: PRELOAD.avif },
					{ type: 'image/webp', srcset: PRELOAD.webp },
				],
			};

			async function answered( view: unknown ): Promise< void > {
				reply = () => answer( view );
				actions.back();
				await settle();
			}

			it( 'is shown at once where it draws the deck already drawn, wherever its layer sits, or no deck', async () => {
				deckCards( 'webp' );
				(
					state.view as { picture: { board: { layers: Deck[] } } }
				 ).picture.board.layers = [
					{
						key: 'trucks-1',
						style: '',
						src: 'https://shop.example/30.png',
					},
					{
						key: 'deck-1',
						style: '',
						src: 'https://shop.example/11.png',
					},
				];
				await answered(
					boarded( [
						{
							key: 'wheels-1',
							style: '',
							src: 'https://shop.example/50.png',
						},
						{
							key: 'deck-1',
							style: '',
							src: 'https://shop.example/11.png',
						},
					] )
				);
				expect( state.view.url ).toBe(
					window.location.origin + '/?answered'
				);

				state.view = START;
				await answered( {
					...boarded( [
						{
							key: 'trucks-1',
							style: '',
							src: 'https://shop.example/30.png',
						},
					] ),
					url: window.location.origin + '/?no-deck',
				} );
				expect( state.view.url ).toBe(
					window.location.origin + '/?no-deck'
				);
				expect( made ).toEqual( [] );
			} );

			it( 'waits for its deck from a view with no picture', async () => {
				deckCards( 'webp' );
				state.view = START;
				await answered( boarded( [ DECK ] ) );
				expect( state.view ).toBe( START );
				made[ 0 ]!.decoded();
				await settle();
				expect( state.view.url ).toBe(
					window.location.origin + '/?answered'
				);
			} );

			it( 'fetches its deck in the type the stage draws, at its sizes', async () => {
				deckCards( 'avif' );
				await answered( boarded( [ DECK ] ) );
				expect(
					made.map( ( image ) => [ image.srcset, image.sizes ] )
				).toEqual( [ [ PRELOAD.avif, PRELOAD.sizes ] ] );
			} );

			it( 'fetches WebP for a deck with no AVIF, and its address alone for one with no files by type', async () => {
				deckCards( 'avif' );
				await answered(
					boarded( [
						{
							key: 'deck-1',
							style: '',
							src: PRELOAD.src,
							sources: [
								{ type: 'image/webp', srcset: PRELOAD.webp },
							],
						},
					] )
				);
				reset();
				deckCards( 'avif' );
				await answered(
					boarded( [
						{
							key: 'deck-1',
							style: '',
							src: 'https://shop.example/13.png',
						},
					] )
				);
				expect(
					made.map( ( image ) => [
						image.src,
						image.srcset,
						image.sizes,
					] )
				).toEqual( [
					[ PRELOAD.src, PRELOAD.webp, '' ],
					[ 'https://shop.example/13.png', '', '' ],
				] );
			} );
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

	it( 'says nothing beside a dimmed Next whose bar gives no words for what is missing', () => {
		state.view = { ...EMPTY, bar: { next: { ready: false } } };
		state.live = true;
		const { next } = dimmedForm( true );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		vi.advanceTimersToNextFrame();
		expect( state.missing ).toBe( '' );
		expect( state.status ).toBe( '' );
		expect( fetched ).toHaveLength( 0 );
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

	// Firefox scrolls a focused control only once it leaves the screen: a card under what the dimmed Next says, above
	// the bar's own top, stayed there (D-388).
	it( 'brings a control under what the bar says over itself into view, and not while it says nothing', () => {
		for ( const said of [ 'cbuild-bar__missing', 'cbuild__limited' ] ) {
			let { below, scrolled } = page( 650 );
			const bar = document.querySelector< HTMLElement >( '.cbuild-bar' )!;
			const line = document.createElement( 'p' );
			line.className = said;
			bar.prepend( line );
			line.getBoundingClientRect = () =>
				( { top: 610, bottom: 642, height: 32 } ) as DOMRect;
			actions.reveal( { target: below } as unknown as FocusEvent );
			expect( scrolled, `${ said }` ).toEqual( [
				'{"block":"nearest"}',
			] );
			( { below, scrolled } = page( 650 ) );
			const empty = document.createElement( 'p' );
			empty.className = said;
			document.querySelector( '.cbuild-bar' )!.prepend( empty );
			empty.getBoundingClientRect = () =>
				( { top: 650, bottom: 650, height: 0 } ) as DOMRect;
			actions.reveal( { target: below } as unknown as FocusEvent );
			expect( scrolled, `${ said }, empty` ).toEqual( [] );
		}
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

	// The auditor's third pass on M12: the limit's line comes 10 s after a press, over a card picked with Space just
	// above the bar, which keeps focus. As the line takes its height the card in focus is brought clear (D-383).
	it( 'brings the control in focus clear when a line over the bar takes its height, and nothing else', () => {
		const root = builder();
		const bar = root.querySelector< HTMLElement >( '.cbuild-bar' )!;
		bar.innerHTML =
			'<p class="cbuild__limited"></p><button class="in-bar">Next</button>';
		const card = document.createElement( 'input' );
		card.type = 'radio';
		root.prepend( card );
		const watched: Array< () => void > = [];
		vi.stubGlobal(
			'ResizeObserver',
			class {
				constructor( private again: () => void ) {}
				observe(): void {
					watched.push( this.again );
				}
			}
		);
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const line = bar.querySelector< HTMLElement >( '.cbuild__limited' )!;
		bar.getClientRects = () => [ {} ] as unknown as DOMRectList;
		bar.getBoundingClientRect = () =>
			( { top: 700, bottom: 780, height: 80 } ) as DOMRect;
		line.getBoundingClientRect = () =>
			( { top: 700, bottom: 700, height: 0 } ) as DOMRect;
		card.getBoundingClientRect = () =>
			( { top: 560, bottom: 696, height: 136 } ) as DOMRect;
		const scrolled: string[] = [];
		card.scrollIntoView = ( options ) =>
			scrolled.push( JSON.stringify( options ) );
		// Focus said without a focus event, which another test's listener on the page could take.
		let focused: Element = card;
		Object.defineProperty( document, 'activeElement', {
			get: () => focused,
			configurable: true,
		} );
		watched[ 0 ]!();
		expect( scrolled, 'the line says nothing' ).toEqual( [] );
		line.getBoundingClientRect = () =>
			( { top: 630, bottom: 692, height: 62 } ) as DOMRect;
		watched[ 0 ]!();
		expect( scrolled ).toEqual( [ '{"block":"nearest"}' ] );
		// Focus in the bar, or nowhere in the builder, is left where it is.
		focused = bar.querySelector< HTMLElement >( '.in-bar' )!;
		watched[ 0 ]!();
		const outside = document.createElement( 'button' );
		document.body.append( outside );
		outside.getBoundingClientRect = card.getBoundingClientRect;
		outside.scrollIntoView = card.scrollIntoView;
		focused = outside;
		watched[ 0 ]!();
		expect( scrolled ).toHaveLength( 1 );
		delete ( document as unknown as { activeElement?: Element } )
			.activeElement;
		document.documentElement.style.removeProperty( '--cbuild-bar-size' );
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

	it( 'crosses over to the change panel going on, as it stands at the step it opened over', async () => {
		motion( false, true );
		document.documentElement.classList.add( 'cbuild-back' );
		state.view = { ...START, progress: [ { current: true } ] };
		const { radio } = form();
		reply = () =>
			answer( {
				...START,
				isChanges: true,
				progress: [ { current: true } ],
			} );
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( state.view ).toMatchObject( { isChanges: true } );
		expect(
			document.documentElement.classList.contains( 'cbuild-back' )
		).toBe( false );
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

// Issue #46, settled on 17 November 2026: "The flip is a button by the board ... with aria-pressed"; "The phone bar's
// board shows the same side". The server says which side a step opens on (`picture.turned`); from 1.2.0 the store keeps
// the shopper's own turn until the step changes (F27, #127).
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

	// Cowork's review of 1.1.0, F27 (#127): "A turn the shopper makes lasts until they change step". Pressed on the
	// wheels, the trucks step showed the top.
	it( "turns the board with the button for the step, and opens the next step on the server's side", async () => {
		state.view = view( 'wheels', { turnable: true, turned: false } );
		actions.over();
		expect( shown().turned, 'pressed: the top' ).toBe( true );
		expect( state.turning ).toBe( true );
		await go( view( 'trucks', { turnable: true, turned: false } ) );
		expect(
			shown().turned,
			'the trucks step opens underneath, the turn on the wheels over'
		).toBe( false );
		expect( state.turning, 'the board turns back' ).toBe( true );
		await go( view( 'hardware', { turnable: true, turned: true } ) );
		expect( shown().turned ).toBe( true );

		actions.under();
		expect( shown().turned, 'pressed again: the underside' ).toBe( false );
		await go( view( 'grip', { turnable: true, turned: true } ) );
		expect( shown().turned, 'the grip step opens on the top' ).toBe( true );
		await go( view( 'review', { turnable: true, turned: false } ) );
		expect( shown().turned ).toBe( false );
	} );

	it( 'keeps the turn while the step stays, as a pick on it is answered, and forgets it on the change panel', async () => {
		state.view = view( 'wheels', { turnable: true, turned: false } );
		actions.over();
		await go( view( 'wheels', { turnable: true, turned: false } ) );
		expect( shown().turned, 'a pick on the same step' ).toBe( true );
		expect( state.turning ).toBe( false );
		await go( {
			...view( 'wheels', { turnable: true, turned: false } ),
			isChanges: true,
		} );
		expect( shown().turned, 'the change panel is a step of its own' ).toBe(
			false
		);
	} );

	it( 'shows the underside where a chosen top cannot be drawn, and the top again once it can, on the same step', async () => {
		state.view = view( 'deck', { turnable: true, turned: false } );
		actions.over();
		await go( view( 'deck', { turnable: false, turned: false } ) );
		expect( shown().turned, 'a deck with no layer image has no top' ).toBe(
			false
		);
		await go( view( 'deck', { turnable: true, turned: false } ) );
		expect( shown().turned ).toBe( true );
	} );

	it( 'does nothing where there is no top, and leaves a view with no picture as it came', async () => {
		state.view = view( 'deck', { turnable: false, turned: false } );
		actions.over();
		expect( shown().turned ).toBe( false );
		expect( state.turning ).toBe( false );

		state.view = view( 'deck', undefined );
		actions.over();
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

	// Cowork's review of 1.1.0, F28 (#128): "a two-option control, Underside and Top"; the side shown is pressed.
	it( 'turns nothing when the side already shown is pressed', () => {
		state.view = view( 'wheels', { turnable: true, turned: false } );
		actions.under();
		expect( shown().turned ).toBe( false );
		expect( state.turning, 'the underside is shown already' ).toBe( false );
		actions.over();
		state.turning = false;
		actions.over();
		expect( shown().turned ).toBe( true );
		expect( state.turning, 'the top is shown already' ).toBe( false );
		actions.under();
		expect( shown().turned ).toBe( false );
		expect( state.turning ).toBe( true );
	} );

	it( 'moves nothing when a new view shows the side already shown, so a board shown again later is not turned', async () => {
		state.view = view( 'deck', { turnable: true, turned: false } );
		actions.over();
		expect( state.turning ).toBe( true );
		await go( view( 'deck', { turnable: true, turned: false } ) );
		expect( shown().turned ).toBe( true );
		expect( state.turning, 'the turn is over' ).toBe( false );
	} );
} );

// Cowork's review of 1.1.0, F20 (#118; D-355, D-356): on a real host the route answers past the 500 ms CLS leaves out
// after a press, so what the store knows a press brings is made at the press, and the step's lit Next is asked ahead.
describe( 'what a press foretells', () => {
	const init = (): void =>
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();

	/**
	 * A builder with the step's form and the bar's Next, started, the form the element its directives run on.
	 */
	function builder(): {
		root: HTMLElement;
		sheet: HTMLFormElement;
		radio: HTMLInputElement;
		width: HTMLButtonElement;
		next: HTMLButtonElement;
	} {
		document.body.innerHTML = `
			<div class="cbuild">
				<form class="cbuild-step" id="cbuild-step-form" method="get" action="/">
					<input type="hidden" name="page_id" value="25">
					<button type="submit" class="cbuild-width__option" name="cbuild[_width]" value="8.5in">8.5″</button>
					<input type="radio" name="cbuild[deck]" value="12">
				</form>
				<div class="cbuild-bar">
					<button type="submit" class="cbuild-bar__next" form="cbuild-step-form" name="cbuild[step]" value="trucks">Next: Trucks</button>
				</div>
			</div>`;
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		setElement( root );
		init();
		const sheet = root.querySelector( 'form' )!;
		setElement( sheet );
		return {
			root,
			sheet,
			radio: root.querySelector( 'input[type=radio]' )!,
			width: root.querySelector( '.cbuild-width__option' )!,
			next: root.querySelector( '.cbuild-bar__next' )!,
		};
	}

	const ASKING = {
		...START,
		presets: { shown: true },
		step: {
			slot: 'deck',
			hasLead: true,
			more: { count: 'Showing 0 of 0' },
			shelf: {
				width: {
					name: 'cbuild[_width]',
					asking: true,
					chosen: '',
					options: [
						{ value: '8in', text: '8″', pressed: 'false' },
						{ value: '8.5in', text: '8.5″', pressed: 'false' },
					],
				},
			},
		},
	};

	type Width = {
		asking: boolean;
		chosen: string;
		options: { pressed: string }[];
	};
	const width = (): Width =>
		( state.view.step as { shelf: { width: Width } } ).shelf.width;

	it( 'folds the width question to the width pressed at its press, keeping the ready builds’ place until the answer', async () => {
		const { root, width: button } = builder();
		state.view = structuredClone( ASKING );
		let answered: ( response: Response ) => void = () => undefined;
		reply = () =>
			new Promise< Response >( ( done ) => ( answered = done ) );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		expect( width().asking ).toBe( false );
		expect( width().chosen ).toBe( '8.5″' );
		expect( width().options.map( ( option ) => option.pressed ) ).toEqual( [
			'false',
			'true',
		] );
		expect(
			( state.view.step as { more: { count: string } } ).more.count
		).toBe( '' );
		expect(
			( state.view as { presets: { shown: boolean } } ).presets.shown,
			'shown, out of sight'
		).toBe( true );
		expect( root.getAttribute( 'data-folded' ) ).toBe( '' );
		expect( fetched ).toHaveLength( 1 );
		await settle();
		answered( answer( NEXT ) );
		await settle();
		expect( root.hasAttribute( 'data-folded' ) ).toBe( false );
	} );

	// Cowork's review of 1.2.0, F30: the step's lead and the guided start went at the width's answer, 0.9 to 1.3 s after
	// the press on the demo, and moved the step up then (D-405).
	it( 'takes the step’s lead and the guided start away at a width’s press, keeping builds the guide was asked for', () => {
		for ( const [ asked, shown ] of [
			[ false, false ],
			[ true, true ],
		] as const ) {
			const { width: button } = builder();
			state.view = {
				...structuredClone( ASKING ),
				guide: { shown: true, asked },
			} as unknown as typeof state.view;
			reply = () => new Promise< Response >( () => undefined );
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: button,
					cancelable: true,
				} )
			);
			expect( ( state.view.step as { hasLead: boolean } ).hasLead ).toBe(
				false
			);
			expect(
				( state.view as { guide: { shown: boolean } } ).guide.shown
			).toBe( shown );
		}
		// A builder an earlier release's container drew has no guide in its view.
		const { width: button } = builder();
		state.view = structuredClone( ASKING );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		expect( ( state.view.step as { hasLead: boolean } ).hasLead ).toBe(
			false
		);
		expect( 'guide' in state.view ).toBe( false );
	} );

	it( 'puts the lead and the guided start back when a width’s press hits a limit', async () => {
		for ( const [ led, guided ] of [
			[ true, true ],
			[ false, false ],
		] as const ) {
			const { width: button } = builder();
			const view = {
				...structuredClone( ASKING ),
				guide: { shown: guided, asked: false },
			};
			view.step.hasLead = led;
			state.view = view as unknown as typeof state.view;
			reply = () =>
				new Response( JSON.stringify( { message: 'Later.' } ), {
					status: 429,
					headers: { 'Content-Type': 'application/json' },
				} );
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: button,
					cancelable: true,
				} )
			);
			await settle();
			await vi.advanceTimersByTimeAsync( 50 );
			expect( width().asking ).toBe( true );
			expect( ( state.view.step as { hasLead: boolean } ).hasLead ).toBe(
				led
			);
			expect(
				( state.view as { guide: { shown: boolean } } ).guide.shown
			).toBe( guided );
		}
		// No guide: none is made.
		const { width: button } = builder();
		state.view = structuredClone( ASKING );
		reply = () =>
			new Response( JSON.stringify( { message: 'Later.' } ), {
				status: 429,
				headers: { 'Content-Type': 'application/json' },
			} );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		await settle();
		await vi.advanceTimersByTimeAsync( 50 );
		expect( 'guide' in state.view ).toBe( false );
	} );

	// The smart swap's notice sits above the step (#135, D-397) and lasts until the next move, which a width's press is: its
	// answer takes the notice away, so the press does, as it does the lead and the guided start (F30, D-405).
	it( 'takes a swap’s notice away at a width’s press, and puts it back when the press hits a limit', async () => {
		const { width: button } = builder();
		state.view = {
			...structuredClone( ASKING ),
			swapped: {
				shown: true,
				text: 'Swapped: deck.',
				undo: { url: '/kit/?_undo[deck]=7', text: 'Undo swap' },
			},
		} as unknown as typeof state.view;
		reply = () =>
			new Response( JSON.stringify( { message: 'Later.' } ), {
				status: 429,
				headers: { 'Content-Type': 'application/json' },
			} );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		const swapped = () =>
			( state.view as { swapped: { shown: boolean; text: string } } )
				.swapped;
		expect( swapped().shown, 'gone at the press' ).toBe( false );
		await settle();
		await vi.advanceTimersByTimeAsync( 50 );
		expect( width().asking ).toBe( true );
		expect( swapped().shown, 'back at the limit' ).toBe( true );
		expect( swapped().text ).toBe( 'Swapped: deck.' );
		// No notice in the view: none is made.
		const { width: again } = builder();
		state.view = structuredClone( ASKING );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: again, cancelable: true } )
		);
		await settle();
		await vi.advanceTimersByTimeAsync( 50 );
		expect( 'swapped' in state.view ).toBe( false );
	} );

	// D-408: the deck step's width line stayed at Next's press until the answer, a second late on the demo, took it, and
	// the cards moved up under the heading (D-406). It goes at the press, with the "Showing" line, as the answer takes
	// them; a press the limit stops puts them back.
	const CHOSEN = {
		...START,
		step: {
			slot: 'deck',
			stepName: 'cbuild[step]',
			next: { value: 'trucks' },
			more: { count: 'Showing 3 of 3' },
			shelf: { width: { shown: true } },
		},
	};
	type Onward = {
		more: { count: string };
		shelf: { width: { shown: boolean } };
	};
	const onward = (): Onward => state.view.step as unknown as Onward;

	it( 'takes the deck step’s width line and its count away at the press of Next, and puts them back at a limit', async () => {
		const { root, next } = builder();
		state.view = structuredClone( CHOSEN ) as unknown as typeof state.view;
		reply = () =>
			new Response( JSON.stringify( { message: 'Later.' } ), {
				status: 429,
				headers: { 'Content-Type': 'application/json' },
			} );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		expect( onward().shelf.width.shown ).toBe( false );
		expect( onward().more.count ).toBe( '' );
		expect( root.hasAttribute( 'data-folded' ) ).toBe( false );
		await settle();
		await vi.advanceTimersByTimeAsync( 50 );
		expect( onward().shelf.width.shown ).toBe( true );
		expect( onward().more.count ).toBe( 'Showing 3 of 3' );
		expect( state.limited ).toBe( 'Later.' );

		// A step with no count has none made.
		const { next: again } = builder();
		const counted = structuredClone( CHOSEN ) as {
			step: { more?: unknown };
		};
		delete counted.step.more;
		state.view = counted as unknown as typeof state.view;
		actions.submit(
			new SubmitEvent( 'submit', { submitter: again, cancelable: true } )
		);
		expect( onward().shelf.width.shown ).toBe( false );
		expect( 'more' in ( state.view.step as object ) ).toBe( false );
		await settle();
		await vi.advanceTimersByTimeAsync( 50 );
		expect( onward().shelf.width.shown ).toBe( true );
		expect( 'more' in ( state.view.step as object ) ).toBe( false );
	} );

	// #152 (D-512): the first step has no Back; the second's came in with the answer a second late, narrowed the total
	// and moved Next. The view gives the first step the next step's Back, which the press shows; a limit hides it again.
	type Back = {
		shown: boolean;
		url: string;
		label: string;
		ahead: { url: string; label: string };
	};
	const FIRST = {
		...START,
		step: {
			slot: 'deck',
			stepName: 'cbuild[step]',
			next: { value: 'trucks' },
		},
		bar: {
			next: { ready: true, missing: '' },
			back: {
				shown: false,
				url: '',
				label: '',
				ahead: { url: '/kit/?cbuild[step]=deck', label: 'Back: Deck' },
			},
		},
	};
	const back = (): Back =>
		( state.view as unknown as { bar: { back: Back } } ).bar.back;

	it( 'shows the next step’s Back at the press of the first step’s Next, and hides it again at a limit', async () => {
		const { next } = builder();
		state.view = structuredClone( FIRST ) as unknown as typeof state.view;
		reply = () =>
			new Response( JSON.stringify( { message: 'Later.' } ), {
				status: 429,
				headers: { 'Content-Type': 'application/json' },
			} );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		expect( back() ).toEqual( {
			shown: true,
			url: '/kit/?cbuild[step]=deck',
			label: 'Back: Deck',
			ahead: { url: '/kit/?cbuild[step]=deck', label: 'Back: Deck' },
		} );
		await settle();
		await vi.advanceTimersByTimeAsync( 50 );
		expect( back() ).toEqual( FIRST.bar.back );
		expect( state.limited ).toBe( 'Later.' );
	} );

	it( 'leaves Back as it is where the step has its own, the view gives none, or the press is no Next', () => {
		const cases: [ string, ( view: typeof FIRST ) => void, string? ][] = [
			[
				'its own Back',
				( view ) => {
					view.bar.back.shown = true;
					view.bar.back.url = '/kit/?cbuild[step]=grip';
					view.bar.back.label = 'Back: Grip';
				},
			],
			[ 'none given', ( view ) => ( view.bar.back.ahead.url = '' ) ],
			[ 'no bar', ( view ) => delete ( view as { bar?: unknown } ).bar ],
			[ 'another value', () => undefined, 'wheels' ],
		];
		for ( const [ name, change, value ] of cases ) {
			const { next } = builder();
			if ( undefined !== value ) {
				next.value = value;
			}
			const view = structuredClone( FIRST );
			change( view );
			const before = JSON.stringify(
				( view as { bar?: unknown } ).bar ?? null
			);
			state.view = view as unknown as typeof state.view;
			reply = () => new Promise< Response >( () => undefined );
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: next,
					cancelable: true,
				} )
			);
			expect(
				`${ name }: ${ JSON.stringify(
					( state.view as { bar?: unknown } ).bar ?? null
				) }`
			).toBe( `${ name }: ${ before }` );
		}
	} );

	it( 'leaves the width line where the press is no Next to another step, or the step shows none', () => {
		const cases: [
			string,
			( view: typeof CHOSEN ) => void,
			string?,
			string?,
		][] = [
			[
				'another step’s width hidden',
				( view ) => ( view.step.shelf.width.shown = false ),
			],
			[
				'no next step',
				( view ) => ( view.step.next.value = '' ),
				'cbuild[step]',
				'',
			],
			[
				'the same step',
				( view ) => ( view.step.next.value = 'deck' ),
				'cbuild[step]',
				'deck',
			],
			[ 'another name', () => undefined, 'cbuild[sort]', 'trucks' ],
			[ 'another value', () => undefined, 'cbuild[step]', 'wheels' ],
		];
		for ( const [ name, change, button, value ] of cases ) {
			const { next } = builder();
			if ( undefined !== button ) {
				next.name = button;
				next.value = value ?? '';
			}
			const view = structuredClone( CHOSEN );
			change( view );
			const shown = view.step.shelf.width.shown;
			state.view = view as unknown as typeof state.view;
			reply = () => new Promise< Response >( () => undefined );
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: next,
					cancelable: true,
				} )
			);
			expect(
				`${ name }: ${ String( onward().shelf.width.shown ) }`
			).toBe( `${ name }: ${ String( shown ) }` );
			expect( `${ name }: ${ onward().more.count }` ).toBe(
				`${ name }: Showing 3 of 3`
			);
		}
		// A form sent with no button pressed, as Enter in a field sends it.
		builder();
		state.view = structuredClone( CHOSEN ) as unknown as typeof state.view;
		actions.submit( new SubmitEvent( 'submit', { cancelable: true } ) );
		expect( onward().shelf.width.shown ).toBe( true );
	} );

	// The auditor's second pass on M12, should-fix 3: a width press the rate limit or the store's limit stopped kept its
	// fold, the widths folded away and no decks under them, and only Change asked again (D-364).
	it( 'asks the width question again, its options open and the pressed one focused, when the press hits a limit', async () => {
		const { root, width: button } = builder();
		state.view = structuredClone( ASKING );
		reply = () =>
			new Response(
				JSON.stringify( {
					message: 'Too many searches. Try again in a minute.',
				} ),
				{ status: 429, headers: { 'Content-Type': 'application/json' } }
			);
		button.focus();
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		expect( width().asking ).toBe( false );
		await settle();
		await vi.advanceTimersByTimeAsync( 50 );
		expect( width().asking ).toBe( true );
		expect( width().chosen ).toBe( '' );
		expect( width().options.map( ( option ) => option.pressed ) ).toEqual( [
			'false',
			'false',
		] );
		expect(
			( state.view.step as { more: { count: string } } ).more.count
		).toBe( 'Showing 0 of 0' );
		expect( root.hasAttribute( 'data-folded' ) ).toBe( false );
		expect( state.limited ).toBe(
			'Too many searches. Try again in a minute.'
		);
		expect( state.status ).toBe( state.limited );
		expect( button.ownerDocument.activeElement ).toBe( button );

		// A view that comes after leaves the next limit nothing to undo.
		reply = () => answer( NEXT );
		actions.back();
		await settle();
		state.view = structuredClone( ASKING );
		reply = () =>
			new Response( JSON.stringify( { message: 'Later.' } ), {
				status: 429,
				headers: { 'Content-Type': 'application/json' },
			} );
		actions.back();
		await settle();
		expect( width().asking ).toBe( true );
		expect( state.limited ).toBe( 'Later.' );
	} );

	it( 'puts back the line, the widths pressed and the count as they were, and focus on the width, when a press hits a limit', async () => {
		const asked = structuredClone( ASKING ) as unknown as {
			step: {
				more?: { count?: string };
				shelf: {
					width: {
						chosen?: string;
						options: { pressed?: string }[];
					};
				};
			};
		};
		// Asked again after Change: a width chosen and pressed before, one option the server marks neither way, and a
		// count with no words.
		asked.step.shelf.width.chosen = '8″';
		asked.step.shelf.width.options[ 0 ]!.pressed = 'true';
		delete asked.step.shelf.width.options[ 1 ]!.pressed;
		asked.step.more = {};
		const bare = structuredClone( ASKING ) as unknown as typeof asked;
		delete bare.step.shelf.width.chosen;
		delete bare.step.more;
		const cases: Array< [ typeof asked, string, string[] ] > = [
			[ asked, '8″', [ 'true', 'false' ] ],
			[ bare, '', [ 'false', 'false' ] ],
		];
		for ( const [ view, chosen, pressed ] of cases ) {
			const { width: button } = builder();
			state.view = structuredClone(
				view
			) as unknown as typeof state.view;
			reply = () =>
				new Response( JSON.stringify( { message: 'Later.' } ), {
					status: 429,
					headers: { 'Content-Type': 'application/json' },
				} );
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: button,
					cancelable: true,
				} )
			);
			expect( width().chosen ).toBe( '8.5″' );
			await settle();
			await vi.advanceTimersByTimeAsync( 50 );
			expect( width().asking ).toBe( true );
			expect( width().chosen ).toBe( chosen );
			expect(
				width().options.map( ( option ) => option.pressed )
			).toEqual( pressed );
			expect(
				( state.view.step as { more?: { count?: string } } ).more?.count
			).toBe( view.step.more ? '' : undefined );
			expect( button.ownerDocument.activeElement ).toBe( button );
		}
	} );

	it( 'folds a step with no count, and a builder the script never started', () => {
		const { width: button } = builder();
		const view = structuredClone( ASKING );
		delete ( view.step as { more?: unknown } ).more;
		state.view = view;
		reply = () => new Promise< Response >( () => undefined );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		expect( width().chosen ).toBe( '8.5″' );

		reset();
		state.view = structuredClone( ASKING );
		setElement( document.querySelector( 'form' )! );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: button, cancelable: true } )
		);
		expect( width().chosen ).toBe( '8.5″' );
	} );

	it( 'folds nothing for a press that is no width the step asks for', () => {
		const { root, width: button, next } = builder();
		const presses: Array< [ HTMLElement, () => void ] > = [
			[ next, () => undefined ],
			[ button, () => ( width().asking = false ) ],
			[ button, () => button.setAttribute( 'value', '9in' ) ],
			[ button, () => button.setAttribute( 'name', 'cbuild[deck]' ) ],
			[
				button,
				() => {
					( state.view.step as { shelf?: unknown } ).shelf =
						undefined;
				},
			],
		];
		state.view = structuredClone( ASKING );
		actions.submit( new SubmitEvent( 'submit', { cancelable: true } ) );
		expect( state.view, 'no button pressed' ).toEqual( ASKING );
		for ( const [ pressed, make ] of presses ) {
			button.setAttribute( 'name', 'cbuild[_width]' );
			button.setAttribute( 'value', '8.5in' );
			state.view = structuredClone( ASKING );
			make();
			const was = JSON.stringify( state.view );
			actions.submit(
				new SubmitEvent( 'submit', {
					submitter: pressed,
					cancelable: true,
				} )
			);
			expect( JSON.stringify( state.view ) ).toBe( was );
			expect( root.hasAttribute( 'data-folded' ) ).toBe( false );
		}
	} );

	it( 'makes at a pick what its answer will show: no ready builds, no deck line, a line kept for each price, none recommended', () => {
		const { root, radio } = builder();
		state.view = {
			...START,
			presets: { shown: true },
			bar: { next: { ready: false, missing: '' }, started: false },
			step: {
				slot: 'deck',
				hasLead: true,
				options: [
					{ value: '11', hasDelta: false, recommended: true },
					{ value: '12', hasDelta: false, recommended: false },
					null,
				],
			},
		};
		reply = () => new Promise< Response >( () => undefined );
		actions.pick( { target: radio } as unknown as Event );
		const step = state.view.step as {
			hasLead: boolean;
			options: ( { hasDelta: boolean; recommended: boolean } | null )[];
		};
		expect(
			( state.view as { presets: { shown: boolean } } ).presets.shown
		).toBe( false );
		expect( step.hasLead ).toBe( false );
		expect( step.options ).toEqual( [
			{ value: '11', hasDelta: true, recommended: false, checked: false },
			{ value: '12', hasDelta: true, recommended: false, checked: true },
			null,
		] );
		expect( root.hasAttribute( 'data-picked' ) ).toBe( true );
		// The build has a part: the bar keeps the estimate's line for the answer (F24; D-374).
		expect(
			( state.view as { bar: { started: boolean } } ).bar.started
		).toBe( true );
	} );

	it( 'keeps a later step’s line, and at No risers no prices and the recommendation, and asks nothing of a view with no ready builds', () => {
		const { radio } = builder();
		state.view = {
			...START,
			step: {
				slot: 'risers',
				hasLead: true,
				options: [
					{ value: '40', hasDelta: true, recommended: true },
					{ value: '39', hasDelta: true, recommended: false },
				],
			},
		};
		reply = () => new Promise< Response >( () => undefined );
		const risers = structuredClone( state.view );
		radio.value = '40';
		actions.pick( { target: radio } as unknown as Event );
		expect(
			( state.view.step as { hasLead: boolean } ).hasLead,
			'the lead is the deck step’s alone'
		).toBe( true );
		state.view = risers;
		radio.value = '0';
		expect( () =>
			actions.pick( { target: radio } as unknown as Event )
		).not.toThrow();
		const step = state.view.step as {
			hasLead: boolean;
			options: { hasDelta: boolean; recommended: boolean }[];
		};
		expect( step.hasLead ).toBe( true );
		expect(
			step.options.map( ( option ) => [
				option.hasDelta,
				option.recommended,
			] )
		).toEqual( [
			[ false, true ],
			[ false, false ],
		] );

		state.view = {
			...START,
			step: { slot: 'deck', hasLead: true },
		};
		actions.pick( { target: radio } as unknown as Event );
		expect(
			( state.view.step as { hasLead: boolean } ).hasLead,
			'No risers is no deck'
		).toBe( true );
	} );

	const READY = {
		...NEXT,
		url: window.location.origin + '/?picked',
		step: { slot: 'deck' },
		bar: {
			next: {
				ready: true,
				onStep: true,
				name: 'cbuild[step]',
				value: 'trucks',
			},
		},
	};

	it( 'asks ahead for the lit Next once a pick’s answer is shown, and shows its answer at the press of Next', async () => {
		const { radio, next } = builder();
		radio.checked = true;
		reply = () => answer( READY );
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( state.view ).toEqual( READY );
		expect( fetched ).toHaveLength( 1 );
		vi.advanceTimersToNextFrame();
		expect( fetched, 'Next asked ahead' ).toHaveLength( 2 );
		expect( fetched[ 1 ]!.searchParams.get( 'cbuild[step]' ) ).toBe(
			'trucks'
		);
		expect( fetched[ 1 ]!.searchParams.get( 'cbuild[deck]' ) ).toBe( '12' );
		reply = () => answer( NEXT );
		await settle();
		expect( state.view, 'nothing shown for it' ).toEqual( READY );

		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		await settle();
		expect( fetched, 'nothing asked again' ).toHaveLength( 2 );
		expect( state.view.url ).toBe( READY.url );
	} );

	it( 'asks nothing ahead for a Next not lit, not the step’s, on the change panel, with no form or no such button, or after a search', async () => {
		const cases: Array< [ string, () => void ] > = [
			[ 'not lit', () => ( READY_OF().bar.next.ready = false ) ],
			[ 'the review’s', () => ( READY_OF().bar.next.onStep = false ) ],
			[ 'the change panel', () => ( READY_OF().isChanges = true ) ],
			[
				'another button',
				() => ( READY_OF().bar.next.value = 'wheels' ),
			],
			[
				'another name',
				() => ( READY_OF().bar.next.name = 'cbuild[x]' ),
			],
			[
				'no bar',
				() => ( ( READY_OF() as { bar?: unknown } ).bar = undefined ),
			],
			[
				'no form',
				() =>
					document
						.querySelector( 'form' )!
						.classList.remove( 'cbuild-step' ),
			],
			[
				'no bar button',
				() =>
					document
						.querySelector( '.cbuild-bar__next' )!
						.classList.remove( 'cbuild-bar__next' ),
			],
		];
		let shown = structuredClone( READY ) as typeof READY & {
			isChanges: boolean;
		};
		function READY_OF(): typeof shown {
			return shown;
		}
		for ( const [ what, make ] of cases ) {
			const { radio } = builder();
			fetched = [];
			shown = structuredClone( READY ) as typeof shown;
			make();
			reply = () => answer( shown );
			actions.pick( { target: radio } as unknown as Event );
			vi.advanceTimersByTime( WAIT );
			await settle();
			vi.advanceTimersToNextFrame();
			expect( [ what, fetched.length ] ).toEqual( [ what, 1 ] );
		}

		const { sheet } = builder();
		fetched = [];
		reply = () => answer( READY );
		const box = document.createElement( 'input' );
		box.type = 'search';
		box.name = 'cbuild[_q]';
		sheet.append( box );
		actions.narrow( { target: box } as unknown as Event );
		await settle();
		vi.advanceTimersToNextFrame();
		expect( fetched, 'a search asks nothing ahead' ).toHaveLength( 1 );
	} );

	it( 'asks ahead again after a link, Back or a form sent, once a search went before', async () => {
		for ( const move of [ 'follow', 'back', 'submit' ] as const ) {
			const { sheet } = builder();
			fetched = [];
			reply = () => answer( READY );
			const box = document.createElement( 'input' );
			box.type = 'search';
			box.name = 'cbuild[_q]';
			sheet.append( box );
			actions.narrow( { target: box } as unknown as Event );
			await settle();
			vi.advanceTimersToNextFrame();
			expect( fetched ).toHaveLength( 1 );
			if ( 'follow' === move ) {
				const link = document.createElement( 'a' );
				link.href =
					window.location.origin + '/?page_id=25&cbuild%5Bdeck%5D=12';
				document.querySelector( '.cbuild' )!.append( link );
				setElement( link );
				actions.follow(
					new MouseEvent( 'click', { cancelable: true, button: 0 } )
				);
			} else if ( 'back' === move ) {
				actions.back();
			} else {
				actions.submit(
					new SubmitEvent( 'submit', { cancelable: true } )
				);
			}
			await settle();
			vi.advanceTimersToNextFrame();
			expect( [ move, fetched.length ] ).toEqual( [ move, 3 ] );
		}
	} );
} );

// Issue #143: a companion plugin (Mature Content) learns that the builder has drawn a view from one event, in place of a
// MutationObserver on `hidden`, `class` and `data-ready`. Once when the page is hydrated, and once after each answer
// the view shows, on the frame the Interactivity API has drawn it; never for a key or a pick before its answer.
describe( 'the rendered event', () => {
	const KIT = { ...START, kit: 7 };

	function builder(): { root: HTMLElement; bar: HTMLElement } {
		const { form: step } = form();
		const root = wrapped();
		const bar = document.createElement( 'div' );
		bar.className = 'cbuild-bar';
		root.append( bar );
		setElement( root );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		setElement( step );
		return { root, bar };
	}

	function hydrated( bar: HTMLElement ): void {
		setElement( bar );
		(
			defined.definition as { callbacks: { ready: () => void } }
		 ).callbacks.ready();
	}

	function heard(): { events: CustomEvent[]; stop: () => void } {
		const events: CustomEvent[] = [];
		const hear = ( event: Event ) => {
			events.push( event as CustomEvent );
		};
		document.addEventListener( 'cbuild:rendered', hear );
		return {
			events,
			stop: () => document.removeEventListener( 'cbuild:rendered', hear ),
		};
	}

	it( 'is sent once the builder is hydrated, on its element, bubbling, with the kit and the step', () => {
		const { root, bar } = builder();
		state.view = KIT;
		const { events, stop } = heard();
		let marked: boolean | undefined;
		const look = () => {
			marked = root.hasAttribute( 'data-ready' );
		};
		root.addEventListener( 'cbuild:rendered', look );
		hydrated( bar );
		stop();
		root.removeEventListener( 'cbuild:rendered', look );
		expect( marked, 'data-ready is set by the first event' ).toBe( true );
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ]!.target ).toBe( root );
		expect( events[ 0 ]!.bubbles ).toBe( true );
		expect( events[ 0 ]!.detail ).toEqual( { kit: 7, step: 'deck' } );
	} );

	it( 'is sent once after each answer is drawn, in the task that draws it, not at the pick', async () => {
		const { root, bar } = builder();
		state.view = KIT;
		hydrated( bar );
		const { events, stop } = heard();
		const radio =
			root.querySelectorAll< HTMLInputElement >(
				'input[type=radio]'
			)[ 1 ]!;
		reply = () => answer( { ...KIT, status: 'A deck. Total: $70.00.' } );
		actions.pick( { target: radio } as unknown as Event );
		expect( events, 'a pick is not an answer' ).toHaveLength( 0 );
		vi.advanceTimersByTime( WAIT );
		await settle();
		expect( state.status ).toBe( 'A deck. Total: $70.00.' );
		expect( events, 'not before the view is drawn' ).toHaveLength( 0 );
		vi.advanceTimersToNextFrame();
		await settle();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ]!.target ).toBe( root );
		expect( events[ 0 ]!.detail ).toEqual( { kit: 7, step: 'deck' } );

		// A step changed is a view drawn too, once.
		reply = () => answer( { ...NEXT, kit: 7 } );
		actions.back();
		await settle();
		vi.advanceTimersToNextFrame();
		vi.advanceTimersToNextFrame();
		await settle();
		stop();
		expect( events ).toHaveLength( 2 );
		expect( events[ 1 ]!.detail ).toEqual( { kit: 7, step: 'trucks' } );
	} );

	it( 'is sent once for a search, after its answer, not for each key typed before it', async () => {
		const { root, bar } = builder();
		state.view = KIT;
		hydrated( bar );
		const search = document.createElement( 'input' );
		search.type = 'search';
		search.name = 'cbuild[_q]';
		root.querySelector( 'form' )!.append( search );
		const { events, stop } = heard();
		reply = () => answer( { ...KIT, url: window.location.origin + '/?q' } );
		for ( const typed of [ 'Z', 'Ze', 'Zeb' ] ) {
			search.value = typed;
			actions.search( { target: search } as unknown as Event );
			vi.advanceTimersByTime( 50 );
			vi.advanceTimersToNextFrame();
		}
		expect( events, 'no answer yet' ).toHaveLength( 0 );
		vi.advanceTimersByTime( 150 );
		await settle();
		vi.advanceTimersToNextFrame();
		vi.advanceTimersToNextFrame();
		await settle();
		stop();
		expect( fetched ).toHaveLength( 1 );
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ]!.detail ).toEqual( { kit: 7, step: 'deck' } );
	} );

	it( 'names the review as the build link does, and the step the change panel opened over', async () => {
		const { bar } = builder();
		state.view = { ...REVIEW, kit: 7, isReview: true, step: { slot: '' } };
		const { events, stop } = heard();
		hydrated( bar );
		reply = () =>
			answer( {
				...KIT,
				url: window.location.origin + '/?changes',
				isChanges: true,
				status: '1 part no longer fits.',
			} );
		actions.back();
		await settle();
		vi.advanceTimersToNextFrame();
		await settle();
		stop();
		expect( events.map( ( event ) => event.detail ) ).toEqual( [
			{ kit: 7, step: 'review' },
			{ kit: 7, step: 'deck' },
		] );
	} );

	it( 'sends nothing on hydration where no builder is found, and throws nothing', () => {
		document.body.innerHTML = '<div class="cbuild-bar"></div>';
		const { events, stop } = heard();
		expect( () =>
			hydrated( document.querySelector< HTMLElement >( '.cbuild-bar' )! )
		).not.toThrow();
		stop();
		expect( events ).toHaveLength( 0 );
	} );
} );

// Spec: Milestone 11, the motion (D-256, D-257): each moment plays on an element the template drew, once the view that
// brings it is drawn, and a press, a pick or a new answer ends it at once.
describe( 'what the stage plays as a view comes in', () => {
	interface Played {
		element: Element;
		animation: { id: string };
		frames: Keyframe[];
		finished: boolean;
	}

	const CURRENCY = {
		symbol: '$',
		decimals: 2,
		decimal_separator: '.',
		thousand_separator: ',',
		format: '%1$s%2$s',
	};

	let played: Played[];
	let reduce: boolean;

	beforeEach( () => {
		played = [];
		reduce = false;
		vi.stubGlobal( 'matchMedia', ( query: string ) => ( {
			matches: reduce && '(prefers-reduced-motion: reduce)' === query,
		} ) );
		Object.defineProperty( Element.prototype, 'animate', {
			configurable: true,
			value( this: Element, frames: Keyframe[] ) {
				const entry: Played = {
					element: this,
					animation: { id: '' },
					frames,
					finished: false,
				};
				played.push( entry );
				return Object.assign( entry.animation, {
					finished: new Promise( () => undefined ),
					finish: () => {
						entry.finished = true;
					},
				} );
			},
		} );
	} );

	afterEach( () => {
		delete ( Element.prototype as { animate?: unknown } ).animate;
	} );

	/**
	 * What played, each moment's name and the element it played on.
	 */
	function seen(): string[] {
		return played.map(
			( entry ) =>
				`${ entry.animation.id } ${ entry.element.getAttribute(
					'data-name'
				) }`
		);
	}

	/**
	 * A started builder with its step's form, its stage's two sides of the board and two photos, and the bar.
	 */
	function builder(): {
		root: HTMLElement;
		sheet: HTMLFormElement;
		radio: HTMLInputElement;
		items: HTMLElement[];
	} {
		document.body.innerHTML = `
			<div class="cbuild">
				<form class="cbuild-step" method="get" action="/">
					<input type="hidden" name="page_id" value="25">
					<input type="radio" name="cbuild[deck]" value="12">
				</form>
				<figure class="cbuild-picture">
					<div class="cbuild-board" data-name="underside"><template></template><img data-name="underside deck-1"><img data-name="underside trucks-1"></div>
					<div class="cbuild-board cbuild-board--top" data-name="top"><img data-name="top deck-1"><img data-name="top grip-1"></div>
					<ul>
						<li class="cbuild-picture__item" data-name="photo deck">Deck</li>
						<li class="cbuild-picture__item" data-name="photo trucks">Trucks</li>
					</ul>
				</figure>
				<div class="cbuild-bar"><p class="cbuild-bar__estimate" data-name="estimate"></p></div>
			</div>`;
		const root = document.querySelector< HTMLElement >( '.cbuild' )!;
		setElement( root );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		const sheet = root.querySelector( 'form' )!;
		setElement( sheet );
		return {
			root,
			sheet,
			radio: sheet.querySelector( 'input[type=radio]' )!,
			items: [
				...root.querySelectorAll< HTMLElement >(
					'.cbuild-picture__item'
				),
			],
		};
	}

	/**
	 * The deck step's view of a board, its underside's layers and its top's, and the stage's photos by part.
	 *
	 * @param turned Whether the top is shown.
	 * @param board  The underside's layers, by key.
	 * @param top    The top's layers, by key.
	 * @param items  The photos' parts, if the view lists any.
	 */
	function pictured(
		turned: boolean,
		board: string[],
		top: string[],
		items?: string[]
	): Record< string, unknown > {
		const layers = ( keys: string[] ) =>
			keys.map( ( key ) => ( {
				key,
				style: '',
				src: `https://shop.example/${ key }.png`,
			} ) );
		return {
			...START,
			picture: {
				turnable: true,
				turned,
				board: { layers: layers( board ) },
				top: { layers: layers( top ) },
				...( items
					? { items: items.map( ( key ) => ( { key } ) ) }
					: {} ),
			},
		};
	}

	/**
	 * Show the answer to a move that stays on the step, and draw it; the builder says it has drawn it.
	 *
	 * @param view The answer's view.
	 */
	async function shown( view: unknown ): Promise< void > {
		// The last view's event, sent in the task that drew it, is heard before this one is asked.
		await settle();
		let told = 0;
		const hear = () => told++;
		document.addEventListener( 'cbuild:rendered', hear );
		reply = () => answer( view );
		actions.back();
		await settle();
		vi.advanceTimersToNextFrame();
		await settle();
		document.removeEventListener( 'cbuild:rendered', hear );
		expect( told, 'the view is drawn' ).toBe( 1 );
	}

	it( 'plays a part new to the board on the side shown alone, once the view is drawn', async () => {
		builder();
		state.view = pictured( false, [ 'deck-1' ], [ 'deck-1' ] );
		reply = () =>
			answer(
				pictured(
					false,
					[ 'deck-1', 'trucks-1' ],
					[ 'deck-1', 'grip-1' ]
				)
			);
		actions.back();
		await settle();
		expect( seen(), 'not before the frame that draws it' ).toEqual( [] );
		vi.advanceTimersToNextFrame();
		// Each part's moment, then its area outlined on the same side (#138, D-413).
		expect( seen() ).toEqual( [
			'cbuild-trucks underside trucks-1',
			'cbuild-outline underside trucks-1',
		] );

		played = [];
		state.view = pictured( true, [ 'deck-1' ], [ 'deck-1' ] );
		await shown(
			pictured( true, [ 'deck-1', 'trucks-1' ], [ 'deck-1', 'grip-1' ] )
		);
		expect( seen() ).toEqual( [
			'cbuild-grip top grip-1',
			'cbuild-outline top grip-1',
		] );
	} );

	it( 'turns the board to the side a new view shows, and plays nothing new on the side as it turns to it', async () => {
		builder();
		state.view = pictured( false, [ 'deck-1' ], [ 'deck-1' ] );
		await shown( pictured( true, [ 'deck-1' ], [ 'deck-1', 'grip-1' ] ) );
		expect( seen() ).toEqual( [ 'cbuild-turn top' ] );
		expect( played[ 0 ]!.frames ).toEqual( [
			{ transform: 'scaleX(0.1)' },
			{ transform: 'none' },
		] );

		played = [];
		await shown(
			pictured( false, [ 'deck-1', 'trucks-1' ], [ 'deck-1' ] )
		);
		expect( seen() ).toEqual( [ 'cbuild-turn underside' ] );
	} );

	it( 'turns the board at the press of Top or Underside, on the next frame', () => {
		builder();
		state.view = pictured( false, [ 'deck-1' ], [ 'deck-1' ] );
		actions.over();
		expect( seen() ).toEqual( [] );
		vi.advanceTimersToNextFrame();
		expect( seen() ).toEqual( [ 'cbuild-turn top' ] );
		actions.under();
		vi.advanceTimersToNextFrame();
		expect( seen() ).toEqual( [
			'cbuild-turn top',
			'cbuild-turn underside',
		] );
	} );

	it( 'turns nothing, and throws nothing, where the page draws no board', async () => {
		document.body.innerHTML = '<div class="cbuild"></div>';
		setElement( document.querySelector< HTMLElement >( '.cbuild' )! );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		state.view = pictured( false, [ 'deck-1' ], [ 'deck-1' ] );
		actions.over();
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		await shown( pictured( false, [ 'deck-1' ], [ 'deck-1' ] ) );
		expect( played ).toEqual( [] );
	} );

	it( 'draws a view whose picture has a side with no layers, or a step with no picture', async () => {
		builder();
		state.view = { ...START, picture: { turnable: false, turned: false } };
		await shown( {
			...START,
			picture: { turnable: false, turned: false },
		} );
		await shown( {
			...START,
			picture: { turnable: true, turned: true },
		} );
		expect( seen(), 'the top shown has nothing drawn on it' ).toEqual( [
			'cbuild-turn top',
		] );
		await shown( START );
	} );

	it( 'closes the photos over a part taken off the stage, and moves none for a view that takes none off', async () => {
		const { items } = builder();
		const at = (
			item: HTMLElement,
			rects: Array< { left: number; top: number } >
		): void => {
			let asked = 0;
			item.getBoundingClientRect = () =>
				rects[ Math.min( asked++, rects.length - 1 ) ] as DOMRect;
		};
		const moving = (): void => {
			at( items[ 0 ]!, [
				{ left: 0, top: 0 },
				{ left: 0, top: 0 },
			] );
			at( items[ 1 ]!, [
				{ left: 0, top: 120 },
				{ left: 0, top: 80 },
			] );
		};
		moving();
		state.view = pictured(
			false,
			[ 'deck-1' ],
			[ 'deck-1' ],
			[ 'deck', 'wheels', 'trucks' ]
		);
		await shown(
			pictured( false, [ 'deck-1' ], [ 'deck-1' ], [ 'deck', 'trucks' ] )
		);
		expect( seen() ).toEqual( [ 'cbuild-removed photo trucks' ] );
		expect( played[ 0 ]!.frames ).toEqual( [
			{ transform: 'translate(0px, 40px)' },
			{ transform: 'none' },
		] );

		for ( const before of [ [ 'deck', 'trucks' ], undefined ] ) {
			moving();
			played = [];
			state.view = pictured( false, [ 'deck-1' ], [ 'deck-1' ], before );
			await shown(
				pictured(
					false,
					[ 'deck-1' ],
					[ 'deck-1' ],
					[ 'deck', 'trucks' ]
				)
			);
			expect( [ before, seen() ] ).toEqual( [ before, [] ] );
		}
	} );

	it( 'counts the total from the old amount to the new as the view comes in, and ends on the server’s string', async () => {
		builder();
		const amount = (): string =>
			( state.view.bar as { amount: string } ).amount;
		state.view = {
			...START,
			money: { amount: 9000, currency: CURRENCY },
			bar: { amount: '$90.00', next: { ready: true } },
		};
		reply = () =>
			answer( {
				...START,
				money: { amount: 9700, currency: CURRENCY },
				bar: { amount: '$97.00', next: { ready: true } },
			} );
		actions.back();
		await settle();
		expect( amount(), 'from the old amount' ).toBe( '$90.00' );
		vi.advanceTimersByTime( 150 );
		expect( amount() ).toMatch( /^\$9\d\.\d\d$/ );
		expect( [ '$90.00', '$97.00' ] ).not.toContain( amount() );
		vi.advanceTimersByTime( 300 );
		expect( amount() ).toBe( '$97.00' );

		// From a view with no total, the server's string at once.
		state.view = START;
		await shown( {
			...START,
			money: { amount: 9700, currency: CURRENCY },
			bar: { amount: '$97.00', next: { ready: true } },
		} );
		expect( amount() ).toBe( '$97.00' );
	} );

	it( 'fades the estimate in when it changes, and leaves it when it does not, where moments only fade, or with none drawn', async () => {
		const { root } = builder();
		const estimated = ( estimate: string ) => ( {
			...START,
			bar: { estimate, next: { ready: true } },
		} );
		state.view = estimated( 'Ships in 2 days' );
		await shown( estimated( 'Ships in 3 days' ) );
		expect( seen() ).toEqual( [ 'cbuild-total estimate' ] );
		expect( played[ 0 ]!.frames ).toEqual( [
			{ opacity: 0 },
			{ opacity: 1 },
		] );

		played = [];
		await shown( estimated( 'Ships in 3 days' ) );
		expect( seen(), 'the same estimate' ).toEqual( [] );

		reduce = true;
		await shown( estimated( 'Ships in 4 days' ) );
		expect( seen(), 'reduced motion' ).toEqual( [] );

		reduce = false;
		root.querySelector( '.cbuild-bar__estimate' )!.remove();
		await shown( estimated( 'Ships in 5 days' ) );
		expect( seen(), 'no estimate drawn' ).toEqual( [] );
	} );

	it( 'ends a moment still playing when the shopper presses, picks, or an answer comes', async () => {
		const { radio } = builder();
		const playing = async (): Promise< Played > => {
			played = [];
			state.view = {
				...START,
				bar: { estimate: 'Ships in 2 days', next: { ready: true } },
			};
			await shown( {
				...START,
				bar: { estimate: 'Ships in 3 days', next: { ready: true } },
			} );
			expect( played ).toHaveLength( 1 );
			expect( played[ 0 ]!.finished ).toBe( false );
			return played[ 0 ]!;
		};

		let moment = await playing();
		document.dispatchEvent(
			new MouseEvent( 'pointerdown', { button: 0 } )
		);
		expect( moment.finished, 'a press' ).toBe( true );

		moment = await playing();
		reply = () => new Promise< Response >( () => undefined );
		actions.pick( { target: radio } as unknown as Event );
		expect( moment.finished, 'a pick' ).toBe( true );

		reset();
		builder();
		moment = await playing();
		let answered: ( response: Response ) => void = () => undefined;
		reply = () =>
			new Promise< Response >( ( done ) => ( answered = done ) );
		actions.back();
		await settle();
		expect( moment.finished, 'not while the answer is awaited' ).toBe(
			false
		);
		answered( answer( START ) );
		await settle();
		expect( moment.finished, 'an answer' ).toBe( true );
	} );

	it( 'sends the board shown toward the cart at an add, its top where the top is shown', async () => {
		for ( const turned of [ false, true ] ) {
			const { sheet } = builder();
			sheet.method = 'post';
			sheet.insertAdjacentHTML(
				'beforeend',
				'<input type="hidden" name="add-to-cart" value="7"><input type="hidden" name="cbuild[picks][deck]" value="101">'
			);
			vi.stubGlobal( 'fetch', ( input: string ) =>
				Promise.resolve(
					input === CART
						? new Response( '{}', { headers: { Nonce: 'n' } } )
						: new Response( '{}', { status: 201 } )
				)
			);
			played = [];
			state.view = {
				...REVIEW,
				picture: (
					pictured( turned, [ 'deck-1' ], [ 'deck-1' ] ) as {
						picture: unknown;
					}
				 ).picture,
			};
			await actions.add(
				new SubmitEvent( 'submit', { cancelable: true } )
			);
			expect( seen() ).toEqual( [
				`cbuild-cart ${ turned ? 'top' : 'underside' }`,
			] );
		}
	} );
} );

// The auditor's M14 review, F3: Show builds' press was held until an answer came, so a pick that overtook it, or a
// limit that answered it, left it set. A pick's own answer replaces the entry and so never read it, but the next answer
// that moves did: a pick that opens the change panel lost its count, and Back went to the guide's builds. The press is
// the move's own, and only the move's answer acts on it.
describe( 'Show builds overtaken', () => {
	const PANEL = {
		...START,
		url: window.location.origin + '/?changes',
		isChanges: true,
		status: '1 part no longer fits.',
	};
	const BACK = {
		...START,
		url: window.location.origin + '/?back',
		step: { slot: 'wheels' },
		guide: { shown: true, asked: true },
	};

	/**
	 * The guide's questions and the step's cards in a builder, its init run on it.
	 */
	function guided(): {
		guide: HTMLFormElement;
		show: HTMLButtonElement;
		radio: HTMLInputElement;
	} {
		document.body.innerHTML = `
			<div class="cbuild">
				<h2 class="cbuild__heading" tabindex="-1">Step</h2>
				<h3 class="cbuild-changes__heading" tabindex="-1">Changes</h3>
				<h3 class="cbuild-guide__heading" tabindex="-1">Builds</h3>
				<form class="cbuild-guide__form" method="get" action="/">
					<input type="hidden" name="cbuild[guide][budget]" value="150">
					<button type="submit" name="cbuild[step]" value="deck">Show builds</button>
				</form>
				<form method="get" action="/">
					<input type="radio" name="cbuild[deck]" value="11">
				</form>
			</div>`;
		setElement( document.querySelector< HTMLElement >( '.cbuild' ) );
		(
			defined.definition as { callbacks: { init: () => void } }
		 ).callbacks.init();
		return {
			guide: document.querySelector( '.cbuild-guide__form' )!,
			show: document.querySelector( '.cbuild-guide__form button' )!,
			radio: document.querySelector( 'input[type=radio]' )!,
		};
	}

	/**
	 * Press Show builds.
	 *
	 * @param guide The guide's form.
	 * @param show  Its button.
	 */
	function press( guide: HTMLFormElement, show: HTMLButtonElement ): void {
		setElement( guide );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: show, cancelable: true } )
		);
	}

	/**
	 * Pick the card, its answer the change panel, and wait for it and the frame after.
	 *
	 * @param radio The card's radio.
	 */
	async function pick( radio: HTMLInputElement ): Promise< void > {
		reply = () => answer( PANEL );
		radio.checked = true;
		actions.pick( { target: radio } as unknown as Event );
		vi.advanceTimersByTime( WAIT );
		await settle();
		vi.advanceTimersToNextFrame();
	}

	it( 'takes Show builds’ own answer to the builds, and Skip’s to the step with the questions shut', async () => {
		const { guide, show } = guided();
		document
			.querySelector( '.cbuild' )!
			.insertAdjacentHTML(
				'beforeend',
				'<details class="cbuild-guide__ask" open><summary>Ask</summary></details><a class="cbuild-guide__skip" href="/?cbuild%5Bstep%5D=deck">Skip</a>'
			);
		reply = () =>
			answer( {
				...PANEL,
				isChanges: false,
				guide: { shown: true, asked: true },
			} );
		press( guide, show );
		await settle();
		vi.advanceTimersToNextFrame();
		expect( state.status ).toBe( '' );
		expect( guide.ownerDocument.activeElement ).toBe(
			document.querySelector( '.cbuild-guide__heading' )
		);
		reply = () =>
			answer( { ...BACK, guide: { shown: true, asked: true } } );
		setElement( document.querySelector( '.cbuild-guide__skip' ) );
		actions.follow(
			new MouseEvent( 'click', { cancelable: true, button: 0 } )
		);
		await settle();
		vi.advanceTimersToNextFrame();
		expect(
			document
				.querySelector( '.cbuild-guide__ask' )!
				.hasAttribute( 'open' )
		).toBe( false );
		expect( guide.ownerDocument.activeElement ).toBe(
			document.querySelector( '.cbuild__heading' )
		);
	} );

	it( 'says the count of a pick that overtakes the press and opens the change panel', async () => {
		const { guide, show, radio } = guided();
		reply = () => new Promise< Response >( () => undefined );
		press( guide, show );
		await pick( radio );
		expect( state.status ).toBe( PANEL.status );
		expect( radio.ownerDocument.activeElement ).toBe(
			document.querySelector( '.cbuild-changes__heading' )
		);
	} );

	it( 'says the count of the pick after a press a limit answered', async () => {
		const { guide, show, radio } = guided();
		reply = () =>
			new Response( JSON.stringify( { message: 'Wait a moment.' } ), {
				status: 429,
				headers: { 'Content-Type': 'application/json' },
			} );
		press( guide, show );
		await settle();
		expect( state.limited ).toBe( 'Wait a moment.' );
		await pick( radio );
		expect( state.status ).toBe( PANEL.status );
	} );

	it( 'takes Back after a press a limit answered to the step’s heading, not the builds', async () => {
		const { guide, show } = guided();
		reply = () =>
			new Response( JSON.stringify( { message: 'Wait a moment.' } ), {
				status: 429,
				headers: { 'Content-Type': 'application/json' },
			} );
		press( guide, show );
		await settle();
		reply = () => answer( BACK );
		actions.back();
		await settle();
		vi.advanceTimersToNextFrame();
		expect( guide.ownerDocument.activeElement ).toBe(
			document.querySelector( '.cbuild__heading' )
		);
	} );
} );
