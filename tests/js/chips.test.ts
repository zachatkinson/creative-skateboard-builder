/**
 * #98 (F13): on a desktop the brand chips sit in one row, the brands with the most parts first, and "N more" opens
 * the rest in place, with focus on the first new chip. The server renders every chip; the store measures which fit
 * and hides the rest, so without the script every chip shows.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setContext, setElement, stores } from './support/interactivity';
import { NAME } from '../../assets/src/builder/view';
import { fitting, shut } from '../../assets/src/builder/row';

interface Group {
	key: string;
	inputs: { value: string; checked: boolean }[];
	more: { shown: boolean; id: string; texts: string[]; fewer: string };
}

interface Defined {
	state: {
		view: Record< string, unknown >;
		live: boolean;
		shut: string[];
		opened: string;
		readonly past: boolean;
		readonly rests: boolean;
		readonly idle: boolean;
		readonly open: boolean;
		readonly rest: string;
	};
	actions: { more: () => void };
	callbacks: { row: () => void };
}

const { state, actions, callbacks } = stores.get( NAME )!
	.definition as unknown as Defined;

const BRAND: Group = {
	key: 'brand',
	inputs: [
		{ value: 'zero', checked: false },
		{ value: 'bones', checked: false },
		{ value: 'acme', checked: false },
		{ value: 'mob', checked: false },
	],
	more: {
		shown: true,
		id: 'cbuild-chips-deck-brand',
		texts: [ '1 more', '2 more', '3 more', '4 more' ],
		fewer: 'Show fewer',
	},
};

/**
 * The brand group's chips as the template renders them, each `width` pixels wide, in a row `room` pixels wide.
 *
 * @param widths The chips' widths.
 * @param room   The row's width.
 * @param button The "N more" button's width.
 */
function rendered(
	widths: number[],
	room: number,
	button = 60
): { options: HTMLElement; more: HTMLButtonElement } {
	document.body.innerHTML = `<div class="cbuild"><div class="cbuild-chips__options" id="cbuild-chips-deck-brand">${ BRAND.inputs
		.map(
			( chip ) =>
				`<span class="cbuild-chips__chip"><input class="cbuild-chips__control" type="checkbox" value="${ chip.value }"${
					chip.checked ? ' checked' : ''
				}><label class="cbuild-chip">${ chip.value }</label></span>`
		)
		.join(
			''
		) }<button class="cbuild-chips__more" type="button"></button></div></div>`;
	const options = document.querySelector< HTMLElement >(
		'.cbuild-chips__options'
	)!;
	options.style.columnGap = '8px';
	Object.defineProperty( options, 'clientWidth', { value: room } );
	options
		.querySelectorAll< HTMLElement >( '.cbuild-chips__chip' )
		.forEach( ( chip, at ) => {
			chip.getBoundingClientRect = () =>
				( { width: widths[ at ] ?? 0 } ) as DOMRect;
		} );
	const more = options.querySelector< HTMLButtonElement >(
		'.cbuild-chips__more'
	)!;
	more.getBoundingClientRect = () => ( { width: button } ) as DOMRect;
	return { options, more };
}

beforeEach( () => {
	vi.useFakeTimers();
	state.view = { step: { slot: 'deck' } };
	state.live = true;
	state.shut = [];
	state.opened = '';
	BRAND.inputs.forEach( ( chip ) => ( chip.checked = false ) );
} );

afterEach( () => {
	vi.useRealTimers();
	setElement( null );
	setContext( {} );
	document.body.innerHTML = '';
} );

