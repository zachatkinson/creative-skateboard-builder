import { test as base } from '@playwright/test';

export { expect } from '@playwright/test';
export type { Page } from '@playwright/test';

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
