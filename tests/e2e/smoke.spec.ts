import { test, expect, Page } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * Milestone 1 has no screen of its own, so the smoke test proves the plugin is active, raises no console error on
 * the shop or in wp-admin, loads nothing on a shop page (spec: Performance, "Nothing loads on pages without a
 * builder") and that its row on the Plugins screen is axe-clean. The axe helper is the one the block (milestone 6)
 * and the admin tabs (milestone 4) will use on every state.
 */

// WooCommerce's own dashboard widgets load over admin-ajax after login; WebKit reports those requests as console
// errors ("due to access control checks"). They are not the plugin's and are left out of the count.
const NOT_OURS = /admin-ajax\.php\?action=woocommerce_/;

function collectConsoleErrors( page: Page ): string[] {
	const errors: string[] = [];
	page.on( 'console', ( message ) => {
		if ( message.type() === 'error' && ! NOT_OURS.test( message.text() ) ) {
			errors.push( message.text() );
		}
	} );
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	// The console says a resource failed without saying which; the response does.
	page.on( 'response', ( response ) => {
		if ( response.status() >= 400 ) {
			errors.push( `${ response.status() } ${ response.url() }` );
		}
	} );
	page.on( 'requestfailed', ( request ) => {
		if ( request.url().includes( '/creative-skateboard-builder/' ) ) {
			errors.push( `failed ${ request.url() }` );
		}
	} );
	return errors;
}

function collectPluginRequests( page: Page ): string[] {
	const requests: string[] = [];
	page.on( 'request', ( request ) => {
		if (
			request.url().includes( '/plugins/creative-skateboard-builder/' )
		) {
			requests.push( request.url() );
		}
	} );
	return requests;
}

test.describe( 'Creative Skateboard Builder, milestone 1', () => {
	test( 'the plugin is active and its row on the Plugins screen is axe-clean', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await login( page );
		// Arrive first, then listen and reload: leaving the dashboard cancels WooCommerce's widget requests, which
		// WebKit reports as page errors of the screen being left.
		// Each page settles before it is left, so no request of its own is cancelled and reported as an error too.
		await page.goto( '/wp-admin/plugins.php' );
		await page.waitForLoadState( 'networkidle' );
		const errors = collectConsoleErrors( page );
		await page.reload();
		await page.waitForLoadState( 'networkidle' );
		const row = page.locator(
			'tr[data-slug="creative-skateboard-builder"]'
		);
		await expect( row ).toBeVisible();
		await expect( row ).toHaveClass( /active/ );
		// By core's id, which is the same in every language.
		await expect(
			row.locator( '#deactivate-creative-skateboard-builder' )
		).toBeVisible();
		// Each row carries core's own hidden notice for the auto-update toggle; an error a person sees is visible.
		await expect( page.locator( '.notice-error:visible' ) ).toHaveCount(
			0
		);
		// Core's "View details" link reads "More information about …" to a screen reader: WordPress's markup.
		// axe is a script in the page, so it cannot run in the project with scripts off; the others run it.
		if ( javaScriptEnabled ) {
			await axe(
				page,
				'plugins-row',
				'tr[data-slug="creative-skateboard-builder"]',
				[],
				// Also core's: the "Visit plugin site" link, whose label and text differ in the fr_CA and he_IL
				// language packs (label-content-name-mismatch). The plugin supplies the address, not the words.
				[
					'.open-plugin-details-modal',
					'.plugin-version-author-uri a[aria-label]',
				]
			);
		}
		expect( errors, errors.join( '\n' ) ).toEqual( [] );
	} );

	// axe is not run on these: the plugin adds no element to them (asserted below), and what axe finds there is
	// WooCommerce's Mini-Cart drawer and the theme's navigation list (D-035).
	// The shop's own pages, as a visitor sees them (tests/e2e/fixtures/shop.php opens the shop and makes the product).
	const SHOP_PAGES = [
		[ 'the shop', '/?post_type=product' ],
		[ 'a product', '/?product=smoke-test-deck' ],
		[ 'the cart', '/?pagename=cart' ],
		[ 'the checkout', '/?pagename=checkout' ],
	] as const;

	for ( const [ name, path ] of SHOP_PAGES ) {
		test( `${ name } loads nothing of the plugin and raises no console error`, async ( {
			page,
		} ) => {
			const errors = collectConsoleErrors( page );
			const requests = collectPluginRequests( page );
			const response = await page.goto( path );
			expect( response?.status() ).toBe( 200 );
			await expect( page.locator( 'body' ) ).toBeVisible();
			await expect(
				page.locator( 'meta[name="woo-coming-soon-page"]' )
			).toHaveCount( 0 );
			await page.waitForLoadState( 'networkidle' );
			const head = await page.locator( 'head' ).innerHTML();
			expect( head ).not.toContain( 'cbuild' );
			// By element, since wp-env titles the site after the folder, which is the plugin's slug.
			await expect(
				page.locator(
					'head [id*="creative-skateboard-builder"], head [href*="/creative-skateboard-builder/"], head [src*="/creative-skateboard-builder/"]'
				)
			).toHaveCount( 0 );
			expect( requests, requests.join( '\n' ) ).toEqual( [] );
			expect( errors, errors.join( '\n' ) ).toEqual( [] );
		} );
	}
} );