describe( 'which chips fit in one row', () => {
	it( 'shows every chip that fits, with no room kept for the button', () => {
		expect( fitting( [ 100, 100, 100 ], [], 316, 8, 60 ) ).toEqual( [
			true,
			true,
			true,
		] );
		expect( fitting( [], [], 316, 8, 60 ) ).toEqual( [] );
	} );

	it( 'keeps room for "N more" at the row\'s end once one does not fit', () => {
		// 60 + (100 + 8) + (100 + 8) = 276; a third would take it to 384.
		expect( fitting( [ 100, 100, 100 ], [], 315, 8, 60 ) ).toEqual( [
			true,
			true,
			false,
		] );
		expect( fitting( [ 100, 100, 100 ], [], 276, 8, 60 ) ).toEqual( [
			true,
			true,
			false,
		] );
		expect( fitting( [ 100, 100, 100 ], [], 275, 8, 60 ) ).toEqual( [
			true,
			false,
			false,
		] );
	} );

	it( 'keeps the order: once one does not fit, a smaller one after it waits too', () => {
		expect( fitting( [ 100, 200, 10 ], [], 300, 8, 50 ) ).toEqual( [
			true,
			false,
			false,
		] );
	} );

	it( 'always shows a chosen chip, and the rest fill what it leaves', () => {
		expect(
			fitting( [ 100, 100, 100 ], [ false, false, true ], 315, 8, 60 )
		).toEqual( [ true, false, true ] );
		expect(
			fitting( [ 100, 100, 100 ], [ false, false, true ], 50, 8, 60 )
		).toEqual( [ false, false, true ] );
	} );

	it( 'counts a chosen chip and its gap once, beside the rest', () => {
		// 60 + 108 chosen, then 108 and 108: 384; a fourth would take it to 492.
		expect(
			fitting( [ 100, 100, 100, 100 ], [ true ], 400, 8, 60 )
		).toEqual( [ true, true, true, false ] );
		// 60 + 108 + 108 = 276; a third would take it to 384.
		expect(
			fitting( [ 100, 100, 100, 100 ], [ true ], 375, 8, 60 )
		).toEqual( [ true, true, false, false ] );
	} );

	it( 'shows every chosen chip, and only those chips, where they alone fill the row', () => {
		expect( fitting( [ 100, 100 ], [ true, true ], 150, 8, 60 ) ).toEqual( [
			true,
			true,
		] );
	} );

	it( "counts a chip's part pixel as a whole one, so a row the browser would wrap is not taken for one that fits", () => {
		// 101 + 8 + 101 = 210: the second goes behind the button.
		expect( fitting( [ 100.4, 100.4 ], [], 208.8, 8, 60 ) ).toEqual( [
			true,
			false,
		] );
		expect( fitting( [ 100.4, 100.4 ], [], 210, 8, 60 ) ).toEqual( [
			true,
			true,
		] );
	} );
} );

describe( 'which chips a row shuts away', () => {
	it( 'measures a row with no button, no chip control or no window as nothing there, and names a chip with no control by nothing', () => {
		const page = document.implementation.createHTMLDocument( '' );
		page.body.innerHTML =
			'<div class="cbuild-chips__options"><span class="cbuild-chips__chip"><input class="cbuild-chips__control" type="checkbox" value="zero"></span><span class="cbuild-chips__chip"></span></div>';
		const options = page.querySelector< HTMLElement >(
			'.cbuild-chips__options'
		)!;
		Object.defineProperty( options, 'clientWidth', { value: 150 } );
		options
			.querySelectorAll< HTMLElement >( '.cbuild-chips__chip' )
			.forEach( ( chip ) => {
				chip.getBoundingClientRect = () =>
					( { width: 100 } ) as DOMRect;
			} );
		expect( shut( options, 0 ) ).toEqual( { values: [ '' ], button: 0 } );
	} );
} );

