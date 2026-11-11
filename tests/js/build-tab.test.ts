import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	edges,
	field,
	find,
	move,
	open,
	rows,
	start,
} from '../../assets/src/admin/build-tab';
import type { Query } from '../../assets/src/admin/build-tab';

/**
 * The Build tab's script (spec: Admin, The kit's Build tab): a slot's row moves by its buttons as well as by
 * dragging (WCAG 2.5.7), and a notice's link opens the tab and moves to its field. The browser tests run it on the
 * real screen (tests/e2e/build-tab.spec.ts).
 */

// A slot's row as the tab prints it.
function row( at: number, label: string ) {
	return `<fieldset id="cbuild-kit-slots-${ at }" class="cbuild-kit-row">
		<legend>Slot: ${ label }</legend>
		<p class="cbuild-kit-moves">
			<span class="cbuild-kit-handle"></span>
			<button type="button" data-cbuild-move="up">Move up</button>
			<button type="button" data-cbuild-move="down">Move down</button>
		</p>
		<details class="cbuild-kit-fold"><summary>Settings</summary>
			<input type="hidden" name="cbuild_kit[slots][${ at }][key]" value="k${ at }">
			<input id="cbuild-kit-slots-${ at }-label" name="cbuild_kit[slots][${ at }][label]" value="${ label }">
			<input id="cbuild-kit-slots-${ at }-units" name="cbuild_kit[slots][${ at }][units]" value="1">
			<select id="cbuild-kit-slots-${ at }-sources-brands" multiple></select>
			<details class="inner"><summary>Attribute values</summary><select id="cbuild-kit-slots-${ at }-sources-attributes-pa_width"></select></details>
		</details>
	</fieldset>`;
}

function screen( labels = [ 'Deck', 'Trucks', 'Wheels' ] ) {
	document.body.innerHTML = `
		<ul class="wc-tabs"><li><a href="#general">General</a></li><li><a href="#cbuild_build">Build</a></li></ul>
		<form><div id="cbuild_build">
			<div id="cbuild-kit-slots">
				<h3>Slots</h3>
				<div class="cbuild-kit-rows" data-cbuild-moved="%1$s is now slot %2$d of %3$d.">
					${ labels.map( ( label, at ) => row( at, label ) ).join( '' ) }
				</div>
			</div>
			<div id="cbuild-kit-presets"><div><fieldset id="cbuild-kit-presets-0"><input id="cbuild-kit-presets-0-name"></fieldset></div></div>
			<div id="cbuild-kit-empty"><p>Nothing to hold focus.</p></div>
		</div></form>`;
	const list = document.querySelector( '.cbuild-kit-rows' ) as HTMLElement;
	return { list };
}

// Where focus is, asked of the page the fixture drew.
function focus(): Element | null {
	return document.body.ownerDocument.activeElement;
}

function order() {
	return Array.from(
		document.querySelectorAll< HTMLInputElement >( 'input[id$="-label"]' )
	).map( ( input ) => input.value );
}

function press( at: number, direction: 'up' | 'down' ) {
	const pressed = document.querySelector< HTMLButtonElement >(
		`#cbuild-kit-slots-${ at } button[data-cbuild-move="${ direction }"]`
	) as HTMLButtonElement;
	pressed.focus();
	return pressed;
}

function disabled() {
	return Array.from(
		document.querySelectorAll< HTMLButtonElement >(
			'button[data-cbuild-move]'
		)
	)
		.filter( ( button ) => button.disabled )
		.map(
			( button ) =>
				`${ button.closest( 'fieldset' )?.id }:${
					button.dataset.cbuildMove
				}`
		);
}

beforeEach( () => {
	document.body.innerHTML = '';
	Element.prototype.scrollIntoView = vi.fn();
} );

describe( 'field', () => {
	it( 'reads an ID of the tab from the address', () => {
		expect( field( '#cbuild-kit-slots-2-units' ) ).toBe(
			'cbuild-kit-slots-2-units'
		);
		expect( field( '#cbuild-kit-rules-r1-axle_fit' ) ).toBe(
			'cbuild-kit-rules-r1-axle_fit'
		);
		expect( field( '#cbuild-kit-presets' ) ).toBe( 'cbuild-kit-presets' );
	} );

	it( 'reads nothing from any other address', () => {
		for ( const hash of [
			'',
			'#',
			'#cbuild-kit',
			'#cbuild-kit-',
			'#cbuild-spec-deck-width',
			'#cbuild-kit-slots-2-units"]',
			'#xcbuild-kit-slots',
			'#cbuild-kit-Slots',
		] ) {
			expect( field( hash ) ).toBeNull();
		}
	} );
} );

