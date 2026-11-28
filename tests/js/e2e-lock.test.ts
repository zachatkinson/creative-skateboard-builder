// @vitest-environment node
import { spawn, spawnSync } from 'node:child_process';
import {
	existsSync,
	mkdirSync,
	mkdtempSync,
	readFileSync,
	rmSync,
	statSync,
	utimesSync,
	writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clear, withLock } from '../e2e/support/lock';

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

	// #172: twenty processes find a dead holder's lock at once, so several judge it abandoned together. One clears it
	// and another takes the lock; a second clearer, judging the lock it saw, must not take the new holder's.
	it( "keeps every key when twenty processes find a dead holder's lock at once", async () => {
		for ( let round = 0; round < 5; round++ ) {
			rmSync( path.join( dir, 'cache.json' ), { force: true } );
			const gone = spawnSync( process.execPath, [ '-e', '' ] ).pid;
			mkdirSync( path.join( dir, 'lock' ) );
			writeFileSync(
				path.join( dir, 'lock', 'owner' ),
				`${ gone } token`
			);
			const keys = Array.from(
				{ length: 20 },
				( _, i ) => `key${ String( i ).padStart( 2, '0' ) }`
			);
			const codes = await Promise.all( keys.map( writer ) );

			expect( codes ).toEqual( keys.map( () => 0 ) );
			expect(
				Object.keys(
					JSON.parse(
						readFileSync( path.join( dir, 'cache.json' ), 'utf8' )
					)
				).sort(),
				`round ${ round }`
			).toEqual( keys );
		}
	}, 120_000 );

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

	// #172: a holder that is slow, not dead, keeps its lock whatever its age; only a dead one's is taken.
	it( 'leaves a lock older than its limit to a holder still running, and gives up', () => {
		mkdirSync( path.join( dir, 'lock' ) );
		writeFileSync(
			path.join( dir, 'lock', 'owner' ),
			`${ process.ppid } token`
		);
		const old = new Date( Date.now() - 1000 );
		utimesSync( path.join( dir, 'lock' ), old, old );

		let ran = false;
		expect( () =>
			withLock(
				path.join( dir, 'lock' ),
				() => {
					ran = true;
				},
				100
			)
		).toThrow( `still held by ${ process.ppid } token` );
		expect( ran ).toBe( false );
		expect(
			readFileSync( path.join( dir, 'lock', 'owner' ), 'utf8' )
		).toBe( `${ process.ppid } token` );
	} );

	it( 'takes a lock with no holder written once it is older than its limit', () => {
		mkdirSync( path.join( dir, 'lock' ) );
		const old = new Date( Date.now() - 1000 );
		utimesSync( path.join( dir, 'lock' ), old, old );

		expect( withLock( path.join( dir, 'lock' ), () => 'ran', 0 ) ).toBe(
			'ran'
		);
	} );

	// #155: a lock's age was read at one millisecond and the wait given up at the next, so a lock made within the
	// millisecond the wait began (its time has a fraction, the clock's reading none) was judged too young, then the
	// wait was over. The clock is set here to cross that millisecond between the two readings.
	it( 'takes a lock with no holder older than its limit when the clock crosses a millisecond between judging it and giving up', () => {
		mkdirSync( path.join( dir, 'lock' ) );
		const at = Math.floor( Date.now() ) + 5000;
		utimesSync(
			path.join( dir, 'lock' ),
			( at + 0.7 ) / 1000,
			( at + 0.7 ) / 1000
		);
		const readings = [ at, at ];
		const clock = vi
			.spyOn( Date, 'now' )
			.mockImplementation( () => readings.shift() ?? at + 1 );
		try {
			expect( withLock( path.join( dir, 'lock' ), () => 'ran', 0 ) ).toBe(
				'ran'
			);
		} finally {
			clock.mockRestore();
		}
	} );

	// #172: a lock judged abandoned, then let go and taken by another before it is cleared. The clearer must leave
	// the new holder's lock where it is: set aside and put back, it was missing for a moment, and a third process
	// could take it then.
	it( 'leaves in place a lock another took after it was judged abandoned', () => {
		const lock = path.join( dir, 'lock' );
		mkdirSync( lock );
		writeFileSync( path.join( lock, 'owner' ), `${ process.ppid } new` );
		const before = statSync( lock, { bigint: true } );

		clear( lock, '1 dead' );

		const after = statSync( lock, { bigint: true } );
		expect( readFileSync( path.join( lock, 'owner' ), 'utf8' ) ).toBe(
			`${ process.ppid } new`
		);
		expect( after.ino ).toBe( before.ino );
		expect( after.ctimeNs, 'never moved' ).toBe( before.ctimeNs );
		expect( existsSync( `${ lock }.breaking` ) ).toBe( false );
	} );

	it( "clears a dead holder's lock", () => {
		const lock = path.join( dir, 'lock' );
		const dead = `${ spawnSync( process.execPath, [ '-e', '' ] ).pid } dead`;
		mkdirSync( lock );
		writeFileSync( path.join( lock, 'owner' ), dead );

		clear( lock, dead );

		expect( existsSync( lock ) ).toBe( false );
		expect( existsSync( `${ lock }.breaking` ) ).toBe( false );
	} );

	it( 'leaves the lock to another clearer at work', () => {
		const lock = path.join( dir, 'lock' );
		const dead = `${ spawnSync( process.execPath, [ '-e', '' ] ).pid } dead`;
		mkdirSync( lock );
		writeFileSync( path.join( lock, 'owner' ), dead );
		mkdirSync( `${ lock }.breaking` );

		clear( lock, dead );

		expect( existsSync( lock ) ).toBe( true );
		expect( existsSync( `${ lock }.breaking` ) ).toBe( true );
	} );

	it( 'leaves a fresh lock with no holder written yet, though one with none was judged abandoned', () => {
		const lock = path.join( dir, 'lock' );
		mkdirSync( lock );

		clear( lock, '', 60_000 );

		expect( existsSync( lock ) ).toBe( true );
	} );

	it( 'takes the clearing over from a clearer that died at it', () => {
		const lock = path.join( dir, 'lock' );
		const dead = `${ spawnSync( process.execPath, [ '-e', '' ] ).pid } dead`;
		mkdirSync( lock );
		writeFileSync( path.join( lock, 'owner' ), dead );
		mkdirSync( `${ lock }.breaking` );
		const old = new Date( Date.now() - 60_000 );
		utimesSync( `${ lock }.breaking`, old, old );

		clear( lock, dead );
		clear( lock, dead );

		expect( existsSync( lock ) ).toBe( false );
		expect( existsSync( `${ lock }.breaking` ) ).toBe( false );
	} );

	it( 'waits while a holder still running has it, past its limit, and takes it once let go', async () => {
		const lock = path.join( dir, 'lock' );
		// Another process takes the lock, holds it 600 ms, past the limit of 500 and short of the 1,000 the wait gives up
		// at, and lets it go.
		const child = spawn( process.execPath, [
			'--disable-warning=MODULE_TYPELESS_PACKAGE_JSON',
			'--input-type=module',
			'-e',
			`const { withLock } = await import( ${ JSON.stringify( LOCK ) } );
			withLock( ${ JSON.stringify( lock ) }, () => Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, 600 ), 200 );`,
		] );
		const exited = new Promise( ( done ) => child.on( 'exit', done ) );
		await vi.waitFor( () => {
			expect(
				existsSync( path.join( lock, 'owner' ) ),
				'the other process holds it'
			).toBe( true );
		} );

		const started = Date.now();
		expect( withLock( lock, () => 'ran', 500 ) ).toBe( 'ran' );
		expect( Date.now() - started ).toBeGreaterThanOrEqual( 300 );
		await exited;
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
