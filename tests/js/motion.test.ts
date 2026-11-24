/**
 * The motion module (spec: Milestone 11, the motion; D-256, D-257, D-305): one place decides whether a moment moves,
 * fades or changes at once, and cuts it short.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	FADE,
	STAGGER,
	TIMES,
	build,
	calm,
	carted,
	close,
	count,
	cross,
	cues,
	cut,
	framesFor,
	measured,
	money,
	play,
	side,
	type Currency,
} from '../../assets/src/builder/motion';

type Played = {
	frames: Keyframe[];
	options: KeyframeAnimationOptions;
	animation: Animation & { finish: ReturnType< typeof vi.fn > };
};

/**
 * Give an element an `animate()` that notes what it was asked, as jsdom has none.
 *
 * @param element The element.
 * @param played  Where each call is noted.
 */
function animated< T extends Element >( element: T, played: Played[] ): T {
	element.animate = ( keyframes, options ) => {
		let settle: () => void = () => undefined;
		const finished = new Promise< Animation >( ( done ) => {
			settle = () => done( animation );
		} );
		const animation = {
			id: '',
			finished,
			finish: vi.fn( () => settle() ),
		} as unknown as Played[ 'animation' ];
		played.push( {
			frames: keyframes as Keyframe[],
			options: options as KeyframeAnimationOptions,
			animation,
		} );
		return animation;
	};
	return element;
}

/**
 * Every key a moment's frames move.
 *
 * @param keyframes The frames.
 */
function keysOf( keyframes: Keyframe[] ): string[] {
	return [
		...new Set( keyframes.flatMap( ( frame ) => Object.keys( frame ) ) ),
	];
}

function reduced( on: boolean ): void {
	vi.stubGlobal( 'matchMedia', ( query: string ) => ( {
		matches: on && '(prefers-reduced-motion: reduce)' === query,
	} ) );
}

beforeEach( () => {
	reduced( false );
} );

afterEach( () => {
	cut();
	vi.unstubAllGlobals();
	document.documentElement.className = '';
	document.body.innerHTML = '';
	Object.defineProperty( document, 'startViewTransition', {
		value: undefined,
		configurable: true,
	} );
} );

describe( 'the moments, from the spec’s table', () => {
	it( 'has each moment’s time', () => {
		expect( TIMES ).toEqual( {
			deck: 280,
			trucks: 240,
			wheels: 220,
			swapped: 200,
			removed: 180,
			grip: 300,
			turn: 400,
			total: 300,
			step: 200,
			cart: 400,
		} );
		expect( FADE ).toBe( 150 );
		expect( STAGGER ).toEqual( { trucks: 60, wheels: 40 } );
	} );
} );

describe( 'calm', () => {
	it( 'fades under reduced motion or on subtle, and moves otherwise', () => {
		const root = document.createElement( 'div' );
		expect( calm( root ) ).toBe( false );
		expect( calm( null ) ).toBe( false );
		root.classList.add( 'cbuild--subtle' );
		expect( calm( root ), 'subtle' ).toBe( true );
		root.classList.remove( 'cbuild--subtle' );
		reduced( true );
		expect( calm( root ), 'reduced motion' ).toBe( true );
		expect( calm( null ) ).toBe( true );
	} );

	it( 'moves where the browser cannot say what the shopper asks', () => {
		vi.stubGlobal( 'matchMedia', undefined );
		expect( calm( document.createElement( 'div' ) ) ).toBe( false );
	} );
} );

