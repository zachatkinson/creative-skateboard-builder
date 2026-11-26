// @vitest-environment node
import { spawn, spawnSync } from 'node:child_process';
import {
	existsSync,
	mkdirSync,
	mkdtempSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { withLock } from '../e2e/support/lock';

/**
 * The browser tests' lock around wp-env (D-422). wp-env's own cache is read, changed and written back by every
 * `wp-env run`, so processes at once lose each other's keys; here each process does the same to a file of its own,
 * with a pause between the read and the write, and every key must be there after.
 */

const LOCK = pathToFileURL(
	path.resolve( __dirname, '../e2e/support/lock.ts' )
).href;

let dir = '';

beforeEach( () => {
	dir = mkdtempSync( path.join( tmpdir(), 'cbuild-lock-' ) );
} );

afterEach( () => {
	rmSync( dir, { recursive: true, force: true } );
} );

/**
 * One process that, under the lock, reads the file, waits, adds its key and writes the file back, as wp-env's
 * setCache() does.
 *
 * @param key The key it adds.
 */
function writer( key: string ): Promise< number | null > {
	const script = `
		const { withLock } = await import( ${ JSON.stringify( LOCK ) } );
		const fs = await import( 'node:fs' );
		const file = ${ JSON.stringify( path.join( dir, 'cache.json' ) ) };
		withLock( ${ JSON.stringify( path.join( dir, 'lock' ) ) }, () => {
			let cache = {};
			try { cache = JSON.parse( fs.readFileSync( file, 'utf8' ) ); } catch {}
			Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, 30 );
			cache[ ${ JSON.stringify( key ) } ] = true;
			fs.writeFileSync( file, JSON.stringify( cache ) );
		} );
	`;
	return new Promise( ( resolve ) => {
		spawn(
			process.execPath,
			[
				'--disable-warning=MODULE_TYPELESS_PACKAGE_JSON',
				'--input-type=module',
				'-e',
				script,
			],
			{
				stdio: 'inherit',
			}
		).on( 'exit', resolve );
	} );
}

describe( 'the lock around wp-env', () => {
	it( 'keeps every key when ten processes rewrite one file at once', async () => {
		const keys = Array.from( { length: 10 }, ( _, i ) => `key${ i }` );
		const codes = await Promise.all( keys.map( writer ) );

		expect( codes ).toEqual( keys.map( () => 0 ) );
		expect(
			Object.keys(
				JSON.parse(
					readFileSync( path.join( dir, 'cache.json' ), 'utf8' )
				)
			).sort()
		).toEqual( keys );
		expect( existsSync( path.join( dir, 'lock' ) ) ).toBe( false );
	}, 30_000 );

	it( 'takes a lock whose holder has died', () => {
		const gone = spawnSync( process.execPath, [ '-e', '' ] ).pid;
		mkdirSync( path.join( dir, 'lock' ) );
		writeFileSync( path.join( dir, 'lock', 'owner' ), `${ gone } token` );

		const started = Date.now();
		expect( withLock( path.join( dir, 'lock' ), () => 'ran' ) ).toBe(
			'ran'
		);
		expect( Date.now() - started ).toBeLessThan( 1000 );
	} );

	it( 'takes a lock older than its limit from a holder still running', () => {
		mkdirSync( path.join( dir, 'lock' ) );
		writeFileSync(
			path.join( dir, 'lock', 'owner' ),
			`${ process.ppid } token`
		);

		expect( withLock( path.join( dir, 'lock' ), () => 'ran', 0 ) ).toBe(
			'ran'
		);
	} );

	it( 'waits while a holder still running has it', () => {
		mkdirSync( path.join( dir, 'lock' ) );
		writeFileSync(
			path.join( dir, 'lock', 'owner' ),
			`${ process.ppid } token`
		);

		const started = Date.now();
		expect( withLock( path.join( dir, 'lock' ), () => 'ran', 400 ) ).toBe(
			'ran'
		);
		expect( Date.now() - started ).toBeGreaterThanOrEqual( 300 );
	} );

	it( 'lets the lock go when what it runs throws', () => {
		expect( () =>
			withLock( path.join( dir, 'lock' ), () => {
				throw new Error( 'inside' );
			} )
		).toThrow( 'inside' );
		expect( existsSync( path.join( dir, 'lock' ) ) ).toBe( false );
	} );
} );
