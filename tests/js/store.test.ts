/**
 * The builder's store (D-148): private and locked; a step's form, a pick, a link and Back each ask the state route
 * and show its view, and each does what it does without JavaScript when the route fails.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setConfig, setElement, stores } from './support/interactivity';
import { NAME, browser, reset } from '../../assets/src/builder/view';
import { WAIT } from '../../assets/src/builder/navigate';

interface Actions {
	submit: ( event: SubmitEvent ) => void;
	pick: ( event: Event ) => void;
	follow: ( event: MouseEvent ) => void;
	back: () => void;
}

interface Defined {
	state: { view: Record< string, unknown >; pending: boolean };
	actions: Actions;
}

const defined = stores.get( NAME )!;
const { state, actions } = defined.definition as Defined;
const ENDPOINT = window.location.origin + '/wp-json/cbuild/v1/kits/7/state';
const START = {
	url: window.location.origin + '/?page_id=25',
	step: { slot: 'deck' },
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
	setConfig( NAME, { endpoint: ENDPOINT, page: 25, locale: 'fr_CA' } );
	state.view = START;
	state.pending = false;
	fetched = [];
	reply = () =>
		answer( {
			url: window.location.origin + '/?next',
			step: { slot: 'trucks' },
		} );
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
		expect( state.view ).toEqual( {
			url: window.location.origin + '/?next',
			step: { slot: 'trucks' },
		} );
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
		expect( state.pending ).toBe( true );
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
		expect( state.view ).toEqual( {
			url: window.location.origin + '/?next',
			step: { slot: 'trucks' },
		} );
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

	it( 'reads a config with nothing in it as no page and no language', () => {
		setConfig( NAME, { endpoint: ENDPOINT } );
		actions.back();
		expect( fetched[ 0 ]!.searchParams.get( 'page' ) ).toBe( '0' );
		expect( fetched[ 0 ]!.searchParams.get( 'locale' ) ).toBe( '' );
	} );
} );
