import { test as base } from '@playwright/test';
import type { Page } from '@playwright/test';

export { expect } from '@playwright/test';
export type { Locator, Page } from '@playwright/test';

/**
 * Playwright's test, with every request to the shop under test marked `X-Creative-Test: 1`, as the agency's other
 * plugins mark theirs, so a run against a real shop (WP_BASE_URL) is told apart from shoppers. Only the shop's own
 * origin gets the header: on a cross-origin request (a theme's web fonts) it would force a CORS preflight.
 */
export const test = base.extend( {
	context: async ( { context, baseURL }, provide ) => {
		const origin = new URL( baseURL || 'http://localhost:8898' ).origin;
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
} );

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
	await Promise.all( [ page.waitForEvent( 'load' ), send() ] );
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
