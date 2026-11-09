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
	run( 'plugin activate creative-skateboard-builder' );
	run( 'theme activate twentytwentyfive' );
	run(
		'eval-file wp-content/plugins/creative-skateboard-builder/tests/e2e/fixtures/shop.php'
	);
}
