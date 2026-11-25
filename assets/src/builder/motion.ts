/**
 * The motion (spec: Milestone 11, the motion; D-256, D-305). One module decides, for every moment, whether it moves,
 * fades or changes at once, and cuts any moment short when the shopper acts again.
 *
 * - **Moves** where the shopper allows motion and the kit's Motion is playful: the board's parts, through the Web
 *   Animations API on the server's own elements, `transform` and `opacity` alone.
 * - **Fades**, 150 ms or less and opacity alone, under `prefers-reduced-motion` or on subtle (D-256).
 * - **Changes at once** where the browser animates nothing.
 *
 * Nothing is built here: each moment animates an element the templates drew. Nothing is stored: the shopper's
 * preference is read from the media query each time.
 */

/**
 * Each moment's time in milliseconds, from the spec's table.
 */
export const TIMES = {
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
	outline: 600,
} as const;

export type Moment = keyof typeof TIMES;

/**
 * A fade's time, under reduced motion or subtle: 150 ms or less (D-256).
 */
export const FADE = 150;

/**
 * How far apart each truck, and each wheel, starts.
 */
export const STAGGER = { trucks: 60, wheels: 40 } as const;

/**
 * The builder's easing: quick to start and slow to settle.
 */
const EASE = 'cubic-bezier(0.2, 0, 0, 1)';

/**
 * A layer of the board, as the view gives it.
 */
export type Layer = { key: string; src?: string; style?: string };

/**
 * A moment for one layer: which, on which layer by its place in the list, and after how long.
 */
export type Cue = { moment: Moment; index: number; delay: number };

/**
 * The moment of a part new to the board, by its slot. A part of another slot settles as a swap does.
 */
const BY_SLOT: Partial<
	Record< string, 'deck' | 'trucks' | 'wheels' | 'grip' >
> = {
	deck: 'deck',
	trucks: 'trucks',
	wheels: 'wheels',
	grip: 'grip',
};

/**
 * Whether a moment only fades: the shopper's device asks for reduced motion, or the kit's Motion is subtle.
 *
 * @param root The builder.
 */
export function calm( root: Element | null ): boolean {
	return (
		true === root?.classList.contains( 'cbuild--subtle' ) ||
		true ===
			window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches
	);
}

/**
 * The moments running, and the view transition, to cut short.
 */
const running = new Set< Animation >();
let crossing: { skipTransition: () => void } | undefined;
let counting: ( () => void ) | undefined;

/**
 * Each calm outline shown, by its layer: the timer that fades it.
 */
const held = new Map< Element, number >();

/**
 * Cut every running moment short at its end: a new pick, a press, or an answer that came.
 */
export function cut(): void {
	for ( const animation of [ ...running ] ) {
		animation.finish();
	}
	running.clear();
	crossing?.skipTransition();
	crossing = undefined;
	counting?.();
	counting = undefined;
	for ( const [ layer, timer ] of held ) {
		window.clearTimeout( timer );
		layer.removeAttribute( 'data-outlined' );
	}
	held.clear();
}

/**
 * Play a moment on one element: its frames, or a fade where the moment is calm. An element the browser cannot
 * animate changes at once.
 *
 * @param element The element.
 * @param moment  The moment.
 * @param frames  Its frames, `transform` and `opacity` alone.
 * @param quiet   Whether it only fades.
 * @param delay   How long it waits to start, in milliseconds.
 * @param fade    The fade where it is calm: in, out, or none for a moment that only moves.
 * @param pseudo  The element's pseudo-element it plays on, such as `::after`, where it is not the element itself.
 */
export function play(
	element: Element,
	moment: Moment,
	frames: Keyframe[],
	quiet: boolean,
	delay = 0,
	fade: 'in' | 'out' | 'none' = 'in',
	pseudo?: string
): Animation | undefined {
	if (
		'function' !== typeof element.animate ||
		( quiet && 'none' === fade )
	) {
		return undefined;
	}
	const faded = 'in' === fade ? [ 0, 1 ] : [ 1, 0 ];
	const animation = element.animate(
		quiet ? faded.map( ( opacity ) => ( { opacity } ) ) : frames,
		{
			duration: quiet ? FADE : TIMES[ moment ],
			delay: quiet ? 0 : delay,
			easing: quiet ? 'linear' : EASE,
			fill: 'backwards',
			...( pseudo ? { pseudoElement: pseudo } : {} ),
		}
	);
	animation.id = `cbuild-${ moment }`;
	running.add( animation );
	const done = () => running.delete( animation );
	animation.finished.then( done, done );
	return animation;
}

