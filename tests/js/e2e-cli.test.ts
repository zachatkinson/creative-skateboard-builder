// @vitest-environment node
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { retried } from '../e2e/support/cli';

/**
 * The browser tests' commands (D-575): every `wp-env run` loads wp-env's config, which asks api.wordpress.org for the
 * latest WordPress version even where the versions are pinned (@wordpress/env 12, lib/wordpress.js). A connection
 * dropped there fails the command before it runs, so such a failure is tried again, twice; any other is not.
 */

let dir = '';
let tries = '';

beforeEach( () => {
	dir = mkdtempSync( path.join( tmpdir(), 'cbuild-cli-' ) );
	tries = path.join( dir, 'tries' );
	writeFileSync( tries, '' );
} );

afterEach( () => {
	rmSync( dir, { recursive: true, force: true } );
} );

/**
 * A command that counts its tries, fails the first `failing` with what it is given on stderr, and prints `done` after.
 *
 * @param failing How many tries fail.
 * @param stderr  What a failed try prints on stderr.
 * @param stdout  What a failed try prints on stdout.
 */
function command( failing: number, stderr: string, stdout = '' ): string {
	return `printf x >> '${ tries }'; if [ "$(wc -c < '${ tries }')" -le ${ failing } ]; then printf '%s' '${ stdout }'; printf '%s\\n' '${ stderr }' >&2; exit 1; fi; echo done`;
}

const count = () => readFileSync( tries, 'utf8' ).length;

const dropped =
	'Error [ERR_SOCKET_CLOSED_BEFORE_CONNECTION]: Socket closed before the connection was established\n    at TLSSocket.onClose (node:net:1305:10)';

describe( 'retried', () => {
	it( 'tries a command again when wp-env lost its connection before running it, and gives what the try that worked printed', () => {
		expect( retried( command( 2, dropped ), [ 0, 0 ] ) ).toBe( 'done' );
		expect( count() ).toBe( 3 );
	} );

	it( 'gives up after three tries, saying so, with the last try’s words', () => {
		expect( () => retried( command( 9, dropped ), [ 0, 0 ] ) ).toThrow(
			/failed with exit code 1 after 3 tries\.\n.*ERR_SOCKET_CLOSED_BEFORE_CONNECTION/
		);
		expect( count() ).toBe( 3 );
	} );

	it( 'tries every other failure once', () => {
		expect( () =>
			retried(
				command( 1, 'Error: The site was never started.' ),
				[ 0, 0 ]
			)
		).toThrow(
			/failed with exit code 1\.\nError: The site was never started\./
		);
		expect( count() ).toBe( 1 );
	} );

	it( 'tries once a command that printed anything, since it ran', () => {
		expect( () =>
			retried( command( 1, dropped, 'Success: half of it' ), [ 0, 0 ] )
		).toThrow( /Success: half of it/ );
		expect( count() ).toBe( 1 );
	} );

	it( 'knows a reset, a timeout and a failed lookup as a lost connection too', () => {
		for ( const code of [
			'ECONNRESET',
			'ETIMEDOUT',
			'EAI_AGAIN',
			'socket hang up',
		] ) {
			writeFileSync( tries, '' );
			expect( retried( command( 1, `Error: ${ code }` ), [ 0 ] ) ).toBe(
				'done'
			);
			expect( count() ).toBe( 2 );
		}
	} );
} );
