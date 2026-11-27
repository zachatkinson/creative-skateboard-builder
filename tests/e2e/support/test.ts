import { test as base } from '@playwright/test';
import type { Page } from '@playwright/test';
import { SITE } from './site';

export { expect } from '@playwright/test';
export type { Locator, Page } from '@playwright/test';
export { SITE } from './site';

/**
 * Playwright's test, with every request to the shop under test marked `X-Creative-Test: 1`, as the agency's other
 * plugins mark theirs, so a run against a real shop (WP_BASE_URL) is told apart from shoppers. Only the shop's own
 * origin gets the header: on a cross-origin request (a theme's web fonts) it would force a CORS preflight.
 */
export const test = base.extend( {
	context: async ( { context, baseURL }, provide ) => {
		const origin = new URL( baseURL || SITE ).origin;
		await context.route(
			( url ) => url.origin === origin,
			( route ) =>
				route.continue( {
					headers: {
						...route.request().headers(),
						'x-creative-test': '1',
					},
				} )
		);
		await provide( context );
	},
	// The builder's pieces are hydrated one region at a time (D-296), and in WebKit, which has no scheduler.yield,
	// that takes some 100 ms past the load: a press before then is sent as the page would send it without the script.
	// A page the store runs on waits for the builder's ready mark, set by its last region, as a shopper sees it settle.
	page: async ( { page, javaScriptEnabled }, provide ) => {
		if ( false !== javaScriptEnabled ) {
			await page.addInitScript( countStart );
			const ready = () => builderReady( page );
			for ( const name of [
				'goto',
				'reload',
				'goBack',
				'goForward',
			] as const ) {
				const move = page[ name ].bind( page ) as (
					...args: unknown[]
				) => Promise< unknown >;
				( page as unknown as Record< string, unknown > )[ name ] =
					async ( ...args: unknown[] ) => {
						const answer = await move( ...args );
						await ready();
						return answer;
					};
			}
		}
		await provide( page );
	},
} );

/**
 * Waits for the builder's ready mark, set once its last region is hydrated (D-296). A page with no builder has nothing
 * to wait for, nor one whose scripts don't run: the test site's pages load jQuery as a classic script in the head, so
 * it is missing exactly where JavaScript is off, and the mark never comes.
 *
 * @param page The page.
 */
export async function builderReady( page: Page ): Promise< void > {
	try {
		await page.waitForFunction(
			() =>
				! document.querySelector( '.cbuild' ) ||
				! ( 'jQuery' in window ) ||
				null !== document.querySelector( '.cbuild[data-ready]' ),
			undefined,
			{ timeout: 15000 }
		);
	} catch ( error ) {
		throw new Error(
			`${ String( error ) }\nThe builder's ready mark never came. Where the page's start stood (#93): ${ JSON.stringify(
				await startSeen( page )
			) }`,
			{ cause: error }
		);
	}
}

interface Start {
	contentLoaded: number;
	yields: number;
	resumed: number;
	errors: string[];
	marks: string[];
}

/**
 * Counts, from before any of the page's scripts run, what the Interactivity runtime's start waits on: the
 * DOMContentLoaded event, and each `scheduler.yield()` it hydrates between, asked and resumed (wp-includes'
 * interactivity module: `onDOMReady( hydrateRegions )`, `splitTask`). In Firefox the runtime once hydrated no region
 * at all, WooCommerce's Mini-Cart's included, with no error (#93); the counts say which wait it stopped in.
 * The wrapper calls the browser's own `yield()` and only counts its answer.
 */
