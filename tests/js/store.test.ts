/**
 * The builder's store (D-148): private and locked; a step's form, a pick, a link and Back each ask the state route
 * and show its view, and each does what it does without JavaScript when the route fails.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setConfig, setElement, stores } from './support/interactivity';
import { NAME, browser, reset } from '../../assets/src/builder/view';
import { SLOW, WAIT } from '../../assets/src/builder/navigate';

interface Actions {
	submit: ( event: SubmitEvent ) => void;
	add: ( event: SubmitEvent ) => Promise< void >;
	copy: () => Promise< void >;
	reveal: ( event: FocusEvent ) => void;
	pick: ( event: Event ) => void;
	follow: ( event: MouseEvent ) => void;
	back: () => void;
}

interface Defined {
	state: {
		view: Record< string, unknown >;
		pending: boolean;
		status: string;
		added: boolean;
		canCopy: boolean;
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
		expect( sent ).toHaveBeenCalledWith( step );
		expect( [ ...new FormData( step ) ] ).toContainEqual( [
			'cbuild[step]',
			'trucks',
		] );
		expect(
			step.querySelector< HTMLInputElement >(
				'input[name="cbuild[step]"]'
			)?.type
		).toBe( 'hidden' );
		expect( state.view ).toBe( START );
	} );

	it( 'adds no field for a button with no name when it sends the form', async () => {
		const { form: step, next } = form();
		next.removeAttribute( 'name' );
		setElement( step );
		reply = () => answer( null, 500 );
		const sent = vi
			.spyOn( browser, 'submit' )
			.mockImplementation( () => undefined );
		actions.submit(
			new SubmitEvent( 'submit', { submitter: next, cancelable: true } )
		);
		await settle();
		expect( sent ).toHaveBeenCalledOnce();
		expect( step.querySelectorAll( 'input[type=hidden]' ) ).toHaveLength(
			2
		);
	} );

	it( 'sends a form through its own submit, which runs no handler', () => {
		const { form: step } = form();
		const submit = vi
			.spyOn( HTMLFormElement.prototype, 'submit' )
			.mockImplementation( () => undefined );
		browser.submit( step );
		expect( submit ).toHaveBeenCalledOnce();
		expect( submit.mock.contexts[ 0 ] ).toBe( step );
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

	it( 'puts focus on the first new card after Show more', async () => {
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
				step: { slot: 'deck', options: [ {}, {}, {}, {} ] },
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
		expect(
			state.status,
			'the card is read; the total has not changed'
		).toBe( '' );
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
		expect( submitted ).toHaveBeenCalledWith( sheet );
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