describe( 'field, of an address with more before it', () => {
	it( 'reads nothing: the ID is the whole of the address', () => {
		expect( field( '#general#cbuild-kit-slots-1-units' ) ).toBeNull();
	} );
} );

describe( 'find', () => {
	it( "stops at the tab's own prefix, whatever else on the page is named like it", () => {
		screen();
		document.body.insertAdjacentHTML(
			'beforeend',
			'<div id="cbuild-kit"></div><div id="cbuild"></div><div id="cbuil"></div>'
		);
		expect( find( document, 'cbuild-kit-nowhere-1' ) ).toBeNull();
		expect( find( document, 'cbuild-kit' ) ).toBeNull();
	} );

	it( 'finds a field by its ID', () => {
		screen();
		expect( find( document, 'cbuild-kit-slots-1-units' )?.id ).toBe(
			'cbuild-kit-slots-1-units'
		);
	} );

	it( 'leads an ID inside a field to the field, and a field that is not there to its row or its section', () => {
		screen();
		expect(
			find( document, 'cbuild-kit-slots-1-sources-brands-0' )?.id
		).toBe( 'cbuild-kit-slots-1-sources-brands' );
		expect( find( document, 'cbuild-kit-slots-1-key' )?.id ).toBe(
			'cbuild-kit-slots-1'
		);
		expect( find( document, 'cbuild-kit-slots-9-units' )?.id ).toBe(
			'cbuild-kit-slots'
		);
	} );

	it( "finds nothing for an ID that is not the tab's, or for none", () => {
		screen();
		expect( find( document, 'cbuild-kit-nowhere-1' ) ).toBeNull();
		expect( find( document, 'cbuild-kit' ) ).toBeNull();
		expect( find( document, '' ) ).toBeNull();
	} );
} );

