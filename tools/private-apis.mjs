/**
 * The gate on the WordPress packages the admin bundles (D-553), in npm run check: none may reach into a private API
 * of a script WordPress provides, since such an API can go in any release. `@wordpress/dataviews 12.0.1` took Menu and
 * kebabCase from wp-components' private APIs, which WordPress 7.2 removes.
 *
 * The one allowed reach is `@wordpress/ui`'s ThemeProvider fallback (utils/theme-provider.mjs, with the opt-in in
 * lock-unlock.mjs it needs): taken only where wp-theme exports no ThemeProvider, which is WordPress 7.0, whose code no
 * longer changes. From 7.1 wp-theme exports it.
 *
 * Run: node tools/private-apis.mjs
 */
import { readdirSync, readFileSync, statSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

/** The bundled packages the admin's script holds, and the modules of each allowed a private reach. */
const PACKAGES = {
	dataviews: [],
	ui: [ 'lock-unlock.mjs', 'utils/theme-provider.mjs' ],
};

const REACH = /unlock\s*\(|__dangerousOptInToUnstableAPIsOnlyForCoreModules/;

/**
 * Every built module under a folder.
 *
 * @param {string} dir The folder.
 * @return {string[]} Their paths.
 */
function modules( dir ) {
	return readdirSync( dir ).flatMap( ( entry ) => {
		const path = join( dir, entry );
		if ( statSync( path ).isDirectory() ) {
			return modules( path );
		}
		return /\.(mjs|js)$/.test( entry ) ? [ path ] : [];
	} );
}

/**
 * Each line of a bundled package that reaches a private API it is not allowed, as "<path>: <line>".
 *
 * @param {string} root node_modules.
 * @return {string[]} The reaches.
 * @throws {Error} A package that is not installed.
 */
export function reaches( root ) {
	const found = [];
	for ( const [ pkg, allowed ] of Object.entries( PACKAGES ) ) {
		const dir = join( root, '@wordpress', pkg, 'build-module' );
		if ( ! existsSync( dir ) ) {
			throw new Error(
				`@wordpress/${ pkg } is not installed: run npm ci.`
			);
		}
		for ( const file of modules( dir ) ) {
			const inside = relative( dir, file ).split( '\\' ).join( '/' );
			if ( allowed.includes( inside ) ) {
				continue;
			}
			for ( const line of readFileSync( file, 'utf8' ).split( '\n' ) ) {
				if ( REACH.test( line ) ) {
					found.push(
						`${ relative( root, file ).split( '\\' ).join( '/' ) }: ${ line.trim() }`
					);
				}
			}
		}
	}
	return found;
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const found = reaches( 'node_modules' );
	if ( found.length > 0 ) {
		console.log(
			'A bundled WordPress package reaches a private API (D-553):'
		);
		console.log( found.join( '\n' ) );
		process.exit( 1 );
	}
	console.log(
		'Bundled packages: no private API reached but the allowed one.'
	);
}
