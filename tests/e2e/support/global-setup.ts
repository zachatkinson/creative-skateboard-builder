import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { request } from '@playwright/test';
import type { FullConfig } from '@playwright/test';
import { locked } from './cli';
import { SESSION } from './login';
import { SITE } from './site';

/**
 * Once before every spec: the plugin active on the browser site with the default theme (tools/e2e-site.sh). The shop
 * fixture opens the shop to visitors and makes one product; milestone 6 adds a kit and its parts to it.
 *
 * Then the administrator is logged in once, over HTTP, and the session's cookies are kept for `login()` to give to
 * each test. No spec tests wp-login.php, and typing into it before every admin test was about four seconds of each
 * on CI's runner: some five of the newest leg's billed minutes (D-085).
 *
 * @param config Playwright's configuration, for the site's address.
 */
export default async function globalSetup( config: FullConfig ) {
	// Inside tools/visual.sh's container there is no wp-env: the script has made the site ready on the host.
	if ( '1' !== process.env.CBUILD_E2E_SITE_READY ) {
		// Under the tests' lock on wp-env (D-422), as another run of the tests in this checkout may be calling it.
		locked( 'bash tools/e2e-site.sh' );
	}

	const baseURL = config.projects[ 0 ]?.use.baseURL || SITE;
	const api = await request.newContext( {
		baseURL,
		extraHTTPHeaders: { 'x-creative-test': '1' },
	} );
	// WordPress refuses a login from a client that did not take its test cookie, which the form's own page sets.
	await api.get( '/wp-login.php' );
	const answer = await api.post( '/wp-login.php', {
		form: {
			log: process.env.WP_ADMIN_USER || 'admin',
			pwd: process.env.WP_ADMIN_PASS || 'password',
			'wp-submit': 'Log In',
			redirect_to: '/wp-admin/',
			testcookie: '1',
		},
	} );
	if ( ! answer.ok() || ! answer.url().includes( '/wp-admin/' ) ) {
		throw new Error(
			`The administrator could not log in: ${ answer.status() } at ${ answer.url() }`
		);
	}
	mkdirSync( path.dirname( SESSION ), { recursive: true } );
	await api.storageState( { path: SESSION } );
	await api.dispose();
}