describe( 'open', () => {
	it( 'opens the Build tab and moves to the field', () => {
		screen();
		const tab = vi.fn();
		document
			.querySelector( 'a[href="#cbuild_build"]' )
			?.addEventListener( 'click', tab );
		const other = vi.fn();
		document
			.querySelector( 'a[href="#general"]' )
			?.addEventListener( 'click', other );

		open( document, '#cbuild-kit-slots-1-units' );

		expect( tab ).toHaveBeenCalledTimes( 1 );
		expect( other ).not.toHaveBeenCalled();
		const target = document.getElementById( 'cbuild-kit-slots-1-units' );
		expect( focus() ).toBe( target );
		expect( target?.scrollIntoView ).toHaveBeenCalledWith( {
			block: 'center',
		} );
	} );

	it( 'opens the group a field is folded into', () => {
		screen();
		const outer = document.querySelector(
			'#cbuild-kit-slots-2 details.cbuild-kit-fold'
		) as HTMLDetailsElement;
		const group = document.querySelector(
			'#cbuild-kit-slots-2 details.inner'
		) as HTMLDetailsElement;
		expect( outer.open ).toBe( false );
		expect( group.open ).toBe( false );
		open( document, '#cbuild-kit-slots-2-sources-attributes-pa_width-0' );
		expect( group.open ).toBe( true );
		expect( outer.open ).toBe( true );
		expect( focus()?.id ).toBe(
			'cbuild-kit-slots-2-sources-attributes-pa_width'
		);
		// A field in the outer group alone leaves another row's groups shut.
		open( document, '#cbuild-kit-slots-1-units' );
		expect(
			document.querySelectorAll( '#cbuild-kit-slots-1 details[open]' )
				.length
		).toBe( 1 );
	} );

	it( 'moves to the first control of a row or a section, which take no focus themselves', () => {
		screen();
		open( document, '#cbuild-kit-presets' );
		expect( focus()?.id ).toBe( 'cbuild-kit-presets-0-name' );
		// A row's first control that can take focus: Move up is switched off on the first row once the rows are made to move.
		rows( document );
		open( document, '#cbuild-kit-slots-0-key' );
		expect( ( focus() as HTMLElement ).dataset.cbuildMove ).toBe( 'down' );
	} );

	it( 'does nothing for an address that names nothing of the tab, and survives a section with no control', () => {
		screen();
		const tab = vi.fn();
		document
			.querySelector( 'a[href="#cbuild_build"]' )
			?.addEventListener( 'click', tab );
		open( document, '#cbuild_part_specs' );
		open( document, '#cbuild-kit-nowhere' );
		expect( tab ).not.toHaveBeenCalled();
		open( document, '#cbuild-kit-empty' );
		expect( tab ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'moves focus without a scroll of its own, and brings the field back a frame later if the page moved', () => {
		screen();
		const target = document.getElementById(
			'cbuild-kit-slots-1-units'
		) as HTMLElement;
		const focused = vi.spyOn( target, 'focus' );
		target.getBoundingClientRect = () =>
			( { top: -300, bottom: -270 } ) as DOMRect;
		const frames: Array< () => void > = [];
		const view = document.defaultView as Window;
		const real = view.requestAnimationFrame;
		view.requestAnimationFrame = ( run: FrameRequestCallback ) =>
			frames.push( () => run( 0 ) );

		open( document, '#cbuild-kit-slots-1-units' );
		view.requestAnimationFrame = real;

		expect( focused ).toHaveBeenCalledWith( { preventScroll: true } );
		expect( target.scrollIntoView ).toHaveBeenCalledTimes( 1 );
		expect( frames ).toHaveLength( 1 );
		frames[ 0 ]?.();
		expect( target.scrollIntoView ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'works on a screen with no tabs', () => {
		screen();
		document.querySelector( '.wc-tabs' )?.remove();
		open( document, '#cbuild-kit-slots-0-label' );
		expect( focus()?.id ).toBe( 'cbuild-kit-slots-0-label' );
	} );
} );

describe( 'edges', () => {
	it( 'switches off Move up on the first row and Move down on the last', () => {
		const { list } = screen();
		edges( list );
		expect( disabled() ).toEqual( [
			'cbuild-kit-slots-0:up',
			'cbuild-kit-slots-2:down',
		] );
	} );

	it( 'switches both off on a row that is alone, and survives a row with no buttons', () => {
		const { list } = screen( [ 'Deck' ] );
		edges( list );
		expect( disabled() ).toEqual( [
			'cbuild-kit-slots-0:up',
			'cbuild-kit-slots-0:down',
		] );
		document.querySelector( '.cbuild-kit-moves' )?.remove();
		expect( () => edges( list ) ).not.toThrow();
	} );
} );

describe( 'move', () => {
	it( 'moves a row up one place, keeps focus on the button and says where the row is', () => {
		const { list } = screen();
		edges( list );
		const speak = vi.fn();
		const pressed = press( 2, 'up' );

		move( pressed, speak );

		expect( order() ).toEqual( [ 'Deck', 'Wheels', 'Trucks' ] );
		expect( focus() ).toBe( pressed );
		expect( speak ).toHaveBeenCalledWith( 'Wheels is now slot 2 of 3.' );
		expect( disabled() ).toEqual( [
			'cbuild-kit-slots-0:up',
			'cbuild-kit-slots-1:down',
		] );
	} );

	it( 'moves a row down one place', () => {
		const { list } = screen();
		edges( list );
		const speak = vi.fn();
		move( press( 0, 'down' ), speak );
		expect( order() ).toEqual( [ 'Trucks', 'Deck', 'Wheels' ] );
		expect( speak ).toHaveBeenCalledWith( 'Deck is now slot 2 of 3.' );
	} );

	it( "gives focus to the row's other button when the one pressed has nowhere left to go", () => {
		const { list } = screen();
		edges( list );
		const pressed = press( 1, 'up' );
		move( pressed );
		expect( order() ).toEqual( [ 'Trucks', 'Deck', 'Wheels' ] );
		expect( pressed.disabled ).toBe( true );
		expect( ( focus() as HTMLElement ).dataset.cbuildMove ).toBe( 'down' );
		expect( focus()?.closest( 'fieldset' )?.id ).toBe(
			'cbuild-kit-slots-1'
		);

		const down = press( 1, 'down' );
		move( down );
		move( down );
		expect( order() ).toEqual( [ 'Deck', 'Wheels', 'Trucks' ] );
		expect( ( focus() as HTMLElement ).dataset.cbuildMove ).toBe( 'up' );
	} );

	it( 'leaves a row at the end where it is', () => {
		screen();
		const speak = vi.fn();
		move( press( 0, 'up' ), speak );
		move( press( 2, 'down' ), speak );
		expect( order() ).toEqual( [ 'Deck', 'Trucks', 'Wheels' ] );
		expect( speak ).not.toHaveBeenCalled();
	} );

	it( 'counts the rows alone, whatever else the list holds', () => {
		const { list } = screen();
		list.insertAdjacentHTML(
			'afterbegin',
			'<p>Drag a slot to move it.</p>'
		);
		list.insertAdjacentHTML( 'beforeend', '<p>Add a slot below.</p>' );
		edges( list );
		expect( disabled() ).toEqual( [
			'cbuild-kit-slots-0:up',
			'cbuild-kit-slots-2:down',
		] );
		const speak = vi.fn();
		move( press( 2, 'up' ), speak );
		expect( speak ).toHaveBeenCalledWith( 'Wheels is now slot 2 of 3.' );
	} );

	it( 'says a name without the space around it, and survives a row whose other button is gone', () => {
		screen();
		const speak = vi.fn();
		const legend = document.querySelector(
			'#cbuild-kit-slots-1 legend'
		) as HTMLElement;
		legend.textContent = '\n\t\tSlot: Trucks\n\t';
		(
			document.getElementById(
				'cbuild-kit-slots-1-label'
			) as HTMLInputElement
		 ).value = '';
		document
			.querySelector(
				'#cbuild-kit-slots-1 button[data-cbuild-move="down"]'
			)
			?.remove();
		expect( () => move( press( 1, 'up' ), speak ) ).not.toThrow();
		expect( speak ).toHaveBeenCalledWith(
			'Slot: Trucks is now slot 1 of 3.'
		);
		expect( order() ).toEqual( [ '', 'Deck', 'Wheels' ] );
	} );

	it( 'names a row with no label by its legend, and says nothing where the page gives no words', () => {
		const { list } = screen();
		const speak = vi.fn();
		(
			document.getElementById(
				'cbuild-kit-slots-2-label'
			) as HTMLInputElement
		 ).value = '';
		move( press( 2, 'up' ), speak );
		expect( speak ).toHaveBeenCalledWith(
			'Slot: Wheels is now slot 2 of 3.'
		);

		document
			.querySelectorAll( 'legend, input[id$="-label"]' )
			.forEach( ( named ) => named.remove() );
		move( press( 2, 'up' ), speak );
		expect( speak ).toHaveBeenLastCalledWith( ' is now slot 1 of 3.' );

		speak.mockClear();
		delete list.dataset.cbuildMoved;
		move( press( 2, 'down' ), speak );
		expect( speak ).not.toHaveBeenCalled();
	} );

	it( 'does nothing for a button that is in no row', () => {
		screen();
		const stray = document.createElement( 'button' );
		stray.dataset.cbuildMove = 'up';
		document.body.append( stray );
		expect( () => move( stray ) ).not.toThrow();
		const loose = document.createElement( 'fieldset' );
		loose.className = 'cbuild-kit-row';
		loose.append( stray );
		expect( () => move( stray ) ).not.toThrow();
	} );
} );

describe( 'rows', () => {
	it( 'moves a row when its button is clicked, and hears a click inside the button', () => {
		screen();
		const speak = vi.fn();
		rows( document, speak );
		expect( disabled() ).toEqual( [
			'cbuild-kit-slots-0:up',
			'cbuild-kit-slots-2:down',
		] );
		const pressed = press( 1, 'down' );
		const icon = document.createElement( 'span' );
		pressed.append( icon );
		icon.click();
		expect( order() ).toEqual( [ 'Deck', 'Wheels', 'Trucks' ] );
		expect( speak ).toHaveBeenCalledWith( 'Trucks is now slot 3 of 3.' );
	} );

	it( 'leaves other clicks alone: a button outside the rows, and what is not an element', () => {
		screen();
		rows( document );
		const stray = document.createElement( 'button' );
		stray.type = 'button';
		stray.dataset.cbuildMove = 'down';
		document.getElementById( 'cbuild-kit-presets-0' )?.append( stray );
		stray.click();
		document.getElementById( 'cbuild-kit-slots-0-label' )?.click();
		document
			.querySelector( '.cbuild-kit-rows' )
			?.dispatchEvent( new Event( 'click' ) );
		expect( order() ).toEqual( [ 'Deck', 'Trucks', 'Wheels' ] );
	} );

	it( 'makes the rows drag by their handle where jQuery UI is loaded, and sets the ends again after a drag', () => {
		const { list } = screen();
		const sortable = vi.fn();
		const $ = ( () => ( { sortable } ) ) as unknown as Query;
		rows( document, undefined, $ );
		expect( sortable ).toHaveBeenCalledTimes( 1 );
		const options = sortable.mock.calls[ 0 ]?.[ 0 ] as {
			items: string;
			handle: string;
			axis: string;
			update: () => void;
		};
		expect( options.items ).toBe( '> .cbuild-kit-row' );
		expect( options.handle ).toBe( '.cbuild-kit-handle' );
		expect( options.axis ).toBe( 'y' );

		// A drag puts the last row first.
		list.prepend( list.lastElementChild as Element );
		options.update();
		expect( disabled() ).toEqual( [
			'cbuild-kit-slots-2:up',
			'cbuild-kit-slots-1:down',
		] );
	} );

	it( 'works where jQuery has no sortable', () => {
		screen();
		const $ = ( () => ( {} ) ) as unknown as Query;
		expect( () => rows( document, undefined, $ ) ).not.toThrow();
	} );
} );

describe( 'start', () => {
	function win( hash: string, more: object = {} ) {
		const heard: Record< string, () => void > = {};
		return {
			heard,
			window: {
				document,
				location: { hash },
				addEventListener: ( event: string, run: () => void ) => {
					heard[ event ] = run;
				},
				innerHeight: 600,
				...more,
			} as unknown as Window & { jQuery?: Query },
		};
	}

	it( "waits for jQuery's ready, then makes the rows move, opens what the address names, and speaks through wp.a11y", () => {
		screen();
		const ready: Array< () => void > = [];
		const sortable = vi.fn();
		const speak = vi.fn();
		const jQuery = ( ( target: Element | ( () => void ) ) => {
			if ( typeof target === 'function' ) {
				ready.push( target );
				return undefined;
			}
			return { sortable };
		} ) as Query;
		const { window: view, heard } = win( '#cbuild-kit-slots-1-units', {
			jQuery,
			wp: { a11y: { speak } },
		} );

		start( view );
		expect( focus()?.id ).not.toBe( 'cbuild-kit-slots-1-units' );
		ready[ 0 ]?.();
		expect( focus()?.id ).toBe( 'cbuild-kit-slots-1-units' );
		expect( sortable ).toHaveBeenCalledTimes( 1 );

		press( 1, 'down' ).click();
		expect( speak ).toHaveBeenCalledWith( 'Trucks is now slot 3 of 3.' );

		view.location.hash = '#cbuild-kit-slots-0-label';
		heard.hashchange?.();
		expect( focus()?.id ).toBe( 'cbuild-kit-slots-0-label' );
	} );

	it( 'runs at once without jQuery on a page that is loaded, and speaks nothing without wp.a11y', () => {
		screen();
		const { window: view } = win( '#cbuild-kit-slots-2-label' );
		start( view );
		expect( focus()?.id ).toBe( 'cbuild-kit-slots-2-label' );
		press( 2, 'up' ).click();
		expect( order() ).toEqual( [ 'Deck', 'Wheels', 'Trucks' ] );
	} );

	it( 'speaks nothing where wp is loaded without a11y', () => {
		screen();
		const { window: view } = win( '', { wp: {} } );
		expect( () => start( view ) ).not.toThrow();
		press( 2, 'up' ).click();
		expect( order() ).toEqual( [ 'Deck', 'Wheels', 'Trucks' ] );
	} );

	it( 'waits for the page where it is still loading', () => {
		screen();
		const heard: Record< string, () => void > = {};
		const doc = {
			readyState: 'loading',
			addEventListener: ( event: string, run: () => void ) => {
				heard[ event ] = run;
			},
			querySelectorAll: () => [],
			getElementById: () => null,
		};
		const { window: view } = win( '', { document: doc } );
		start( view );
		expect( Object.keys( heard ) ).toEqual( [ 'DOMContentLoaded' ] );
		expect( () => heard.DOMContentLoaded?.() ).not.toThrow();
	} );
} );

describe( 'the script, when the page loads it', () => {
	afterEach( () => {
		window.location.hash = '';
	} );

	it( 'starts itself on the window', async () => {
		screen();
		window.location.hash = '#cbuild-kit-slots-2-label';
		vi.resetModules();
		await import( '../../assets/src/admin/build-tab' );
		expect( focus()?.id ).toBe( 'cbuild-kit-slots-2-label' );
		expect( disabled() ).toEqual( [
			'cbuild-kit-slots-0:up',
			'cbuild-kit-slots-2:down',
		] );
	} );
} );