/**
 * The moments a new board shows: each part new to it, from the deck up, and each swapped for another. A part the
 * board no longer has is closed over by the stage's photos (see `close()`), since its element is gone.
 *
 * @param before The board's layers before.
 * @param after  Its layers now.
 */
export function cues( before: Layer[], after: Layer[] ): Cue[] {
	const was = new Map( before.map( ( layer ) => [ layer.key, layer.src ] ) );
	const counted: Partial< Record< Moment, number > > = {};
	const found: Cue[] = [];
	after.forEach( ( layer, index ) => {
		if ( was.has( layer.key ) ) {
			if ( was.get( layer.key ) !== layer.src ) {
				found.push( { moment: 'swapped', index, delay: 0 } );
			}
			return;
		}
		const moment = BY_SLOT[ layer.key.replace( /-\d+$/, '' ) ];
		if ( ! moment ) {
			found.push( { moment: 'swapped', index, delay: 0 } );
			return;
		}
		const seen = counted[ moment ] ?? 0;
		counted[ moment ] = seen + 1;
		const stagger =
			'trucks' === moment || 'wheels' === moment ? STAGGER[ moment ] : 0;
		found.push( { moment, index, delay: seen * stagger } );
	} );
	return found;
}

/**
 * Which side of the board a layer stands on, from its place across it: -1 on the left, 1 on the right.
 *
 * @param layer The layer.
 */
export function side( layer: Layer ): number {
	const x = /--cbuild-layer-x:(-?[\d.]+)%/.exec( layer.style ?? '' );
	return x && Number( x[ 1 ] ) >= 50 ? 1 : -1;
}

/**
 * A moment's frames on a layer, `transform` and `opacity` alone.
 *
 * @param cue   The moment.
 * @param layer The layer it plays on.
 */
export function framesFor( cue: Cue, layer: Layer ): Keyframe[] {
	const shown = { opacity: 1, transform: 'none' };
	switch ( cue.moment ) {
		case 'deck':
			// The deck settles onto the stage from just above.
			return [
				{ opacity: 0, transform: 'translateY(-4%) scale(1.03)' },
				shown,
			];
		case 'trucks':
			// Onto its holes.
			return [ { opacity: 0, transform: 'translateY(-40%)' }, shown ];
		case 'wheels':
			// Onto its axle end, turning a quarter.
			return [
				{
					opacity: 0,
					transform: `translateX(${ side( layer ) * 60 }%) rotate(-90deg)`,
				},
				shown,
			];
		case 'grip':
			// From the nose to the tail: the stylesheet sets its origin at the nose.
			return [ { transform: 'scaleY(0)' }, { transform: 'none' } ];
		default:
			// The new part settles where the old one was.
			return [ { opacity: 0, transform: 'translateY(-8%)' }, shown ];
	}
}

/**
 * Play the board's moments on its drawn layers.
 *
 * @param board  The board's element.
 * @param before Its layers before.
 * @param after  Its layers now, in the order they are drawn.
 * @param quiet  Whether they only fade.
 */
export function build(
	board: Element | null,
	before: Layer[],
	after: Layer[],
	quiet: boolean
): void {
	const drawn = layersOf( board );
	for ( const cue of cues( before, after ) ) {
		const element = drawn[ cue.index ];
		const layer = after[ cue.index ];
		if ( element && layer ) {
			play(
				element,
				cue.moment,
				framesFor( cue, layer ),
				quiet,
				cue.delay
			);
		}
	}
}

/**
 * The board's drawn layers, in the order they are drawn: its children past the template that draws them.
 *
 * @param board The board's element.
 */
function layersOf( board: Element | null ): Element[] {
	return board
		? [ ...board.children ].filter(
				( child ) => 'TEMPLATE' !== child.tagName
			)
		: [];
}

