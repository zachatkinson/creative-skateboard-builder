import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { request } from '@playwright/test';
import type { FullConfig } from '@playwright/test';
import { SESSION } from './login';

/**
 * Once before every spec: the plugin active on the browser site with the default theme. The shop
 * fixture opens the shop to visitors and makes one product; milestone 6 adds a kit and its parts to it.
 *
 * Then the administrator is logged in once, over HTTP, and the session's cookies are kept for `login()` to give to
 * each test. No spec tests wp-login.php, and typing into it before every admin test was about four seconds of each
 * on CI's runner: some five of the newest leg's billed minutes (D-085).
 *
 * @param config Playwright's configuration, for the site's address.
 */
export default async function globalSetup( config: FullConfig ) {
	const run = ( command: string ) =>
		execSync( `npx wp-env run cli -- wp ${ command }`, {
			stdio: [ 'ignore', 'ignore', 'inherit' ],
		} );
	// Activate only what is not active: WP-CLI warns about the rest, and a warning nobody needs hides one that matters.
	const ensure = ( kind: 'plugin' | 'theme', slug: string ) =>
		execSync(
			`npx wp-env run cli -- sh -c "wp ${ kind } is-active ${ slug } || wp ${ kind } activate ${ slug }"`,
			{ stdio: [ 'ignore', 'ignore', 'inherit' ] }
		);
	ensure( 'plugin', 'creative-skateboard-builder' );
	ensure( 'theme', 'twentytwentyfive' );
	run(
		'eval-file wp-content/plugins/creative-skateboard-builder/tests/e2e/fixtures/shop.php'
	);

	const baseURL =
		config.projects[ 0 ]?.use.baseURL || 'http://localhost:8898';
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
