/**
 * Updates the plugin as a shop does from the upload screen: Plugins › Add New › Upload Plugin, the zip, then "Replace
 * current with uploaded" (Cowork's M7 review, F3; D-208), then opens the Plugins screen. The pages WordPress answers
 * with are printed, so the caller can look for the result and for a critical error.
 *
 *   node tools/upgrade/upload.mjs <the zip> [the site, http://localhost:<CBUILD_PORT, else 8898>]
 */
import { chromium } from '@playwright/test';

const [
	zip,
	site = `http://localhost:${ process.env.CBUILD_PORT || '8898' }`,
] = process.argv.slice( 2 );
if ( ! zip ) {
	process.stderr.write( 'Usage: node tools/upgrade/upload.mjs <the zip> [the site]\n' );
	process.exit( 2 );
}

const browser = await chromium.launch();
try {
	const context = await browser.newContext( { baseURL: site } );
	const page = await context.newPage();
	page.setDefaultTimeout( 60_000 );

	// WordPress refuses a login from a client that did not take its test cookie, which the form's own page sets.
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.WP_ADMIN_USER || 'admin' );
	await page.fill( '#user_pass', process.env.WP_ADMIN_PASS || 'password' );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

	await page.goto( '/wp-admin/plugin-install.php?tab=upload' );
	await page.setInputFiles( '#pluginzip', zip );
	await Promise.all( [ page.waitForURL( /update\.php\?action=upload-plugin/ ), page.click( '#install-plugin-submit' ) ] );
	await page.waitForSelector( 'a.update-from-upload-overwrite' );
	// The second request is the one that replaces the files and deletes the uploaded zip.
	await Promise.all( [ page.waitForURL( /overwrite=update-plugin/ ), page.click( 'a.update-from-upload-overwrite' ) ] );
	await page.waitForLoadState( 'load' );
	// The whole page: a fatal error stops WordPress's output part way, with its critical error message at the foot.
	process.stdout.write( ( await page.locator( 'body' ).innerText() ) + '\n' );
	// The shop's next page, the Plugins screen: the first admin request of the new release, which runs its migrations.
	await page.goto( '/wp-admin/plugins.php' );
	process.stdout.write( ( await page.locator( 'body' ).innerText() ) + '\n' );
} finally {
	await browser.close();
}