/**
 * The outline's frames: drawn in from just inside the part's box, so it never reaches past it, held, and gone.
 */
const OUTLINE: Keyframe[] = [
	{ opacity: 0, transform: 'scale(0.94)' },
	{ opacity: 1, transform: 'none', offset: 0.25 },
	{ opacity: 1, transform: 'none', offset: 0.7 },
	{ opacity: 0, transform: 'none' },
];

/**
 * After a pick, the area of the board each part new to it or swapped stands on is outlined for 600 ms (#138; D-413),
 * so the eye finds what changed; the build's sentence says it in words. The outline is each layer's `::after`, which
 * the stylesheet draws over its box and hides: moved here by `transform` and `opacity` alone, so nothing is laid out
 * again. Where it is calm, the outline shows at once, by the layer's `data-outlined`, and fades out over the last
 * 150 ms: no movement, 600 ms in all. A part removed has no layer left to outline, and the photos closing over it say
 * it.
 *
 * @param board  The board's element.
 * @param before Its layers before.
 * @param after  Its layers now, in the order they are drawn.
 * @param quiet  Whether moments only fade.
 */
export function outline(
	board: Element | null,
	before: Layer[],
	after: Layer[],
	quiet: boolean
): void {
	const drawn = layersOf( board );
	for ( const cue of cues( before, after ) ) {
		const layer = drawn[ cue.index ];
		if ( ! layer || 'function' !== typeof layer.animate ) {
			continue;
		}
		if ( ! quiet ) {
			play( layer, 'outline', OUTLINE, false, 0, 'none', '::after' );
			continue;
		}
		window.clearTimeout( held.get( layer ) );
		layer.setAttribute( 'data-outlined', '' );
		held.set(
			layer,
			window.setTimeout( () => {
				held.delete( layer );
				layer.removeAttribute( 'data-outlined' );
				play( layer, 'outline', [], true, 0, 'out', '::after' );
			}, TIMES.outline - FADE )
		);
	}
}

/**
 * Where each of the stage's photos is, so a part removed can be closed over once the view is drawn.
 *
 * @param items The stage's photos.
 */
export function measured( items: Element[] ): Map< Element, DOMRect > {
	return new Map(
		items.map( ( item ) => [ item, item.getBoundingClientRect() ] )
	);
}

/**
 * A part removed: the stage's photos still shown slide from where they were into their new places.
 *
 * @param was   Where each photo was.
 * @param quiet Whether moments only fade: then the photos are put in their places at once.
 */
export function close( was: Map< Element, DOMRect >, quiet: boolean ): void {
	for ( const [ item, then ] of was ) {
		if ( ! item.isConnected ) {
			continue;
		}
		const now = item.getBoundingClientRect();
		const x = then.left - now.left;
		const y = then.top - now.top;
		if ( Math.abs( x ) >= 1 || Math.abs( y ) >= 1 ) {
			play(
				item,
				'removed',
				[
					{ transform: `translate(${ x }px, ${ y }px)` },
					{ transform: 'none' },
				],
				quiet,
				0,
				'none'
			);
		}
	}
}

/**
 * The shop's way of writing a price, as the route sends it.
 */
export type Currency = {
	symbol: string;
	decimals: number;
	decimal_separator: string;
	thousand_separator: string;
	format: string;
};

/**
 * An amount in minor units, written as `wc_price()` writes it, in plain text: for the counting total's in-between
 * frames alone (D-257). The last frame is the server's own string.
 *
 * @param minor    The amount in minor units.
 * @param currency The shop's way of writing it.
 */
export function money( minor: number, currency: Currency ): string {
	const decimals = Math.max( 0, Math.trunc( currency.decimals ) );
	const unit = 10 ** decimals;
	const whole = Math.floor( Math.abs( minor ) / unit );
	const part = Math.abs( minor ) % unit;
	const grouped = String( whole ).replace(
		/\B(?=(\d{3})+(?!\d))/g,
		currency.thousand_separator
	);
	const amount =
		decimals > 0
			? grouped +
				currency.decimal_separator +
				String( part ).padStart( decimals, '0' )
			: grouped;
	return (
		( minor < 0 ? '-' : '' ) +
		currency.format
			.replace( '%1$s', currency.symbol )
			.replace( '%2$s', amount )
	);
}