describe( 'play', () => {
	it( 'moves an element by its frames, for the moment’s time, after its delay, and names it', () => {
		const played: Played[] = [];
		const element = animated( document.createElement( 'span' ), played );
		const frames = [
			{ opacity: 0, transform: 'translateY(-4%)' },
			{ opacity: 1, transform: 'none' },
		];
		const animation = play( element, 'trucks', frames, false, 60 );
		expect( played[ 0 ]!.frames ).toBe( frames );
		expect( played[ 0 ]!.options ).toEqual( {
			duration: 240,
			delay: 60,
			easing: 'cubic-bezier(0.2, 0, 0, 1)',
			fill: 'backwards',
		} );
		expect( animation?.id ).toBe( 'cbuild-trucks' );
	} );

	it( 'fades in, or out, in 150 ms with no delay where it is calm, and an only-moving moment changes at once', () => {
		const played: Played[] = [];
		const element = animated( document.createElement( 'span' ), played );
		play(
			element,
			'turn',
			[ { transform: 'scaleX(0.1)' }, { transform: 'none' } ],
			true,
			60
		);
		expect( played[ 0 ]!.frames ).toEqual( [
			{ opacity: 0 },
			{ opacity: 1 },
		] );
		expect( played[ 0 ]!.options ).toEqual( {
			duration: FADE,
			delay: 0,
			easing: 'linear',
			fill: 'backwards',
		} );
		play( element, 'swapped', [], true, 0, 'out' );
		expect( played[ 1 ]!.frames ).toEqual( [
			{ opacity: 1 },
			{ opacity: 0 },
		] );
		expect(
			play( element, 'removed', [], true, 0, 'none' )
		).toBeUndefined();
		expect( played ).toHaveLength( 2 );
		expect(
			play(
				element,
				'removed',
				[ { transform: 'none' } ],
				false,
				0,
				'none'
			)
		).toBeDefined();
	} );

	it( 'changes at once where the browser animates nothing', () => {
		const element = document.createElement( 'span' );
		Object.defineProperty( element, 'animate', { value: undefined } );
		expect( play( element, 'deck', [], false ) ).toBeUndefined();
	} );
} );

