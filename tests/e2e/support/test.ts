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
	await page.waitForFunction(
		() =>
			! document.querySelector( '.cbuild' ) ||
			! ( 'jQuery' in window ) ||
			null !== document.querySelector( '.cbuild[data-ready]' ),
		undefined,
		{ timeout: 15000 }
	);
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
