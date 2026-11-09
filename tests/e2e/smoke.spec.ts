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
	} ) => {
		await login( page );
		const errors = collectConsoleErrors( page );
		await page.goto( '/wp-admin/plugins.php' );
		const row = page.locator(
			'tr[data-slug="creative-skateboard-builder"]'
		);
		await expect( row ).toBeVisible();
		await expect( row ).toHaveClass( /active/ );
		await expect(
			row.getByRole( 'link', {
				name: 'Deactivate Creative Skateboard Builder',
			} )
		).toBeVisible();
		// Each row carries core's own hidden notice for the auto-update toggle; an error a person sees is visible.
		await expect( page.locator( '.notice-error:visible' ) ).toHaveCount(
			0
		);
		// Core's "View details" link reads "More information about …" to a screen reader: WordPress's markup.
		await axe(
			page,
			'plugins-row',
			'tr[data-slug="creative-skateboard-builder"]',
			[],
			[ '.open-plugin-details-modal' ]
		);
		expect( errors, errors.join( '\n' ) ).toEqual( [] );
	} );

	test( 'a shop page loads nothing of the plugin and raises no console error', async ( {
		page,
	} ) => {
		const errors = collectConsoleErrors( page );
		const requests = collectPluginRequests( page );
		const response = await page.goto( '/?post_type=product' );
		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).toBeVisible();
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
} );
