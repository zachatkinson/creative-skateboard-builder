import type { Page, Route } from '@playwright/test';

/**
 * A press measured as the demo answers it (D-406). On the demo the state route answers 0.9 to 1.3 s after a press,
 * past the 500 ms after an input that CLS leaves out, so whatever the answer moves counts. The local sites answer
 * within that window, and F30 (Cowork's review of 1.2.0) slipped past every layout-shift test that way. These hold the
 * answer, put a theme's header above the builder where a test asks, and read the shifts CLS counts that the builder
 * takes part in.
 */

/** A layout shift CLS counts: one with no recent input. */
export interface Shift {
	/** When, in the page's milliseconds. */
	at: number;
	value: number;
	/** Each node moved, as its first class and its top before and after. */
	sources: string[];
	/** Whether a node moved is the builder or inside it. */
	builder: boolean;
}

/** The shifts read after a press. */
export interface Measured {
	/** The builder's own shifts, summed: those with a node of the builder among their sources. */
	counted: number;
	/** Those shifts. */
	shifts: Shift[];
	/** Shifts of the page's own, outside the builder, which are not counted. */
	outside: Shift[];
	/** The shifts in words, for an annotation or a message. */
	said: string;
}

/** The answer's JSON, as a test may change it before the page has it. */
export type Answer = Record< string, unknown >;

/** Answers held, and how to let them go. */
export interface Held {
	/** Let the answers through again. */
	release: () => Promise< void >;
	/** Wait until no answer is held: a press the store waits for without saying it is busy, as Next asked ahead. */
	drained: () => Promise< void >;
}

/**
 * Hold each answer of the state route for `delay` ms, as the demo's host takes. `edit` may change an answer's JSON
 * before the page has it.
 *
 * @param page          The page.
 * @param options       How long, and what to change.
 * @param options.delay The hold, in ms: 1 s by default, the middle of the demo's 0.9 to 1.3 s.
 * @param options.edit  A change to each answer's JSON.
 */
export async function holdAnswers(
	page: Page,
	{
		delay = 1000,
		edit,
	}: { delay?: number; edit?: ( answer: Answer ) => void } = {}
): Promise< Held > {
	const state = ( url: URL ) => url.pathname.endsWith( '/state' );
	let holding = 0;
	const pass = async ( route: Route ) => {
		const answer = await route.fetch().catch( () => null );
		await new Promise( ( done ) => setTimeout( done, delay ) );
		if ( ! answer ) {
			return;
		}
		if ( ! edit ) {
			await route.fulfill( { response: answer } ).catch( () => null );
			return;
		}
		const json = ( await answer.json() ) as Answer;
		edit( json );
		await route.fulfill( { response: answer, json } ).catch( () => null );
	};
	const hold = async ( route: Route ) => {
		holding++;
		try {
			await pass( route );
		} finally {
			holding--;
		}
	};
	await page.route( state, hold );
	return {
		release: () => page.unroute( state, hold ),
		drained: async () => {
			while ( holding > 0 ) {
				await new Promise( ( done ) => setTimeout( done, 50 ) );
			}
		},
	};
}

/**
 * Put a theme's header of `height` px just above the builder, and scroll the page to its top, as a shop's page opens:
 * on the demo, Kadence's header puts the builder's rail at 129 px (F31).
 *
 * @param page   The page.
 * @param height The header's height, in CSS pixels.
 */
export async function themeHeader(
	page: Page,
	height: number
): Promise< void > {
	await page.locator( '.cbuild' ).evaluate( ( builder, tall ) => {
		const header = document.createElement( 'div' );
		header.className = 'cbuild-e2e-header';
		header.style.blockSize = `${ tall }px`;
		builder.before( header );
		header.scrollIntoView( { block: 'start' } );
	}, height );
}

/**
 * Start reading the layout shifts CLS counts, those with no recent input, from now on.
 *
 * @param page The page.
 */
export async function watchShifts( page: Page ): Promise< void > {
	await page.evaluate( () => {
		const seen = window as unknown as { cbuildShifts: unknown[] };
		seen.cbuildShifts = [];
		new PerformanceObserver( ( list ) => {
			for ( const entry of list.getEntries() as unknown as {
				value: number;
				hadRecentInput: boolean;
				startTime: number;
				sources?: {
					node?: Node | null;
					previousRect: DOMRectReadOnly;
					currentRect: DOMRectReadOnly;
				}[];
			}[] ) {
				if ( entry.hadRecentInput ) {
					continue;
				}
				const sources = entry.sources ?? [];
				seen.cbuildShifts.push( {
					at: entry.startTime,
					value: entry.value,
					builder: sources.some( ( source ) => {
						const node = source.node;
						const element =
							node instanceof Element
								? node
								: ( node?.parentElement ?? null );
						return null !== element?.closest( '.cbuild' );
					} ),
					sources: sources.map(
						( source ) =>
							`${
								source.node instanceof Element
									? String( source.node.className ).split(
											' '
										)[ 0 ]
									: ( source.node?.nodeName ?? '?' )
							} ${ Math.round(
								source.previousRect.y
							) }→${ Math.round( source.currentRect.y ) }`
					),
				} );
			}
		} ).observe( { type: 'layout-shift' } );
	} );
}

/**
 * The page's clock, to measure a press from.
 *
 * @param page The page.
 */
export function now( page: Page ): Promise< number > {
	return page.evaluate( () => performance.now() );
}

/**
 * The shifts read since `from`, once the page has drawn two more frames for the observer to report.
 *
 * @param page The page.
 * @param from The page's time the press was made at.
 */
export async function shiftsSince(
	page: Page,
	from: number
): Promise< Measured > {
	await page.evaluate(
		() =>
			new Promise( ( done ) =>
				requestAnimationFrame( () => requestAnimationFrame( done ) )
			)
	);
	const all = await page.evaluate(
		( since ) =>
			(
				window as unknown as { cbuildShifts: Shift[] }
			 ).cbuildShifts.filter( ( shift ) => shift.at >= since ),
		from
	);
	const shifts = all.filter( ( shift ) => shift.builder );
	const outside = all.filter( ( shift ) => ! shift.builder );
	const words = ( list: Shift[] ) =>
		list
			.map(
				( shift ) =>
					`+${ Math.round( shift.at - from ) } ms ${ shift.value.toFixed(
						3
					) } [${ shift.sources.join( '; ' ) }]`
			)
			.join( ' | ' );
	const counted = shifts.reduce( ( sum, shift ) => sum + shift.value, 0 );
	return {
		counted,
		shifts,
		outside,
		said: `${ counted.toFixed( 3 ) } ${ words( shifts ) }${
			outside.length
				? ` (outside the builder, not counted: ${ words( outside ) })`
				: ''
		}`,
	};
}
