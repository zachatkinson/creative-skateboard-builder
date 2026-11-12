import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, Page } from './support/test';

/**
 * The builder's look on the three themes of the spec's Quality bar, light and dark (docs/AUDIT.md: visual snapshots):
 * Twenty Twenty-Five, a block theme; Storefront, WooCommerce's own classic theme; and Kadence. Each test asks for its
 * theme and colours by cookie, which the e2e theme mu-plugin answers for that request alone, so the site's own theme
 * stays the default the other specs expect (D-162).
 *
 * The builder's own box is pictured, the theme's ground behind it included, at a desktop and a phone width, on a
 * blank start and on a full build's review. Motion is off, so no picture is taken halfway through a transition.
 *
 * Only `bash tools/visual.sh` runs these, in Playwright's Linux container: the baselines are that container's fonts
 * and Chromium. A baseline changes only on purpose, with `bash tools/visual.sh --update-snapshots`.
 */

const THEMES = [ 'twentytwentyfive', 'storefront', 'kadence' ] as const;
const SCHEMES = [ 'light', 'dark' ] as const;
const WIDTHS = [
	{ name: 'desktop', width: 1280, height: 900 },
	{ name: 'phone', width: 390, height: 844 },
] as const;

const page = (): string =>
	(
		JSON.parse(
			readFileSync(
				path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
				'utf8'
			)
		) as { page: string }
	 ).page;

/**
 * Wait until the builder is still: its fonts loaded, its photos decoded, and no answer pending.
 *
 * @param on    The page.
 * @param width The screen's width.
 */
async function still( on: Page, width: number ) {
	await expect( on.locator( '.cbuild' ) ).toHaveAttribute(
		'aria-busy',
		'false'
	);
	// The whole page in one screen: a picture of a box taller than the screen is taken in pieces, and a theme's fixed
	// bar is drawn into the middle of it. A fixed bar then sits at the page's foot, below the builder.
	await on.setViewportSize( {
		width,
		height: await on.evaluate(
			() => document.documentElement.scrollHeight
		),
	} );
	await on.evaluate( async () => {
		await document.fonts.ready;
		await Promise.all(
			[
				...document.querySelectorAll< HTMLImageElement >(
					'.cbuild img'
				),
			]
				.filter( ( image ) => ! image.hidden && image.src )
				.map( ( image ) => {
					image.loading = 'eager';
					return image.decode().catch( () => undefined );
				} )
		);
	} );
}

for ( const theme of THEMES ) {
	for ( const scheme of SCHEMES ) {
		test.describe( `On ${ theme }, ${ scheme }`, () => {
			test.beforeEach( async ( { context, baseURL } ) => {
				const url = baseURL || 'http://localhost:8898';
				await context.addCookies( [
					{ name: 'cbuild_e2e_theme', value: theme, url },
					{ name: 'cbuild_e2e_scheme', value: scheme, url },
				] );
			} );

			for ( const size of WIDTHS ) {
				test( `the builder at its start and at the review, ${ size.name }`, async ( {
					page: on,
				} ) => {
					await on.setViewportSize( {
						width: size.width,
						height: size.height,
					} );
					await on.emulateMedia( {
						colorScheme: scheme,
						reducedMotion: 'reduce',
					} );
					await on.goto( page() );
					await still( on, size.width );
					const builder = on.locator( '.cbuild' );
					await expect( builder ).toHaveScreenshot(
						`${ theme }-${ scheme }-${ size.name }-start.png`,
						{ animations: 'disabled', caret: 'hide' }
					);

					await on
						.locator( '.cbuild-presets' )
						.getByRole( 'button', { name: 'Start from Street' } )
						.click();
					await expect(
						on.getByRole( 'heading', {
							level: 2,
							name: 'Step 7 of 7: Review',
						} )
					).toBeVisible();
					await still( on, size.width );
					await expect( builder ).toHaveScreenshot(
						`${ theme }-${ scheme }-${ size.name }-review.png`,
						{ animations: 'disabled', caret: 'hide' }
					);
				} );
			}
		} );
	}
}
