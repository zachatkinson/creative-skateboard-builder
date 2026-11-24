import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, Page, SITE } from './support/test';

/**
 * The builder's look on the three themes of the spec's Quality bar, light and dark (docs/AUDIT.md: visual snapshots):
 * Twenty Twenty-Five, a block theme; Storefront, WooCommerce's own classic theme; and Kadence. Each test asks for its
 * theme and colours by cookie, which the e2e theme mu-plugin answers for that request alone, so the site's own theme
 * stays the default the other specs expect (D-162).
 *
 * The builder's own box is pictured, the theme's ground behind it included, at a desktop, a tablet and a phone width, on a
 * blank start and on a full build's review. Motion is off, so no picture is taken halfway through a transition.
 *
 * Only `bash tools/visual.sh` runs these, in Playwright's Linux container: the baselines are that container's fonts
 * and Chromium. A baseline changes only on purpose, with `bash tools/visual.sh --update-snapshots`.
 */

const THEMES = [ 'twentytwentyfive', 'storefront', 'kadence' ] as const;
const SCHEMES = [ 'light', 'dark' ] as const;
const WIDTHS = [
	{ name: 'desktop', width: 1280, height: 900 },
	// Milestone 7's plan: the picture at three widths. A tablet held upright, at 48rem, where the phone's bar gives way.
	{ name: 'tablet', width: 768, height: 1024 },
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
 * @param on     The page.
 * @param width  The screen's width.
 * @param height The screen's height.
 */
async function still( on: Page, width: number, height: number ) {
	await expect( on.locator( '.cbuild' ) ).toHaveAttribute(
		'aria-busy',
		'false'
	);
	// The stage and the layout are sized by the screen's height (spec: The layout), so before the screen grows to the
	// page's height they keep, in pixels, the heights they have on the screen the test is about.
	await on.setViewportSize( { width, height } );
	await on.evaluate( () => {
		for ( const piece of document.querySelectorAll< HTMLElement >(
			'.cbuild__stage, .cbuild__layout'
		) ) {
			piece.style.blockSize = `${
				piece.getBoundingClientRect().height
			}px`;
		}
	} );
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
				const url = baseURL || SITE;
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
					await still( on, size.width, size.height );
					const builder = on.locator( '.cbuild' );
					await expect( builder ).toHaveScreenshot(
						`${ theme }-${ scheme }-${ size.name }-start.png`,
						{
							animations: 'disabled',
							caret: 'hide',
							// A ready build's picture is composed by a job of its own (D-252), so a tile shows it or its
							// deck's photo as the job has run or not: CI's fresh site and a used one drew Cruiser's
							// differently (PR #71's first visual run). The tile's frame, words and button stay compared.
							mask: [ on.locator( '.cbuild-preset__photo' ) ],
						}
					);

					await on
						.locator( '.cbuild-presets' )
						.getByRole( 'button', { name: 'Start from Street' } )
						.click();
					await expect(
						on.getByRole( 'heading', {
							level: 2,
							name: 'Step 7 of 7: Check your build',
						} )
					).toBeVisible();
					// The pointer off the builder: left where Start from Street was, it was over the bar's "Show the
					// parts" once the review was drawn, and the picture had its hover or not by when the page settled.
					// Moved before the page settles, not after: moved just before the shot, the link's hover was once
					// still leaving it, and its words were drawn a shade off (158 pixels, Kadence dark, tablet).
					await on.mouse.move( 0, 0 );
					await still( on, size.width, size.height );
					await expect( builder ).toHaveScreenshot(
						`${ theme }-${ scheme }-${ size.name }-review.png`,
						{
							animations: 'disabled',
							caret: 'hide',
							// The link names the parts by their IDs, which are the site's own: a baseline made on one site
							// failed on CI's where the field is wide enough to show them (CI run 43531285881, Kadence).
							mask: [ on.locator( '.cbuild-share__link' ) ],
						}
					);
				} );
			}
		} );
	}
}

