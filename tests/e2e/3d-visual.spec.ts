import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, Page, SITE } from './support/test';

/**
 * Milestone 14: the 3D view's canvas at its fixed places, top, underside, three-quarter and the graphic close-up, on the
 * scene contract's fixture with its graphic and grip (tests/e2e/mu-plugins/cbuild-e2e-scene.php). Reduced motion puts
 * the camera there at once. Only `bash tools/visual.sh` runs these, in Playwright's Linux container, where Chromium
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

test( 'the 3D view at its fixed places', async ( { page, context } ) => {
	await context.addCookies( [
		{ name: 'cbuild_e2e_scene', value: 'pinned', url: SITE },
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
	await page.emulateMedia( { reducedMotion: 'reduce' } );
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
