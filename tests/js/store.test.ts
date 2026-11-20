/**
 * The builder's store (D-148): private and locked; a step's form, a pick, a link and Back each ask the state route
 * and show its view, and each does what it does without JavaScript when the route fails.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setConfig, setElement, stores } from './support/interactivity';
import { NAME, SETTLE, browser, reset } from '../../assets/src/builder/view';
import { SLOW, WAIT } from '../../assets/src/builder/navigate';

interface Actions {
	submit: ( event: SubmitEvent ) => void;
	add: ( event: SubmitEvent ) => Promise< void >;
	copy: () => Promise< void >;
	turn: () => void;
	reveal: ( event: FocusEvent ) => void;
	pick: ( event: Event ) => void;
	search: ( event: Event ) => void;
	follow: ( event: MouseEvent ) => void;
	back: () => void;
	filters: () => void;
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
		] );
		await settle();
		expect( replaced ).toHaveBeenCalledWith(
			null,
			'',
			window.location.origin + '/?next'
		);
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
		setElement( document.body );
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

	it( 'says the build and its total after a pick, and leaves focus and the title alone', async () => {
		const { radio } = form();
		document.body.insertAdjacentHTML(
			'afterbegin',
			'<h2 class="cbuild__heading" tabindex="-1">Step</h2>'
		);
		setElement( document.body );
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
					more: { added: '2 more parts shown, 4 of 4.' },
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
		expect( state.status ).toBe( '2 more parts shown, 4 of 4.' );
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
		const carts = ( event: Event ) =>
			told.push( 'carts:' + String( event.bubbles ) );
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
		expect( told ).toEqual( [ 'carts:true', 'builder:cbuild' ] );
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

	it( 'shows the next view at once under reduced motion', async () => {
		const transitions = motion( true, true );
		await step();
		expect( transitions ).toHaveLength( 0 );
		expect( state.view ).toEqual( NEXT );
	} );

	it( 'shows the next view at once where the browser has no view transitions', async () => {
		motion( false, false );
		await step();
		expect( state.view ).toEqual( NEXT );
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

	it( 'moves nothing when a new view shows the side already shown, so a board shown again later is not turned', async () => {
		state.view = view( 'deck', { turnable: true, turned: false } );
		actions.turn();
		expect( state.turning ).toBe( true );
		await go( view( 'trucks', { turnable: true, turned: false } ) );
		expect( shown().turned ).toBe( true );
		expect( state.turning, 'the turn is over' ).toBe( false );
	} );
} );
