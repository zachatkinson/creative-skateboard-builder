/**
 * #80: a mouse press held while the builder's regions hydrate. Hydration takes the pressed control out of the page and
 * puts it back, and the browser drops the click; the store gives the control the click it lost, once.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import '../../assets/src/builder/view';

/**
 * The element the pointer comes up over, as `document.elementFromPoint()` would find it.
 */
let over: Element | null = null;

/**
 * A press of the mouse on an element, its button coming up over `over`; the browser's click only where `clicked`.
 *
 * @param element         The element pressed.
 * @param options         How it is pressed.
 * @param options.type    The pointer's type.
 * @param options.button  The mouse button.
 * @param options.clicked Whether the browser's click reaches the element.
 * @param options.between What happens while the button is down.
 */
function press(
	element: Element,
	{
		type = 'mouse',
		button = 0,
		clicked = false,
		between = () => {},
	}: {
		type?: string;
		button?: number;
		clicked?: boolean;
		between?: () => void;
	} = {}
): void {
	element.dispatchEvent(
		new PointerEvent( 'pointerdown', {
			bubbles: true,
			pointerType: type,
			button,
		} )
	);
	between();
	element.dispatchEvent(
		new PointerEvent( 'pointerup', { bubbles: true, pointerType: type } )
	);
	if ( clicked ) {
		element.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
	}
	vi.runAllTimers();
}

describe( 'a press held while the builder hydrates', () => {
	let builder: HTMLElement;
	let button: HTMLButtonElement;
	let clicks: number;

	beforeEach( () => {
		vi.useFakeTimers();
		document.body.innerHTML =
			'<div class="cbuild"><div class="cbuild-width"><button type="button"><span>8.25″</span></button></div>' +
			'<input type="radio" id="deck" name="deck" value="1"><label for="deck">Hard Hat</label></div>' +
			'<button type="button" class="outside">Elsewhere</button>';
		builder = document.querySelector< HTMLElement >( '.cbuild' )!;
		button = builder.querySelector( 'button' )!;
		clicks = 0;
		button.addEventListener( 'click', () => clicks++ );
		over = button.querySelector( 'span' );
		document.elementFromPoint = () => over;
	} );

	afterEach( () => {
		vi.useRealTimers();
		document.body.innerHTML = '';
	} );

	it( 'clicks a control the browser dropped the click of, once the press comes up over it', () => {
		const parent = button.parentElement!;
		press( button.querySelector( 'span' )!, {
			// Hydration takes it out of the page and puts it back.
			between: () => {
				button.remove();
				parent.append( button );
			},
		} );
		expect( clicks ).toBe( 1 );
	} );

	it( 'leaves a press whose click arrived alone', () => {
		press( button, { clicked: true } );
		expect( clicks ).toBe( 1 );
	} );

	it( 'clicks nothing when the press comes up over something else', () => {
		over = builder.querySelector( 'label' );
		press( button );
		expect( clicks ).toBe( 0 );
		over = null;
		press( button );
		expect( clicks ).toBe( 0 );
	} );

	it( 'clicks nothing for a finger, a pen or another mouse button', () => {
		press( button, { type: 'touch' } );
		press( button, { type: 'pen' } );
		press( button, { button: 2 } );
		expect( clicks ).toBe( 0 );
	} );

	it( 'clicks nothing outside the builder, nor a control gone from the page', () => {
		const outside = document.querySelector< HTMLElement >( '.outside' )!;
		let elsewhere = 0;
		outside.addEventListener( 'click', () => elsewhere++ );
		over = outside;
		press( outside );
		expect( elsewhere ).toBe( 0 );
		over = button;
		press( button, { between: () => button.remove() } );
		expect( clicks ).toBe( 0 );
	} );

	it( "clicks a card's radio, which the press lands on beside its label", () => {
		const radio = builder.querySelector< HTMLInputElement >( 'input' )!;
		over = radio;
		press( radio );
		expect( radio.checked ).toBe( true );
	} );

	it( 'sees the press before a control that keeps its events to itself', () => {
		const parent = button.parentElement!;
		for ( const type of [ 'pointerdown', 'pointerup', 'click' ] ) {
			button.addEventListener( type, ( event ) =>
				event.stopPropagation()
			);
		}
		press( button, {
			between: () => {
				button.remove();
				parent.append( button );
			},
		} );
		expect( clicks ).toBe( 1 );
		press( button, { clicked: true } );
		expect( clicks ).toBe( 2 );
	} );

	it( 'forgets a press once it has come up', () => {
		press( button, { clicked: true } );
		// A click with no press before it, as a keyboard's.
		button.dispatchEvent(
			new PointerEvent( 'pointerup', {
				bubbles: true,
				pointerType: 'mouse',
			} )
		);
		vi.runAllTimers();
		expect( clicks ).toBe( 1 );
	} );
} );
