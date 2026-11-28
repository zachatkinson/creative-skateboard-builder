/**
 * The gate on the WordPress packages the admin bundles (D-553): none may reach into a private API of a script
 * WordPress provides, since such an API can go in any release, as components' did under `@wordpress/dataviews 12.0.1`.
 * The one allowed reach is `@wordpress/ui`'s ThemeProvider fallback, taken only where wp-theme has no public
 * ThemeProvider: WordPress 7.0, whose code no longer changes.
 */
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
// @ts-expect-error The gate is a plain module of the tools.
import { reaches } from '../../tools/private-apis.mjs';

let root: string;

/**
 * Writes a package's built module.
 *
 * @param pkg  The package, as `@wordpress/<pkg>`.
 * @param file The module's path in build-module.
 * @param code Its code.
 */
function module( pkg: string, file: string, code: string ): void {
	const dir = join( root, '@wordpress', pkg, 'build-module' );
	mkdirSync( join( dir, file, '..' ), { recursive: true } );
	writeFileSync( join( dir, file ), code );
}

beforeEach( () => {
	root = mkdtempSync( join( tmpdir(), 'private-apis-' ) );
	module(
		'dataviews',
		'index.mjs',
		'import { Button } from "@wordpress/components";'
	);
	module(
		'ui',
		'lock-unlock.mjs',
		'const { lock, unlock } = __dangerousOptInToUnstableAPIsOnlyForCoreModules( "I acknowledge", "@wordpress/ui" );'
	);
	module(
		'ui',
		'utils/theme-provider.mjs',
		'return unlock(themePackage.privateApis).ThemeProvider;'
	);
	module( 'ui', 'button.mjs', 'export const Button = 1;' );
} );

afterEach( () => {
	rmSync( root, { recursive: true, force: true } );
} );

describe( 'the private APIs the bundled packages reach', () => {
	it( 'finds none in DataViews, and only the ThemeProvider fallback in @wordpress/ui', () => {
		expect( reaches( root ) ).toEqual( [] );
	} );

	it( "finds DataViews' unlock of a script's private APIs, as 12.0.1's", () => {
		module(
			'dataviews',
			'components/dataviews-item-actions/index.mjs',
			'const { Menu, kebabCase } = unlock( componentsPrivateApis );'
		);
		expect( reaches( root ) ).toEqual( [
			'@wordpress/dataviews/build-module/components/dataviews-item-actions/index.mjs: const { Menu, kebabCase } = unlock( componentsPrivateApis );',
		] );
	} );

	it( 'finds a bundled package opting in under a name of its own', () => {
		module(
			'dataviews',
			'lock-unlock.mjs',
			'const { unlock } = __dangerousOptInToUnstableAPIsOnlyForCoreModules( "I acknowledge", "@wordpress/dataviews" );'
		);
		expect( reaches( root ) ).toHaveLength( 1 );
	} );

	it( '@wordpress/ui unlocking anything but the theme fallback', () => {
		module(
			'ui',
			'menu.mjs',
			'const { Menu } = unlock( componentsPrivateApis );'
		);
		expect( reaches( root ) ).toEqual( [
			'@wordpress/ui/build-module/menu.mjs: const { Menu } = unlock( componentsPrivateApis );',
		] );
	} );

	it( 'leaves source maps alone, and fails when a package is missing, as an install that never ran', () => {
		module(
			'dataviews',
			'index.mjs.map',
			'unlock( componentsPrivateApis )'
		);
		expect( reaches( root ) ).toEqual( [] );
		rmSync( join( root, '@wordpress', 'ui' ), { recursive: true } );
		expect( () => reaches( root ) ).toThrow( '@wordpress/ui' );
	} );
} );
