import { execSync } from 'node:child_process';
import path from 'node:path';
import { withLock } from './lock';

/**
 * The one way the browser tests reach the browser site's command line (D-422). Every `wp-env run` reads wp-env's cache
 * file, changes a key and writes the file back, not atomically (@wordpress/env 12, lib/cache.js, setCache()), and a
 * read that meets a file mid-write gives an empty cache. Two at once, from two of Playwright's workers, can write back
 * a cache with only the WordPress version left in it, and every `wp-env run` after says the site was never started.
 * So one runs at a time, in every worker: the lock is a directory in this checkout's tmp/, which is the lane's own.
 */
export const LOCK = path.join( process.cwd(), 'tmp', 'wp-env-run.lock' );

/**
 * What wp-env says when it lost its connection to api.wordpress.org while loading its config, before the command ran:
 * every `wp-env run` asks there for the latest WordPress version, pinned versions or not (@wordpress/env 12,
 * lib/wordpress.js, getLatestWordPressVersion()). CI run 46120019654 lost one test to it (D-575).
 */
const DROPPED =
	/ERR_SOCKET_CLOSED_BEFORE_CONNECTION|ECONNRESET|ETIMEDOUT|EAI_AGAIN|socket hang up/;

/**
 * Waits, holding the thread: the helpers are synchronous, as `execSync` is.
 *
 * @param ms How long, in milliseconds.
 */
function pause( ms: number ): void {
	Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, ms );
}

/**
 * Runs a shell command on the host and gives back what it printed, trimmed. A try that failed on a lost connection,
 * having printed nothing on stdout, so before the command ran, is tried again after each pause; any other failure
 * throws at once. A failure throws with what it said on stderr and stdout, which wp-env and WP-CLI print their reasons
 * to: the tests' helpers used to throw stderr away.
 *
 * @param command The command.
 * @param pauses  The waits before each try after the first, in milliseconds: so its tries are one more.
 */
export function retried(
	command: string,
	pauses: readonly number[] = [ 2000, 5000 ]
): string {
	for ( let at = 0; ; at++ ) {
		try {
			return execSync( command, {
				stdio: [ 'ignore', 'pipe', 'pipe' ],
				encoding: 'utf8',
			} ).trim();
		} catch ( error ) {
			const failed = error as {
				status?: number | null;
				stderr?: string;
				stdout?: string;
			};
			const again =
				at < pauses.length &&
				! failed.stdout?.trim() &&
				DROPPED.test( failed.stderr ?? '' );
			if ( again ) {
				pause( pauses[ at ] ?? 0 );
				continue;
			}
			const tried = at > 0 ? ` after ${ at + 1 } tries` : '';
			throw new Error(
				[
					`${ command } failed with exit code ${ failed.status }${ tried }.`,
					failed.stderr?.trim(),
					failed.stdout?.trim(),
				]
					.filter( Boolean )
					.join( '\n' )
			);
		}
	}
}

/**
 * Runs a shell command on the host under the lock, tried again where wp-env lost its connection (retried()).
 *
 * @param command The command.
 */
export function locked( command: string ): string {
	return withLock( LOCK, () => retried( command ) );
}

/**
 * Runs a command in the browser site's cli container.
 *
 * @param command The command, after `wp-env run cli --`.
 */
export function cli( command: string ): string {
	return locked( `npx wp-env run cli -- ${ command }` );
}

/**
 * Runs WP-CLI in the browser site.
 *
 * @param command The command, after `wp`.
 */
export function wp( command: string ): string {
	return cli( `wp ${ command }` );
}