// Cowork's 0.8.0 review, F1 (#53): the demo's dark Kadence, its "contrast" preset dark too, with a deck card in focus
// from the keyboard. The ring's two tones are seen on the near-black page (focus.spec.ts measures them).
test( 'a deck card in focus on the demo’s dark Kadence, desktop', async ( {
	page: on,
	context,
	baseURL,
} ) => {
	const url = baseURL || SITE;
	await context.addCookies( [
		{ name: 'cbuild_e2e_theme', value: 'kadence', url },
		{ name: 'cbuild_e2e_scheme', value: 'demo-dark', url },
	] );
	await on.setViewportSize( { width: 1280, height: 900 } );
	await on.emulateMedia( { colorScheme: 'dark', reducedMotion: 'reduce' } );
	// The deck step lists its cards once a width is picked (spec: The deck's width first).
	const widthed = new URL( page() );
	widthed.searchParams.set( 'cbuild[_width]', '8.25in' );
	await on.goto( widthed.toString() );
	await still( on, 1280, 900 );
	for ( let i = 0; i < 120; i++ ) {
		await on.keyboard.press( 'Tab' );
		if (
			await on.evaluate(
				() =>
					document.documentElement.ownerDocument.activeElement?.matches(
						'.cbuild-card__control'
					) ?? false
			)
		) {
			break;
		}
	}
	// The card and the ring around it: a picture of the card's own box would leave out what is drawn outside it.
	const box = await on
		.locator( '.cbuild-card:has(> .cbuild-card__control:focus-visible)' )
		.boundingBox();
	expect( box ).not.toBeNull();
	if ( box ) {
		await expect( on ).toHaveScreenshot(
			'kadence-demo-dark-desktop-card-focus.png',
			{
				animations: 'disabled',
				caret: 'hide',
				clip: {
					x: box.x - 12,
					y: box.y - 12,
					width: box.width + 24,
					height: box.height + 24,
				},
			}
		);
	}
} );

// Cowork's 0.8.0 review, F2 (#54): on a phone, with the deck picked and the trucks' step shown, the bar gives the
// estimate under its amount, as the summary does on a wider screen. Kadence, light and dark.
for ( const scheme of SCHEMES ) {
	test( `the phone bar with an estimate on Kadence, ${ scheme }`, async ( {
		page: on,
		context,
		baseURL,
	} ) => {
		const url = baseURL || SITE;
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: 'kadence', url },
			{ name: 'cbuild_e2e_scheme', value: scheme, url },
		] );
		const fixture = JSON.parse(
			readFileSync(
				path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
				'utf8'
			)
		) as { parts: Record< string, number > };
		await on.setViewportSize( { width: 390, height: 844 } );
		await on.emulateMedia( {
			colorScheme: scheme,
			reducedMotion: 'reduce',
		} );
		const link = new URL( page() );
		link.searchParams.set(
			'cbuild[deck]',
			String( fixture.parts[ 'builder-test-deck-8-25' ] )
		);
		link.searchParams.set( 'cbuild[step]', 'trucks' );
		await on.goto( link.toString() );
		await expect( on.locator( '.cbuild-bar__estimate' ) ).toBeVisible();
		await still( on, 390, 844 );
		await expect( on.locator( '.cbuild-bar' ) ).toHaveScreenshot(
			`kadence-${ scheme }-phone-bar-estimate.png`,
			{ animations: 'disabled', caret: 'hide' }
		);
	} );
}

// Cowork's 1.0.0 review, F11 (#96): the deck step with a width picked on Kadence, whose plain rules for a fieldset,
// a button and a heading once outranked the builder's resets. The width line, the brand chips and the cards are each a
// fieldset, the step's heading is under Back to the shop, and on a phone Filters waits above the folded chips.
for ( const size of [ WIDTHS[ 0 ], WIDTHS[ 2 ] ] ) {
	test( `the deck step with a width picked on Kadence, light, ${ size.name }`, async ( {
		page: on,
		context,
		baseURL,
	} ) => {
		const url = baseURL || 'http://localhost:8898';
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: 'kadence', url },
			{ name: 'cbuild_e2e_scheme', value: 'light', url },
		] );
		await on.setViewportSize( { width: size.width, height: size.height } );
		await on.emulateMedia( {
			colorScheme: 'light',
			reducedMotion: 'reduce',
		} );
		const link = new URL( page() );
		link.searchParams.set( 'cbuild[_width]', '8in' );
		await on.goto( link.toString() );
		await expect( on.locator( '.cbuild-step__fieldset' ) ).toBeVisible();
		await still( on, size.width, size.height );
		await expect( on.locator( '.cbuild' ) ).toHaveScreenshot(
			`kadence-light-${ size.name }-deck.png`,
			{ animations: 'disabled', caret: 'hide' }
		);
	} );
}