function countStart() {
	const start: Start = {
		contentLoaded: -1,
		yields: 0,
		resumed: 0,
		errors: [],
		marks: [],
	};
	( window as unknown as { cbuildStart: Start } ).cbuildStart = start;
	// An error or a promise no one handled, a module's that failed to load among them, said where the trace may not.
	window.addEventListener(
		'error',
		( event: Event ) => {
			start.errors.push(
				event instanceof ErrorEvent
					? `${ event.message } (${ event.filename }:${ event.lineno })`
					: `${ ( event.target as Element | null )?.outerHTML?.slice( 0, 120 ) } failed to load`
			);
		},
		true
	);
	// What a script said went wrong without throwing: the Interactivity runtime logs a directive's error (#93).
	// eslint-disable-next-line no-console -- the diagnostic wraps it, and passes every message on.
	const said = console.error.bind( console );
	// eslint-disable-next-line no-console -- as above.
	console.error = ( ...args: unknown[] ) => {
		start.errors.push(
			`console: ${ args
				.map( ( arg ) =>
					arg instanceof Error
						? `${ arg.message } ${ arg.stack ?? '' }`
						: String( arg )
				)
				.join( ' ' )
				.slice( 0, 400 ) }`
		);
		said( ...args );
	};
	window.addEventListener( 'unhandledrejection', ( event ) => {
		start.errors.push( `unhandled: ${ String( event.reason ) }` );
	} );
	document.addEventListener( 'DOMContentLoaded', () => {
		start.contentLoaded = performance.now();
		// The ready mark's every change, and the bar's, with its time: set and taken away again, or never set (#93).
		new MutationObserver( ( changes ) => {
			for ( const change of changes ) {
				const element = change.target as Element;
				start.marks.push(
					`${ Math.round( performance.now() ) } ${
						element.className.split( ' ' )[ 0 ]
					} ${ change.attributeName } ${
						element.hasAttribute( change.attributeName! )
							? 'on'
							: 'off'
					}`
				);
			}
		} ).observe( document.body, {
			subtree: true,
			attributeFilter: [ 'data-ready' ],
		} );
	} );
	const scheduler = (
		window as unknown as { scheduler?: { yield?: () => Promise< void > } }
	 ).scheduler;
	const native = scheduler?.yield?.bind( scheduler );
	if ( scheduler && native ) {
		scheduler.yield = () => {
			start.yields++;
			return native().then( () => {
				start.resumed++;
			} );
		};
	}
}

/**
 * What a page that never set the ready mark had done: its counts and errors from `countStart()`, its load, its
 * regions, whether the runtime's module ran to its end (one of its last statements marks the history entry with
 * `wpInteractivityId`), when the runtime and the builder's script arrived, and whether a yield and a timer asked now
 * are answered within a second.
 *
 * @param page The page.
 */
async function startSeen( page: Page ): Promise< unknown > {
	return page
		.evaluate( async () => {
			const start = ( window as unknown as { cbuildStart?: Start } )
				.cbuildStart;
			const counted = start ? { ...start } : null;
			const [ navigation ] = performance.getEntriesByType(
				'navigation'
			) as PerformanceNavigationTiming[];
			const within = ( answer: Promise< unknown > ) =>
				Promise.race( [
					answer.then( () => 'answered' ),
					new Promise( ( done ) =>
						setTimeout( () => done( 'not within 1 s' ), 1000 )
					),
				] );
			const scheduler = (
				window as unknown as {
					scheduler?: { yield?: () => Promise< void > };
				}
			 ).scheduler;
			return {
				counted,
				runtimeRan:
					'object' === typeof window.history.state &&
					null !== window.history.state &&
					'wpInteractivityId' in window.history.state,
				scripts: performance
					.getEntriesByType( 'resource' )
					.filter( ( entry ) =>
						/interactivity\/index|builder\/view/.test( entry.name )
					)
					.map(
						( entry ) =>
							`${ new URL( entry.name ).pathname } ${ Math.round(
								entry.startTime
							) } to ${ Math.round(
								( entry as PerformanceResourceTiming )
									.responseEnd
							) } ms`
					),
				readyState: document.readyState,
				contentLoadedStart: navigation?.domContentLoadedEventStart,
				loadEnd: navigation?.loadEventEnd,
				regions: document.querySelectorAll( '[data-wp-interactive]' )
					.length,
				yieldNow: scheduler?.yield
					? await within( scheduler.yield() )
					: 'no scheduler.yield',
				timerNow: await within(
					new Promise( ( done ) => setTimeout( done, 0 ) )
				),
				frameNow: await within(
					new Promise( ( done ) => requestAnimationFrame( done ) )
				),
				// Whether any region's effects ran: callbacks.busy, a watch, sets aria-busy on the builder.
				busySet:
					document
						.querySelector( '.cbuild' )
						?.getAttribute( 'aria-busy' ) ?? 'none',
				bars: document.querySelectorAll( '.cbuild-bar' ).length,
				visible: document.visibilityState,
			};
		} )
		.catch( ( error: unknown ) => String( error ) );
}