describe( 'cut', () => {
	it( 'finishes every running moment, once, and skips the view transition', async () => {
		const played: Played[] = [];
		const element = animated( document.createElement( 'span' ), played );
		play( element, 'deck', [], false );
		play( element, 'grip', [], false );
		const skip = vi.fn();
		Object.defineProperty( document, 'startViewTransition', {
			value: ( update: () => void ) => {
				update();
				return {
					finished: new Promise( () => undefined ),
					skipTransition: skip,
				};
			},
			configurable: true,
		} );
		cross( () => undefined, true, false );
		cut();
		expect(
			played.map(
				( { animation } ) => animation.finish.mock.calls.length
			)
		).toEqual( [ 1, 1 ] );
		expect( skip ).toHaveBeenCalledTimes( 1 );
		cut();
		expect( played[ 0 ]!.animation.finish ).toHaveBeenCalledTimes( 1 );
		expect( skip ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'leaves a moment that has finished alone', async () => {
		const played: Played[] = [];
		const element = animated( document.createElement( 'span' ), played );
		play( element, 'deck', [], false );
		played[ 0 ]!.animation.finish();
		await played[ 0 ]!.animation.finished;
		await Promise.resolve();
		cut();
		expect( played[ 0 ]!.animation.finish ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'cues', () => {
	const deck = { key: 'deck-1', src: 'deck.png' };
	const trucks = [
		{ key: 'trucks-1', src: 't.png' },
		{ key: 'trucks-2', src: 't.png' },
	];
	const wheels = [ 1, 2, 3, 4 ].map( ( n ) => ( {
		key: `wheels-${ n }`,
		src: 'w.png',
	} ) );

	it( 'settles a deck picked onto an empty stage', () => {
		expect( cues( [], [ deck ] ) ).toEqual( [
			{ moment: 'deck', index: 0, delay: 0 },
		] );
	} );

	it( 'slides each truck in 60 ms after the one before, and each wheel 40 ms', () => {
		expect( cues( [ deck ], [ deck, ...trucks ] ) ).toEqual( [
			{ moment: 'trucks', index: 1, delay: 0 },
			{ moment: 'trucks', index: 2, delay: 60 },
		] );
		expect(
			cues( [ deck, ...trucks ], [ deck, ...trucks, ...wheels ] ).map(
				( cue ) => cue.delay
			)
		).toEqual( [ 0, 40, 80, 120 ] );
	} );

	it( 'lays the grip on the top, and swaps a part whose picture changed', () => {
		expect(
			cues( [ deck ], [ deck, { key: 'grip-1', src: 'g.png' } ] )
		).toEqual( [ { moment: 'grip', index: 1, delay: 0 } ] );
		expect(
			cues(
				[ deck, ...trucks ],
				[ { key: 'deck-1', src: 'other.png' }, ...trucks ]
			)
		).toEqual( [ { moment: 'swapped', index: 0, delay: 0 } ] );
	} );

	it( 'settles a part of a slot with no moment of its own as a swap, and leaves an unchanged board still', () => {
		expect(
			cues( [ deck ], [ deck, { key: 'hardware-1', src: 'h.png' } ] )
		).toEqual( [ { moment: 'swapped', index: 1, delay: 0 } ] );
		expect( cues( [ deck, ...trucks ], [ deck, ...trucks ] ) ).toEqual(
			[]
		);
		expect(
			cues( [ deck, ...trucks ], [ deck ] ),
			'a part removed is closed over by the photos'
		).toEqual( [] );
	} );
} );

describe( 'frames', () => {
	const cue = (
		moment: 'deck' | 'trucks' | 'wheels' | 'grip' | 'swapped'
	) => ( { moment, index: 0, delay: 0 } );

	it( 'moves transform and opacity alone, ending where the part is drawn', () => {
		for ( const moment of [
			'deck',
			'trucks',
			'wheels',
			'swapped',
		] as const ) {
			const shown = framesFor( cue( moment ), { key: 'x-1' } );
			expect( keysOf( shown ) ).toEqual( [ 'opacity', 'transform' ] );
			expect( shown[ 0 ]!.opacity ).toBe( 0 );
			expect( shown.at( -1 ) ).toEqual( {
				opacity: 1,
				transform: 'none',
			} );
		}
		expect( framesFor( cue( 'grip' ), { key: 'grip-1' } ) ).toEqual( [
			{ transform: 'scaleY(0)' },
			{ transform: 'none' },
		] );
	} );

	it( 'settles the deck from just above, the trucks onto their holes, and a swap where the old part was', () => {
		expect(
			framesFor( cue( 'deck' ), { key: 'deck-1' } )[ 0 ]!.transform
		).toBe( 'translateY(-4%) scale(1.03)' );
		expect(
			framesFor( cue( 'trucks' ), { key: 'trucks-1' } )[ 0 ]!.transform
		).toBe( 'translateY(-40%)' );
		expect(
			framesFor( cue( 'swapped' ), { key: 'deck-1' } )[ 0 ]!.transform
		).toBe( 'translateY(-8%)' );
	} );

	it( 'slides each wheel in from its own side, turning a quarter', () => {
		const left = {
			key: 'wheels-1',
			style: '--cbuild-layer-x:4.5%;--cbuild-layer-y:10%',
		};
		const right = {
			key: 'wheels-2',
			style: '--cbuild-layer-y:10%;--cbuild-layer-x:80%',
		};
		expect( framesFor( cue( 'wheels' ), left )[ 0 ]!.transform ).toBe(
			'translateX(-60%) rotate(-90deg)'
		);
		expect( framesFor( cue( 'wheels' ), right )[ 0 ]!.transform ).toBe(
			'translateX(60%) rotate(-90deg)'
		);
	} );

	it( 'reads a layer’s side from its place across the board', () => {
		expect( side( { key: 'a', style: '--cbuild-layer-x:50%' } ) ).toBe( 1 );
		expect( side( { key: 'a', style: '--cbuild-layer-x:49.99%' } ) ).toBe(
			-1
		);
		expect( side( { key: 'a', style: '--cbuild-layer-x:-5%' } ) ).toBe(
			-1
		);
		expect( side( { key: 'a' } ) ).toBe( -1 );
	} );
} );

describe( 'build', () => {
	it( 'plays each cue on the layer drawn in its place, past the template', () => {
		const played: Played[] = [];
		const board = document.createElement( 'div' );
		board.innerHTML =
			'<template></template><picture></picture><picture></picture><picture></picture>';
		const drawn = [ ...board.querySelectorAll( 'picture' ) ].map(
			( layer ) => animated( layer, played )
		);
		const after = [
			{ key: 'deck-1' },
			{ key: 'trucks-1' },
			{ key: 'trucks-2' },
		];
		build( board, [ { key: 'deck-1' } ], after, false );
		expect( played.map( ( { animation } ) => animation.id ) ).toEqual( [
			'cbuild-trucks',
			'cbuild-trucks',
		] );
		expect( played.map( ( { options } ) => options.delay ) ).toEqual( [
			0, 60,
		] );
		expect( drawn[ 0 ] ).toBeDefined();
	} );

	it( 'fades them where it is calm, and passes over a layer that is not drawn or a board that is none', () => {
		const played: Played[] = [];
		const board = document.createElement( 'div' );
		board.innerHTML = '<picture></picture>';
		animated( board.querySelector( 'picture' )!, played );
		build( board, [], [ { key: 'deck-1' }, { key: 'trucks-1' } ], true );
		expect( played ).toHaveLength( 1 );
		expect( played[ 0 ]!.frames ).toEqual( [
			{ opacity: 0 },
			{ opacity: 1 },
		] );
		expect( () =>
			build( null, [], [ { key: 'deck-1' } ], false )
		).not.toThrow();
	} );
} );

describe( 'a part removed', () => {
	function placed( element: Element, left: number, top: number ): void {
		element.getBoundingClientRect = () => ( { left, top } ) as DOMRect;
	}

	it( 'slides each photo still shown from where it was, and leaves one that did not move or left', () => {
		const played: Played[] = [];
		const item = () =>
			animated(
				document.body.appendChild( document.createElement( 'li' ) ),
				played
			);
		const [ moved, still, gone ] = [ item(), item(), item() ];
		placed( moved, 100, 50 );
		placed( still, 10, 10 );
		placed( gone, 0, 0 );
		const was = measured( [ moved, still, gone ] );
		placed( moved, 40, 50.5 );
		placed( still, 10.5, 9.5 );
		gone.remove();
		close( was, false );
		expect( played ).toHaveLength( 1 );
		expect( played[ 0 ]!.frames ).toEqual( [
			{ transform: 'translate(60px, -0.5px)' },
			{ transform: 'none' },
		] );
		expect( played[ 0 ]!.options.duration ).toBe( 180 );
		placed( moved, 100, 49 );
		close( was, false );
		expect( played[ 1 ]!.frames[ 0 ] ).toEqual( {
			transform: 'translate(0px, 1px)',
		} );
	} );

	it( 'puts the photos in their places at once where it is calm', () => {
		const played: Played[] = [];
		const item = animated(
			document.body.appendChild( document.createElement( 'li' ) ),
			played
		);
		placed( item, 100, 0 );
		const was = measured( [ item ] );
		placed( item, 0, 0 );
		close( was, true );
		expect( played ).toEqual( [] );
	} );
} );

describe( 'money', () => {
	const dollars: Currency = {
		symbol: '$',
		decimals: 2,
		decimal_separator: '.',
		thousand_separator: ',',
		format: '%1$s%2$s',
	};

	it( 'writes an amount as wc_price() does, in each of WooCommerce’s formats', () => {
		expect( money( 18400, dollars ) ).toBe( '$184.00' );
		expect( money( 123456789, dollars ) ).toBe( '$1,234,567.89' );
		expect( money( 5, dollars ) ).toBe( '$0.05' );
		expect( money( 0, dollars ) ).toBe( '$0.00' );
		expect( money( -1250, dollars ) ).toBe( '-$12.50' );
		// fr_CA: a comma for the decimals, a no-break space for thousands and before the symbol on the right.
		const french = {
			symbol: '$',
			decimals: 2,
			decimal_separator: ',',
			thousand_separator: ' ',
			format: '%2$s %1$s',
		};
		expect( money( 123456, french ) ).toBe( '1 234,56 $' );
		expect( money( 18400, { ...dollars, format: '%1$s %2$s' } ) ).toBe(
			'$ 184.00'
		);
		expect(
			money( 18400, { ...dollars, format: '%2$s%1$s', symbol: '€' } )
		).toBe( '184.00€' );
	} );

	it( 'writes a currency with no decimals, or three, and a decimals count that is no whole number', () => {
		const yen = { ...dollars, symbol: '¥', decimals: 0 };
		expect( money( 18400, yen ) ).toBe( '¥18,400' );
		expect(
			money( 1234567, { ...dollars, symbol: 'KD', decimals: 3 } )
		).toBe( 'KD1,234.567' );
		expect( money( 999, { ...dollars, decimals: 2.7 } ) ).toBe( '$9.99' );
		expect( money( 999, { ...dollars, decimals: -1 } ) ).toBe( '$999' );
	} );
} );

describe( 'count', () => {
	const dollars: Currency = {
		symbol: '$',
		decimals: 2,
		decimal_separator: '.',
		thousand_separator: ',',
		format: '%1$s%2$s',
	};
	let frames: Array< ( now: number ) => void >;
	let cancelled: number[];

	beforeEach( () => {
		frames = [];
		cancelled = [];
		vi.stubGlobal(
			'requestAnimationFrame',
			( callback: ( now: number ) => void ) => frames.push( callback )
		);
		vi.stubGlobal( 'cancelAnimationFrame', ( frame: number ) =>
			cancelled.push( frame )
		);
	} );

	function run( now: number ): void {
		const next = frames.splice( 0 );
		next.forEach( ( frame ) => frame( now ) );
	}

	it( 'counts from the old amount to the new over 300 ms, easing out, and lands on the server’s string', () => {
		const shown: string[] = [];
		count(
			10000,
			20000,
			'$200.00 server',
			dollars,
			( text ) => shown.push( text ),
			false
		);
		expect( shown ).toEqual( [ '$100.00' ] );
		run( 1000 );
		run( 1150 );
		run( 1299 );
		run( 1300 );
		expect( shown ).toEqual( [
			'$100.00',
			'$100.00',
			'$187.50',
			'$200.00',
			'$200.00 server',
		] );
		expect( frames ).toEqual( [] );
	} );

	it( 'counts down as well', () => {
		const shown: string[] = [];
		count(
			20000,
			10000,
			'last',
			dollars,
			( text ) => shown.push( text ),
			false
		);
		run( 0 );
		run( 150 );
		expect( shown.at( -1 ) ).toBe( '$112.50' );
	} );

	it( 'changes at once where it is calm, where nothing changed, or where the browser has no frames', () => {
		const shown: string[] = [];
		count(
			100,
			200,
			'calm',
			dollars,
			( text ) => shown.push( text ),
			true
		);
		count(
			200,
			200,
			'same',
			dollars,
			( text ) => shown.push( text ),
			false
		);
		vi.stubGlobal( 'requestAnimationFrame', undefined );
		count(
			100,
			200,
			'none',
			dollars,
			( text ) => shown.push( text ),
			false
		);
		expect( shown ).toEqual( [ 'calm', 'same', 'none' ] );
	} );

	it( 'lands at once when cut short, or when a newer total starts counting', () => {
		const shown: string[] = [];
		count(
			100,
			200,
			'first',
			dollars,
			( text ) => shown.push( text ),
			false
		);
		run( 0 );
		cut();
		expect( shown.at( -1 ) ).toBe( 'first' );
		expect( cancelled ).toHaveLength( 1 );
		run( 100 );
		expect( shown.at( -1 ), 'the frame waiting is cancelled' ).not.toBe(
			'$1.00'
		);
		count(
			100,
			300,
			'second',
			dollars,
			( text ) => shown.push( text ),
			false
		);
		count(
			300,
			400,
			'third',
			dollars,
			( text ) => shown.push( text ),
			false
		);
		expect( shown.slice( -2 ) ).toEqual( [ 'second', '$3.00' ] );
		cut();
		expect( shown.at( -1 ) ).toBe( 'third' );
		cut();
		expect( shown.filter( ( text ) => 'third' === text ) ).toHaveLength(
			1
		);
	} );
} );

describe( 'cross', () => {
	function transitions(): {
		updates: Array< () => unknown >;
		finish: () => void;
	} {
		const updates: Array< () => unknown > = [];
		let finish: () => void = () => undefined;
		Object.defineProperty( document, 'startViewTransition', {
			value: ( update: () => unknown ) => {
				updates.push( update );
				return {
					finished: new Promise< void >( ( done ) => {
						finish = done;
					} ),
					skipTransition: vi.fn(),
				};
			},
			configurable: true,
		} );
		return { updates, finish: () => finish() };
	}

	it( 'crosses a step over the way the shopper is going, by a class on the page’s root', async () => {
		const { updates } = transitions();
		const change = vi.fn();
		cross( change, true, false );
		const page = document.documentElement;
		expect( page.classList.contains( 'cbuild-back' ) ).toBe( false );
		expect( page.classList.contains( 'cbuild-calm' ) ).toBe( false );
		expect( change ).not.toHaveBeenCalled();
		vi.useFakeTimers();
		const drawn = updates[ 0 ]!() as Promise< void >;
		expect( change ).toHaveBeenCalledTimes( 1 );
		vi.runAllTimers();
		await expect( drawn ).resolves.toBeUndefined();
		vi.useRealTimers();
		cross( change, false, true );
		expect( page.classList.contains( 'cbuild-back' ) ).toBe( true );
		expect( page.classList.contains( 'cbuild-calm' ), 'a fade' ).toBe(
			true
		);
	} );

	// The builder's view-transition rules hold only while its own transition runs, so another on the page is left as
	// its author made it (M11 audit, finding 9).
	it( 'marks the page’s root as crossing for the life of its own transition alone', async () => {
		const page = document.documentElement;
		const first = transitions();
		let during: boolean | undefined;
		cross(
			() => {
				during = page.classList.contains( 'cbuild-crossing' );
			},
			true,
			false
		);
		expect( page.classList.contains( 'cbuild-crossing' ) ).toBe( true );
		vi.useFakeTimers();
		const drawn = first.updates[ 0 ]!() as Promise< void >;
		vi.runAllTimers();
		await drawn;
		vi.useRealTimers();
		expect( during, 'while the view changes' ).toBe( true );
		first.finish();
		await Promise.resolve();
		await Promise.resolve();
		expect( page.classList.contains( 'cbuild-crossing' ) ).toBe( false );

		// An older transition that finishes while a newer one runs leaves the mark to the newer.
		const older = transitions();
		cross( () => undefined, true, false );
		const newer = transitions();
		cross( () => undefined, false, false );
		older.finish();
		await Promise.resolve();
		await Promise.resolve();
		expect(
			page.classList.contains( 'cbuild-crossing' ),
			'the newer'
		).toBe( true );
		newer.finish();
		await Promise.resolve();
		await Promise.resolve();
		expect( page.classList.contains( 'cbuild-crossing' ) ).toBe( false );

		// Cut short, the transition still takes its mark away when it ends.
		const skipped = transitions();
		cross( () => undefined, true, false );
		cut();
		skipped.finish();
		await Promise.resolve();
		await Promise.resolve();
		expect( page.classList.contains( 'cbuild-crossing' ), 'cut' ).toBe(
			false
		);
	} );

	it( 'leaves the page’s root unmarked where the browser has no view transitions', () => {
		cross( () => undefined, true, false );
		expect(
			document.documentElement.classList.contains( 'cbuild-crossing' )
		).toBe( false );
	} );

	it( 'changes at once where the browser has no view transitions', () => {
		const change = vi.fn();
		cross( change, true, false );
		expect( change ).toHaveBeenCalledTimes( 1 );
		expect(
			document.documentElement.classList.contains( 'cbuild-back' )
		).toBe( false );
	} );

	it( 'forgets a transition once it has finished, and an older one finishing keeps the newer', async () => {
		const first = transitions();
		cross( () => undefined, true, false );
		const skips: Array< ReturnType< typeof vi.fn > > = [];
		Object.defineProperty( document, 'startViewTransition', {
			value: () => {
				const skipTransition = vi.fn();
				skips.push( skipTransition );
				return {
					finished: new Promise( () => undefined ),
					skipTransition,
				};
			},
			configurable: true,
		} );
		cross( () => undefined, true, false );
		first.finish();
		await Promise.resolve();
		await Promise.resolve();
		cut();
		expect( skips[ 0 ] ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'skips nothing once the transition it started has finished', async () => {
		const skip = vi.fn();
		let finish: () => void = () => undefined;
		Object.defineProperty( document, 'startViewTransition', {
			value: () => ( {
				finished: new Promise< void >( ( done ) => {
					finish = done;
				} ),
				skipTransition: skip,
			} ),
			configurable: true,
		} );
		cross( () => undefined, true, false );
		finish();
		await Promise.resolve();
		await Promise.resolve();
		cut();
		expect( skip ).not.toHaveBeenCalled();
	} );
} );

describe( 'carted', () => {
	function placed(
		element: Element,
		left: number,
		top: number,
		width: number,
		height: number
	): void {
		element.getBoundingClientRect = () =>
			( { left, top, width, height } ) as DOMRect;
	}

	it( 'shrinks the board toward the Mini-Cart’s button, for 400 ms', () => {
		const played: Played[] = [];
		const board = animated(
			document.body.appendChild( document.createElement( 'div' ) ),
			played
		);
		const cart = document.body.appendChild(
			document.createElement( 'button' )
		);
		cart.className = 'wc-block-mini-cart__button';
		placed( board, 100, 200, 200, 400 );
		placed( cart, 900, 10, 40, 40 );
		carted( board, false );
		expect( played[ 0 ]!.frames ).toEqual( [
			{ opacity: 1, transform: 'none' },
			{ opacity: 0, transform: 'translate(720px, -370px) scale(0.1)' },
		] );
		expect( played[ 0 ]!.options.duration ).toBe( 400 );
		expect( played[ 0 ]!.animation.id ).toBe( 'cbuild-cart' );
	} );

	it( 'fades the board in place with no Mini-Cart, or one not shown, and leaves it where it is calm', () => {
		const played: Played[] = [];
		const board = animated(
			document.body.appendChild( document.createElement( 'div' ) ),
			played
		);
		placed( board, 0, 0, 100, 100 );
		carted( board, false );
		const cart = document.body.appendChild(
			document.createElement( 'button' )
		);
		cart.className = 'wc-block-mini-cart__button';
		placed( cart, 0, 0, 0, 0 );
		carted( board, false );
		expect( played.map( ( { frames } ) => frames ) ).toEqual( [
			[ { opacity: 1 }, { opacity: 0.2 }, { opacity: 1 } ],
			[ { opacity: 1 }, { opacity: 0.2 }, { opacity: 1 } ],
		] );
		carted( board, true );
		carted( null, false );
		expect( played ).toHaveLength( 2 );
	} );
} );
