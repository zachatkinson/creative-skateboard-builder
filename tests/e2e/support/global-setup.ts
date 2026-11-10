import { execSync } from 'node:child_process';

/**
 * Once before every spec: the plugin active on the browser site with the default theme. The shop
 * fixture opens the shop to visitors and makes one product; milestone 6 adds a kit and its parts to it.
 */
export default function globalSetup() {
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
}
