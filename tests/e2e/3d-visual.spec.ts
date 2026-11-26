import { readFileSync } from 'node:fs';
import path from 'node:path';
import type { BrowserContext } from '@playwright/test';
import { test, expect, Page, SITE } from './support/test';

/**
 * Milestone 14: the 3D view's canvas at its fixed places, top, underside, three-quarter and the graphic close-up; on the
 * dark scheme; and with a kickflip held in the air. Each is the scene contract's fixture with its graphic and grip,
 * pinned through `cbuild_scene` (tests/e2e/mu-plugins/cbuild-e2e-scene.php). Reduced motion puts the camera there at
 * once. Only `bash tools/visual.sh` runs these, in Playwright's Linux container, where Chromium
 * draws WebGL in software (SwiftShader): the same pixels on every run there, and pictures a Mac's GPU would not match.
 *
 * Tolerance: 1 % of the pixels, each by Playwright's default colour threshold. Antialiased edges move by a pixel between
 * SwiftShader builds; a part drawn wrong, or a camera in the wrong place, moves far more.
 */

const fixture = () =>
	JSON.parse(
		readFileSync(
			path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
			'utf8'
		)
	) as { parts: Record< string, number >; three: { page: string } };

/**
 * Wait until the view draws nothing more, read through its probe.
 *
 * @param page The page.
 */
async function still( page: Page ) {
	let last = -2;
	await expect
		.poll(
			async () => {
				const now = await page.evaluate(
					() =>
						(
							window as Window & {
								cbuildProbe?: {
									probe: () => { frames: number };
								};
							}
						 ).cbuildProbe?.probe().frames ?? -1
				);
				const same = now === last && now > 0;
				last = now;
				return same;
			},
			{ intervals: [ 600 ], timeout: 20_000 }
		)
		.toBe( true );
}

/**
 * Open the 3D view on the 3D kit's page, with the probe on and reduced motion, and wait until it is still.
 *
 * @param page    The page.
 * @param context The browser's context.
 * @param scheme  The site's colours, as the e2e theme plugin gives them by cookie.
 */
async function opened(
	page: Page,
	context: BrowserContext,
	scheme: 'light' | 'dark' = 'light'
) {
	await context.addCookies( [
		{ name: 'cbuild_e2e_scene', value: 'pinned', url: SITE },
		{ name: 'cbuild_e2e_scheme', value: scheme, url: SITE },
	] );
	await page.addInitScript( () => {
		const mark = () =>
			document.documentElement?.setAttribute( 'data-cbuild-probe', '' );
		if ( document.documentElement ) {
			mark();
		} else {
			document.addEventListener( 'readystatechange', mark, {
				once: true,
			} );
		}
	} );
	await page.emulateMedia( { reducedMotion: 'reduce', colorScheme: scheme } );
	await page.setViewportSize( { width: 1280, height: 900 } );
	const url = new URL( fixture().three.page );
	for ( const [ slot, sku ] of [
		[ 'deck', 'builder-test-deck-8-0' ],
		[ 'trucks', 'builder-test-trucks-139' ],
		[ 'wheels', 'builder-test-wheels-52' ],
	] as const ) {
		url.searchParams.set(
			`cbuild[${ slot }]`,
			String( fixture().parts[ sku ] )
		);
	}
	url.searchParams.set( 'cbuild[step]', 'wheels' );
	await page.goto( url.toString() );
	await page.getByRole( 'button', { name: 'View in 3D' } ).click();
	await still( page );
}

test( 'the 3D view at its fixed places', async ( { page, context } ) => {
	await opened( page, context );
	const frame = page.locator( '.cbuild-3d__frame' );
	const places = page.getByRole( 'group', { name: 'Views of the board' } );
	for ( const [ name, file ] of [
		[ 'Reset view', 'three-quarter' ],
		[ 'Top', 'top' ],
		[ 'Underside', 'underside' ],
		[ 'Graphic close-up', 'close-up' ],
	] as const ) {
		await places.getByRole( 'button', { name, exact: true } ).click();
		await still( page );
		await expect( frame ).toHaveScreenshot( `3d-${ file }.png`, {
			maxDiffPixelRatio: 0.01,
		} );
	}
} );

// 1.4.0: the view's ground is the builder's (D-500), so on the dark scheme the board is drawn on the dark ground.
test( 'the 3D view on the dark scheme', async ( { page, context } ) => {
	await opened( page, context, 'dark' );
	await expect( page.locator( '.cbuild-3d__frame' ) ).toHaveScreenshot(
		'3d-dark.png',
		{ maxDiffPixelRatio: 0.01 }
	);
} );

// 1.4.0: a kickflip held half over, through the probe, at the same moment on every run (D-502).
test( 'a kickflip in the air', async ( { page, context } ) => {
	await opened( page, context );
	await page.evaluate( () => {
		const probed = (
			window as Window & {
				cbuildProbe?: {
					hold: ( trick: unknown, progress: number ) => void;
					tricks: Record< string, unknown >;
				};
			}
		 ).cbuildProbe!;
		probed.hold( probed.tricks.kickflip, 510 / 1200 );
	} );
	await still( page );
	await expect( page.locator( '.cbuild-3d__frame' ) ).toHaveScreenshot(
		'3d-kickflip.png',
		{ maxDiffPixelRatio: 0.01 }
	);
} );
