import { expect, Page } from '@playwright/test';

const USER = process.env.WP_ADMIN_USER || 'admin';
const PASS = process.env.WP_ADMIN_PASS || 'password';

/**
 * Logs in as the administrator through wp-login.php; shared by every spec that opens the admin.
 *
 * wp-login.php focuses and selects the username field from a timer a moment after it loads. On a slow runner that
 * timer can fire between the two fills and the password lands in the wrong field (Creative Search's CI on WordPress
 * 7.0, 23 October 2026), so this waits for the page's own focus, then checks both fields before it submits.
 *
 * @param page The page.
 */
export async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	const user = page.locator( '#user_login' );
	const pass = page.locator( '#user_pass' );
	await expect( user )
		.toBeFocused( { timeout: 3000 } )
		.catch( () => {} );
	await expect( async () => {
		await user.fill( USER );
		await pass.fill( PASS );
		await expect( user ).toHaveValue( USER, { timeout: 500 } );
		await expect( pass ).toHaveValue( PASS, { timeout: 500 } );
	} ).toPass( { timeout: 15_000 } );
	await Promise.all( [
		page.waitForURL( /wp-admin/ ),
		page.click( '#wp-submit' ),
	] );
}