describe( 'the brand chips in one row', () => {
	it( 'hides a chip past the row only where the group has "N more" and it is not open', () => {
		state.shut = [ 'acme', 'mob' ];
		setContext( { group: BRAND, chip: BRAND.inputs[ 2 ] } );
		expect( state.past ).toBe( true );
		setContext( { group: BRAND, chip: BRAND.inputs[ 0 ] } );
		expect( state.past ).toBe( false );
		setContext( {
			group: { ...BRAND, more: { ...BRAND.more, shown: false } },
			chip: BRAND.inputs[ 2 ],
		} );
		expect( state.past ).toBe( false );
		setContext( { group: BRAND, chip: BRAND.inputs[ 2 ] } );
		state.opened = 'deck';
		expect( state.past ).toBe( false );
		state.view = { step: { slot: 'wheels' } };
		expect( state.past ).toBe( true );
	} );

	it( "names the button with how many it holds, or Show fewer once open, from the server's words", () => {
		setContext( { group: BRAND } );
		state.shut = [ 'acme', 'mob' ];
		expect( state.rest ).toBe( '2 more' );
		expect( state.open ).toBe( false );
		expect( state.idle ).toBe( false );
		state.opened = 'deck';
		expect( state.rest ).toBe( 'Show fewer' );
		expect( state.open ).toBe( true );
		state.opened = '';
		state.shut = [];
		// Nothing past the row: the button is idle, out of sight, and still measured with words it may take.
		expect( state.idle ).toBe( true );
		expect( state.rest ).toBe( '1 more' );
	} );

	it( 'offers the button only once the script runs, and only on a group that has it', () => {
		setContext( { group: BRAND } );
		expect( state.rests ).toBe( true );
		state.live = false;
		expect( state.rests ).toBe( false );
		state.live = true;
		setContext( {
			group: { ...BRAND, more: { ...BRAND.more, shown: false } },
		} );
		expect( state.rests ).toBe( false );
	} );

	it( 'measures which chips fit and hides the rest, keeping a chosen one in view', () => {
		const { options } = rendered( [ 100, 100, 100, 100 ], 400 );
		setElement( options );
		setContext( { group: BRAND } );
		callbacks.row();
		vi.advanceTimersToNextFrame();
		// 60 + 108 + 108 + 108 = 384; a fourth would take it to 492.
		expect( state.shut ).toEqual( [ 'mob' ] );

		BRAND.inputs[ 3 ]!.checked = true;
		const again = rendered( [ 100, 100, 100, 100 ], 400 );
		setElement( again.options );
		callbacks.row();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'acme' ] );
	} );

	it( "measures again with the button's new words, and never takes back what a wider button left out", () => {
		const { options, more } = rendered( [ 100, 100, 100, 100 ], 400, 60 );
		setElement( options );
		setContext( { group: BRAND } );
		callbacks.row();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'mob' ] );
		// "1 more" turned out wider than the idle button measured.
		more.getBoundingClientRect = () => ( { width: 80 } ) as DOMRect;
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'acme', 'mob' ] );
		more.getBoundingClientRect = () => ( { width: 60 } ) as DOMRect;
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'acme', 'mob' ] );
	} );

	it( "lays the row out again when its size or a chip's changes, and not for a view that changes none of its chips", () => {
		const watched: { callback: () => void; target: Element }[] = [];
		let watchers = 0;
		vi.stubGlobal(
			'ResizeObserver',
			class {
				constructor( private readonly callback: () => void ) {
					watchers++;
				}
				observe( target: Element ): void {
					watched.push( { callback: this.callback, target } );
				}
			}
		);
		const { options } = rendered( [ 100, 100, 100, 100 ], 400 );
		setElement( options );
		setContext( { group: BRAND } );
		callbacks.row();
		callbacks.row();
		vi.advanceTimersToNextFrame();
		// The second pass measures the button with its words and finds the same.
		vi.advanceTimersToNextFrame();
		// One watcher for the row, on the row and each chip (a web font that arrives late changes the chips alone).
		expect( watchers ).toBe( 1 );
		expect( [
			...new Set( watched.map( ( entry ) => entry.target ) ),
		] ).toEqual( [
			options,
			...options.querySelectorAll( '.cbuild-chips__chip' ),
		] );
		expect( state.shut ).toEqual( [ 'mob' ] );

		state.shut = [ 'zero' ];
		callbacks.row();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'zero' ] );

		watched[ 0 ]!.callback();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'mob' ] );

		// A chip's own change of size lays the row out again.
		state.shut = [ 'zero' ];
		watched.find( ( entry ) => entry.target !== options )!.callback();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'mob' ] );
		vi.unstubAllGlobals();
	} );

	it( 'lays the row out again for a view that changes a chip, though its words run on into the next', () => {
		const { options } = rendered( [ 100, 100, 100, 100 ], 400 );
		setElement( options );
		const group = {
			...BRAND,
			inputs: [ { value: 'zero', count: '12', checked: false } ],
		};
		setContext( { group } );
		callbacks.row();
		for ( let frame = 0; frame < 4; frame++ ) {
			vi.advanceTimersToNextFrame();
		}
		expect( state.shut ).toEqual( [ 'mob' ] );
		state.shut = [];
		group.inputs = [ { value: 'zero1', count: '2', checked: false } ];
		callbacks.row();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'mob' ] );

		// A group whose chips the view leaves out is laid out once, as one with none.
		state.shut = [];
		const bare = rendered( [ 100, 100, 100, 100 ], 400 );
		setElement( bare.options );
		setContext( { group: { more: BRAND.more } } );
		callbacks.row();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [ 'mob' ] );
	} );

	it( 'measures the row in three frames at most, however the button’s words keep changing its width', () => {
		const { options, more } = rendered( [ 100, 100, 100, 100 ], 400 );
		const widths = [ 60, 80, 200, 300 ];
		let asked = 0;
		more.getBoundingClientRect = () =>
			( {
				width: widths[ Math.min( asked++, widths.length - 1 ) ],
			} ) as DOMRect;
		setElement( options );
		setContext( { group: BRAND } );
		callbacks.row();
		for ( let frame = 0; frame < 5; frame++ ) {
			vi.advanceTimersToNextFrame();
		}
		expect( state.shut ).toEqual( [ 'bones', 'acme', 'mob' ] );
	} );

	it( 'lays out nothing, and throws nothing, outside a group or in one with no "N more"', () => {
		const { options } = rendered( [ 100, 100, 100, 100 ], 100 );
		setElement( options );
		for ( const context of [ {}, { group: { inputs: BRAND.inputs } } ] ) {
			setContext( context );
			expect( () => callbacks.row() ).not.toThrow();
			vi.advanceTimersToNextFrame();
			expect( state.shut ).toEqual( [] );
		}
	} );

	it( 'leaves a group with no "N more" as it is', () => {
		const { options } = rendered( [ 100, 100, 100, 100 ], 100 );
		setElement( options );
		setContext( {
			group: { ...BRAND, more: { ...BRAND.more, shown: false } },
		} );
		callbacks.row();
		vi.advanceTimersToNextFrame();
		expect( state.shut ).toEqual( [] );
	} );

	it( 'opens the rest in place with focus on the first new chip, and folds them again', () => {
		const { options, more } = rendered( [ 100, 100, 100, 100 ], 400 );
		options
			.querySelectorAll( '.cbuild-chips__chip' )
			.forEach( ( chip, at ) =>
				chip.classList.toggle( 'cbuild-chips__chip--past', at >= 2 )
			);
		more.focus();
		setElement( more );
		actions.more();
		expect( state.opened ).toBe( 'deck' );
		vi.advanceTimersToNextFrame();
		expect( options.ownerDocument.activeElement ).toBe(
			options.querySelector( 'input[value="acme"]' )
		);
		more.focus();
		actions.more();
		expect( state.opened ).toBe( '' );
		vi.advanceTimersToNextFrame();
		expect( options.ownerDocument.activeElement ).toBe( more );
	} );
	it( 'opens the rest, and throws nothing, where no chip is past the row, the button is in none, or none was found', () => {
		const { options, more } = rendered( [ 100, 100, 100, 100 ], 400 );
		setElement( more );
		actions.more();
		expect( state.opened ).toBe( 'deck' );
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		state.opened = '';
		const loose = document.createElement( 'button' );
		document.body.append( loose );
		setElement( loose );
		actions.more();
		expect( state.opened ).toBe( 'deck' );
		state.opened = '';
		setElement( null );
		actions.more();
		expect( state.opened ).toBe( 'deck' );
		expect( () => vi.advanceTimersToNextFrame() ).not.toThrow();
		expect( options.isConnected ).toBe( true );
	} );
} );
