import { execSync } from 'node:child_process';

/**
 * Once before every spec: the plugin active on the browser site with the default theme. Milestone 6 adds the shop
 * fixture (a kit and its parts) here.
 */
export default function globalSetup() {
	const run = ( command: string ) =>
		execSync( `npx wp-env run cli -- wp ${ command }`, {
			stdio: [ 'ignore', 'ignore', 'inherit' ],
		} );
	run( 'plugin activate creative-skateboard-builder' );
	run( 'theme activate twentytwentyfive' );
}