/**
 * Waits until WordPress's emoji script is done with the page. Core asks a worker whether the browser draws emoji
 * itself, keeps the answer in sessionStorage, and where it does not (the test browsers, headless; Chrome on Windows,
 * for flags) adds twemoji and wp-emoji, often after the page's load; wp-emoji then reads the whole page in one task as
 * it loads, or on its 50 ms timer if twemoji came second (wp-emoji-loader.js, wp-emoji.js `load()`). At four times
 * slower on CI's runner that task fell on the first pick of the paint test (#49). The wait is for the answer, then for
 * both scripts where they are needed, then for the page to fetch nothing for half a second. The reads wp-emoji's
 * observer makes on each change after that stay in what is measured, as a shopper whose browser loads it has them.
 *
 * @param page The page.
 */
export async function emojiDone( page: Page ) {
	await page.waitForFunction(
		() => {
			const settings = (
				window as unknown as {
					_wpemojiSettings?: { supports?: { everything?: boolean } };
				}
			 )._wpemojiSettings;
			if ( ! settings?.supports ) {
				return true;
			}
			try {
				if (
					null === sessionStorage.getItem( 'wpEmojiSettingsSupports' )
				) {
					return false;
				}
			} catch {
				// No storage: the answer is not kept, and its settings are read as they stand.
			}
			return (
				false !== settings.supports.everything ||
				( 'twemoji' in window &&
					undefined !==
						( window as unknown as { wp?: { emoji?: unknown } } ).wp
							?.emoji )
			);
		},
		undefined,
		{ timeout: 15000 }
	);
	await settled( page );
}

/**
 * A number as the page writes it for the project's locale: French writes a decimal comma. The page formats a value
 * it shows with WordPress's `number_format_i18n()`, so the expectation follows the locale the project asks for.
 *
 * @param project The Playwright project's name.
 * @param text    Text holding a number written with a point.
 */
export function inLocale( project: string, text: string ): string {
	return project === 'fr-ca' ? text.replace( /(\d)\.(\d)/g, '$1,$2' ) : text;
}

/**
 * Does something that sends a form, and waits for the page that answers. The wait is for the next `load` event, set
 * before the form is sent: `waitForLoadState( 'load' )` is already true of the page the form is on, so it waited for
 * nothing, and a `goto` straight after could cancel the post before the server had it (CI, 10 November 2026:
 * settings.spec.ts on webkit-phone).
 *
 * @param page The page.
 * @param send What sends the form: a click, or Enter on its button.
 */
export async function answered( page: Page, send: () => Promise< void > ) {
	// The form's own navigation, waited for too, so a press that sends nothing fails here and says so, not later at the
	// next check, as it did under WebKit when Save build was pressed before the page's load (#50).
	await Promise.all( [
		page
			.waitForRequest( ( request ) => request.isNavigationRequest(), {
				timeout: 15000,
			} )
			.catch( () => {
				throw new Error(
					'The form sent nothing: no page was asked for within 15 s.'
				);
			} ),
		page.waitForEvent( 'load' ),
		send(),
	] );
	// The page that answered, with the store running on it where it runs at all.
	await builderReady( page );
}

/**
 * Waits until WooCommerce has finished putting focus on its notice. It does so twice: once as the page loads, and again
 * from a timer it sets 500 ms after DOMContentLoaded (woocommerce.js:243, focus_populate_live_region). Focus taken
 * between the two is taken back, and a key pressed then falls on the notice (CI run 44307517356). A timer set here to
 * end no sooner than WooCommerce's runs after it, as the HTML standard orders timers, so this waits for that one.
 *
 * @param page The page.
 */
export async function noticeFocusDone( page: Page ) {
	await page.waitForLoadState( 'domcontentloaded' );
	await page.evaluate(
		() =>
			new Promise< void >( ( resolve ) => {
				const [ entry ] = performance.getEntriesByType(
					'navigation'
				) as PerformanceNavigationTiming[];
				const due = ( entry?.domContentLoadedEventEnd ?? 0 ) + 500;
				setTimeout(
					resolve,
					Math.max( 0, Math.ceil( due - performance.now() ) )
				);
			} )
	);
}

/**
 * Waits until the page has loaded and has fetched nothing more for half a second, by the page's own resource timing.
 * It stands where `waitForLoadState( 'networkidle' )` stood: under Firefox that wait never ended on the cart and the
 * checkout, though every request had been answered within a second (issue #35, CI, 12 November 2026).
 *
 * @param page The page.
 */
export async function settled( page: Page ) {
	await page.waitForLoadState( 'load' );
	const fetched = () =>
		page.evaluate(
			() => performance.getEntriesByType( 'resource' ).length
		);
	let before = -1;
	let now = await fetched();
	while ( now !== before ) {
		before = now;
		await page.waitForTimeout( 500 );
		now = await fetched();
	}
}