/**
 * Count a total from the old amount to the new: each frame written by `show`, and the last the server's own string.
 * Where moments only fade, or the browser has no frames, the total changes at once. Cut short, it lands at once.
 *
 * @param from     The amount before, in minor units.
 * @param to       The amount now.
 * @param last     The server's string for it.
 * @param currency The shop's way of writing it.
 * @param show     Writes a frame.
 * @param quiet    Whether moments only fade.
 */
export function count(
	from: number,
	to: number,
	last: string,
	currency: Currency,
	show: ( text: string ) => void,
	quiet: boolean
): void {
	counting?.();
	counting = undefined;
	if (
		quiet ||
		from === to ||
		'function' !== typeof window.requestAnimationFrame
	) {
		show( last );
		return;
	}
	let start: number | undefined;
	let frame = 0;
	const land = () => {
		window.cancelAnimationFrame( frame );
		counting = undefined;
		show( last );
	};
	const step = ( now: number ) => {
		start ??= now;
		const done = Math.min( 1, ( now - start ) / TIMES.total );
		if ( done >= 1 ) {
			land();
			return;
		}
		// Easing out, as the parts do.
		const eased = 1 - ( 1 - done ) ** 3;
		show( money( Math.round( from + ( to - from ) * eased ), currency ) );
		frame = window.requestAnimationFrame( step );
	};
	counting = land;
	show( money( from, currency ) );
	frame = window.requestAnimationFrame( step );
}

/**
 * Cross a step over with a view transition, the panel sliding the way the shopper is going; a fade where moments only
 * fade; at once where the browser has none. Firefox has no transition types, so the direction is a class on the
 * page's root for every browser (spec: Firefox).
 *
 * @param change  What changes the view.
 * @param forward Whether the shopper is going on, not back.
 * @param quiet   Whether moments only fade.
 */
export function cross(
	change: () => void,
	forward: boolean,
	quiet: boolean
): void {
	if ( 'function' !== typeof document.startViewTransition ) {
		change();
		return;
	}
	const page = document.documentElement;
	page.classList.toggle( 'cbuild-back', ! forward );
	page.classList.toggle( 'cbuild-calm', quiet );
	// The stylesheet's view-transition rules hold only while this mark is on, so a transition of the theme's or
	// another plugin's is left as its author made it.
	page.classList.add( 'cbuild-crossing' );
	const transition = document.startViewTransition( () => {
		change();
		return new Promise< void >( ( done ) => window.setTimeout( done ) );
	} );
	crossing = transition;
	const done = () => {
		// A newer transition keeps the mark; this one, the last or cut short, takes it away.
		if ( crossing === transition || undefined === crossing ) {
			crossing = undefined;
			page.classList.remove( 'cbuild-crossing' );
		}
	};
	transition.finished.then( done, done );
}

/**
 * Add to cart: the board shrinks toward the Mini-Cart's button where the page has one, and otherwise fades in place.
 * Where moments only fade, the Mini-Cart's count changing is the moment.
 *
 * @param board The board's element.
 * @param quiet Whether moments only fade.
 */
export function carted( board: Element | null, quiet: boolean ): void {
	if ( ! board ) {
		return;
	}
	const cart = document.querySelector( '.wc-block-mini-cart__button' );
	const from = board.getBoundingClientRect();
	const to = cart?.getBoundingClientRect();
	const frames: Keyframe[] =
		to && to.width > 0
			? [
					{ opacity: 1, transform: 'none' },
					{
						opacity: 0,
						transform: `translate(${
							to.left +
							to.width / 2 -
							( from.left + from.width / 2 )
						}px, ${
							to.top +
							to.height / 2 -
							( from.top + from.height / 2 )
						}px) scale(0.1)`,
					},
				]
			: [ { opacity: 1 }, { opacity: 0.2 }, { opacity: 1 } ];
	play( board, 'cart', frames, quiet, 0, 'none' );
}
